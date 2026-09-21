/*═══════════════════════════════════════════════════════════════════════════
 * JP CAPSULE v2  ·  10_Sua24h_NopTien
 * ---------------------------------------------------------------------------
 * Nhân viên SỬA BÁO CÁO TRONG 24H · NỘP TIỀN · NỘP BỔ SUNG.
 * Cấu trúc giữ đúng như POSH v3 (Sua24h_LichSu), chỉ đổi tên trường cho JP.
 *
 * BA NGUYÊN TẮC LẤY TỪ POSH v3:
 *  1. PHÂN QUYỀN CHẶN Ở MÁY CHỦ, không chỉ ở giao diện — giao diện sửa được,
 *     máy chủ thì không. Nhân viên chỉ đụng được báo cáo của cơ sở mình.
 *  2. TÁCH BẠCH ô của NHÂN VIÊN và ô của KẾ TOÁN:
 *     · nvPaid / nvPaidDate / nvPayStatus / nvPayNote  ← nhân viên tự báo
 *     · paid   / paidDate   / payStatus                ← kế toán xác nhận thực nhận
 *     Kế toán đã xác nhận rồi thì nhân viên KHÔNG sửa được nữa.
 *  3. Mọi lần nộp ghi 1 dòng nhật ký JP_Payments — cộng dồn, không ghi đè.
 *═══════════════════════════════════════════════════════════════════════════*/

/*──────────────────── ① BÁO CÁO CÒN TRONG 24H ────────────────────*/

/** Còn trong hạn tự sửa không? */
function jpTrong24h_(head) {
  var t = head.submittedAt;
  if (!t) return true;                          // chưa nộp thì sửa thoải mái
  var ms = (t instanceof Date) ? t.getTime() : new Date(t).getTime();
  if (isNaN(ms)) return true;
  return (Date.now() - ms) <= JP_EDIT_HOURS * 3600 * 1000;
}

/** Giờ còn lại được sửa. */
function jpGioConLai_(head) {
  var t = head.submittedAt;
  if (!t) return JP_EDIT_HOURS;
  var ms = (t instanceof Date) ? t.getTime() : new Date(t).getTime();
  if (isNaN(ms)) return JP_EDIT_HOURS;
  var con = JP_EDIT_HOURS - (Date.now() - ms) / 3600000;
  return Math.max(0, Math.round(con * 10) / 10);
}

/** Kế toán đã "chạm" vào báo cáo chưa (duyệt hoặc ghi nhận nộp tiền)? */
function jpKeToanDaCham_(head) {
  var k = jpChuKy_(head);
  return !!(k.rev.by || k.stock.by || jpNum_(head.paid) > 0 || head.status === JP_ST_DONE);
}

/**
 * Danh sách báo cáo nhân viên còn được sửa (trong 24h, kế toán chưa chạm).
 */
function jpMyEditable(token) {
  var u = jpNeedNV_(jpAuth_(token));

  return jpRows_(JP_TABS.REPORTS)
    .filter(function (r) {
      if (String(r.userId) !== String(u.id)) return false;
      if (!jpCanSeeLoc_(u, r.locationId)) return false;     // chặn ở MÁY CHỦ
      if (r.status === JP_ST_DONE) return false;
      return jpTrong24h_(r) || r.status === JP_ST_DRAFT || r.status === JP_ST_FIXING;
    })
    .sort(function (a, b) { return jpDate_(a.fromDate) < jpDate_(b.fromDate) ? 1 : -1; })
    .map(function (r) {
      return {
        id: String(r.id), locationName: jpStr_(r.locationName),
        machineType: jpStr_(r.machineType) || JP_TYPE_MONEY,
        fromDate: jpDate_(r.fromDate), toDate: jpDate_(r.toDate),
        status: jpStr_(r.status), totalSubmit: jpNum_(r.totalSubmit),
        gioConLai: jpGioConLai_(r),
        keToanDaCham: jpKeToanDaCham_(r)
      };
    });
}

/**
 * Mở lại báo cáo đã nộp để sửa (trong 24h).
 * Chuyển về trạng thái NHÁP để nhân viên sửa rồi nộp lại.
 */
function jpReopenIn24h(token, reportId, reason) {
  var u = jpNeedNV_(jpAuth_(token));

  return jpLock_(function () {
    var head = jpFindOne_(JP_TABS.REPORTS, 'id', reportId);
    if (!head) throw new Error('Không tìm thấy báo cáo');

    jpNeedLoc_(u, head.locationId);                        // nguyên tắc #1
    if (String(head.userId) !== String(u.id)) {
      throw new Error('Đây không phải báo cáo của bạn');
    }
    if (head.status === JP_ST_DRAFT || head.status === JP_ST_FIXING) {
      return { ok: true, status: head.status, msg: 'Báo cáo đang mở, sửa được luôn' };
    }
    if (jpKeToanDaCham_(head)) {
      throw new Error('Kế toán đã duyệt hoặc đã ghi nhận nộp tiền — liên hệ kế toán để mở lại');
    }
    if (!jpTrong24h_(head)) {
      throw new Error('Đã quá ' + JP_EDIT_HOURS + ' giờ kể từ lúc nộp — liên hệ kế toán');
    }

    jpFields_(JP_TABS.REPORTS, head._row, {
      status: JP_ST_DRAFT,
      rejectPart: '', rejectReason: '', rejectBy: '', rejectAt: ''
    });
    jpAudit_(u, 'EDIT_24H', reportId, head.locationName,
             { conLai: jpGioConLai_(head), reason: jpStr_(reason) });

    return { ok: true, status: JP_ST_DRAFT,
             msg: 'Đã mở lại để sửa · còn ' + jpGioConLai_(head) + ' giờ' };
  });
}

/**
 * Nhân viên SỬA KỲ BÁO CÁO (từ ngày – đến ngày) — Andy chốt 11/08/2026:
 * *"cho cơ sở có phần sửa báo cáo 24g có thể sửa được ngày nộp"*, và chốt tiếp
 * *"ngày nộp là ngày nộp báo cáo"* = **kỳ của báo cáo**.
 *
 * Trước bản này chọn nhầm kỳ lúc mở là **không sửa được**: báo cáo nằm vĩnh viễn ở
 * kỳ sai, doanh thu rơi sai tháng, và mở lại đúng kỳ thì thành hai báo cáo.
 *
 * ⚠️ **KHÔNG có đồng hồ 24h riêng ở đây — cố ý.** Hàm chỉ nhận báo cáo **đang mở để
 * sửa** (`NHAP` / `CAN_SUA`). Báo cáo đã nộp thì nhân viên phải bấm **Sửa trong 24h**
 * (`jpReopenIn24h`) trước, và chính hàm đó giữ đồng hồ. Thêm đồng hồ thứ hai ở đây là
 * hai chỗ đếm cùng một thứ, rồi lệch nhau.
 *
 * ⚠️ **CHẶN TRÙNG KỲ.** Đổi sang đúng kỳ đã có một báo cáo khác của chính mình là hai
 * báo cáo cùng cơ sở · cùng kỳ · cùng loại máy ⇒ **doanh thu tính hai lần**, mà sổ vẫn
 * cân nên không phép kiểm nào bắt được. `jpOpenReport` chặn đúng bộ khoá này lúc tạo;
 * đường sửa mà không chặn là cửa sau vào đúng tình huống đó.
 *
 * ⚠️ **KHÔNG tự tính lại tồn đầu / chỉ số đầu kỳ.** Chúng được gieo lúc TẠO theo kỳ
 * liền trước của ngày CŨ; dời kỳ thì "kỳ liền trước" có thể đã khác. Nhưng tính lại là
 * **ghi đè số nhân viên có thể đã sửa tay** — nên hàm **NÓI RA** để soát lại, đúng luật
 * "ô có số rồi thì không tự đụng vào".
 */
function jpSuaKyBaoCao(token, reportId, tuNgay, denNgay) {
  var u = jpNeedNV_(jpAuth_(token));
  var f = jpDate_(tuNgay);
  var t = jpDate_(denNgay) || f;
  if (!f) throw new Error('Chọn ngày bắt đầu kỳ');
  if (t < f) throw new Error('Ngày kết thúc phải sau ngày bắt đầu');

  return jpLock_(function () {
    var head = jpFindOne_(JP_TABS.REPORTS, 'id', reportId);
    if (!head) throw new Error('Không tìm thấy báo cáo');

    jpNeedLoc_(u, head.locationId);
    if (String(head.userId) !== String(u.id)) {
      throw new Error('Đây không phải báo cáo của bạn');
    }
    if (!jpCanEditReport_(u, head)) {
      throw new Error('Báo cáo đã nộp — bấm "Sửa trong ' + JP_EDIT_HOURS +
                      ' giờ" trước rồi mới đổi được kỳ');
    }
    if (jpKeToanDaCham_(head)) {
      throw new Error('Kế toán đã duyệt hoặc đã ghi nhận nộp tiền — liên hệ kế toán');
    }

    var cuF = jpDate_(head.fromDate), cuT = jpDate_(head.toDate);
    if (cuF === f && cuT === t) return { ok: true, fromDate: f, toDate: t,
                                         msg: 'Kỳ không đổi' };

    var mType = jpStr_(head.machineType) || JP_TYPE_MONEY;
    var trung = jpRows_(JP_TABS.REPORTS).filter(function (r) {
      return String(r.id) !== String(head.id)
          && String(r.locationId) === String(head.locationId)
          && String(r.userId) === String(u.id)
          && (jpStr_(r.machineType) || JP_TYPE_MONEY) === mType
          && jpDate_(r.fromDate) === f && jpDate_(r.toDate) === t;
    })[0];
    if (trung) {
      throw new Error('Bạn đã có báo cáo ' + jpStr_(trung.id) + ' đúng kỳ ' +
                      jpDMY_(f) + ' – ' + jpDMY_(t) + ' — đổi sang kỳ đó là hai bản ' +
                      'cùng kỳ, doanh thu bị tính hai lần');
    }

    jpFields_(JP_TABS.REPORTS, head._row, { fromDate: f, toDate: t });
    jpAudit_(u, 'SUA_KY', head.id, head.locationName,
             { cu: cuF + '..' + cuT, moi: f + '..' + t });

    /* Người khác cũng báo cáo đúng kỳ mới thì CẢNH BÁO, không chặn — cùng luật với
       `jpTrungNguoiKhac_` lúc tạo (Andy chốt: mỗi nhân viên một bản riêng). */
    var nguoiKhac = jpTrungNguoiKhac_({ locationId: head.locationId, fromDate: f,
                                        toDate: t, machineType: mType }, u.id);

    /* Đổi sang một kỳ CHỒNG lên báo cáo đã nộp khác thì nói ra NGAY — đây là lúc
       người dùng đang cầm tay vào cái sửa được, chứ đợi tới màn quét của kế toán thì
       nhân viên chỉ còn 24 giờ. Cảnh báo, KHÔNG chặn (xem `jpKyChongNhau_`). */
    var chong = jpKyChongNhau_({ id: head.id, locationId: head.locationId,
                                 machineType: mType, fromDate: f, toDate: t });

    return {
      ok: true, fromDate: f, toDate: t, trungNguoiKhac: nguoiKhac,
      kyChong: chong.map(function (r) { return String(r.id); }),
      msg: 'Đã đổi kỳ từ ' + jpDMY_(cuF) + ' – ' + jpDMY_(cuT) + ' sang ' +
           jpDMY_(f) + ' – ' + jpDMY_(t) +
           '. ⚠️ Tồn đầu và chỉ số đầu kỳ được lấy theo kỳ CŨ — soát lại giúp.' +
           (nguoiKhac.length ? ' Cùng kỳ mới còn ' + nguoiKhac.length +
             ' người khác cũng có báo cáo.' : '') +
           (chong.length ? ' ⚠️ Kỳ mới CHỒNG với ' + chong.length + ' báo cáo đã nộp (' +
             chong.map(function (r) { return String(r.id); }).join(', ') +
             ') — kỳ sau sẽ không tự điền được tồn đầu.' : '')
    };
  });
}

/*──────────── ①b SẮP LẠI KỲ CHỒNG NHAU — công cụ của KẾ TOÁN ────────────*/

/**
 * SẮP LẠI KỲ cho một cơ sở đang có báo cáo chồng kỳ (Andy giao 15/08/2026).
 *
 * AEON Bình Tân có 4 báo cáo đã nộp chồng kỳ nhau (01→15, 06→15, 08→15, 15→15/08).
 * Sửa tay thì phải: đăng nhập PIN cơ sở → mở từng bản → "Sửa trong 24 giờ" → "Đổi kỳ"
 * → nhân 4 lần, và **hết 24 giờ là hết cửa**. Hàm này làm một lượt, phía kế toán.
 *
 * ⚠️⚠️ **XẾP THEO CHỈ SỐ ĐỒNG HỒ, KHÔNG theo ngày tạo.** Đây là cả lý do hàm này tồn
 * tại thay vì để người ta gõ tay bốn cặp ngày: chỉ số là **số vật lý của máy**, nó nói
 * bản nào thật sự đứng trước. Gán ngày theo thứ tự tạo mà chỉ số lại ngược thì kỳ CUỐI
 * theo ngày không phải bản có chỉ số cao nhất ⇒ kỳ sau gieo **tồn đầu và chỉ số đầu
 * SAI**, mà không dòng nào giải thích.
 *
 * ⚠️ **KHÔNG đụng một con số tiền nào.** Chỉ ghi `fromDate`/`toDate`. Doanh thu đi từ
 * chênh đồng hồ nên đổi nhãn kỳ không đổi tiền — và đó chính là điều làm việc này an
 * toàn. Đụng vào tiền ở đây là vượt quá cái người dùng đang nhờ.
 *
 * ⚠️ **HAI BƯỚC** như `jpDoiTkKhoCu`: `ghi = false` chỉ trả bảng xem trước; `ghi = true`
 * mới ghi. Sửa kỳ của báo cáo đã nộp thì không được một-nút-xong.
 *
 * ⚠️ **Không đo được chỉ số thì DỪNG, không đoán.** Mẫu TÁCH sau 12/08 không còn đồng hồ
 * (`jpMayGoTay_`), nên cơ sở đó không có gì để xếp — trả `khongDoDuoc` và **không ghi**.
 * Rơi về "xếp theo ngày tạo" là đúng cái sai mà hàm này sinh ra để tránh.
 */
function jpSapXepLaiKy(token, locationId, machineType, ghi) {
  var u = jpNeedKT_(jpAuth_(token));
  var mType = jpStr_(machineType) || JP_TYPE_MONEY;

  return jpLock_(function () {
    var ds = jpRows_(JP_TABS.REPORTS).filter(function (r) {
      if (String(r.locationId) !== String(locationId)) return false;
      if ((jpStr_(r.machineType) || JP_TYPE_MONEY) !== mType) return false;
      var st = jpStr_(r.status);
      /* Chỉ bản ĐÃ NỘP mới có nghĩa. Nháp thì cơ sở tự sửa kỳ được, không cần tới đây. */
      return st === JP_ST_PENDING || st === JP_ST_FIXING;
    });
    if (ds.length < 2) {
      return { ok: true, ghi: false, soDoi: 0, dong: [],
               msg: 'Cơ sở này chỉ có ' + ds.length + ' báo cáo đã nộp — không có gì để sắp lại.' };
    }

    /* Kế toán đã chạm vào bản nào thì DỪNG CẢ LƯỢT — sắp lại nửa vời là bốn kỳ vẫn
       chồng, mà giờ lệch thêm so với chữ ký đã ký. */
    var daCham = ds.filter(jpKeToanDaCham_).map(function (r) { return String(r.id); });
    if (daCham.length) {
      return { ok: false, ghi: false, soDoi: 0, dong: [], daCham: daCham,
               msg: 'DỪNG — kế toán đã ký hoặc đã ghi nhận nộp tiền cho: ' +
                    daCham.join(', ') + '. Mở lại các báo cáo đó trước rồi sắp lại.' };
    }

    /* Chỉ số đồng hồ NHỎ NHẤT của mỗi báo cáo — mốc để xếp thứ tự */
    var theoBc = {};
    jpRows_(JP_TABS.ROWS).forEach(function (r) {
      (theoBc[String(r.reportId)] = theoBc[String(r.reportId)] || []).push(r);
    });
    var chiSoDau = function (rid) {
      var mn = null;
      (theoBc[String(rid)] || []).forEach(function (r) {
        var kind = jpStr_(r.rowKind) || JP_ROW_MONEY;
        if (kind !== JP_ROW_MONEY && kind !== JP_ROW_COIN && kind !== JP_ROW_MAY) return;
        /* Máy xu đọc đồng hồ XU, còn lại đọc đồng hồ TIỀN — hai loại khác nhau, đừng gộp */
        var v = !jpBlank_(r.cAfter) ? r.cBefore : r.mBefore;
        if (jpBlank_(v)) return;
        var n = jpNum_(v);
        if (mn === null || n < mn) mn = n;
      });
      return mn;
    };

    var chua = [];
    ds.forEach(function (r) {
      r.__cs = chiSoDau(r.id);
      if (r.__cs === null) chua.push(String(r.id));
    });
    if (chua.length) {
      return { ok: false, ghi: false, soDoi: 0, dong: [], khongDoDuoc: chua,
               msg: 'DỪNG — không đọc được chỉ số đồng hồ của: ' + chua.join(', ') +
                    '. Không có chỉ số thì không biết bản nào đứng trước, mà đoán theo ' +
                    'ngày tạo là đúng cái sai cần tránh. Sửa kỳ tay ở web nhân viên.' };
    }

    /* Ngày BẮT ĐẦU giữ nguyên tập cũ, chỉ ghép lại theo thứ tự chỉ số — nên vùng phủ
       của cả cơ sở không đổi, chỉ hết chồng nhau. */
    var mocDau = ds.map(function (r) { return jpDate_(r.fromDate); }).sort();
    var cuoiCung = ds.map(function (r) {
      return jpDate_(r.toDate) || jpDate_(r.fromDate);
    }).sort().reverse()[0];

    var xep = ds.slice().sort(function (a, b) { return a.__cs - b.__cs; });

    var dong = [], soDoi = 0;
    xep.forEach(function (r, i) {
      var f = mocDau[i];
      var t = (i + 1 < mocDau.length) ? jpLuiMotNgay_(mocDau[i + 1]) : cuoiCung;
      if (t < f) t = f;                       // hai kỳ cùng ngày bắt đầu — giữ 1 ngày
      var cuF = jpDate_(r.fromDate), cuT = jpDate_(r.toDate) || cuF;
      var doi = (cuF !== f || cuT !== t);
      if (doi) soDoi++;
      dong.push({ id: String(r.id), chiSoDau: r.__cs, cuTu: cuF, cuDen: cuT,
                  moiTu: f, moiDen: t, doi: doi, rev: jpNum_(r.revMeter) });
      if (ghi && doi) {
        jpFields_(JP_TABS.REPORTS, r._row, { fromDate: f, toDate: t });
        jpAudit_(u, 'SAP_XEP_LAI_KY', r.id, jpStr_(r.locationName),
                 { cu: cuF + '..' + cuT, moi: f + '..' + t, chiSoDau: r.__cs });
      }
    });

    return {
      ok: true, ghi: !!ghi, soDoi: soDoi, dong: dong,
      msg: (ghi ? 'Đã sắp lại ' : 'Xem trước — sẽ sắp lại ') + soDoi + '/' + ds.length +
           ' báo cáo theo thứ tự CHỈ SỐ ĐỒNG HỒ. Không con số tiền nào bị đụng.' +
           (ghi ? ' Chạy lại Quét dây chuyền để soát ô ⑥.' : '')
    };
  });
}

/** Lùi một ngày trên chuỗi `yyyy-MM-dd`. Tự viết vì `jpDate_` chỉ chuẩn hoá, không tính. */
function jpLuiMotNgay_(ymd) {
  var p = jpStr_(ymd).split('-');
  if (p.length !== 3) return ymd;
  var d = new Date(Number(p[0]), Number(p[1]) - 1, Number(p[2]));
  d.setDate(d.getDate() - 1);
  var hai = function (n) { return (n < 10 ? '0' : '') + n; };
  return d.getFullYear() + '-' + hai(d.getMonth() + 1) + '-' + hai(d.getDate());
}

/*──────────────────── ② NỘP TIỀN & NỘP BỔ SUNG ────────────────────*/

/** Báo cáo của tôi còn thiếu tiền chưa nộp đủ. */
function jpMyUnpaid(token) {
  var u = jpNeedNV_(jpAuth_(token));

  return jpRows_(JP_TABS.REPORTS)
    .filter(function (r) {
      if (String(r.userId) !== String(u.id)) return false;
      if (!jpCanSeeLoc_(u, r.locationId)) return false;
      if (r.status === JP_ST_DRAFT) return false;
      return jpNum_(r.nvPaid) < jpNvPhaiNop_(r);
    })
    .sort(function (a, b) { return jpDate_(a.fromDate) < jpDate_(b.fromDate) ? 1 : -1; })
    .map(function (r) {
      /* TIỀN MẶT, không phải totalSubmit — xem `jpNvPhaiNop_` ở `00_Config`. */
      var phaiNop = jpNvPhaiNop_(r), daNop = jpNum_(r.nvPaid);
      return {
        id: String(r.id), locationName: jpStr_(r.locationName),
        machineType: jpStr_(r.machineType) || JP_TYPE_MONEY,
        fromDate: jpDate_(r.fromDate), toDate: jpDate_(r.toDate),
        status: jpStr_(r.status),
        phaiNop: phaiNop, daNop: daNop, conThieu: phaiNop - daNop,
        nvPayStatus: jpStr_(r.nvPayStatus) || JP_PAY_NONE,
        nvPaidDate: jpDate_(r.nvPaidDate),
        ktXacNhan: jpNum_(r.paid), ktPayStatus: jpStr_(r.payStatus)
      };
    });
}

/**
 * Nhân viên báo đã nộp tiền. Gọi lại nhiều lần = NỘP BỔ SUNG (cộng dồn).
 * p = { reportId, amount, payDate, method:'TM'|'CK', note, dataUrl? }
 *
 * Số tiền CỘNG DỒN, không ghi đè — mỗi lần ghi 1 dòng nhật ký JP_Payments.
 */
function jpAddPayment(token, p) {
  var u = jpNeedNV_(jpAuth_(token));
  p = p || {};

  var amount = jpNum_(p.amount);
  if (amount <= 0) throw new Error('Nhập số tiền đã nộp');

  return jpLock_(function () {
    var head = jpFindOne_(JP_TABS.REPORTS, 'id', p.reportId);
    if (!head) throw new Error('Không tìm thấy báo cáo');

    jpNeedLoc_(u, head.locationId);                        // nguyên tắc #1
    if (String(head.userId) !== String(u.id)) {
      throw new Error('Đây không phải báo cáo của bạn');
    }
    if (head.status === JP_ST_DRAFT) {
      throw new Error('Nộp báo cáo trước rồi mới ghi nhận nộp tiền');
    }
    if (jpNum_(head.paid) > 0) {
      throw new Error('Kế toán đã xác nhận nhận tiền — không tự sửa được nữa');
    }

    var truoc = jpNum_(head.nvPaid);
    var sau   = truoc + amount;                            // nguyên tắc #3: cộng dồn
    var phai  = jpNvPhaiNop_(head);
    if (sau > phai) {
      throw new Error('Nộp ' + jpMoney_(sau) + 'đ vượt số phải nộp ' + jpMoney_(phai) + 'đ');
    }

    /* --- Ảnh chứng từ nộp tiền (tuỳ chọn) --- */
    var photoId = '', photoUrl = '';
    if (p.dataUrl) {
      var res = jpUploadPhoto(token, {
        reportId: head.id, scope: 'REPORT', refId: head.id,
        kind: JP_PHOTO_PROOF, dataUrl: p.dataUrl, takenAt: jpStr_(p.payDate)
      });
      if (res && res.photo) { photoId = res.photo.id; photoUrl = res.photo.url; }
    }

    /* --- Ghi nhật ký từng lần nộp --- */
    var isSup = truoc > 0 ? 'Y' : 'N';
    jpAppend_(JP_TABS.PAYMENTS, {
      id: jpNextId_('PM'),
      reportId: head.id, locationId: head.locationId,
      amount: amount, payDate: jpDate_(p.payDate) || jpToday_(),
      /* BẮT BUỘC chọn cách thu (Andy chốt 04/08/2026) — chặn ở máy chủ. Trước đây
         để trống thì mặc định TM, mà mặc định là nói sai đã nhận tiền mặt. */
      method: jpCachThu_(p.method),
      note: jpStr_(p.note), photoId: photoId, photoUrl: photoUrl,
      isSupplement: isSup,
      createdBy: u.hoTen, createdAt: new Date()
    });

    /* --- Cập nhật ĐÚNG 4 ô của nhân viên, không đụng ô kế toán --- */
    jpFields_(JP_TABS.REPORTS, head._row, {
      nvPaid: sau,
      nvPaidDate: jpDate_(p.payDate) || jpToday_(),
      nvPayStatus: jpPayStatus_(sau, phai),
      nvPayNote: jpStr_(p.note)
    });

    jpAudit_(u, isSup === 'Y' ? 'PAY_SUPPLEMENT' : 'PAY_FIRST', head.id,
             head.locationName, { amount: amount, tong: sau, phai: phai });

    return {
      ok: true, daNop: sau, phaiNop: phai, conThieu: phai - sau,
      nvPayStatus: jpPayStatus_(sau, phai),
      msg: (isSup === 'Y' ? 'Đã ghi nhận nộp bổ sung ' : 'Đã ghi nhận nộp ') +
           jpMoney_(amount) + 'đ · ' + jpTenCachThu_(jpCachThu_(p.method)) +
           ' · tổng ' + jpMoney_(sau) + '/' + jpMoney_(phai) + 'đ' +
           /* ⚠️ CẢNH BÁO, KHÔNG CHẶN — cùng luật với thiếu ảnh lúc nộp báo cáo: mạng
              ở mall hay hỏng, chặn cứng là nhân viên không ghi được lần nộp nào.
              Nhưng chuyển khoản thì LUÔN có biên lai, nên thiếu ảnh ở đây là chuyện
              đáng nói ra — không thì kế toán không có gì đối chiếu với sao kê. */
           (jpCachThu_(p.method) === JP_PAY_TM || photoId
             ? '' : ' ⚠️ Chuyển khoản mà chưa có ảnh biên lai — kế toán sẽ không đối ' +
                    'chiếu được với sao kê, bổ sung ảnh giúp nhé.')
    };
  });
}

/** Lịch sử các lần nộp của 1 báo cáo. */
function jpPaymentHistory(token, reportId) {
  var u = jpAuth_(token);
  var head = jpFindOne_(JP_TABS.REPORTS, 'id', reportId);
  if (!head) throw new Error('Không tìm thấy báo cáo');
  if (!jpIsKT_(u)) jpNeedLoc_(u, head.locationId);

  return jpFind_(JP_TABS.PAYMENTS, 'reportId', reportId)
    .sort(function (a, b) { return jpDate_(a.payDate) < jpDate_(b.payDate) ? -1 : 1; })
    .map(function (r) {
      return {
        id: String(r.id), amount: jpNum_(r.amount),
        payDate: jpDate_(r.payDate), method: jpStr_(r.method),
        note: jpStr_(r.note), photoUrl: jpStr_(r.photoUrl),
        isSupplement: jpStr_(r.isSupplement) === 'Y',
        createdBy: jpStr_(r.createdBy), createdAt: r.createdAt || ''
      };
    });
}

/**
 * Nhân viên SỬA NGÀY NỘP của một lần đã ghi (Andy chốt 11/08/2026:
 * *"cho cơ sở có phần sửa báo cáo 24g có thể sửa được ngày nộp"*).
 *
 * Trước bản này gõ nhầm ngày thì đường duy nhất là **xoá rồi ghi lại** — mất ghi chú,
 * mất ảnh chứng từ đã tải lên, và dòng nhật ký đổi `id` nên tra lại không ra.
 *
 * ⚠️⚠️ **KHÔNG gắn thêm đồng hồ 24h cho hàm này**, dù Andy nói trong ngữ cảnh sửa-24h.
 * Lý do: `jpDeletePayment` **vốn không có giới hạn thời gian** — chốt duy nhất của nó là
 * *kế toán chưa xác nhận*. Nếu sửa-ngày chặt hơn xoá, nhân viên chỉ việc **xoá rồi ghi
 * lại** là qua — tức cái đồng hồ đó là hàng rào trang trí, trông như bảo vệ mà không
 * bảo vệ gì. Mà thêm đồng hồ vào cả `jpDeletePayment` thì lại là siết một chức năng
 * đang chạy mà không ai yêu cầu. Nên dùng **đúng cùng một chốt với xoá**.
 *
 * ⚠️ Chốt thật là `head.paid > 0` — kế toán đã ghi nhận thực nhận thì con số đã vào sổ
 * công nợ và bút toán `1111`/`1121`, sửa ngày sau lưng kế toán là lệch sổ.
 *
 * ⚠️ `nvPaidDate` trên header phải TÍNH LẠI theo **ngày muộn nhất còn lại**, không phải
 * gán đại ngày vừa sửa: hai chỗ cùng nói "ngày nộp" mà lệch nhau thì màn nào cũng đúng
 * theo công thức riêng của nó, và không ai biết chỗ nào sai.
 */
function jpSuaNgayNop(token, paymentId, ngayMoi) {
  var u = jpNeedNV_(jpAuth_(token));
  var ngay = jpDate_(ngayMoi);
  if (!ngay) throw new Error('Chọn ngày nộp mới');
  /* Ngày ở TƯƠNG LAI là gõ nhầm — tiền chưa nộp thì chưa ghi được. */
  if (ngay > jpToday_()) throw new Error('Ngày nộp không thể ở tương lai');

  return jpLock_(function () {
    var pm = jpFindOne_(JP_TABS.PAYMENTS, 'id', paymentId);
    if (!pm) throw new Error('Không tìm thấy lần nộp này');

    var head = jpFindOne_(JP_TABS.REPORTS, 'id', pm.reportId);
    if (!head) throw new Error('Không tìm thấy báo cáo');
    jpNeedLoc_(u, head.locationId);
    if (String(head.userId) !== String(u.id)) throw new Error('Không phải báo cáo của bạn');
    if (jpNum_(head.paid) > 0) {
      throw new Error('Kế toán đã xác nhận nhận tiền — liên hệ kế toán để sửa');
    }

    var cu = jpDate_(pm.payDate);
    if (cu === ngay) return { ok: true, payDate: ngay, msg: 'Ngày nộp không đổi' };

    jpFields_(JP_TABS.PAYMENTS, pm._row, { payDate: ngay });

    /* Tính lại ngày nộp trên header = ngày MUỘN NHẤT trong các lần còn lại. */
    var ds = jpFind_(JP_TABS.PAYMENTS, 'reportId', String(head.id));
    var muon = '';
    ds.forEach(function (r) {
      var d = jpDate_(r.payDate);
      if (d && (!muon || d > muon)) muon = d;
    });
    jpFields_(JP_TABS.REPORTS, head._row, { nvPaidDate: muon });

    jpAudit_(u, 'PAY_SUA_NGAY', head.id, head.locationName,
             { payId: String(pm.id), cu: cu, moi: ngay, tien: jpNum_(pm.amount) });

    return { ok: true, payDate: ngay, nvPaidDate: muon,
             msg: 'Đã đổi ngày nộp ' + jpMoney_(jpNum_(pm.amount)) + 'đ từ ' +
                  jpDMY_(cu) + ' sang ' + jpDMY_(ngay) };
  });
}

/**
 * Nhân viên xoá 1 lần nộp ghi nhầm — chỉ khi kế toán chưa xác nhận.
 */
function jpDeletePayment(token, paymentId) {
  var u = jpNeedNV_(jpAuth_(token));

  return jpLock_(function () {
    var pm = jpFindOne_(JP_TABS.PAYMENTS, 'id', paymentId);
    if (!pm) throw new Error('Không tìm thấy lần nộp này');

    var head = jpFindOne_(JP_TABS.REPORTS, 'id', pm.reportId);
    if (!head) throw new Error('Không tìm thấy báo cáo');
    jpNeedLoc_(u, head.locationId);
    if (String(head.userId) !== String(u.id)) throw new Error('Không phải báo cáo của bạn');
    if (jpNum_(head.paid) > 0) throw new Error('Kế toán đã xác nhận — không xoá được');

    var sau = Math.max(0, jpNum_(head.nvPaid) - jpNum_(pm.amount));
    jpDelete_(JP_TABS.PAYMENTS, pm._row);
    jpFields_(JP_TABS.REPORTS, head._row, {
      nvPaid: sau,
      nvPayStatus: jpPayStatus_(sau, jpNvPhaiNop_(head))
    });
    jpAudit_(u, 'PAY_DELETE', head.id, head.locationName, { amount: jpNum_(pm.amount) });

    return { ok: true, daNop: sau, msg: 'Đã xoá lần nộp ' + jpMoney_(pm.amount) + 'đ' };
  });
}

/*──────────────────── ③ CHO WEB KẾ TOÁN ────────────────────*/

/**
 * Kế toán xem nhân viên đã báo nộp bao nhiêu, đối chiếu với số thực nhận.
 * Lệch giữa nvPaid và paid chính là chỗ cần soát.
 */
/*──────────────────── CÁCH THU CỦA NHÂN VIÊN ────────────────────
 * Andy 16/08/2026: *"phần bổ sung nộp tiền phải có 2 hình thức chứ"*.
 *
 * ⚠️⚠️ Chốt 07/08/2026 KHÔNG bị đảo: *SỐ TIỀN* nhân viên phải nộp vẫn là **tiền mặt**
 * (`jpNvPhaiNop_` trừ hẳn `revBank` ra), vì tiền khách quét QR/CK về thẳng tài khoản
 * công ty, nhân viên không cầm khoản đó. Cái mở ra ở đây là *CÁCH GIAO* khoản tiền mặt
 * đó về công ty: cầm tiền tới đưa, hay chuyển khoản. Hai việc khác nhau.
 *
 * ⚠️ `nvPaid` là số nhân viên TỰ KHAI, **không vào sổ** — sổ dùng `paid` của kế toán
 * (đối chiếu: `nvPaid` chỉ xuất hiện đúng một lần ở `08` làm con số `nvBaoNop` để xem).
 * Nên đổi cách thu ở đây **không đụng một bút toán nào**; `1111`/`1121` vẫn do cách thu
 * KẾ TOÁN khai lúc xác nhận quyết định. Đừng "dọn" thành lấy cách thu của nhân viên.
 */

/** Cộng số tiền theo từng cách thu. `{TM: 5000000, CK: 2000000}` */
function jpTheoCachThu_(dsNop) {
  var ra = {};
  (dsNop || []).forEach(function (x) {
    /* Dòng nộp đời cũ (trước 04/08/2026) có thể trống cách thu — lúc đó nhân viên chỉ
       nộp được tiền mặt nên coi là TM là đúng sự thật, không phải đoán bừa. */
    var m = jpStr_(x.method) || JP_PAY_TM;
    ra[m] = (ra[m] || 0) + jpNum_(x.amount);
  });
  return ra;
}

/**
 * Nhãn ngắn cho bảng: `Tiền mặt` · `Chuyển khoản` · `Tiền mặt + Chuyển khoản`.
 *
 * ⚠️ Đi theo THỨ TỰ CỐ ĐỊNH, không dùng `Object.keys`: thứ tự khoá phụ thuộc thứ tự
 * ghi dòng, nên cùng một báo cáo có thể ra `TM + CK` lần này và `CK + TM` lần sau —
 * hai chuỗi khác nhau cho cùng một sự thật thì lọc/so sánh/chụp ảnh đều lệch.
 */
function jpNhanCachThu_(theoCach) {
  var thu = [JP_PAY_TM, JP_PAY_CK, JP_PAY_QR].filter(function (m) {
    return jpNum_((theoCach || {})[m]) > 0;
  });
  if (!thu.length) return '';
  return thu.map(jpTenCachThu_).join(' + ');
}

/** Tên tiếng Việt của một cách thu. */
function jpTenCachThu_(m) {
  if (m === JP_PAY_CK) return 'Chuyển khoản';
  if (m === JP_PAY_QR) return 'QR';
  return 'Tiền mặt';
}

function jpKtPaymentBoard(token, f) {
  jpNeedKT_(jpAuth_(token));
  f = f || {};
  var from = jpDate_(f.fromDate), to = jpDate_(f.toDate);

  var list = jpRows_(JP_TABS.REPORTS).filter(function (r) {
    if (r.status === JP_ST_DRAFT) return false;
    if (from && jpDate_(r.toDate) < from) return false;
    if (to && jpDate_(r.fromDate) > to) return false;
    if (f.locationId && String(r.locationId) !== String(f.locationId)) return false;
    if (f.onlyLech) {
      if (jpNum_(r.nvPaid) === jpNum_(r.paid)) return false;
    }
    return true;
  });

  var byReport = {};
  jpRows_(JP_TABS.PAYMENTS).forEach(function (p) {
    (byReport[String(p.reportId)] = byReport[String(p.reportId)] || []).push(p);
  });

  return {
    ok: true,
    rows: list.map(function (r) {
      var lan = (byReport[String(r.id)] || []);
      return {
        id: String(r.id), maKH: jpStr_(r.maKH),
        locationName: jpStr_(r.locationName),
        machineType: jpStr_(r.machineType) || JP_TYPE_MONEY,
        period: jpDMY_(r.fromDate) + ' – ' + jpDMY_(r.toDate),
        userName: jpStr_(r.userName), status: jpStr_(r.status),
        phaiNop: jpNvPhaiNop_(r),
        nvPaid: jpNum_(r.nvPaid), nvPaidDate: jpDate_(r.nvPaidDate),
        nvPayStatus: jpStr_(r.nvPayStatus) || JP_PAY_NONE,
        nvPayNote: jpStr_(r.nvPayNote),
        ktPaid: jpNum_(r.paid), ktPaidDate: jpDate_(r.paidDate),
        ktPayStatus: jpStr_(r.payStatus) || JP_PAY_NONE,
        lech: jpNum_(r.nvPaid) - jpNum_(r.paid),
        soLanNop: lan.length,
        coBoSung: lan.filter(function (x) { return jpStr_(x.isSupplement) === 'Y'; }).length,
        /* ⚠️⚠️ CÁCH THU phải lên tới bảng này. Từ 16/08/2026 nhân viên chọn được
           `TM` hoặc `CK`, mà bảng chỉ in một con số thì kế toán **đi tìm tiền mặt
           của một khoản đã chuyển khoản** — hoặc tệ hơn, xác nhận đã nhận tiền mặt
           mà trong két không có đồng nào. Bảng này là cửa DUY NHẤT kế toán xem
           nhân viên báo nộp, `jpPaymentHistory` phải mở từng báo cáo mới thấy. */
        nvTheoCach: jpTheoCachThu_(lan),
        nvCachThu: jpNhanCachThu_(jpTheoCachThu_(lan))
      };
    })
  };
}


/*──────────────────── ④ LỊCH SỬ THEO THÁNG (nhân viên) ────────────────────*/

/**
 * Lịch sử báo cáo của TÔI trong một tháng, chi tiết tới từng ô/máy.
 * Đối chiếu POSH v3 `getMonthHistory`: nhân viên cần xem lại số mình đã báo,
 * không chỉ danh sách đầu báo cáo. `jpMyReports` chỉ trả 30 bản gần nhất ở mức
 * đầu báo cáo — hết tháng muốn soát lại từng ô thì không có cửa nào.
 *
 * Chỉ báo cáo của chính mình, và vẫn qua `jpCanSeeLoc_` — người bị đổi cơ sở thì
 * không đọc lại được số của cơ sở cũ.
 */
function jpMyMonthHistory(token, thang, nam) {
  var u = jpNeedNV_(jpAuth_(token));
  var m = jpNum_(thang), y = jpNum_(nam);
  if (m < 1 || m > 12 || y < 2020) throw new Error('Tháng / năm không hợp lệ');

  var soNgay = new Date(y, m, 0).getDate();
  var dau = y + '-' + ('0' + m).slice(-2) + '-01';
  var cuoi = y + '-' + ('0' + m).slice(-2) + '-' + ('0' + soNgay).slice(-2);

  /* Báo cáo trong tháng: tính theo NGÀY CUỐI KỲ, vì đó là ngày chốt số */
  var heads = jpRows_(JP_TABS.REPORTS).filter(function (r) {
    if (String(r.userId) !== String(u.id)) return false;
    if (!jpCanSeeLoc_(u, r.locationId)) return false;
    var t = jpDate_(r.toDate) || jpDate_(r.fromDate);
    return t >= dau && t <= cuoi;
  });
  if (!heads.length) {
    return { ok: true, thang: m, nam: y, reports: [], rows: [], tong: jpTongRong_() };
  }

  var idSet = {};
  heads.forEach(function (h) { idSet[String(h.id)] = h; });

  /* Đọc JP_Rows MỘT lần rồi lọc theo tập id — không đọc trong vòng lặp */
  var rows = jpRows_(JP_TABS.ROWS).filter(function (r) {
    return idSet[String(r.reportId)];
  });

  var tong = jpTongRong_();
  heads.forEach(function (h) {
    tong.doanhThu  += jpNum_(h.revMeter);
    tong.phaiNop   += jpNvPhaiNop_(h);
    tong.daNop     += jpNum_(h.nvPaid);
    tong.ktXacNhan += jpNum_(h.paid);
  });
  rows.forEach(function (r) { tong.hangBan += jpNum_(r.soldQty); });

  return {
    ok: true, thang: m, nam: y,
    reports: heads
      .sort(function (a, b) { return jpDate_(a.toDate) < jpDate_(b.toDate) ? 1 : -1; })
      .map(function (r) {
        return {
          id: String(r.id), locationName: jpStr_(r.locationName),
          machineType: jpStr_(r.machineType) || JP_TYPE_MONEY,
          fromDate: jpDate_(r.fromDate), toDate: jpDate_(r.toDate),
          status: jpStr_(r.status),
          revMeter: jpNum_(r.revMeter), revBank: jpNum_(r.revBank),
          totalSubmit: jpNum_(r.totalSubmit),
          nvPaid: jpNum_(r.nvPaid), ktPaid: jpNum_(r.paid),
          warnCount: jpNum_(r.warnCount), chuKy: jpChuKy_(r)
        };
      }),
    rows: rows.map(function (r) {
      var h = idSet[String(r.reportId)];
      return {
        reportId: String(r.reportId),
        ngay: jpDate_(h.toDate),
        locationName: jpStr_(h.locationName),
        rowKind: jpStr_(r.rowKind) || JP_ROW_MONEY,
        machineCode: jpStr_(r.machineCode),
        itemCode: jpStr_(r.itemCode), itemName: jpStr_(r.itemName),
        mBefore: jpNumOrBlank_(r.mBefore), mAfter: jpNumOrBlank_(r.mAfter),
        mActual: jpNum_(r.mActual), amount: jpNum_(r.amount),
        cash: jpNum_(r.cash), bank: jpNum_(r.bank),
        soldQty: jpNum_(r.soldQty),
        stockLeftCalc: jpNum_(r.stockLeftCalc), hLeft: jpNum_(r.hLeft),
        stockActual: jpNumOrBlank_(r.stockActual),
        warnCount: jpSoWarn_(r.warnJson)
      };
    }).sort(function (a, b) {
      if (a.ngay !== b.ngay) return a.ngay < b.ngay ? 1 : -1;
      return String(a.reportId) < String(b.reportId) ? -1 : 1;
    }),
    tong: tong
  };
}

function jpTongRong_() {
  return { doanhThu: 0, phaiNop: 0, daNop: 0, ktXacNhan: 0, hangBan: 0 };
}

/** Đếm cảnh báo đã chốt trong dòng. JSON hỏng thì coi như không có. */
function jpSoWarn_(warnJson) {
  if (!jpStr_(warnJson)) return 0;
  try {
    var a = JSON.parse(warnJson);
    return a && a.length ? a.length : 0;
  } catch (e) { return 0; }
}

/*═══════════════════════════════════════════════════════════════════════════
 * ĐỀ NGHỊ SỬA TỒN ĐẦU  (Andy chốt 05/08/2026)
 * ---------------------------------------------------------------------------
 * Andy: *"tồn đầu điền lần đầu và muốn sửa chỉ sửa được đầu tháng mới hoặc gửi đề
 * nghị kế toán"*.
 *
 * Vì sao phải có cửa này: ô tồn đầu là **tồn cuối kỳ trước**, nên sửa tay là viết lại
 * quá khứ — kho đã xuất giá vốn theo số đó, sổ 632 đã có dòng. Cho sửa tự do thì
 * không ai lần ra được số ban đầu là bao nhiêu.
 *
 * HAI đường, đúng như Andy nói:
 *   ① **Đầu tháng mới** — kỳ này là kỳ ĐẦU TIÊN của một tháng chưa có báo cáo
 *      `HOAN_TAT` nào ⇒ mở ô cho sửa thẳng, vì chưa có gì phía sau để phá.
 *   ② **Gửi đề nghị kế toán** — mọi lúc khác. Kế toán duyệt thì máy chủ ghi vào ô.
 *
 * ⚠️ `jpMoTonDau_` là **chỗ duy nhất** quyết định mở hay khoá. Client đọc cờ `moTonDau`
 * từ máy chủ chứ không tự suy — web ở `ANYONE_ANONYMOUS` nên client không bao giờ là
 * chốt, và hai chỗ tự suy là hai luật rồi lệch nhau.
 *═══════════════════════════════════════════════════════════════════════════*/

/**
 * Kỳ của báo cáo này có phải kỳ ĐẦU THÁNG không (⇒ tồn đầu mở cho sửa)?
 *
 * Đúng khi trong tháng của `fromDate` **chưa có báo cáo `HOAN_TAT` nào** của cùng cơ sở.
 * Dùng `HOAN_TAT` chứ không dùng "có báo cáo nào" — báo cáo nháp của chính mình không
 * được tính, không thì mở báo cáo lên là tự khoá luôn ô của mình.
 */
function jpMoTonDau_(head) {
  var f = jpDate_(head.fromDate);
  if (!f) return false;
  var thang = String(f).slice(0, 7);                 // YYYY-MM
  var loc = jpStr_(head.locationId);

  var xong = jpFind_(JP_TABS.REPORTS, 'locationId', loc).filter(function (r) {
    if (jpStr_(r.status) !== JP_ST_DONE) return false;
    if (String(r.id) === String(head.id)) return false;
    var d = jpDate_(r.fromDate) || jpDate_(r.toDate);
    return d && String(d).slice(0, 7) === thang;
  });
  return xong.length === 0;
}

function jpTrangThaiDeNghi_(t) {
  var v = jpStr_(t).toUpperCase();
  if (v !== JP_DN_DUYET && v !== JP_DN_TU_CHOI) {
    throw new Error('Chỉ duyệt hoặc từ chối — không nhận trạng thái "' + t + '"');
  }
  return v;
}

/**
 * NHÂN VIÊN gửi đề nghị sửa tồn đầu của một dòng.
 *
 * ⚠️ **Bắt buộc có lý do.** Không có lý do thì kế toán không có căn cứ nào để duyệt,
 * và sáu tháng sau không ai biết vì sao số đổi — đúng cùng luật với lệch máy / hoàn khách.
 *
 * ⚠️ **Không cho gửi trùng.** Một dòng đang có đề nghị `CHO` thì gửi thêm là kế toán
 * thấy hai dòng cùng nội dung, duyệt cả hai là cộng hai lần vào cùng một ô.
 */
function jpGuiDeNghiTonDau(token, p) {
  var u = jpNeedNV_(jpAuth_(token));
  p = p || {};
  /* ⚠️ `jpNeedOwner_(u, head)` chỉ KIỂM, không trả head — phải tự lấy head trước. */
  var hs = jpFind_(JP_TABS.REPORTS, 'id', jpStr_(p.reportId));
  if (!hs.length) throw new Error('Không thấy báo cáo ' + jpStr_(p.reportId));
  var head = hs[0];
  jpNeedOwner_(u, head);
  var rowId = jpStr_(p.rowId);
  var lyDo  = jpStr_(p.lyDo);
  if (!lyDo) throw new Error('Phải ghi lý do — kế toán cần căn cứ để duyệt');

  var rows = jpFind_(JP_TABS.ROWS, 'reportId', head.id);
  var row = null;
  rows.forEach(function (r) { if (String(r.id) === rowId) row = r; });
  if (!row) throw new Error('Không thấy dòng ' + rowId + ' trong báo cáo này');

  var cot = jpStr_(row.rowKind) === JP_ROW_HANG ? 'stockOpen' : 'hOpen';
  var soCu = jpNum_(row[cot]);
  var soMoi = jpNum_(p.soMoi);
  if (soMoi < 0) throw new Error('Tồn đầu không âm được');
  if (soMoi === soCu) throw new Error('Số mới bằng số cũ (' + soCu + ') — không cần đề nghị');

  var trung = jpFind_(JP_TABS.DENGHI, 'rowId', rowId).filter(function (d) {
    return jpStr_(d.trangThai) === JP_DN_CHO;
  });
  if (trung.length) {
    return { ok: false, msg: 'Dòng này đang có một đề nghị chờ kế toán xử lý' };
  }

  var id = jpNextId_(JP_TABS.DENGHI, 'DN');
  jpAppend_(JP_TABS.DENGHI, {
    id: id, loai: JP_DN_TON_DAU,
    reportId: head.id, rowId: rowId,
    locationId: jpStr_(head.locationId), locationName: jpStr_(head.locationName),
    itemCode: jpStr_(row.itemCode), itemMisa: jpStr_(row.itemMisa),
    itemName: jpStr_(row.itemName),
    soCu: soCu, soMoi: soMoi, lyDo: lyDo,
    userId: u.id, userName: u.hoTen, guiLuc: new Date(),
    trangThai: JP_DN_CHO, ktBy: '', ktLuc: '', ktGhiChu: ''
  });
  jpAudit_(u, 'DENGHI_TON_DAU', head.id, rowId,
           { soCu: soCu, soMoi: soMoi, lyDo: lyDo });
  return { ok: true, id: id,
           msg: 'Đã gửi đề nghị — kế toán duyệt thì số sẽ tự đổi, bạn không phải gõ lại' };
}

/** Đề nghị của chính nhân viên này, mới nhất trước. */
function jpDeNghiCuaToi(token, limit) {
  var u = jpNeedNV_(jpAuth_(token));
  var ds = jpFind_(JP_TABS.DENGHI, 'userId', u.id);
  ds.sort(function (a, b) { return String(b.guiLuc) > String(a.guiLuc) ? 1 : -1; });
  return { ok: true, rows: ds.slice(0, jpNum_(limit) || 30).map(jpDeNghiRa_) };
}

/** KẾ TOÁN: danh sách đề nghị. `chiCho` = chỉ lấy dòng đang chờ. */
function jpKtDanhSachDeNghi(token, chiCho) {
  jpNeedKT_(jpAuth_(token));
  var ds = jpRows_(JP_TABS.DENGHI);
  if (chiCho) ds = ds.filter(function (d) { return jpStr_(d.trangThai) === JP_DN_CHO; });
  ds.sort(function (a, b) { return String(b.guiLuc) > String(a.guiLuc) ? 1 : -1; });
  return { ok: true, soCho: ds.filter(function (d) {
             return jpStr_(d.trangThai) === JP_DN_CHO; }).length,
           rows: ds.map(jpDeNghiRa_) };
}

function jpDeNghiRa_(d) {
  return { id: jpStr_(d.id), loai: jpStr_(d.loai),
           reportId: jpStr_(d.reportId), rowId: jpStr_(d.rowId),
           locationName: jpStr_(d.locationName),
           itemCode: jpStr_(d.itemCode), itemMisa: jpStr_(d.itemMisa),
           itemName: jpStr_(d.itemName),
           soCu: jpNum_(d.soCu), soMoi: jpNum_(d.soMoi), lyDo: jpStr_(d.lyDo),
           userName: jpStr_(d.userName), guiLuc: d.guiLuc || '',
           trangThai: jpStr_(d.trangThai) || JP_DN_CHO,
           ktBy: jpStr_(d.ktBy), ktLuc: d.ktLuc || '', ktGhiChu: jpStr_(d.ktGhiChu) };
}

/**
 * KẾ TOÁN duyệt hoặc từ chối một đề nghị.
 *
 * ⚠️ Duyệt thì máy chủ **tự ghi vào ô** rồi tính lại cả báo cáo — không bắt nhân viên
 * gõ lại. Bắt gõ lại là số đã duyệt và số thật gõ vào có thể khác nhau, mà không ai
 * đối chiếu.
 *
 * ⚠️ **Chỉ ghi khi báo cáo CHƯA `HOAN_TAT`.** Đã hoàn tất là kho đã xuất giá vốn theo
 * số cũ; ghi đè là sổ 632 lệch với lớp tồn mà không có dòng nào giải thích. Lúc đó phải
 * mở lại báo cáo (`jpKtReopen` hoàn kho) rồi mới duyệt — hàm nói rõ chuyện đó.
 *
 * ⚠️ **Không xử lý lại dòng đã xử lý** — duyệt hai lần là ghi hai lần.
 */
function jpKtXuLyDeNghi(token, p) {
  var u = jpNeedKT_(jpAuth_(token));
  p = p || {};
  var tt = jpTrangThaiDeNghi_(p.trangThai);

  var ds = jpFind_(JP_TABS.DENGHI, 'id', jpStr_(p.id));
  if (!ds.length) throw new Error('Không thấy đề nghị ' + jpStr_(p.id));
  var d = ds[0];
  if (jpStr_(d.trangThai) !== JP_DN_CHO) {
    return { ok: false, msg: 'Đề nghị này đã được xử lý (' + jpStr_(d.trangThai) + ')' };
  }

  if (tt === JP_DN_DUYET) {
    var hs = jpFind_(JP_TABS.REPORTS, 'id', jpStr_(d.reportId));
    if (!hs.length) throw new Error('Không thấy báo cáo ' + jpStr_(d.reportId));
    var head = hs[0];
    if (jpStr_(head.status) === JP_ST_DONE) {
      return { ok: false, msg: 'Báo cáo đã HOÀN TẤT — kho đã xuất giá vốn theo số cũ. ' +
               'Phải MỞ LẠI báo cáo (kho tự hoàn) rồi mới duyệt đề nghị này.' };
    }
    var rows = jpFind_(JP_TABS.ROWS, 'reportId', head.id);
    var row = null;
    rows.forEach(function (r) { if (String(r.id) === String(d.rowId)) row = r; });
    if (!row) throw new Error('Không thấy dòng ' + jpStr_(d.rowId));

    var cot = jpStr_(row.rowKind) === JP_ROW_HANG ? 'stockOpen' : 'hOpen';
    var ghi = {};
    ghi[cot] = jpNum_(d.soMoi);
    jpFields_(JP_TABS.ROWS, row._row, ghi);

    /* Tính lại cả báo cáo: đổi tồn đầu là đổi tồn cuối, đổi tiền theo hàng, đổi lệch
       bảng tổng. Không tính lại là các số đó đứng im ở giá trị cũ. */
    row[cot] = jpNum_(d.soMoi);
    var lai = jpFind_(JP_TABS.ROWS, 'reportId', head.id);
    lai.forEach(function (r) { jpCalcRow_(r, jpStr_(head.machineType)); });
    jpCalcReport_(head, lai);
    jpFields_(JP_TABS.REPORTS, head._row, {
      revMeter: head.revMeter, revBank: head.revBank, revCashMeter: head.revCashMeter,
      revHang: head.revHang, lechTienHang: head.lechTienHang,
      refundRows: head.refundRows,
      cashActual: head.cashActual, totalSubmit: head.totalSubmit,
      warnCount: head.warnCount
    });
  }

  jpFields_(JP_TABS.DENGHI, d._row, {
    trangThai: tt, ktBy: u.hoTen, ktLuc: new Date(), ktGhiChu: jpStr_(p.ghiChu)
  });
  jpAudit_(u, 'DENGHI_' + tt, jpStr_(d.reportId), jpStr_(d.rowId),
           { soCu: jpNum_(d.soCu), soMoi: jpNum_(d.soMoi), ghiChu: jpStr_(p.ghiChu) });

  return { ok: true, msg: tt === JP_DN_DUYET
    ? 'Đã duyệt — tồn đầu đổi từ ' + jpNum_(d.soCu) + ' thành ' + jpNum_(d.soMoi) +
      ', báo cáo đã tính lại'
    : 'Đã từ chối đề nghị' };
}

