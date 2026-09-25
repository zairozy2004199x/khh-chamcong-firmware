/*═══════════════════════════════════════════════════════════════════════════
 * JP CAPSULE v2 — WEB NHÂN VIÊN  ·  05_BaoCao
 * ---------------------------------------------------------------------------
 * Vòng đời báo cáo phía nhân viên: tạo · lưu nháp · nộp · xem lại · sửa.
 *
 * BẤT BIẾN:
 *  · 1 nhân viên · 1 cơ sở · 1 kỳ · 1 loại máy  ⇒  ĐÚNG 1 BÁO CÁO, nộp 1 lần.
 *    Báo cáo thuộc về NGƯỜI TẠO: người khác cùng cơ sở không sửa, không nộp được
 *    (Andy chốt 03/08/2026). Riêng số ĐẦU KỲ vẫn lấy chung theo cơ sở, vì chỉ số
 *    đồng hồ là số vật lý của máy, không phụ thuộc ai đi thu.
 *  · Nhân viên tự chọn ngày (từ – đến), tự thêm khu vực và đặt tên.
 *  · Client KHÔNG gửi tiền lên. Server tính lại toàn bộ trước khi ghi.
 *  · Chỉ báo cáo HOÀN TẤT mới được dùng làm gốc nối kỳ (auto-carry).
 *═══════════════════════════════════════════════════════════════════════════*/

/*──────────────────── ① MỞ / TẠO BÁO CÁO ────────────────────*/

function jpOpenReport(token, locationId, fromDate, toDate, machineType) {
  var u = jpNeedNV_(jpAuth_(token));
  jpNeedLoc_(u, locationId);

  var f = jpDate_(fromDate), t = jpDate_(toDate) || f;
  if (!f) throw new Error('Chọn ngày báo cáo');
  if (t < f) throw new Error('Ngày kết thúc phải sau ngày bắt đầu');

  var loc = jpFindOne_(JP_TABS.LOCATIONS, 'id', locationId);
  if (!loc) throw new Error('Không tìm thấy cơ sở');

  /* Loại báo cáo TÁCH BẠCH: máy tiền và máy xu là 2 báo cáo riêng.
     Nhân viên được gán loại nào thì mặc định mở đúng loại đó — không lấy loại của
     cơ sở nữa, vì một cơ sở có thể có cả hai loại máy. */
  var duoc = jpMayTypeCua_(u);
  var mType = jpStr_(machineType) || duoc || jpStr_(loc.machineType) || JP_TYPE_MONEY;
  if (mType !== JP_TYPE_COIN) mType = JP_TYPE_MONEY;
  jpNeedMayType_(u, mType);

  /* Lọc cả theo userId: báo cáo của người khác không phải việc của mình */
  var mine = jpRows_(JP_TABS.REPORTS).filter(function (r) {
    return String(r.locationId) === String(locationId)
        && String(r.userId) === String(u.id)
        && jpDate_(r.fromDate) === f && jpDate_(r.toDate) === t
        && (jpStr_(r.machineType) || JP_TYPE_MONEY) === mType;
  });

  var done = mine.filter(function (r) { return r.status === JP_ST_DONE; })[0];
  if (done) {
    return { ok: false, locked: true,
             msg: 'Báo cáo ' + (mType === JP_TYPE_COIN ? 'MÁY XU' : 'MÁY TIỀN') +
                  ' kỳ ' + jpDMY_(f) + ' – ' + jpDMY_(t) + ' đã duyệt xong, không sửa được' };
  }

  var open = mine.filter(function (r) {
    return r.status === JP_ST_DRAFT || r.status === JP_ST_FIXING
        || r.status === JP_ST_PENDING;
  })[0];

  if (open) return jpGetReport(token, open.id);

  /* Mẫu báo cáo CHỐT LÚC TẠO. Kế toán đổi mẫu của cơ sở sau đó thì báo cáo này giữ
     đúng bố cục nhân viên đã nhập — cùng lý do `machineType` cũng chốt ở đây.
     Mẫu TÁCH chỉ có ở báo cáo máy TIỀN: máy xu vốn đã tách hai bảng rồi. */
  var mau = (mType === JP_TYPE_COIN) ? JP_BC_MAU_CHUNG : jpBcMau_(loc.bcMau);

  var head = {
    id: jpNextId_('RP'),
    createdAt: new Date(),
    locationId: String(locationId),
    locationName: jpStr_(loc.name),
    maKH: jpStr_(loc.maKH),
    machineType: mType,
    bcMau: mau,
    fromDate: f, toDate: t,
    userId: u.id, userName: u.hoTen,
    status: JP_ST_DRAFT,
    revMeter: 0, revBank: 0, revCashMeter: 0,
    revHang: 0, lechTienHang: 0,
    adjMachine: 0, adjMachineNote: '',
    refundCustomer: 0, refundNote: '', refundRows: 0,
    cashActual: 0, totalSubmit: 0,
    paid: 0, paidDate: '', payStatus: 'CHUA_NOP',
    warnCount: 0, remark: ''
  };
  jpAppend_(JP_TABS.REPORTS, head);

  /* TỰ NHẢY Y NHƯ BÁO CÁO TRƯỚC (Andy chốt 04/08/2026). Gieo ngay vào sheet chứ không
     để client tự dựng: nhân viên mở là thấy đủ dòng, và bản nháp có thật nên đổi máy /
     mất mạng giữa buổi vẫn còn.
     ⚠️ **MỌI MẪU**, không riêng TÁCH (sửa 11/08/2026 — Andy: *"sao nhân viên gửi báo
     cáo rồi mà báo cáo sau nó kh tự ghi lại hàng tên mã misa và tồn đầu"*). Trước đó
     chỉ mẫu TÁCH được gieo, nên máy xu và máy tiền mẫu chung phải gõ lại **toàn bộ**
     dòng mỗi kỳ — không có gì trên client tự dựng dòng, mọi dòng đều do người bấm
     "+ Thêm". VICOM 3/2 là máy xu nên rơi đúng vào đó. */
  var gieo = jpGieoDongTuKyTruoc_(head);

  jpAudit_(u, 'REPORT_CREATE', head.id, loc.name,
           { from: f, to: t, type: mType, mau: mau,
             gieoDong: gieo ? gieo.soDong : 0,
             tuBaoCao: gieo ? gieo.tuBaoCao : '',
             trungNguoiKhac: jpTrungNguoiKhac_(head, u.id).length });

  /* ⚠️ Trả kèm `gieo` để client NÓI RA đã gieo bao nhiêu dòng và gieo từ báo cáo nào —
     nhất là khi nguồn CHƯA DUYỆT thì tồn đầu còn có thể đổi. Gieo im lặng là nhân viên
     thấy sẵn số rồi tin luôn, không soát lại. */
  var ra = jpGetReport(token, head.id);
  if (gieo) ra.gieo = gieo;
  return ra;
}

/**
 * GIEO DÒNG cho báo cáo mẫu TÁCH vừa tạo — *"báo cáo sau web sẽ tự nhảy y như báo
 * cáo trước nhưng tồn cuối là tồn đầu bc sau"* (Andy chốt 04/08/2026).
 *
 * Lấy **báo cáo `HOAN_TAT` gần nhất cùng cơ sở, cùng loại máy** làm khuôn: khu vực,
 * dòng máy, dòng hàng — giữ nguyên thứ tự. Tồn cuối đếm được của kỳ đó thành tồn đầu
 * kỳ này.
 *
 * ⚠️ **KHÔNG mang theo số phát sinh trong kỳ** — chỉ số sau đồng hồ, nhập thêm, trả
 * kho, hàng lỗi, tồn cuối đều để TRỐNG. Sao chép sang là nhân viên bấm Nộp mà không
 * nhập gì cũng ra một báo cáo trông đầy đủ với số của kỳ trước ⇒ doanh thu kỳ này
 * bằng kỳ trước mà không ai biết. Chỉ số ĐẦU kỳ thì có, vì nó là số vật lý đã chốt.
 *
 * ⚠️⚠️ **ƯU TIÊN `HOAN_TAT`, nhưng KHÔNG bắt buộc** (sửa 11/08/2026 — Andy:
 * *"sao nhân viên gửi báo cáo rồi mà báo cáo sau nó kh tự ghi lại"*). Bản cũ đòi
 * `HOAN_TAT`, nên kỳ trước **đã nộp mà kế toán chưa duyệt** là kỳ này trắng trơn —
 * nhân viên phải gõ lại tên hàng + mã MISA + tồn đầu cho **cả 54 mã** của SBPQ. Duyệt
 * có thể muộn vài ngày, mà kỳ sau thì tới ngay.
 *
 * Thứ tự tìm: `HOAN_TAT` gần nhất → nếu không có thì bản **ĐÃ NỘP MỘT LẦN**
 * (`CHO_DUYET` / `CAN_SUA`).
 *
 * ⚠️ **KHÔNG lấy `NHAP`** — nháp có thể đang gõ nửa vời, gieo từ đó là bê nguyên một
 * danh sách dở dang sang kỳ mới. "Đã nộp một lần" là mốc chắc chắn rằng danh sách dòng
 * đã đủ (`jpSubmitReport` chặn khi còn ô bắt buộc trống).
 *
 * ⚠️ Gieo từ bản CHƯA DUYỆT thì **ô tồn đầu vẫn GÕ ĐƯỢC** — `carried` do
 * `jpPrevClosing_` quyết định và hàm đó **chỉ nhận `HOAN_TAT`**, nên tự khắc không
 * khoá. Đúng: số đó còn có thể đổi nếu kế toán trả về. Và `jpOpenReport` **nói ra**
 * nguồn gieo là bản chưa duyệt, thay vì để nhân viên tin một con số chưa chốt.
 *
 * ⚠️ Dòng hàng có `stockActual` **để trống** ở kỳ trước thì tồn đầu kỳ này ra 0 —
 * nhưng `jpSubmitReport` không cho nộp khi còn ô đó trống, nên tình huống đó không
 * sinh ra từ đường trong app. Vẫn giữ `jpNum_` để dữ liệu sửa tay trên Sheet không
 * làm hàm chết.
 */
/**
 * VÌ SAO KHÔNG GIEO ĐƯỢC DÒNG NÀO — trả về câu nói cho nhân viên.
 *
 * Andy 15/08/2026 mở `RP20260815-0027` (AEON Bình Tân) và báo *"bảng trắng không có
 * dòng nào"*. Máy chủ làm **đúng luật**: ba báo cáo trước của cơ sở đó đều kéo tới
 * `15/08`, mà kỳ mới cũng bắt đầu `15/08` ⇒ `toDate < fromDate` loại sạch.
 *
 * ⚠️ **Nhưng đó là lỗi của APP.** Trước bản này hàm gieo trả `null` và **không nói gì**
 * — nhân viên mở ra thấy bảng trắng, không biết vì sao, không biết phải làm gì. Đúng
 * cái bệnh đã ghi ở mục "ký một phần": máy chủ đúng luật mà người dùng không có đường
 * nào biết. Bảng trắng lúc này trông y hệt lúc app hỏng.
 *
 * ⚠️ **Đừng "chữa" bằng cách nới thành `toDate <= fromDate`.** Hai kỳ cùng chứa một
 * ngày thì tồn cuối ngày đó thành tồn đầu của chính ngày đó, và doanh thu ngày đó nằm
 * ở CẢ HAI báo cáo — sai tiền, mà sổ vẫn cân. Chỗ phải sửa là **kỳ báo cáo**, và nay
 * nhân viên sửa được (`jpSuaKyBaoCao`), nên câu trả lời phải chỉ thẳng vào đó.
 *
 * Bốn tình huống, **bốn câu khác nhau** — gộp lại một câu chung là người đọc không
 * biết mình phải làm gì tiếp:
 */
function jpViSaoKhongGieo_(head, f, cungCoSo, ung) {
  var ra = { soDong: 0, tuBaoCao: '', denNgay: '', boQuaTraKho: [], daDuyet: false };

  /* ① Cơ sở chưa từng có báo cáo nào cùng loại máy + cùng mẫu */
  if (!cungCoSo.length) {
    ra.ma = 'KY_DAU';
    ra.lyDo = 'Đây là kỳ ĐẦU TIÊN của cơ sở này, chưa có kỳ trước để ghi lại. ' +
              'Lần này nhập tay; từ kỳ sau web sẽ tự điền mã hàng và tồn đầu.';
    return ra;
  }

  /* ② Có báo cáo cũ, nhưng KHÔNG cái nào kết thúc trước ngày kỳ này bắt đầu.
     Đây là tình huống của Andy — và là tình huống DUY NHẤT người dùng tự sửa được. */
  if (!ung.length) {
    var gan = cungCoSo.slice().sort(function (a, b) {
      return jpDate_(a.toDate) < jpDate_(b.toDate) ? 1 : -1;
    })[0];
    ra.ma = 'CHONG_KY';
    ra.tuBaoCao = String(gan.id);
    ra.denNgay = jpDate_(gan.toDate);
    ra.lyDo = 'Kỳ này bắt đầu ' + f + ', nhưng báo cáo gần nhất của cơ sở (' + gan.id +
              ') kéo tới ' + jpDate_(gan.toDate) + ' — HAI KỲ CHỒNG NHAU nên không ghi ' +
              'lại được: tồn cuối của một ngày không thể là tồn đầu của chính ngày đó, ' +
              'và doanh thu ngày đó sẽ nằm ở cả hai báo cáo. Sửa kỳ này cho bắt đầu SAU ' +
              jpDate_(gan.toDate) + ' (nút "Đổi kỳ" ở đầu báo cáo) rồi mở lại.';
    return ra;
  }

  /* ③ Có kỳ trước hợp lệ về ngày, nhưng chưa cái nào từng được NỘP */
  ra.ma = 'CHUA_NOP';
  var cu = ung[0];
  ra.tuBaoCao = String(cu.id);
  ra.denNgay = jpDate_(cu.toDate);
  ra.lyDo = 'Kỳ trước (' + cu.id + ', đến ' + jpDate_(cu.toDate) + ') còn là NHÁP, ' +
            'chưa nộp lần nào — nháp có thể đang gõ dở nên web không lấy làm chuẩn. ' +
            'Nộp kỳ đó rồi mở lại kỳ này là có ngay.';
  return ra;
}

/**
 * KỲ LIỀN TRƯỚC của một cơ sở — **nguồn DUY NHẤT** của câu hỏi *"số đầu kỳ lấy từ đâu"*.
 *
 * ⚠️⚠️ Trước bản này bậc thang trạng thái nằm **bên trong** `jpGieoDongTuKyTruoc_`, nên
 * `jpGetOpening` — đường CÒN LẠI cũng cần đúng câu trả lời đó — không dùng được, và nó
 * đi hỏi `jpPrevClosing_` (**chỉ nhận `HOAN_TAT`**). Hai chỗ hiểu "kỳ trước" khác nhau
 * là gốc của lỗi *"tồn không nhập cho báo cáo sau"*: xem chú thích ở `jpGetOpening`.
 *
 * Trả về cả `cungCoSo` và `ung` vì `jpViSaoKhongGieo_` cần phân biệt "cơ sở chưa có kỳ
 * nào" với "có kỳ nhưng chồng ngày" — hai tình huống cần hai câu trả lời khác hẳn.
 *
 * ⚠️ Lấy kỳ **GẦN NHẤT**, KHÔNG ưu tiên `HOAN_TAT` toàn cục. Bản đầu viết "HOAN_TAT
 * trước, không có thì mới lấy bản đã nộp" — SAI, và `day-chuyen` bắt được: một bản duyệt
 * từ 05/08 thắng bản vừa nộp 03/09, nên tồn đầu kỳ 04/09 **nhảy qua cả kỳ 01–03/09**.
 * `HOAN_TAT` chỉ dùng để phá thế bằng khi hai bản cùng ngày kết thúc.
 */
function jpKyLienTruoc_(locationId, f, excludeReportId, machineType, mau) {
  var mType = jpStr_(machineType) || JP_TYPE_MONEY;

  /* Tách làm HAI bước — cùng cơ sở trước, rồi mới lọc theo ngày. Gộp một bước thì khi
     không gieo được ta **không phân biệt được** "cơ sở chưa có kỳ nào" với "có kỳ nhưng
     chồng ngày", mà hai cái đó cần hai câu trả lời khác hẳn nhau. */
  var cungCoSo = jpRows_(JP_TABS.REPORTS).filter(function (r) {
    return String(r.locationId) === String(locationId)
        && String(r.id) !== String(excludeReportId)
        && (jpStr_(r.machineType) || JP_TYPE_MONEY) === mType
        && jpBcMau_(r.bcMau) === mau;
  });

  /* ⚠️ `toDate < fromDate` — kỳ nguồn phải KẾT THÚC TRƯỚC ngày kỳ này bắt đầu. Cho phép
     `<=` là hai kỳ cùng chứa một ngày ⇒ tồn cuối ngày đó thành tồn đầu của chính ngày
     đó, và doanh thu ngày đó nằm ở cả hai báo cáo. */
  var ung = cungCoSo.filter(function (r) {
    return jpDate_(r.toDate) < f;
  }).sort(function (a, b) {
    return jpDate_(a.toDate) < jpDate_(b.toDate) ? 1 : -1;
  });

  var ut = {};
  ut[JP_ST_DONE] = 3;        // đã duyệt — chắc nhất
  ut[JP_ST_PENDING] = 2;     // đã nộp, chờ duyệt
  ut[JP_ST_FIXING] = 1;      // kế toán trả về — vẫn từng nộp đủ
  /* `NHAP` KHÔNG có trong bảng này: nháp có thể đang gõ nửa vời. */

  var truoc = ung.filter(function (r) { return !!ut[jpStr_(r.status)]; })
                 .sort(function (a, b) {
    var da = jpDate_(a.toDate), db = jpDate_(b.toDate);
    if (da !== db) return da < db ? 1 : -1;                   // kỳ gần nhất trước
    return ut[jpStr_(b.status)] - ut[jpStr_(a.status)];
  })[0];

  return { truoc: truoc || null, cungCoSo: cungCoSo, ung: ung };
}

function jpGieoDongTuKyTruoc_(head) {
  var f = jpDate_(head.fromDate);
  var mType = jpStr_(head.machineType) || JP_TYPE_MONEY;

  /* ⚠️ Khớp **CÙNG MẪU với báo cáo mới**, đừng gán cứng TÁCH: gieo dòng mẫu này sang
     mẫu kia là bố cục lệch hẳn (dòng `MAY` chỉ có tiền, dòng `MONEY` có cả hàng). */
  var mau = jpBcMau_(head.bcMau);

  var ky = jpKyLienTruoc_(head.locationId, f, head.id, mType, mau);
  var cungCoSo = ky.cungCoSo, ung = ky.ung, truoc = ky.truoc;

  if (!truoc) return jpViSaoKhongGieo_(head, f, cungCoSo, ung);
  var daDuyet = jpStr_(truoc.status) === JP_ST_DONE;

  var zonesCu = jpFind_(JP_TABS.ZONES, 'reportId', truoc.id)
                .sort(function (a, b) { return jpNum_(a.seq) - jpNum_(b.seq); });
  var rowsCu  = jpFind_(JP_TABS.ROWS, 'reportId', truoc.id)
                .sort(function (a, b) { return jpNum_(a.seq) - jpNum_(b.seq); });
  if (!rowsCu.length) {
    return { soDong: 0, tuBaoCao: String(truoc.id), denNgay: jpDate_(truoc.toDate),
             boQuaTraKho: [], daDuyet: daDuyet, ma: 'KY_TRUOC_RONG',
             lyDo: 'Kỳ trước (' + truoc.id + ', đến ' + jpDate_(truoc.toDate) +
                   ') không có dòng nào để ghi lại. Kỳ này gõ tay.' };
  }

  var mapZone = {};
  zonesCu.forEach(function (z, i) {
    var id = head.id + '-Z' + (i + 1);
    mapZone[String(z.id)] = id;
    jpAppend_(JP_TABS.ZONES, {
      id: id, reportId: head.id, seq: i + 1,
      name: jpStr_(z.name) || ('Khu vực ' + (i + 1)),
      clusterId: jpStr_(z.clusterId), note: ''
    });
  });

  /*
   * TỰ BỎ DÒNG "ĐÃ TRẢ KHO VỀ 0" (Andy chốt 05/08/2026)
   * ---------------------------------------------------------------------------
   * Andy: *"khi hàng nào bấm trả kho về 0 thì bc sau tự xoá dòng đó nhưng vẫn xem
   * lại trong lịch sử báo cáo được"*.
   *
   * Điều kiện phải CHẶT — đúng hai vế cùng lúc:
   *   ① tồn cuối kỳ trước = 0   VÀ   ② kỳ trước CÓ trả kho (`returnQty > 0`)
   *
   * ⚠️ Đừng bỏ vế ②. Tồn cuối = 0 vì **bán hết** là chuyện thường, và cơ sở sẽ nhập
   * lại đúng mã đó — bỏ dòng đi là kỳ sau nhân viên phải gõ lại mã hàng + mã Misa từ
   * đầu, đúng cái Andy vừa yêu cầu khỏi phải gõ.
   *
   * ⚠️ **Chỉ không GIEO, KHÔNG xoá gì.** Dòng cũ nằm nguyên trong báo cáo cũ nên lịch
   * sử vẫn xem lại được, kho vẫn có dòng xuất, giá vốn vẫn nguyên. Xoá thật là mất
   * căn cứ đối chiếu — cùng lý do kiểm kê không sửa thẳng vào dòng xuất gốc.
   *
   * Tồn cuối lấy từ `stockLeftCalc` (số web TÍNH) chứ không phải `stockActual`: từ
   * 05/08 nhân viên gõ ĐÃ BÁN và tồn cuối là số suy ra, còn `stockActual` chỉ là ô
   * "đếm thực tế" TUỲ CHỌN — dựa vào nó là mã nào không đếm sẽ bị bỏ oan.
   */
  var boQuaTraKho = [];
  var moi = [];
  var seq = 0;
  rowsCu.forEach(function (r) {
    var conLai = jpNum_(r.stockLeftCalc);
    var daTra  = jpNum_(r.returnQty);
    if (jpBoDongTraKho_(r)) {
      boQuaTraKho.push(jpStr_(r.itemCode) || jpStr_(r.itemMisa) || jpStr_(r.itemName));
      return;
    }
    var i = seq++;
    var kind = jpStr_(r.rowKind) || JP_ROW_MAY;
    var d = {
      id: head.id + '-R' + (i + 1),
      reportId: head.id,
      zoneId: mapZone[String(r.zoneId)] || '',
      seq: i + 1,
      rowKind: kind,
      machineId: jpStr_(r.machineId), machineCode: jpStr_(r.machineCode),
      itemCode: jpStr_(r.itemCode), itemMisa: jpStr_(r.itemMisa),
      itemName: jpStr_(r.itemName), price: jpNum_(r.price),
      /* ⚠️ GIEO giá 1 xung theo DÒNG — loại máy của một ô không đổi giữa hai kỳ, nên
         nhân viên chọn MỘT LẦN rồi nó tự theo. Bắt chọn lại mỗi kỳ là chỗ họ sẽ quên,
         mà quên thì dòng đó về nửa tiền (hoặc gấp đôi) và sổ vẫn cân.
         ⚠️ Andy chốt 29/08/2026 (sửa lại lời chốt trước đó): hai loại máy **XEN LẪN
         nhau** trong cùng một khu, KHÔNG tách theo khu vực. Nên cờ nằm ở DÒNG. */
      giaXung: jpGiaXung_(r.giaXung),
      /* Đầu kỳ = cuối kỳ trước. Phát sinh trong kỳ để TRỐNG — xem chú thích trên. */
      mBefore: jpNumOrBlank_(r.mAfter), mAfter: '',
      cBefore: jpNumOrBlank_(r.cAfter), cAfter: '',
      /* Tồn đầu = tồn cuối TÍNH của kỳ trước. Dòng máy tiền vẫn dùng `stockActual`
         (ô "Hàng tồn thực tế" của bảng đó là ô BẮT BUỘC), còn dòng HÀNG của mẫu tách
         thì lấy `stockLeftCalc` vì ô đếm ở đó là tuỳ chọn. */
      hOpen: jpNum_(r.stockActual),
      hBefore: jpNumOrBlank_(r.hAfter), hAfter: '',
      /* ⚠️ Bảng nào có ô đếm là **TUỲ CHỌN** thì phải lấy `stockLeftCalc` (số web
         TÍNH), không lấy `stockActual`: mã nào nhân viên không đếm sẽ bị gieo tồn 0 —
         tức mất hàng thật khỏi kỳ sau. Đúng ba bảng: `HANG` (mẫu tách), `STOCK` (máy
         xu) và `NGOAI` (kho ngoài). Dòng `MONEY` giữ `stockActual` vì ô "Hàng tồn thực
         tế" của bảng đó là ô BẮT BUỘC. */
      stockOpen: jpTonCuoiGieo_(r, kind),
      stockActual: '',
      soldQty: '',                 // ĐÃ BÁN là phát sinh trong kỳ — để TRỐNG
      addQty1: 0, addQty2: 0, defectQty: 0, returnQty: 0,
      /* Hoàn khách là PHÁT SINH TRONG KỲ — để 0, đừng mang theo kỳ trước.
         Mang theo là nhân viên bấm Nộp mà không sửa gì cũng ra một khoản hoàn
         y kỳ trước, tức trừ tiền thật mà không ai gõ số đó. */
      refundAmt: 0, refundQty: 0, refundRowNote: '',
      stockOut: 0, topupNote: '',
      bank: 0, cash: 0,
      giaXu: jpNum_(r.giaXu), xuDaysJson: '', xuLa: 0,
      note: ''
    };
    jpCalcRow_(d, mType);
    moi.push(d);
  });

  if (moi.length) jpBulkAppendRows_(moi);
  return { soDong: moi.length, tuBaoCao: String(truoc.id), denNgay: jpDate_(truoc.toDate),
           daDuyet: daDuyet, trangThaiNguon: jpStr_(truoc.status),
           boQuaTraKho: boQuaTraKho };
}

/**
 * Tồn cuối kỳ trước dùng làm tồn đầu kỳ này.
 *
 * ⚠️ **Nguồn duy nhất** của luật này — ba bảng có ô đếm TUỲ CHỌN thì phải lấy số web
 * TÍNH, không lấy ô đếm. Viết lại lẻ ở chỗ thứ hai là có ngày một chỗ hiểu khác, và
 * hậu quả là **gieo tồn 0 cho mã nhân viên không đếm** — mất hàng thật khỏi kỳ sau mà
 * bảng trông sạch sẽ.
 */
/**
 * Dòng kỳ trước có ĐƯỢC BỎ khi gieo hay không — *"hàng nào bấm trả kho về 0 thì bc sau
 * tự xoá dòng đó"* (Andy chốt 05/08/2026).
 *
 * ⚠️ Điều kiện CHẶT — **ba** vế cùng lúc:
 *   ① là dòng chỉ giữ HÀNG (`HANG` · `STOCK` · `NGOAI`)
 *   ② tồn cuối kỳ trước = 0
 *   ③ kỳ trước CÓ trả kho (`returnQty > 0`)
 *
 * ⚠️ Bỏ vế ① là **mất luôn cái máy** khỏi báo cáo kỳ sau: dòng `MONEY` mang cả tiền và
 * hàng nên tồn về 0 + trả kho là chuyện thường, mà bỏ nó đi thì kỳ sau không còn ô máy
 * đó để nhập chỉ số đồng hồ. `MAY`/`COIN` thì `jpDongGiuHang_` đã loại sẵn.
 *
 * ⚠️ Bỏ vế ③ là **bán hết cũng bị bỏ** — mà bán hết là chuyện thường và cơ sở sẽ nhập
 * lại đúng mã đó, nên kỳ sau nhân viên phải gõ lại từ đầu.
 *
 * Tách thành hàm riêng để đo được TỪNG LOẠI DÒNG: fixture chạy thật không có dòng
 * `MONEY` nào rơi vào tình huống này, nên thử ngược trên nó **bỏ lọt** (đã mắc).
 */
function jpBoDongTraKho_(r) {
  if (!jpDongTheoMa_(r.rowKind)) return false;
  return jpNum_(r.stockLeftCalc) === 0 && jpNum_(r.returnQty) > 0;
}

/**
 * Dòng được định danh bằng **MÃ HÀNG** (`HANG` · `STOCK` · `NGOAI`), khác dòng định
 * danh bằng **Ô MÁY** (`MONEY` · `MAY` · `COIN`).
 *
 * ⚠️ **KHÁC `jpDongGiuHang_`** — hàm đó trả lời câu *"dòng này có sinh giá vốn 632
 * không"*, nên nó **loại `NGOAI`** (kho ngoài không sinh giá vốn) và **nhận `MONEY`**
 * (máy tiền mang cả hàng). Dùng lẫn hai khái niệm là sai cả hai chiều: `NGOAI` sẽ không
 * bao giờ được bỏ dòng dù đã trả kho hết, và `MONEY` thì bị bỏ ⇒ **mất luôn ô máy**.
 * Đã mắc đúng thế, `tinh-tien.js` bắt được.
 *
 * Hai luật dựa vào đúng khái niệm này: bỏ dòng đã trả kho, và tồn đầu lấy từ đâu.
 */
function jpDongTheoMa_(kind) {
  var k = jpStr_(kind) || JP_ROW_MONEY;
  return k === JP_ROW_HANG || k === JP_ROW_STOCK || k === JP_ROW_NGOAI;
}

function jpTonCuoiGieo_(r, kind) {
  return jpDongTheoMa_(kind) ? jpNum_(r.stockLeftCalc) : jpNum_(r.stockActual);
}

/**
 * Báo cáo CÙNG cơ sở · cùng kỳ · cùng loại máy nhưng của NGƯỜI KHÁC.
 *
 * Mỗi nhân viên một bản riêng (Andy chốt 03/08/2026), nhưng chỉ số đồng hồ là số
 * VẬT LÝ của máy: hai người cùng báo một máy là doanh thu bị tính hai lần. Không
 * chặn — đúng lựa chọn tách bản — nhưng phải nói ra để còn soát.
 */
function jpTrungNguoiKhac_(head, userId) {
  var f = jpDate_(head.fromDate), t = jpDate_(head.toDate);
  var mType = jpStr_(head.machineType) || JP_TYPE_MONEY;

  return jpRows_(JP_TABS.REPORTS).filter(function (r) {
    return String(r.locationId) === String(head.locationId)
        && String(r.userId) !== String(userId)
        && jpDate_(r.fromDate) === f && jpDate_(r.toDate) === t
        && (jpStr_(r.machineType) || JP_TYPE_MONEY) === mType;
  }).map(function (r) {
    return { userName: jpStr_(r.userName), status: jpStr_(r.status) };
  });
}

/*──────────────────── ② ĐỌC BÁO CÁO ────────────────────*/

/**
 * KỲ CHỒNG VỚI BÁO CÁO ĐÃ NỘP KHÁC — cảnh báo NGAY LÚC MỞ, đừng đợi tới màn quét.
 *
 * Andy 15/08/2026: bốn báo cáo AEON Bình Tân chồng kỳ nhau (01→15, 06→15, 08→15,
 * 15→15/08), cả bốn đã nộp mới lộ ra ở phép ⑥ của `jpQuetDayChuyen`. Lúc đó muốn sửa
 * thì nhân viên chỉ còn 24 giờ, hết hạn là phải nhờ kế toán trả về.
 *
 * ⚠️ **CẢNH BÁO, KHÔNG CHẶN.** Cùng luật với *"thiếu ảnh thì cảnh báo, không chặn nộp"*:
 * có thể có tình huống thật cần kỳ chồng mà ta chưa biết, chặn cứng là khoá cơ sở giữa
 * ca. Phép ⑥ vẫn là lưới cuối — cái này chỉ để bắt SỚM.
 *
 * ⚠️ Chỉ so với báo cáo **ĐÃ NỘP ít nhất một lần**. Hai bản nháp chồng nhau là chuyện
 * thường (mở thử rồi bỏ), báo ở đó là cảnh báo giả — mà báo giả vài lần thì không ai
 * đọc nữa, đúng bệnh `canBang` cũ.
 *
 * ⚠️ Cùng **loại máy** mới tính: một cơ sở kiêm cả máy tiền và máy xu thì hai đường
 * đồng hồ khác nhau, kỳ trùng ngày là bình thường.
 */
function jpKyChongNhau_(head) {
  var f = jpDate_(head.fromDate), t = jpDate_(head.toDate) || f;
  var mType = jpStr_(head.machineType) || JP_TYPE_MONEY;
  if (!f) return [];

  return jpRows_(JP_TABS.REPORTS).filter(function (r) {
    if (String(r.id) === String(head.id)) return false;
    if (String(r.locationId) !== String(head.locationId)) return false;
    if ((jpStr_(r.machineType) || JP_TYPE_MONEY) !== mType) return false;
    var st = jpStr_(r.status);
    if (st !== JP_ST_DONE && st !== JP_ST_PENDING && st !== JP_ST_FIXING) return false;
    var rf = jpDate_(r.fromDate), rt = jpDate_(r.toDate) || rf;
    return rf <= t && f <= rt;                      // hai khoảng ngày giao nhau
  }).sort(function (a, b) {
    return jpDate_(a.fromDate) < jpDate_(b.fromDate) ? -1 : 1;
  });
}

function jpGetReport(token, reportId) {
  var u = jpAuth_(token);
  var head = jpFindOne_(JP_TABS.REPORTS, 'id', reportId);
  if (!head) throw new Error('Không tìm thấy báo cáo');
  if (!jpIsKT_(u)) jpNeedLoc_(u, head.locationId);

  var zones = jpFind_(JP_TABS.ZONES, 'reportId', reportId)
              .sort(function (a, b) { return jpNum_(a.seq) - jpNum_(b.seq); });
  var rows  = jpFind_(JP_TABS.ROWS, 'reportId', reportId)
              .sort(function (a, b) { return jpNum_(a.seq) - jpNum_(b.seq); });
  var photos = jpFind_(JP_TABS.PHOTOS, 'reportId', reportId);

  var prev = jpPrevClosing_(head.locationId, jpDate_(head.fromDate), reportId,
                            head.machineType);

  /*
   * ĐỒNG HỒ ĐẾM TRỨNG — đọc từ CƠ SỞ mỗi lần mở, **cố ý KHÔNG chốt vào báo cáo**.
   *
   * Ngược hẳn `bcMau` (chốt lúc tạo, ở `JP_Reports.bcMau`). Andy chốt 15/08/2026
   * *"đổi luôn cả báo cáo đang nhập dở"*, và ở đây làm vậy được vì cờ này chỉ **ẩn hai
   * ô**, không xáo lại bố cục bảng — số đã gõ không rơi sang cột khác. `bcMau` mà đọc
   * lại từ cơ sở thì đúng là hỏng: bảng CHUNG và bảng TÁCH khác nhau cả chục cột.
   *
   * ⚠️ Cơ sở bị xoá khỏi danh mục thì `loc` là `null` ⇒ rơi về CÓ đồng hồ, tức hiện
   * đủ cột. Mất một báo cáo cũ vì thiếu một dòng danh mục thì tệ hơn nhiều so với
   * hiện thừa hai ô.
   */
  var loc = jpFindOne_(JP_TABS.LOCATIONS, 'id', head.locationId);

  return {
    ok: true,
    head: jpPubHead_(head),
    coDhTrung: jpCoDhTrung_(loc ? loc.coDhTrung : ''),
    /* Cờ ĐỌC LẠI mỗi lần mở (như `coDhTrung`), không chốt lúc tạo: kế toán bật giữa
       buổi thì báo cáo đang nhập dở phải hiện cột "Loại máy" ngay. */
    chonGiaXung: jpChonGiaXung_(loc ? loc.chonGiaXung : ''),
    zones: zones.map(jpPubZone_),
    rows: rows.map(function (r) { return jpPubRow_(r, prev); }),
    photos: photos.map(jpPubPhoto_),
    canEdit: jpCanEditReport_(u, head),
    /* Cảnh báo cấp báo cáo KHÔNG lưu vào sheet, nên phải dựng lại lúc mở — dùng
       đúng `jpCanhBaoHead_` mà lúc lưu dùng, không viết lại câu chữ ở đây.
       ⚠️ Cảnh báo KỲ CHỒNG nối thêm Ở ĐÂY, cố ý KHÔNG đưa vào `jpCanhBaoHead_`: hàm đó
       là hàm THUẦN (chỉ đọc `head`) và `jpSaveReport` gọi nó trong khoá. Cho nó đọc
       bảng `JP_Reports` là đổi bản chất hàm, và `tinh-tien.js` — vốn stub `jpRows_`
       theo TÊN TAB và **ném lỗi** với tab chưa gán — sẽ chết hàng loạt. Kỳ báo cáo
       không đổi lúc lưu, nên dựng ở lúc MỞ là đủ. */
    headWarns: (function () {
      var w = jpCanhBaoHead_(head);
      jpKyChongNhau_(head).forEach(function (r) {
        w.push(jpWarn_(JP_WARN.KY_CHONG,
          'Kỳ ' + jpDate_(head.fromDate) + ' → ' + (jpDate_(head.toDate) || jpDate_(head.fromDate)) +
          ' chồng với ' + r.id + ' (' + jpDate_(r.fromDate) + ' → ' +
          (jpDate_(r.toDate) || jpDate_(r.fromDate)) + ', ' + jpStr_(r.status) + '). ' +
          'Doanh thu đi từ chỉ số đồng hồ nên CHƯA chắc tính hai lần, nhưng tồn đầu kỳ ' +
          'sau sẽ không tự điền được và sổ công nợ chia theo kỳ sẽ lệch. Sửa bằng nút ' +
          '"Đổi kỳ", hoặc soi ô ⑥ ở màn Quét dây chuyền bên web kế toán.'));
      });
      return w;
    })(),
    prevClosing: prev,
    trungNguoiKhac: jpIsNV_(u) ? jpTrungNguoiKhac_(head, u.id) : [],

    /*
     * TIẾN ĐỘ ẢNH ĐI CÙNG LƯỢT NÀY (Andy 10/08/2026: *"bấm cái nào cũng load lâu quá"*).
     *
     * Bản trước: mở báo cáo là `jpOpenReport`/`jpGetReport` → rồi client gọi tiếp
     * `jpPhotoProgress` ⇒ HAI đợt chờ nối tiếp ≈ 0,9s trên Apps Script, mà mở báo cáo
     * là việc nhân viên làm nhiều nhất trong ca. Gộp còn một đợt.
     *
     * Rẻ hơn gọi riêng: `jpRows_` cache trong một lần chạy, nên `JP_Rows`/`JP_Zones`
     * mà hàm này vừa đọc thì `jpPhotoWarns_` dùng lại, không tốn lượt Sheets mới.
     *
     * ⚠️ `try/catch` là CÓ CHỦ Ý và có tiền lệ: `refreshPhotoProgress` bên client vốn
     * đã `.catch(function () {})` — ảnh là phần phụ, hỏng thì báo cáo vẫn phải mở
     * được (cùng luật với "thiếu ảnh thì cảnh báo, KHÔNG chặn nộp"). Không có `boc`
     * này thì một thư mục Drive lỗi là nhân viên không mở nổi báo cáo.
     * Client thấy `anhTienDo` rỗng thì tự gọi `jpPhotoProgress` như cũ.
     */
    anhTienDo: (function () {
      try { return jpPhotoProgress(token, reportId); } catch (e) { return null; }
    })()
  };
}

function jpPubHead_(h) {
  return {
    id: String(h.id), locationId: String(h.locationId),
    locationName: jpStr_(h.locationName), maKH: jpStr_(h.maKH),
    machineType: jpStr_(h.machineType),
    bcMau: jpBcMau_(h.bcMau),
    fromDate: jpDate_(h.fromDate), toDate: jpDate_(h.toDate),
    userName: jpStr_(h.userName), status: jpStr_(h.status),
    revMeter: jpNum_(h.revMeter), revBank: jpNum_(h.revBank),
    revCashMeter: jpNum_(h.revCashMeter),
    revHang: jpNum_(h.revHang), lechTienHang: jpNum_(h.lechTienHang),
    adjMachine: jpNum_(h.adjMachine), adjMachineNote: jpStr_(h.adjMachineNote),
    refundCustomer: jpNum_(h.refundCustomer), refundNote: jpStr_(h.refundNote),
    /* Bảng tổng cần CẢ BA số. `refundTotal`/`revMeterRong` là số SUY RA từ cột đã
       lưu, không phải cột riêng — mở lại báo cáo thì trường tạm của
       `jpCalcReport_` không còn, thiếu nó là ô "TIỀN RÒNG" in 0đ. */
    refundRows: jpNum_(h.refundRows),
    /* Cờ MỞ Ô TỒN ĐẦU — `jpMoTonDau_` là chỗ DUY NHẤT quyết định. Client chỉ đọc cờ,
       không tự suy: web ở ANYONE_ANONYMOUS nên client không bao giờ là chốt, và hai
       chỗ tự suy là hai luật rồi lệch nhau. */
    moTonDau: jpMoTonDau_(h),
    refundTotal: jpHoanTong_(h),
    revMeterRong: jpNum_(h.revMeter) - jpHoanTong_(h),
    cashActual: jpNum_(h.cashActual), totalSubmit: jpNum_(h.totalSubmit),
    /* ⚠️ PHẢI qua `jpNgayGio_` — `jpSubmitReport` ghi `new Date()` vào cột này nên
       đọc lại là ĐỐI TƯỢNG DATE. Trả thẳng thì `google.script.run` đóng gói không
       được, client nhận **null**. Và vì MỌI báo cáo đã nộp đều có cột này, kế toán
       mở báo cáo NÀO cũng chết — không riêng báo cáo đã ký. */
    submittedAt: jpNgayGio_(h.submittedAt),
    chuKy: jpChuKy_(h),
    rejectPart: jpStr_(h.rejectPart), rejectReason: jpStr_(h.rejectReason),
    payStatus: jpStr_(h.payStatus), paid: jpNum_(h.paid), paidDate: jpDate_(h.paidDate),
    warnCount: jpNum_(h.warnCount), remark: jpStr_(h.remark)
  };
}

function jpPubZone_(z) {
  return {
    id: String(z.id), seq: jpNum_(z.seq), name: jpStr_(z.name),
    clusterId: jpStr_(z.clusterId), note: jpStr_(z.note)
  };
}

function jpPubRow_(r, prev) {
  var o = {
    id: String(r.id), zoneId: String(r.zoneId), seq: jpNum_(r.seq),
    machineId: jpStr_(r.machineId), machineCode: jpStr_(r.machineCode),
    itemCode: jpStr_(r.itemCode), itemMisa: jpStr_(r.itemMisa),
    itemName: jpStr_(r.itemName), price: jpNum_(r.price),
    /* ⚠️ Giá 1 xung phải TRẢ VỀ client, không thì mở lại báo cáo là ô chọn về mặc định
       5.000 và lượt lưu kế tiếp **ghi đè mất** lựa chọn cũ — dòng đó âm thầm về nửa
       tiền. Qua `jpGiaXung_` để dòng đời cũ (chưa có cột này) ra đúng mặc định. */
    giaXung: jpGiaXung_(r.giaXung),
    /* Trả về client cũng phải giữ ô trống, không thì mở lại báo cáo là thấy số 0
       và trạng thái "chưa nhập" mất luôn sau một lần lưu nháp. */
    mBefore: jpNumOrBlank_(r.mBefore), mAfter: jpNumOrBlank_(r.mAfter),
    mActual: jpNum_(r.mActual),
    cBefore: jpNumOrBlank_(r.cBefore), cAfter: jpNumOrBlank_(r.cAfter),
    cActual: jpNum_(r.cActual),
    /*
     * Ba ô TIỀN của bảng tiền mẫu TÁCH — trả về qua `jpNumOrBlank_` vì ở mẫu đó
     * chúng là ô NHÂN VIÊN GÕ (Andy chốt 12/08/2026), và **ô trống là CHƯA GÕ**.
     * Trả 0 thì mở lại báo cáo là ô hiện `0`, trạng thái "chưa nhập" mất luôn ⇒
     * checklist báo đủ điều kiện nộp, `jpLechTM_` coi như đếm được 0 ⇒ lệch ra
     * −(tiền mặt app) ⇒ TỔNG PHẢI NỘP về 0. Sổ vẫn cân, không ai báo.
     * Mẫu chung / máy xu thì máy chủ tính nên chúng không bao giờ trống.
     */
    amount: jpNumOrBlank_(r.amount), cashReal: jpNumOrBlank_(r.cashReal),
    cash: jpNumOrBlank_(r.cash),
    bank: jpNum_(r.bank), soldQty: jpNum_(r.soldQty),
    /* Lệch tiền mặt — SUY RA mỗi lượt gọi, không lưu cột nào. Lưu là hai nguồn sự
       thật: sửa `cash` trên sheet thì cột lưu không đổi. */
    lechTM: jpLechTM_(r),
    rowKind: jpStr_(r.rowKind) || JP_ROW_MONEY,
    collection: jpNum_(r.collection),
    hOpen: jpNum_(r.hOpen),
    hBefore: jpNumOrBlank_(r.hBefore), hAfter: jpNumOrBlank_(r.hAfter),
    hLeft: jpNum_(r.hLeft), stockOut: jpNum_(r.stockOut), topupNote: jpStr_(r.topupNote),
    giaXu: jpNum_(r.giaXu), xuTong: jpNum_(r.xuTong), xuLa: jpNum_(r.xuLa),
    xuDays: (function () { try { return r.xuDaysJson ? JSON.parse(r.xuDaysJson) : []; } catch (e) { return []; } })(),
    stockOpen: jpNum_(r.stockOpen), addQty1: jpNum_(r.addQty1), addQty2: jpNum_(r.addQty2),
    stockLeftCalc: jpNum_(r.stockLeftCalc), stockActual: jpNumOrBlank_(r.stockActual),
    defectQty: jpNum_(r.defectQty), returnQty: jpNum_(r.returnQty),
    refundAmt: jpNum_(r.refundAmt), refundQty: jpNum_(r.refundQty),
    refundRowNote: jpStr_(r.refundRowNote),
    note: jpStr_(r.note),
    /* Bọc try/catch: một ô warnJson hỏng không được làm cả báo cáo không mở được */
    warns: (function () {
      try { return r.warnJson ? JSON.parse(r.warnJson) : []; } catch (e) { return []; }
    })()
  };
  /* Dòng máy nối kỳ theo `machineId`; dòng hàng theo mã hàng — cùng khoá
     `jpPrevClosing_` dùng, đừng tra bằng `machineId` cho dòng hàng vì nó rỗng. */
  var p = prev && (o.rowKind === JP_ROW_HANG || o.rowKind === JP_ROW_STOCK
                     || o.rowKind === JP_ROW_NGOAI
                   ? prev['ITEM:' + o.itemCode]
                   : prev[o.machineId]);
  o.carried = !!p;
  return o;
}

function jpPubPhoto_(p) {
  var fid = jpStr_(p.fileId);
  return {
    id: String(p.id), scope: jpStr_(p.scope), refId: String(p.refId),
    kind: jpStr_(p.kind), fileId: fid,
    /* ⚠️ `url` DỰNG LẠI theo `JP_ANH_SZ_NHO`, không dùng cột đã lưu — cột đó ghi cứng
       `sz=w600` lúc tải lên (nặng gấp 5 lần ô 74px cần). Dòng đời đầu không có
       `fileId` thì mới rơi về cột cũ, không thì mất ảnh. */
    url: fid ? jpAnhUrl_(fid, JP_ANH_SZ_NHO) : jpStr_(p.url),
    /* Bản để ĐỌC CHỈ SỐ khi bấm vào xem — client không tự ghép cỡ, hai chỗ ghép là
       có ngày một chỗ đổi mà chỗ kia không. */
    urlTo: fid ? jpAnhUrl_(fid, JP_ANH_SZ_TO) : jpStr_(p.url),
    /* Hiện `jpUploadPhoto` ghi bằng `jpStr_` nên đã là chuỗi — bọc để sau này ai
       đổi sang `new Date()` thì không lặp lại lỗi payload về null. */
    takenAt: jpNgayGio_(p.takenAt)
  };
}

function jpCanEditReport_(u, head) {
  if (!jpIsNV_(u)) return false;
  if (String(head.userId) !== String(u.id)) return false;   // báo cáo của người khác
  var s = jpStr_(head.status);
  return s === JP_ST_DRAFT || s === JP_ST_FIXING;
}

/** Chặn ở MÁY CHỦ, không chỉ ẩn nút — bài học POSH v3. */
function jpNeedOwner_(u, head) {
  if (String(head.userId) !== String(u.id)) {
    throw new Error('Báo cáo này của ' + (jpStr_(head.userName) || 'nhân viên khác') +
                    ' — bạn không sửa được');
  }
}

/*──────────────────── ③ AUTO-CARRY: SỐ ĐẦU KỲ ────────────────────*/

function jpPrevClosing_(locationId, f, excludeReportId, machineType) {
  /* PHẢI lọc theo loại máy: một cơ sở làm cả báo cáo TIỀN và XU, không lọc thì
     số cuối kỳ của báo cáo xu chảy sang báo cáo tiền qua khoá ITEM:<mã hàng>. */
  var mType = jpStr_(machineType) || '';

  var truocDo = jpRows_(JP_TABS.REPORTS).filter(function (r) {
    return String(r.locationId) === String(locationId)
        && String(r.id) !== String(excludeReportId)
        && (!mType || (jpStr_(r.machineType) || JP_TYPE_MONEY) === mType)
        && jpDate_(r.toDate) < f;
  }).sort(function (a, b) {
    return jpDate_(a.toDate) < jpDate_(b.toDate) ? 1 : -1;   // mới nhất trước
  });

  /* ⚠️⚠️ CÓ KỲ MỚI HƠN CHƯA DUYỆT thì KHÔNG chốt gì cả (sửa 11/08/2026).
     `carried` là cờ **KHOÁ Ô** — nó nói "số này của kỳ đã chốt, đừng sửa". Nếu giữa kỳ
     `HOAN_TAT` gần nhất và kỳ này còn một kỳ **đã nộp mà chưa duyệt**, thì số của kỳ
     đã duyệt kia đã **cũ** (nhảy qua cả một kỳ phát sinh). Khoá nó lại là ép nhân viên
     giữ một con số vừa cũ vừa không sửa được.
     Trả `{}` thì chỉ **mở khoá**, KHÔNG mất số: `jpGieoDongTuKyTruoc_` đọc thẳng dòng
     của kỳ liền trước nên giá trị vẫn được điền. */
  var moiNhat = truocDo.filter(function (r) {
    var s = jpStr_(r.status);
    return s === JP_ST_DONE || s === JP_ST_PENDING || s === JP_ST_FIXING;
  })[0];
  if (moiNhat && jpStr_(moiNhat.status) !== JP_ST_DONE) return {};

  var heads = truocDo.filter(function (r) { return r.status === JP_ST_DONE; });

  if (!heads.length) return {};

  var allRows = jpRows_(JP_TABS.ROWS);
  var byReport = {};
  allRows.forEach(function (r) {
    (byReport[String(r.reportId)] = byReport[String(r.reportId)] || []).push(r);
  });

  var map = {};
  heads.forEach(function (h) {                 // duyệt từ mới → cũ
    (byReport[String(h.id)] || []).forEach(function (r) {
      var k = jpStr_(r.machineId) || ('ITEM:' + jpStr_(r.itemCode));
      if (!k || map[k]) return;                // đã có từ kỳ mới hơn thì giữ
      /* Ở đây PHẢI ra số: số này thành "chỉ số đầu kỳ" của kỳ sau, mà ô đầu kỳ bị
         khoá nên client không nhập tay được. Rủi ro: kỳ trước để trống chỉ số sau
         ⇒ carry ra 0 ⇒ kỳ sau tính cả đồng hồ thành doanh thu. Chặn ở jpSubmitReport
         (không cho nộp khi còn ô chỉ số sau trống) nên tình huống đó không sinh ra. */
      map[k] = {
        mAfter: jpNum_(r.mAfter),
        cAfter: jpNum_(r.cAfter),
        hAfter: jpNum_(r.hAfter),
        stockActual: jpNum_(r.stockActual),
        /* ⚠️ Mang theo CẢ `stockLeftCalc` và `rowKind` để chỗ dùng áp được đúng luật
           `jpTonCuoiGieo_`: bảng nào có ô đếm là TUỲ CHỌN thì tồn cuối phải lấy số WEB
           TÍNH, không lấy số đếm. Chỉ mang `stockActual` là mã nào nhân viên không đếm
           bị gieo tồn 0 — mất hàng thật khỏi kỳ sau. */
        stockLeftCalc: jpNum_(r.stockLeftCalc),
        rowKind: jpStr_(r.rowKind),
        itemMisa: jpStr_(r.itemMisa),
        fromReport: String(h.id),
        toDate: jpDate_(h.toDate)
      };
    });
  });
  return map;
}

/**
 * SỐ ĐẦU KỲ cho MỘT dòng — gọi khi nhân viên vừa chọn ô máy / chọn mã hàng.
 *
 * ⚠️⚠️ **`found` và `chot` là HAI chuyện khác nhau. Đừng gộp.**
 *   `found` = CÓ số để điền vào ô
 *   `chot`  = số đó của một kỳ **ĐÃ DUYỆT** ⇒ client KHOÁ ô lại, đừng cho sửa
 *
 * Bản trước chỉ có `found`, và nó lấy từ `jpPrevClosing_` — hàm **chỉ nhận `HOAN_TAT`**.
 * Nên cơ sở nào kế toán chưa duyệt kịp thì `found: false` ⇒ client **không điền gì cả**
 * ⇒ nhân viên chọn mã hàng xong thấy **tồn đầu trống**, gõ tay hoặc bỏ trống, và kỳ sau
 * carry sai. Đo thật 18/08/2026: **AMTP không có báo cáo nào `HOAN_TAT`** (cả 4 bản đứng
 * ở `CHO_DUYET`) nên đường này **luôn** trả rỗng — đúng lỗi *"tồn không nhập cho báo cáo
 * sau"* Andy báo.
 *
 * ⚠️ Chú thích ở `jpPrevClosing_` nói *"trả `{}` thì chỉ mở khoá, KHÔNG mất số, vì
 * `jpGieoDongTuKyTruoc_` đọc thẳng dòng của kỳ liền trước"* — đúng, nhưng **chỉ đúng cho
 * đường GIEO CẢ BÁO CÁO lúc tạo**. Đường này là đường thứ hai và nó **không có** chỗ lùi
 * nào, nên lời hứa đó bị vỡ đúng ở đây. Nay cả hai đường dùng chung `jpKyLienTruoc_`.
 *
 * ⚠️ Tồn cuối lấy theo `jpTonCuoiGieo_`, **KHÔNG** lấy thẳng `stockActual`: bảng nào có
 * ô đếm là TUỲ CHỌN (`HANG` · `STOCK` · `NGOAI`) thì mã nào nhân viên không đếm sẽ bị
 * gieo tồn **0** — mất hàng thật khỏi kỳ sau. Đo thật: cả 156 dòng của AMTP đều **trống
 * ô đếm**, nên bản trước có duyệt xong cũng vẫn gieo 0 cho mọi mã.
 */
function jpGetOpening(token, reportId, machineId, itemCode) {
  var u = jpAuth_(token);
  var head = jpFindOne_(JP_TABS.REPORTS, 'id', reportId);
  if (!head) throw new Error('Không tìm thấy báo cáo');
  if (!jpIsKT_(u)) jpNeedLoc_(u, head.locationId);   // nhân viên chỉ đọc cơ sở của mình

  var f = jpDate_(head.fromDate);
  var key = jpStr_(machineId) || ('ITEM:' + jpStr_(itemCode));

  /* ① Kỳ ĐÃ DUYỆT — có số VÀ khoá ô. */
  var p = jpPrevClosing_(head.locationId, f, reportId, head.machineType)[key];
  if (p) {
    return { ok: true, found: true, chot: true,
             mBefore: p.mAfter, cBefore: p.cAfter, hBefore: p.hAfter,
             hOpen: jpTonCuoiGieo_(p, p.rowKind),
             stockOpen: jpTonCuoiGieo_(p, p.rowKind),
             itemMisa: p.itemMisa || '', fromReport: p.fromReport };
  }

  /* ② Chưa duyệt kịp thì VẪN phải ra số — của kỳ LIỀN TRƯỚC, nhưng KHÔNG khoá ô.
        Không khoá vì số đó chưa chốt, kế toán còn có thể trả về bắt sửa. */
  var ky = jpKyLienTruoc_(head.locationId, f, reportId, head.machineType,
                          jpBcMau_(head.bcMau));
  if (ky.truoc) {
    var d = jpFind_(JP_TABS.ROWS, 'reportId', ky.truoc.id).filter(function (r) {
      return (jpStr_(r.machineId) || ('ITEM:' + jpStr_(r.itemCode))) === key;
    })[0];
    if (d) {
      return { ok: true, found: true, chot: false,
               mBefore: jpNum_(d.mAfter), cBefore: jpNum_(d.cAfter),
               hBefore: jpNum_(d.hAfter),
               hOpen: jpTonCuoiGieo_(d, jpStr_(d.rowKind)),
               stockOpen: jpTonCuoiGieo_(d, jpStr_(d.rowKind)),
               itemMisa: jpStr_(d.itemMisa), fromReport: String(ky.truoc.id),
               chuaDuyet: jpStr_(ky.truoc.status) };
    }
  }

  /* ③ Thật sự chưa có kỳ nào — cơ sở mới, hoặc mã hàng mới lần đầu bán. */
  return { ok: true, found: false, chot: false, mBefore: 0, cBefore: 0,
           hBefore: 0, hOpen: 0, stockOpen: 0, itemMisa: '' };
}

/*──────────────────── ④ LƯU NHÁP ────────────────────*/

function jpSaveReport(token, payload) {
  var u = jpNeedNV_(jpAuth_(token));
  payload = payload || {};

  return jpLock_(function () {
    var head = jpFindOne_(JP_TABS.REPORTS, 'id', payload.reportId);
    if (!head) throw new Error('Không tìm thấy báo cáo');
    jpNeedLoc_(u, head.locationId);
    jpNeedOwner_(u, head);
    if (!jpCanEditReport_(u, head)) {
      throw new Error('Báo cáo đang ở trạng thái ' + head.status + ', không sửa được');
    }

    var type = jpStr_(head.machineType) || JP_TYPE_MONEY;
    var mau  = jpBcMau_(head.bcMau);
    var itemMap = jpItemMap_();

    /* --- Giữ bản cũ để so sánh, phục vụ RESET THÔNG MINH chữ ký --- */
    var oldHead = {
      revMeter: head.revMeter, revBank: head.revBank,
      adjMachine: head.adjMachine, refundCustomer: head.refundCustomer
    };
    var oldRows = jpFind_(JP_TABS.ROWS, 'reportId', head.id).map(function (r) {
      return { id: r.id, mAfter: r.mAfter, cAfter: r.cAfter, bank: r.bank,
               /* Bảng tiền mẫu TÁCH: ba ô này là ô NHÂN VIÊN GÕ, nên sửa chúng phải
                  huỷ chữ ký doanh thu. Head-level đã bắt được hầu hết (revMeter đi
                  theo `amount`, adjMachine đi theo lệch), nhưng đổi `cash` và
                  `cashReal` **cùng một lượng** thì head y nguyên trong khi số kế
                  toán vừa ký đã khác. */
               amount: r.amount, cash: r.cash, cashReal: r.cashReal,
               stockActual: r.stockActual, addQty1: r.addQty1, addQty2: r.addQty2,
               defectQty: r.defectQty, returnQty: r.returnQty,
               refundAmt: r.refundAmt };
    });

    /* --- Ghi lại khu vực --- */
    jpDeleteWhere_(JP_TABS.ZONES, 'reportId', head.id);
    var locXung = jpFindOne_(JP_TABS.LOCATIONS, 'id', head.locationId);
    var choPhepXung = jpChonGiaXung_(locXung ? locXung.chonGiaXung : '');

    var zoneIds = [];
    var zoneTaken = jpIdsDangDung_(payload.zones);
    (payload.zones || []).forEach(function (z, i) {
      var id = jpIdMoi_(jpStr_(z.id), head.id + '-Z', zoneTaken);
      zoneIds.push(id);
      jpAppend_(JP_TABS.ZONES, {
        id: id, reportId: head.id, seq: i + 1,
        name: jpStr_(z.name) || ('Khu vực ' + (i + 1)),
        clusterId: jpStr_(z.clusterId), note: jpStr_(z.note)
      });
    });

    /* --- Tính lại từng dòng ở SERVER --- */
    jpDeleteWhere_(JP_TABS.ROWS, 'reportId', head.id);
    var calcRows = [];
    var rowTaken = jpIdsDangDung_(payload.rows);
    (payload.rows || []).forEach(function (r, i) {
      /* ⚠️ Tra qua `jpTraItem_` chứ KHÔNG index thẳng — nhân viên gõ `100jp031` chữ
         thường thì index thẳng là trượt, mà mã đó hoàn toàn đúng. Trượt ở đây không
         chỉ mất tên hàng: `itemCode` lưu xuống giữ nguyên chữ thường nên duyệt xong
         kho KHÔNG tìm được lớp tồn ⇒ giá vốn 0đ, sổ 632 thiếu mà sổ vẫn CÂN. */
      var item = jpTraItem_(itemMap, r.itemCode) || {};
      var row = {
        id: jpIdMoi_(jpStr_(r.id), head.id + '-R', rowTaken),
        reportId: head.id,
        zoneId: zoneIds[jpNum_(r.zoneKey)] || zoneIds[0] || '',
        /* ⚠️ Giá 1 xung là của TỪNG DÒNG (từng ô máy), không phải của khu — hai loại
           máy xen lẫn nhau trong cùng một khu (Andy 29/08/2026). Qua `jpGiaXung_` để
           số lạ rơi về mặc định thay vì đi thẳng vào công thức tiền.
           ⚠️⚠️ Cơ sở KHÔNG bật cờ thì ÉP về mặc định — không phải chỉ ẩn ô chọn. Web ở
           `ANYONE_ANONYMOUS`, ai cũng gọi được `jpSaveReport` từ console, nên ẩn giao
           diện mà không chặn ở máy chủ là **không chặn gì cả**. */
        giaXung: choPhepXung ? jpGiaXung_(r.giaXung) : JP_RATE_MONEY_PULSE,
        seq: i + 1,
        /* Mặc định theo MẪU của báo cáo, không theo loại máy: ở mẫu TÁCH thì dòng
           không ghi rõ loại phải là `MAY` (chỉ tiền), rơi về `MONEY` là dòng đó
           sinh cả `soldQty` ⇒ xuất kho hai lần cùng dòng `HANG`. */
        rowKind: jpStr_(r.rowKind) ||
                 (type === JP_TYPE_COIN ? JP_ROW_COIN
                  : mau === JP_BC_MAU_TACH ? JP_ROW_MAY : JP_ROW_MONEY),
        machineId: jpStr_(r.machineId),
        machineCode: jpStr_(r.machineCode),
        /* ⚠️ SNAP về cách viết CHUẨN của danh mục (`item.code`) — đây là chỗ DUY NHẤT
           làm việc đó, và phải làm ở đường LƯU chứ không ở 14 chỗ đọc: `itemCode`
           lưu xuống là thứ KHO tra lớp tồn theo, snap ở đây thì mọi chỗ đọc về sau
           tự đúng. Mã không có trong danh mục thì GIỮ NGUYÊN nguyên văn nhân viên gõ
           — đừng viết hoa nó lên: làm vậy là sinh thêm một biến thể mới (`B chuột
           nước` và `B CHUỘT NƯỚC` thành hai mã) trong khi kế toán đang đối chiếu. */
        itemCode: jpStr_(item.code) || jpStr_(r.itemCode) || jpStr_(r.itemMisa),
        itemMisa: jpStr_(r.itemMisa) || jpStr_(item.misa),
        itemName: jpStr_(r.itemName) || jpStr_(item.name),
        /* Để `jpCalcRow_` tự suy giá qua `jpGiaDong_` (Misa trước) khi danh mục
           không có giá — đừng chốt cứng `jpGiaTuMa_(r.itemCode)` ở đây, mã nội bộ
           kiểu A-077 không có tiền tố giá. */
        price: jpNum_(item.price),
        /* Chỉ số đồng hồ đi qua jpNumOrBlank_: ô trống PHẢI ở lại là ô trống,
           không thì hàm tính không phân biệt được "chưa nhập" với "nhập số 0". */
        mBefore: jpNumOrBlank_(r.mBefore), mAfter: jpNumOrBlank_(r.mAfter),
        cBefore: jpNumOrBlank_(r.cBefore), cAfter: jpNumOrBlank_(r.cAfter),
        /*
         * TIỀN — ô GÕ ở bảng tiền mẫu TÁCH (Andy chốt 12/08/2026: *"nhân viên tự
         * điền hết"*), ô TỰ TÍNH ở mọi mẫu khác. Nhận cả bốn vào rồi để `jpCalcRow_`
         * ghi đè những cái nó tính được — mẫu chung / máy xu không đổi hành vi.
         *
         * ⚠️ `cash` · `amount` · `cashReal` phải qua **`jpNumOrBlank_`**: ô trống là
         * CHƯA GÕ. Dùng `jpNum_` là chúng thành số 0 ngay ở đây, `jpThieuChiSo_` đọc
         * lại chỉ thấy 0 nên **không chặn nộp**, và `jpLechTM_` coi như đếm được 0 ⇒
         * lệch = −(tiền mặt app) ⇒ tiền phải nộp của cả kỳ về 0. Sổ vẫn cân.
         * ⚠️ `bank` (QR) thì `jpNum_` là ĐÚNG — trống nghĩa là không có khách quét mã,
         * đa số dòng như vậy; đòi gõ 0 là bắt gõ thừa 20 ô mỗi kỳ.
         */
        bank: jpNum_(r.bank), cash: jpNumOrBlank_(r.cash),
        amount: jpNumOrBlank_(r.amount), cashReal: jpNumOrBlank_(r.cashReal),
        /* máy tiền: đồng hồ đếm trứng */
        hOpen: jpNum_(r.hOpen),
        hBefore: jpNumOrBlank_(r.hBefore), hAfter: jpNumOrBlank_(r.hAfter),
        stockOut: jpNum_(r.stockOut), topupNote: jpStr_(r.topupNote),
        /* máy xu: bảng tồn kho */
        giaXu: jpNum_(r.giaXu),
        xuDaysJson: r.xuDays ? JSON.stringify(r.xuDays) : jpStr_(r.xuDaysJson),
        xuLa: jpNum_(r.xuLa),
        stockOpen: jpNum_(r.stockOpen),
        addQty1: jpNum_(r.addQty1), addQty2: jpNum_(r.addQty2),
        stockActual: jpNumOrBlank_(r.stockActual),
        defectQty: jpNum_(r.defectQty), returnQty: jpNum_(r.returnQty),
        /* Hoàn khách theo mã — chỉ `refundAmt` là ô nhân viên gõ; `refundQty`
           do `jpCalcRow_` suy ra ngay sau đây, đừng nhận từ client. */
        refundAmt: Math.abs(jpNum_(r.refundAmt)),
        refundRowNote: jpStr_(r.refundRowNote),
        /* ĐÃ BÁN — dòng HÀNG của mẫu tách thì đây là ô NHÂN VIÊN GÕ (đổi 05/08).
           Mọi loại dòng khác thì `jpCalcRow_` ghi đè ngay sau đây, nên nhận vào
           không ảnh hưởng gì. Tiền vẫn do máy chủ tính = số lượng × giá. */
        soldQty: jpNumOrBlank_(r.soldQty),
        /* Nhân viên chỉ gõ TÊN HÀNG + MÃ MISA ở bảng hàng (Andy chốt 05/08), nên
           `itemCode` — thứ KHO tra theo — rơi về Misa khi để trống. Trong danh mục
           thật hai mã bằng nhau (`jpNapDanhMucHangJP` gán `code = misa`) nên khớp.
           Thiếu bước này là dòng hàng không có itemCode ⇒ jpXuatKhoBaoCao_ không
           tìm được lớp tồn ⇒ cắm cờ thieuLop, giá vốn rơi về giá mua gần nhất. */
        note: jpStr_(r.note)
      };
      jpCalcRow_(row, type);
      calcRows.push(row);
    });

    if (calcRows.length) jpBulkAppendRows_(calcRows);

    /* --- Tổng kết --- */
    var hp = payload.head || {};
    head.adjMachine = jpNum_(hp.adjMachine);
    head.adjMachineNote = jpStr_(hp.adjMachineNote);
    head.refundCustomer = Math.abs(jpNum_(hp.refundCustomer));
    head.refundNote = jpStr_(hp.refundNote);
    head.remark = jpStr_(hp.remark);
    jpCalcReport_(head, calcRows);

    jpFields_(JP_TABS.REPORTS, head._row, {
      revMeter: head.revMeter, revBank: head.revBank, revCashMeter: head.revCashMeter,
      revHang: head.revHang, lechTienHang: head.lechTienHang,
      adjMachine: head.adjMachine, adjMachineNote: head.adjMachineNote,
      refundCustomer: head.refundCustomer, refundNote: head.refundNote,
      refundRows: head.refundRows,
      cashActual: head.cashActual, totalSubmit: head.totalSubmit,
      warnCount: head.warnCount, remark: head.remark
    });

    /* --- Chữ ký: chỉ huỷ đúng phần nhân viên vừa sửa --- */
    var changed = jpDiffParts_(oldHead, oldRows, head, calcRows);
    if (changed.length) jpResetSignatures_(head, changed, u);

    return {
      ok: true, reportId: head.id,
      resetSign: changed,
      totals: {
        revMeter: head.revMeter, revBank: head.revBank,
        revCashMeter: head.revCashMeter, adjMachine: head.adjMachine,
        refundCustomer: head.refundCustomer,
        refundRows: head.refundRows, refundTotal: head.refundTotal,
        cashActual: head.cashActual, totalSubmit: head.totalSubmit,
        revHang: head.revHang, revMeterRong: head.revMeterRong,
        lechTienHang: head.lechTienHang,
        coBangTong: !!head.coBangTong
      },
      warnCount: head.warnCount,
      headWarns: head.warns || [],
      rows: calcRows.map(function (r) {
        return { id: r.id, rowKind: r.rowKind,
                 mActual: r.mActual, cActual: r.cActual,
                 collection: r.collection, amount: r.amount, soldQty: r.soldQty,
                 hLeft: r.hLeft, xuTong: r.xuTong,
                 stockLeftCalc: r.stockLeftCalc,
                 refundAmt: r.refundAmt, refundQty: r.refundQty,
                 warns: r.warns };
      })
    };
  });
}

/*
 * CẤP ID KHÔNG TRÙNG cho khu vực / dòng.
 *
 * Trước đây id dòng mới là `<báo cáo>-R<vị trí>`, mà dòng cũ giữ id cũ. Có
 * -R1 -R2 -R3, xoá -R1 rồi thêm 1 dòng ⇒ dòng mới nằm ở vị trí 3 ⇒ id -R3
 * TRÙNG dòng cũ. Hệ quả: ảnh gắn sai dòng, jpDiffParts_ so sai, reset chữ ký sai.
 */

/** Tập id mà client gửi lên — id mới phải tránh hết những cái này. */
function jpIdsDangDung_(list) {
  var taken = {};
  (list || []).forEach(function (x) {
    var id = jpStr_(x && x.id);
    if (id) taken[id] = 1;
  });
  return taken;
}

/** Giữ id cũ nếu có và chưa bị chiếm; không thì cấp số nhỏ nhất còn trống. */
function jpIdMoi_(idCu, prefix, taken) {
  if (idCu && taken[idCu] !== 2) { taken[idCu] = 2; return idCu; }
  var n = 1, id;
  do { id = prefix + n; n++; } while (taken[id]);
  taken[id] = 2;
  return id;
}

/** Ghi nhiều dòng 1 lần — tránh appendRow trong vòng lặp. */
function jpBulkAppendRows_(rows) {
  jpAppendMany_(JP_TABS.ROWS, rows);
}

function jpItemMap_() {
  var m = {}, hoa = {}, doi = {};
  jpRows_(JP_TABS.ITEMS).forEach(function (i) {
    var ma = jpStr_(i.code);
    m[ma] = {
      /* `code` = cách viết CHUẨN trong danh mục. Có nó thì chỗ lưu báo cáo mới snap
         được mã nhân viên gõ về đúng cách viết này — xem `jpTraItem_` ở 00_Config. */
      code: ma,
      misa: jpStr_(i.misa), name: jpStr_(i.name),
      price: jpNum_(i.price) || jpGiaTuMa_(i.code),
      /* ĐVT có ở đây vì báo cáo N-X-T phải in nó ra cho form MISA. Qua `jpDvt_` chứ
         không rơi-về tại chỗ — xem chú thích ở 00_Config. */
      dvt: jpDvt_(i)
    };
    var U = ma.toUpperCase();
    if (U !== ma) { if (hoa[U]) doi[U] = true; else hoa[U] = ma; }
    else          { if (hoa[U] && hoa[U] !== ma) doi[U] = true; else hoa[U] = ma; }
  });
  /* Gài thêm khoá HOA để `jpTraItem_` dung được HOA/thường.
     ⚠️ CHỐT CHỐNG NHẬP NHẰNG: nếu danh mục có HAI mã chỉ khác chữ hoa thì khoá HOA
     trỏ vào đâu cũng là đoán — bỏ hẳn khoá đó, hành vi giữ y như trước bản này chứ
     không chọn bừa một mã. Không có mã nào đè lên mã thật đang tồn tại. */
  Object.keys(hoa).forEach(function (U) {
    if (doi[U]) return;
    if (!m[U]) m[U] = m[hoa[U]];
  });
  return m;
}

/*──────────────────── ⑤ NỘP BÁO CÁO ────────────────────*/

function jpSubmitReport(token, reportId) {
  var u = jpNeedNV_(jpAuth_(token));

  return jpLock_(function () {
    var head = jpFindOne_(JP_TABS.REPORTS, 'id', reportId);
    if (!head) throw new Error('Không tìm thấy báo cáo');
    jpNeedLoc_(u, head.locationId);
    jpNeedOwner_(u, head);
    if (!jpCanEditReport_(u, head)) throw new Error('Báo cáo không ở trạng thái nộp được');

    var rows = jpFind_(JP_TABS.ROWS, 'reportId', reportId);
    if (!rows.length) throw new Error('Chưa có dòng nào để nộp');

    var thieu = jpThieuChiSo_(rows);
    if (thieu.length) {
      throw new Error('Còn ' + thieu.length + ' ô chưa nhập chỉ số sau / tồn cuối kỳ: ' +
        thieu.slice(0, 5).join(', ') + (thieu.length > 5 ? '…' : '') +
        '. Nhập đủ rồi nộp — để trống sẽ làm sai số đầu kỳ của kỳ sau.');
    }

    /* ⚠️ QR không thể lớn hơn Thành tiền — xem `jpQRVuotTien_` (`00_Config`). Đặt SAU
       `jpThieuChiSo_` vì thiếu số thì phải nhắc thiếu trước; đặt TRƯỚC phép lý do lệch
       máy vì số lệch tính ra từ chính mấy ô này, nói lý do cho một con số sai là vô nghĩa. */
    var qrSai = jpQRVuotTien_(rows);
    if (qrSai.length) {
      throw new Error('QR lớn hơn Thành tiền — app của máy báo "Thành tiền" ĐÃ GỒM cả ' +
        'tiền mặt lẫn QR, nên QR không thể lớn hơn. Sửa lại: ' +
        qrSai.slice(0, 4).join(' · ') + (qrSai.length > 4 ? '…' : '') +
        '. Để nguyên thì tiền phải nộp tính ra ÂM và cơ sở coi như không phải nộp gì.');
    }

    if (jpNum_(head.adjMachine) !== 0 && !jpStr_(head.adjMachineNote)) {
      throw new Error('Nhập lý do cho khoản lệch máy');
    }
    if (jpNum_(head.refundCustomer) !== 0 && !jpStr_(head.refundNote)) {
      throw new Error('Nhập lý do cho khoản hoàn khách');
    }

    /* Chốt cảnh báo thiếu ảnh vào báo cáo. KHÔNG chặn nộp (Andy chốt 03/08/2026):
       mạng ở mall hay hỏng, chặn là nhân viên mắc kẹt cả buổi. Nhưng phải lưu lại
       để kế toán mở báo cáo là thấy và trả về được, chứ không chỉ hiện thoáng
       trên máy nhân viên lúc bấm nộp. */
    var pw = jpPhotoWarns_(reportId, head.locationId).warns;

    jpFields_(JP_TABS.REPORTS, head._row, {
      status: JP_ST_PENDING,
      submittedAt: new Date(),
      photoWarnJson: pw.length ? JSON.stringify(pw) : '',
      rejectPart: '', rejectReason: '', rejectBy: '', rejectAt: ''
    });
    jpAudit_(u, 'REPORT_SUBMIT', head.id, head.locationName,
             { rows: rows.length, total: jpNum_(head.totalSubmit), thieuAnh: pw.length });

    return { ok: true, status: JP_ST_PENDING, thieuAnh: pw.length,
             msg: 'Đã nộp báo cáo ' + jpDMY_(head.fromDate) + ' – ' + jpDMY_(head.toDate) +
                  (pw.length ? ' · còn thiếu ' + pw.length + ' chỗ ảnh, kế toán sẽ thấy' : '') };
  });
}

/**
 * Các ô còn để trống "chỉ số sau" — chặn nộp.
 *
 * Không phải chuyện hình thức: chỉ số sau của kỳ này là chỉ số ĐẦU KỲ của kỳ sau,
 * và ô đầu kỳ bị khoá nên nhân viên kỳ sau không sửa được. Để trống ⇒ kỳ sau
 * carry ra 0 ⇒ `Actual = Sau − 0` = cả số trên mặt đồng hồ thành doanh thu một kỳ.
 *
 * Đồng hồ ĐẾM TRỨNG không chặn — nhiều điểm chưa dùng tới nó, chỉ cảnh báo W8.
 */
function jpThieuChiSo_(rows) {
  var thieu = [];
  (rows || []).forEach(function (r) {
    var kind = jpStr_(r.rowKind) || JP_ROW_MONEY;
    /* Bảng tồn kho và kho ngoài không có đồng hồ nên không chặn nộp vì thiếu chỉ số */
    if (kind === JP_ROW_STOCK || kind === JP_ROW_NGOAI) return;
    /* ⚠️ Dòng nhân viên thêm ra rồi để trống KHÔNG được chặn nộp — xem `jpDongTrong_`.
       Gặp thật 26/08/2026: một dòng thứ 16 để trống làm SBPQ không nộp được báo cáo nào,
       và câu chặn còn chỉ vào cột "ô máy" mà mẫu TÁCH không có. */
    if (jpDongTrong_(r)) return;

    /*⚠️ Dòng CHƯA CHỌN ô máy / chưa chọn mã hàng thì tên cũ chỉ ra `dòng 9` — nhân
       viên đọc xong **không biết là dòng nào** trên bảng, vì đúng dòng đó trên màn
       cũng đang trống trơn. Phải nói ra là nó chưa được chọn, kèm việc cần làm:
       chọn ô máy / mã hàng, hoặc xoá dòng đi. Gặp thật 18/08/2026 ở SBPQ.

       ⚠️ NHƯNG phải là HAI câu riêng, không gộp làm một (sửa 26/08/2026). Gộp thì dòng
       COIN của VICOM 3/2 — có `mAfter 85710` thật, chỉ thiếu chỉ số COIN — ra câu
       *"dòng 1 — chưa chọn ô máy, chọn hoặc xoá dòng này (COIN)"*: nó nói SAI thứ đang
       thiếu, và chữ `(COIN)` bị chôn ở cuối. Nhân viên đi chọn ô máy rồi vẫn bị chặn.
       ⚠️ Và "chọn gì" phải theo ĐÚNG MẪU: mẫu TÁCH không có cột ô máy (Andy chốt
       12/08), nên dòng `MAY` gõ tay phải nói **mã hàng**. Chỉ vào một cột không có trên
       màn hình là đúng lại bệnh 18/08. */
    var coDanhTinh = !!(jpStr_(r.machineId) || jpStr_(r.machineCode) ||
                        jpStr_(r.itemCode)  || jpStr_(r.itemMisa) || jpStr_(r.itemName));
    var ten = jpStr_(r.machineCode) || jpStr_(r.itemCode) || ('dòng ' + jpStr_(r.seq));
    if (!coDanhTinh) {
      var can = (kind === JP_ROW_HANG || (kind === JP_ROW_MAY && jpMayGoTay_(r)))
                ? 'mã hàng' : 'ô máy';
      thieu.push('dòng ' + jpStr_(r.seq) + ' — chưa chọn ' + can +
                 ', chọn hoặc xoá dòng này');
    }

    /* Dòng HÀNG của mẫu TÁCH không có đồng hồ, nhưng **ĐÃ BÁN chặn nộp y hệt**.
       ⚠️ Đổi 05/08/2026 cùng lượt Andy chốt "còn lại và tồn cuối máy tính": ô chặn nay
       là `soldQty` (NV gõ), KHÔNG còn là `stockActual`. `stockActual` thành ô "đếm thực
       tế" TUỲ CHỌN nên chặn theo nó là chặn oan mọi mã không đếm.
       Lập luận giữ nguyên: tồn cuối kỳ này là tồn đầu kỳ sau và ô đầu kỳ bị khoá, nên
       để trống là kỳ sau carry sai ⇒ giá vốn 632 lệch. Ô trống là CHƯA KHAI, không
       phải bán được 0. */
    if (kind === JP_ROW_HANG) {
      if (jpBlank_(r.soldQty)) thieu.push(ten + ' (số đã bán)');
      return;
    }

    /*
     * Dòng MÁY của mẫu TÁCH — SBPQ **không còn đồng hồ** (Andy chốt 12/08/2026:
     * *"cái này sẽ là nhân viên tự điền hết"*). Chặn theo `mAfter` là chặn nộp
     * **mọi** báo cáo SBPQ mãi mãi, vì ô đó không còn trên bảng.
     *
     * Ba ô phải có, mỗi ô một lý do riêng:
     *   `amount`   thiếu ⇒ doanh thu 5111 của ô máy đó **về 0** mà không ai báo
     *   `cash`     thiếu ⇒ `jpLechTM_` trả 0 ⇒ lệch tan biến, thừa/thiếu quỹ mất dấu
     *   `cashReal` thiếu ⇒ chính là **tiền phải nộp**, không có nó thì không biết
     *                      cơ sở phải giao về bao nhiêu
     * Ô **QR để trống là 0** — hợp lệ, đa số dòng không có khách quét mã.
     *
     * ⚠️ Báo cáo đời cũ CÓ đồng hồ thì vẫn chặn theo đồng hồ: tiền của chúng suy ra
     * từ `mAfter`, đòi thêm ba ô kia là chặn oan một báo cáo đã đủ số.
     */
    if (kind === JP_ROW_MAY && jpMayGoTay_(r)) {
      if (jpBlank_(r.amount))   thieu.push(ten + ' (thành tiền)');
      if (jpBlank_(r.cash))     thieu.push(ten + ' (tiền mặt app)');
      if (jpBlank_(r.cashReal)) thieu.push(ten + ' (tiền mặt thực tế)');
      return;
    }

    if (jpBlank_(r.mAfter)) thieu.push(ten + (kind === JP_ROW_COIN ? ' (MONEY)' : ''));
    if (kind === JP_ROW_COIN && jpBlank_(r.cAfter)) thieu.push(ten + ' (COIN)');
  });
  return thieu;
}

/*──────────────────── ⑥ DANH SÁCH BÁO CÁO CỦA NHÂN VIÊN ────────────────────*/

function jpMyReports(token, limit) {
  var u = jpNeedNV_(jpAuth_(token));
  var n = jpNum_(limit) || 30;
  return jpRows_(JP_TABS.REPORTS)
    .filter(function (r) { return String(r.userId) === String(u.id); })
    .sort(function (a, b) { return jpDate_(a.fromDate) < jpDate_(b.fromDate) ? 1 : -1; })
    .slice(0, n)
    .map(function (r) {
      return {
        id: String(r.id), locationName: jpStr_(r.locationName),
        fromDate: jpDate_(r.fromDate), toDate: jpDate_(r.toDate),
        machineType: jpStr_(r.machineType) || JP_TYPE_MONEY,
        status: jpStr_(r.status), totalSubmit: jpNum_(r.totalSubmit),
        warnCount: jpNum_(r.warnCount),
        chuKy: jpChuKy_(r),
        rejectReason: jpStr_(r.rejectReason)
      };
    });
}


/*──────────────────── ⑦ XOÁ BÁO CÁO CÒN NHÁP ────────────────────
 *
 * Andy 31/08/2026: *"nhớ là nháp có chức năng xóa đi để tránh bị nặng quá"*.
 * Đo trên sheet thật đúng lúc đó: **30/66** báo cáo mang `NHAP`, giữ **271/1294**
 * dòng `JP_Rows` (21%) và **32/93** `JP_Zones` (34%); **10** cái rỗng hoàn toàn.
 *
 * ⚠️⚠️ CHỈ `NHAP`, và lý do "chỉ nó thì an toàn" là một chốt THẬT, không phải cho
 * chắc ăn:
 *
 *   · `jpKyLienTruoc_` **cố ý** bỏ `NHAP` khỏi bảng ưu tiên gieo (*"nháp có thể
 *     đang gõ nửa vời"*), và `jpPrevClosing_` chỉ chốt theo `HOAN_TAT`. Cộng lại:
 *     xoá một nháp **KHÔNG thể** làm đổi số đầu kỳ của bất cứ báo cáo nào khác.
 *     Đây là điều kiện làm cho việc xoá trở nên vô hại — mất nó là mất cả tính
 *     năng, nên đừng "dọn" hai hàm kia cho `NHAP` vào tham gia gieo.
 *   · `HOAN_TAT` đã ra **sổ 632 + công nợ 131**. Xoá là sổ lệch mà **KHÔNG có
 *     đường lần** — bút toán được SUY RA từ báo cáo, báo cáo mất là bút toán mất
 *     luôn, im lặng, và bảng cân đối **vẫn CÂN**.
 *   · `CHO_DUYET` đang nằm trên bàn kế toán, `CAN_SUA` là cái kế toán đã trả về và
 *     đang chờ nhân viên sửa. Xoá là làm mất việc của người khác mà họ không biết.
 *
 * ⚠️ ĐỌC LẠI head **BÊN TRONG** khoá. Đọc trước rồi xoá sau là chừa một cửa sổ để
 * nhân viên bấm Nộp xen vào giữa ⇒ xoá mất một báo cáo **đã nộp**, đúng thứ điều
 * kiện trạng thái ở trên tồn tại để chặn.
 *
 * ⚠️ XOÁ CON TRƯỚC, HEAD SAU CÙNG. Làm ngược thì chết giữa đường là `JP_Rows`
 * thành rác **VÔ HÌNH** — không màn nào còn thấy chúng, mà đó đúng là cái nặng
 * sheet đang phải dọn. Xoá con trước thì chết giữa đường chỉ còn lại một nháp
 * rỗng: vẫn thấy, vẫn xoá lại được.
 */
function jpXoaBaoCaoNhap(token, reportId) {
  var u = jpAuth_(token);
  var id = jpStr_(reportId);
  if (!id) throw new Error('Thiếu mã báo cáo');

  return jpLock_(function () {
    var head = jpFindOne_(JP_TABS.REPORTS, 'id', id);
    if (!head) throw new Error('Không tìm thấy báo cáo ' + id);

    var st = jpStr_(head.status);
    if (st !== JP_ST_DRAFT) {
      throw new Error('Chỉ xoá được báo cáo còn NHÁP. ' + id + ' đang ở ' +
        (st || '?') + ' — ' +
        (st === JP_ST_DONE
          ? 'đã duyệt đủ hai chữ ký và đã vào sổ giá vốn + công nợ'
          : st === JP_ST_FIXING
            ? 'kế toán đã trả về và đang chờ bạn sửa'
            : 'đã nộp, kế toán đang xử lý') + '.');
    }

    /* Nhân viên: chỉ báo cáo CỦA MÌNH, và phải thuộc cơ sở của mình. Kế toán: nháp
       nào cũng được — họ là người dọn sheet. Vai trò khác thì không có cửa nào:
       web ở `ANYONE_ANONYMOUS` nên ẩn nút không chặn được gì. */
    if (jpIsNV_(u)) {
      jpNeedLoc_(u, head.locationId);
      jpNeedOwner_(u, head);
    } else if (!jpIsKT_(u)) {
      throw new Error('Không có quyền xoá báo cáo');
    }

    /* ⚠️ ĐI KIỂM, đừng TIN. Một nháp lẽ ra không thể có dòng xuất kho hay lần nộp
       tiền nào — `jpXuatKhoBaoCao_` chỉ chạy khi báo cáo thành `HOAN_TAT`. Nhưng
       dữ liệu đời cũ và những lần sửa tay đã từng để lại thứ trái luật, và cái giá
       của việc đoán sai ở đây là **mất giá vốn mà sổ vẫn CÂN**. Hai lượt đọc này
       rẻ hơn nhiều so với một lần đoán sai. */
    var xuat = jpFind_(JP_TABS.KHO_XUAT, 'reportId', id);
    if (xuat.length) {
      throw new Error('KHÔNG xoá: báo cáo còn NHÁP mà đã có ' + xuat.length +
        ' dòng xuất kho (sổ 632/641). Dữ liệu này trái luật — gọi kế toán soát ' +
        'trước, đừng xoá đi mất dấu.');
    }
    var traTien = jpFind_(JP_TABS.PAYMENTS, 'reportId', id);
    if (traTien.length) {
      throw new Error('KHÔNG xoá: báo cáo còn NHÁP mà đã có ' + traTien.length +
        ' lần nộp tiền. Xoá đi là mất dấu số tiền đã nộp — huỷ các lần nộp trước.');
    }

    var dong = jpFind_(JP_TABS.ROWS, 'reportId', id);
    var khu  = jpFind_(JP_TABS.ZONES, 'reportId', id);
    var anh  = jpFind_(JP_TABS.PHOTOS, 'reportId', id);

    /* Ghi audit TRƯỚC khi xoá: sau khi xoá thì không còn gì để đọc ra mà ghi.
       `jpAudit_` nuốt lỗi (chủ ý) nên nó không chặn được việc xoá, nhưng đặt
       trước thì dòng audit mang được số liệu thật của cái vừa mất. */
    jpAudit_(u, 'REPORT_DELETE', id, jpStr_(head.locationName), {
      status: st,
      tuNgay: jpDate_(head.fromDate), denNgay: jpDate_(head.toDate),
      loaiMay: jpStr_(head.machineType), bcMau: jpStr_(head.bcMau),
      cuaAi: jpStr_(head.userName),
      soDong: dong.length, soKhu: khu.length, soAnh: anh.length
    });

    /* Ảnh: bỏ file Drive vào thùng rác rồi mới xoá dòng — cùng cách
       `jpDeletePhoto` đã làm, kể cả việc nuốt lỗi Drive (mất một file ảnh không
       được phép làm hỏng việc xoá dòng sheet). */
    anh.forEach(function (p) {
      try { DriveApp.getFileById(p.fileId).setTrashed(true); } catch (e) {}
    });

    var xDong = jpDeleteWhere_(JP_TABS.ROWS, 'reportId', id);
    var xKhu  = jpDeleteWhere_(JP_TABS.ZONES, 'reportId', id);
    var xAnh  = jpDeleteWhere_(JP_TABS.PHOTOS, 'reportId', id);
    jpDelete_(JP_TABS.REPORTS, head._row);          // HEAD SAU CÙNG

    return { ok: true, id: id, soDong: xDong, soKhu: xKhu, soAnh: xAnh,
             msg: 'Đã xoá nháp ' + id + ' — ' + xDong + ' dòng, ' + xKhu +
                  ' khu vực' + (xAnh ? ', ' + xAnh + ' ảnh' : '') + '.' };
  });
}

