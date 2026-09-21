/*═══════════════════════════════════════════════════════════════════════════
 * JP CAPSULE v2 — 04_TinhToan
 * ---------------------------------------------------------------------------
 * TOÀN BỘ công thức tiền và cảnh báo. Sai số ở đâu cũng chỉ sửa file này.
 *
 * ── MÁY TIỀN (form SUMMARY STATION SALES AND CASH REPORT) ──
 *   Có HAI đồng hồ riêng, đối chiếu chéo với nhau:
 *     · Đồng hồ TIỀN  : Actual = After − Before ; Collection = Actual ÷ 2
 *                       Tiền = Collection × 10.000 = Actual × 5.000
 *     · Đồng hồ TRỨNG : Đã bán = After − Before
 *     · Kiểm chéo     : Tiền ÷ Giá theo mã  PHẢI BẰNG  Đã bán
 *     · Còn lại       = Ban đầu − Đã bán
 *
 * ── MÁY XU ──
 *   (a) Bảng tiền theo vị trí: COIN × 50.000 = MONEY × 10.000 = Cash + CK
 *   (b) Bảng hàng tồn theo mã: Hàng bán = Tổng xu kiểm ÷ Giá xu
 *       Hàng còn = Tồn đầu + Bổ sung1 + Bổ sung2 − Xu lạ − Hàng bán − Lỗi/mẫu − Trả kho
 *═══════════════════════════════════════════════════════════════════════════*/

/*──────── ⓪ CHÊNH LỆCH MỘT ĐỒNG HỒ ────────*/

/**
 * Sau − Trước, kèm cảnh báo. Trả 0 nếu không tính được.
 *
 * Trước đây chỗ này viết `if (mA && mAct < 0)`, nên khi nhân viên nhập "trước"
 * rồi BỎ TRỐNG "sau" thì mA = 0 ⇒ điều kiện sai ⇒ không cảnh báo, không kẹp về 0,
 * và chênh lệch ÂM chảy thẳng vào doanh thu. Nay tách rõ hai tình huống.
 */
function jpMeterDelta_(before, after, tenDongHo, warns) {
  var b = jpNum_(before), a = jpNum_(after);

  if (jpBlank_(after)) {
    if (b > 0) {
      warns.push(jpWarn_(JP_WARN.METER_MISSING, tenDongHo + ': trước = ' + b + ', sau còn trống'));
    }
    return 0;
  }
  if (a < b) {
    warns.push(jpWarn_(JP_WARN.METER_BACKWARD, tenDongHo + ': ' + b + ' → ' + a));
    return 0;
  }
  return a - b;
}

/*──────── ① MÁY TIỀN — 1 dòng = 1 ô/máy ────────*/

/*──────── HOÀN KHÁCH THEO MÃ HÀNG (Andy chốt 05/08/2026) ────────
 *
 * Andy: *"phải có hoàn khách theo hàng vì khi hoàn tức là khách đưa 500k đi nhưng chỉ
 * lấy 3 sp (300k cho mã 100k chẳng hạn), thì hoàn khách 200k thì đâu có bán 5 quả"*.
 *
 * Đúng tình huống: đồng hồ tiền ghi 500.000đ nên nếu suy số quả TỪ TIỀN thì ra 5, mà
 * hàng ra khỏi máy chỉ 3. Khoản hoàn 200.000đ **chính là lời giải thích** cho chỗ lệch.
 * Một số hoàn chung ở cuối báo cáo không nói được hụt ở mã nào — mà mỗi mã một giá.
 *
 * Nhân viên gõ **TIỀN hoàn** (Andy chọn 05/08): tiền là con số phải khớp với két, và
 * hoàn lẻ không tròn quả (máy kẹt, bù thiện chí) vẫn ghi được. Số quả do máy suy ra.
 *
 * ⚠️⚠️ CHỖ DỄ ĐẾM HAI LẦN NHẤT CỦA CẢ REPO — đọc kỹ trước khi sửa:
 *
 *   Trừ `refundQty` khỏi `soldQty` **CHỈ KHI** `soldQty` được suy ra TỪ TIỀN.
 *
 * Vì sao: có hai đường ra `soldQty`, và chúng phản ứng khác nhau với việc hoàn.
 *
 * | Đường ra `soldQty` | Ở đâu | Máy đã nhả hàng chưa | Trừ hoàn? |
 * |---|---|---|---|
 * | `amount / price` (suy TỪ TIỀN) | `jpCalcMoneyRow_` khi CHƯA có đồng hồ trứng | tiền tính cả phần khách không lấy | **TRỪ** |
 * | đồng hồ đếm trứng | `jpCalcMoneyRow_` khi có `hAfter` | đồng hồ chỉ đếm quả ĐÃ NHẢ | **KHÔNG** |
 * | kiểm đếm tồn (`đầu + nhập − trả − lỗi − cuối`) | `jpCalcHangRow_`, `jpCalcStockRow_` | tồn cuối đếm được đã phản ánh đúng | **KHÔNG** |
 *
 * Trừ ở hai đường dưới là **xuất kho thiếu** và **632 thiếu giá vốn** — mà sổ VẪN CÂN
 * nên không phép kiểm nào bắt được. Đúng loại bẫy `COIN`/`NGOAI` đã mắc hai lần.
 *
 * Trả về `{amt, qty, canhBao}`. `qty` làm tròn xuống: hoàn 150.000đ ở mã 100.000đ là
 * 1 quả + 50.000đ lẻ, KHÔNG phải 1,5 quả — kho không nhận số lẻ.
 */
function jpHoanTheoMa_(row, price) {
  var amt = Math.abs(jpNum_(row.refundAmt));
  if (amt === 0) return { amt: 0, qty: 0, canhBao: '' };

  if (price <= 0) {
    return { amt: amt, qty: 0, canhBao:
      'Hoàn khách ' + jpMoney_(amt) + 'đ mà mã ' + jpStr_(row.itemCode) +
      ' không suy ra được giá — không quy ra được số quả, tiền vẫn trừ đúng' };
  }
  var qty = Math.floor(amt / price);
  var du  = amt - qty * price;
  return { amt: amt, qty: qty, canhBao: du === 0 ? '' :
    'Hoàn khách ' + jpMoney_(amt) + 'đ không chia tròn cho giá ' + jpMoney_(price) +
    'đ — quy ra ' + qty + ' quả, còn lẻ ' + jpMoney_(du) + 'đ' };
}

/**
 * W14 — DÒNG CÓ BÁN HÀNG MÀ MÃ KHÔNG SUY RA ĐƯỢC GIÁ. **NGUỒN DUY NHẤT** của cảnh báo
 * này; ba hàm tính dòng giữ hàng đều gọi vào đây.
 *
 * ⚠️ VÌ SAO PHẢI GỌI Ở CẢ BA: trước 22/08/2026 câu này chỉ nằm trong `jpCalcHangRow_`,
 * tức **chỉ mẫu TÁCH** được cảnh báo. Dòng `MONEY` (máy tiền mẫu chung) và `STOCK`
 * (máy xu) — hai loại dòng chiếm gần hết hệ thống — **không cảnh báo gì cả**. Đo trên
 * sheet thật: **1.173 cái đã bán mang mã ngoài danh mục**, phần lớn là dòng `MONEY` ở
 * BÌNH TÂN và VINWONDER, và **không ai được báo một lần nào**.
 *
 * ⚠️ Danh sách gọi phải trùng `JP_ROW_GIU_HANG` (MONEY · STOCK · HANG). Thêm loại dòng
 * giữ hàng mới thì thêm lời gọi ở đó — thiếu là loại dòng đó lại im lặng như cũ.
 *
 * ⚠️ Câu chữ nói HẬU QUẢ, không nói trạng thái: mã không suy ra được giá thì gần như
 * chắc chắn không có trong danh mục, nên duyệt xong kho không tìm được lớp tồn ⇒ giá
 * vốn về 0đ và **sổ 632 thiếu trong khi sổ vẫn CÂN**. Cùng bài học `jpKetKy_` (11/08).
 */
function jpCanhBaoMaKhongGia_(row, warns) {
  var ban = jpNum_(row.soldQty);
  if (ban <= 0 || jpNum_(row.price) > 0) return;
  warns.push(jpWarn_(JP_WARN.PRICE_NO_RATE,
    'Bán ' + ban + ' cái mà mã ' + jpStr_(row.itemCode) +
    ' không suy ra được giá — không đối chiếu được với tiền, và nếu mã này không có ' +
    'trong danh mục thì duyệt xong kho không tìm được lớp tồn nên ' + ban +
    ' cái này ra sổ 632 với giá vốn 0đ'));
}

function jpCalcMoneyRow_(row) {
  var warns = [];

  /* --- Đồng hồ TIỀN --- */
  var mAct = jpMeterDelta_(row.mBefore, row.mAfter, 'Đồng hồ tiền', warns);

  row.mActual   = mAct;
  /*
   * ⚠️ GIÁ 1 XUNG LẤY THEO KHU, không dùng thẳng hằng số nữa — xem `jpGiaXung_`
   * (`00_Config`). SUNWORLD PQ có HAI loại máy tiền tách theo khu vực (Andy 29/08/2026).
   *
   * ⚠️ `collection` phải suy từ `amount`, **KHÔNG** giữ `mAct / 2`: cột đó là "đơn vị
   * 10.000đ" của form giấy, nên với máy 10.000đ/xung nó phải bằng CHÍNH số xung. Để
   * `mAct / 2` là cột trên màn hình nói một đằng, tiền tính một nẻo — mà form giấy là
   * thứ kế toán đối chiếu bằng mắt.
   */
  var giaXung   = jpGiaXung_(row.giaXung);
  row.amount     = mAct * giaXung;
  row.collection = row.amount / 10000;              // đơn vị 10.000đ, đúng như form
  row.cash       = row.amount - jpNum_(row.bank);

  /*
   * LỆCH TIỀN MẶT theo TỪNG MÃ (Andy chốt 30/08/2026) — `jpLechTM_` (`00_Config`) là
   * nguồn duy nhất của phép trừ này, dùng chung với mẫu TÁCH.
   *
   * ⚠️ Ô "Thực thu" TRỐNG là **CHƯA ĐẾM**, không phải đếm được 0 — `jpLechTM_` trả 0
   * khi trống. Coi trống là 0 thì lệch ra `−(tiền mặt máy báo)` và `jpCalcReport_` đặt
   * `adjMachine = Σ lệch` ⇒ **tiền phải nộp của cả báo cáo về 0**, mất trắng một kỳ mà
   * sổ vẫn cân. Đây đúng là cái bẫy đã ghi ở `jpLechTM_`.
   */
  row.lechTM = jpLechTM_(row);
  if (row.lechTM !== 0) {
    warns.push(jpWarn_(JP_WARN.LECH_TM_APP,
      'Đếm được ' + (row.lechTM > 0 ? 'NHIỀU hơn' : 'ÍT hơn') + ' máy báo ' +
      jpMoney_(Math.abs(row.lechTM)) + 'đ'));
  }

  /* --- Số trứng đã bán: ưu tiên ĐỒNG HỒ ĐẾM TRỨNG, đối chiếu với tiền --- */
  var price = jpGiaDong_(row);          // Misa trước, rồi mới itemCode — xem jpGiaDong_
  row.price = price;

  /*
   * ⚠️⚠️ SỐ TRỨNG SUY TỪ **TIỀN THẬT SỰ THU ĐƯỢC**, không phải từ tiền đồng hồ báo
   * (Andy 31/08/2026: *"Này cộng thêm tiền ví dụ + 100k phải trừ thêm 1 trứng mới đúng
   * á e"*).
   *
   * Đồng hồ tiền đếm XUNG. Máy nhả một quả mà không ăn được xung thì tiền vẫn nằm
   * trong hộp nhưng `amount` không có nó — và trước bản này quả trứng đó **không bao
   * giờ ra khỏi tồn**: `soldQty` suy từ `amount`, nên tồn sổ sách cao hơn tồn thật
   * VĨNH VIỄN, kỳ nào kiểm đếm cũng báo THIẾU đúng chỗ đó, và **632 hụt đúng một quả
   * giá vốn trong khi sổ VẪN CÂN**.
   *
   * ⚠️⚠️ **CHỈ CỘNG PHẦN THỪA, KHÔNG TRỪ PHẦN HỤT** — `Math.max(0, ...)`. Hai chiều
   * KHÔNG đối xứng:
   *   · thừa tiền ⇒ có xung không đếm được ⇒ quả trứng ĐÃ RA. Cộng vào là đúng.
   *   · hụt tiền ⇒ đồng hồ ĐÃ đếm xung, tức máy đã nhận tiền và ĐÃ NHẢ trứng; tiền
   *     thiếu là tiền **mất sau khi bán**, không phải trứng còn trong máy. Trừ trứng ở
   *     đây là **xuất kho thiếu ⇒ 632 hụt giá vốn, mà sổ vẫn CÂN** — đúng loại lỗi
   *     không phép kiểm nào bắt được. Máy đếm dư xung thật thì `stockActual` sẽ báo
   *     THỪA và kế toán nhìn thấy; tự trừ đi thì không ai còn thấy gì.
   *
   * ⚠️ `jpLechTM_` trả **0 khi ô "Thực thu" TRỐNG** (chưa đếm) ⇒ báo cáo đời cũ và mọi
   * dòng chưa đếm ra **y hệt trước**. Đừng "dọn" thành `jpNum_(row.cashReal) - ...`.
   *
   * ⚠️ Chỉ đụng `soldQty`, **KHÔNG đụng `row.amount`**: doanh thu vẫn là tiền đồng hồ
   * (`revMeter`), phần thừa đã đi đường riêng qua `adjMachine`. Cộng vào `amount` là
   * **đếm hai lần** đúng khoản đó.
   */
  var tienBan     = row.amount + Math.max(0, row.lechTM);
  var soldByMoney = price > 0 ? Math.floor(tienBan / price) : 0;
  var hasCounter  = !jpBlank_(row.hAfter);
  var soldByMeter = hasCounter
    ? jpMeterDelta_(row.hBefore, row.hAfter, 'Đồng hồ đếm trứng', warns)
    : 0;

  /* --- Hoàn khách gắn ở đúng mã này — xem chú thích dài ở `jpHoanTheoMa_` --- */
  var hoan = jpHoanTheoMa_(row, price);
  row.refundAmt = hoan.amt;
  row.refundQty = hoan.qty;
  if (hoan.canhBao) warns.push(jpWarn_(JP_WARN.REFUND_REMAINDER, hoan.canhBao));
  if (hoan.amt > 0 && !jpStr_(row.refundRowNote)) {
    warns.push(jpWarn_(JP_WARN.MISSING_REASON,
      'Hoàn khách ' + jpMoney_(hoan.amt) + 'đ ở mã này chưa có lý do'));
  }

  /* Đồng hồ trứng là số vật lý — tin nó trước. Chưa nhập thì mới suy từ tiền.
     ⚠️ CHỈ nhánh suy-từ-tiền mới trừ hoàn khách: đồng hồ trứng đếm quả ĐÃ NHẢ nên
     phần khách không lấy vốn đã không nằm trong đó. Trừ cả hai nhánh là xuất kho
     thiếu ⇒ 632 thiếu giá vốn, mà sổ VẪN CÂN nên không ai bắt được. */
  var sold = hasCounter ? soldByMeter : Math.max(0, soldByMoney - hoan.qty);
  row.soldQty = sold;

  if (hasCounter && hoan.qty > 0) {
    warns.push(jpWarn_(JP_WARN.MONEY_MISMATCH,
      'Có đồng hồ đếm trứng nên số bán lấy theo đồng hồ (' + soldByMeter +
      ') — hoàn khách ' + hoan.qty + ' quả KHÔNG trừ thêm, vì đồng hồ chỉ đếm quả đã nhả'));
  }

  /* KIỂM CHÉO hai đồng hồ — lý do máy tiền có hai đồng hồ ngay từ đầu.
     ⚠️ So với `soldByMoney` ĐÃ TRỪ hoàn khách. Không trừ thì mọi dòng có hoàn đều
     báo lệch oan đúng bằng số quả hoàn — mà đó là khoản ĐÃ ĐƯỢC GIẢI THÍCH. Báo sai
     vài lần là không ai đọc cảnh báo nữa. */
  var tienDuSuc = soldByMoney - hoan.qty;
  if (hasCounter && price > 0 && soldByMeter !== tienDuSuc) {
    var lechTien = (soldByMeter - tienDuSuc) * price;
    warns.push(jpWarn_(JP_WARN.MONEY_MISMATCH,
      'Đồng hồ trứng bán ' + soldByMeter + ' · tiền chỉ đủ ' + tienDuSuc +
      ' (' + jpMoney_(tienBan) + 'đ' +
      (hoan.amt > 0 ? ' − hoàn ' + jpMoney_(hoan.amt) + 'đ' : '') +
      ' ÷ ' + jpMoney_(price) + 'đ) · lệch ' +
      jpMoney_(Math.abs(lechTien)) + 'đ ' +
      (lechTien > 0 ? 'THIẾU TIỀN' : 'THỪA TIỀN')));
  }

  /*
   * ⚠️⚠️ CHỈ nhắc khi máy THẬT SỰ CÓ đồng hồ đếm trứng.
   *
   * Andy chốt 15/08/2026: **mọi cơ sở máy TIỀN đều không có đồng hồ đếm trứng**, và
   * `coDhTrung = 'N'` đã **ẩn hai ô `hBefore`/`hAfter` khỏi form**. Nhưng chỗ này vẫn
   * nhắc *"chưa nhập đồng hồ đếm trứng"* cho **từng dòng** ⇒ một báo cáo 11 ô máy đẻ ra
   * 11 cảnh báo về **một ô không tồn tại trên màn hình**, nhân viên không có cách nào
   * làm nó tắt. Andy nhìn báo cáo thật: *"sao cảnh báo nhiều thế này"* — 37 cảnh báo.
   *
   * Báo động giả vài lần là không ai đọc cảnh báo nữa, và lúc đó cảnh báo THẬT
   * (lệch tiền, lệch hàng) chìm nghỉm cùng.
   *
   * ⚠️ Dò bằng **DỮ LIỆU**, không dò bằng cờ hiển thị: máy có đồng hồ thì ô "trước"
   * luôn có số (gieo từ kỳ trước). Cả hai ô cùng trống ⇒ máy này không có đồng hồ trứng.
   * `00_Config` đã chốt *"cờ hiển thị KHÔNG được đụng cách tính"* — và luật đó vẫn giữ
   * nguyên ở đây: `soldQty` không đổi một chút nào, chỉ bớt một câu nhắc vô nghĩa.
   *
   * ⚠️ `hBefore` có số mà `hAfter` trống thì **VẪN nhắc** — đó mới là quên nhập thật,
   * và là lý do cảnh báo này tồn tại.
   */
  /*
   * ⚠️⚠️ `hBefore = 0` **KHÔNG** phải "có đồng hồ". Bản đầu dò bằng `!jpBlank_(hBefore)`
   * — mà số `0` không phải ô trống, nên **16 dòng mang số 0 sót lại** vẫn bị coi là có
   * đồng hồ mà quên nhập. Đo thật trên `RP20260809-0018` (16/08/2026): 22 dòng hai ô
   * cùng trống thì hết cảnh báo đúng, còn 16 dòng `hBefore = 0` thì **13 cái W8 vẫn
   * nằm nguyên** — tức bản vá chỉ ăn được một nửa.
   *
   * Máy đang chạy có đồng hồ thật thì chỉ số kỳ trước **luôn > 0** (gieo từ kỳ trước).
   * Nên chỉ số `0` ở ô "trước" là số sót, không phải số đọc được từ máy.
   *
   * ⚠️ Đánh đổi đã biết: máy có đồng hồ **hoàn toàn mới**, kỳ đầu tiên đúng bằng 0, mà
   * nhân viên quên nhập ô "sau" thì kỳ đó không được nhắc. Chấp nhận được — sang kỳ sau
   * `hBefore` đã > 0 nên cảnh báo trở lại, và tiền vẫn tính đúng vì `soldQty` suy từ
   * tiền khi chưa có đồng hồ. Đổi lại, 13 cảnh báo vô nghĩa mỗi báo cáo biến mất.
   */
  var coDhTrungThat = jpNum_(row.hBefore) > 0 || !jpBlank_(row.hAfter);
  if (!hasCounter && coDhTrungThat && mAct > 0) {
    warns.push(jpWarn_(JP_WARN.METER_MISSING,
      'Chưa nhập đồng hồ đếm trứng — không kiểm chéo được số bán'));
  }

  /*
   * Nói ra việc quy tiền thừa thành trứng. ⚠️ Không có câu này thì `Đã bán` tự nhảy lên
   * một số KHÔNG bằng `tiền đồng hồ ÷ giá`, và kế toán soi vào tưởng máy tính sai.
   * ⚠️ Chỉ nói khi THẬT SỰ ra thêm được quả nào: lệch +50.000đ với giá 100.000đ thì
   * `floor` không ra thêm quả nào, câu "quy ra 0 quả" là tiếng ồn — phần dư đó đã có
   * cảnh báo `PRICE_REMAINDER` ngay dưới lo rồi.
   */
  if (!hasCounter && price > 0 && row.lechTM > 0) {
    var themQua = Math.floor(tienBan / price) - Math.floor(row.amount / price);
    if (themQua > 0) {
      warns.push(jpWarn_(JP_WARN.LECH_QUY_TRUNG,
        'Thu thừa ' + jpMoney_(row.lechTM) + 'đ so với đồng hồ ⇒ tính thêm ' + themQua +
        ' quả đã bán (đồng hồ không đếm được cú đó, nhưng hàng đã ra khỏi máy)'));
    }
  }

  if (price > 0) {
    /* ⚠️ So với `tienBan`, KHÔNG so với `row.amount`: `soldByMoney` nay suy từ tiền
       thu được, nên trừ vào `amount` là ra số dư ÂM giả đúng bằng phần lệch. */
    var du = tienBan - soldByMoney * price;
    if (du !== 0) {
      warns.push(jpWarn_(JP_WARN.PRICE_REMAINDER,
        'Dư ' + jpMoney_(du) + 'đ so với giá ' + jpMoney_(price) + 'đ/trứng', du));
    }
  }

  /* --- Hàng hoá: Còn lại = Ban đầu + Bổ sung − Đã bán − Trả về kho --- */
  var open = jpNum_(row.hOpen);
  var them = jpNum_(row.addQty1) + jpNum_(row.addQty2);
  var tra  = jpNum_(row.returnQty);
  var out  = jpNum_(row.stockOut);
  var left = open + them - sold - tra;
  row.hLeft = left;

  if (row.stockActual !== '' && row.stockActual !== null && row.stockActual !== undefined) {
    var act = jpNum_(row.stockActual);
    /*
     * ⚠️⚠️ **KHÔNG cộng `Tồn ngoài`.** Bản trước tính `expect = left + out` ⇒ đếm kho đệm
     * HAI LẦN, và báo THIẾU đúng bằng phần tồn ngoài trên **mọi** dòng.
     *
     * Andy giải thích 15/08/2026: *"chỗ nào bán nhiều quá sẽ có kho riêng và khi họ lấy
     * ra và bổ sung trong máy"* — `Tồn ngoài` là **kho đệm TẠI CHỖ**. Hàng ở đó **chưa
     * bán, chưa trả kho**, nên nó **đã nằm trong** `left = đầu + bổ sung − bán − trả`.
     * `Hàng tồn thực tế` là số đếm **tổng** cơ sở đang giữ ⇒ so thẳng với `left`.
     *
     * Số thật trên báo cáo Andy gửi, dòng nào cũng khớp KHÍT khi bỏ `+ out`:
     *   B003-Lân  32 − 11 = 21 · đếm 21     Chuột Nước 83 − 16 = 67 · đếm 67
     *   Lucky Cap 41 −  6 = 35 · đếm 35     Ngựa       34 −  6 = 28 · đếm 28
     * Tám ô máy khớp khít cùng lúc thì không phải trùng hợp.
     *
     * ⚠️ Cùng trường `stockOut` nhưng ở **dòng KHO NGOÀI** (`jpCalcStockRow_`) nó mang
     * nghĩa **"cấp ra máy"** và bị TRỪ (xem chú thích ở đó). Hai nghĩa ngược nhau trong
     * cùng một file — đừng "thống nhất" bằng cách sửa đại một bên: mỗi bảng có một luồng
     * hàng riêng, chỉ có tên trường là trùng.
     */
    var expect = left;
    if (act !== expect) {
      var lech = act - expect;
      warns.push(jpWarn_(JP_WARN.STOCK_MISMATCH,
        'Tính ' + expect + ' (đầu ' + open + ' + bổ sung ' + them +
        ' − bán ' + sold + ' − trả kho ' + tra + ')' +
        ' · đếm ' + act + ' · ' + (lech > 0 ? 'THỪA ' + lech : 'THIẾU ' + (-lech))));
    }
    /* ⚠️ Chốt thay thế: kho đệm là MỘT PHẦN của số đã đếm, không thể lớn hơn nó. Lớn hơn
       nghĩa là hai ô đang được hiểu khác nhau — nói ra chỗ đó thay vì báo thiếu hàng. */
    if (out > act) {
      warns.push(jpWarn_(JP_WARN.STOCK_MISMATCH,
        'Tồn ngoài ' + out + ' lớn hơn hàng tồn thực tế ' + act +
        ' — kho đệm là một phần của số đã đếm, không thể nhiều hơn'));
    }
  }

  /* Dòng MONEY giữ hàng (JP_ROW_GIU_HANG) nên PHẢI qua phép W14 — xem hàm đó */
  jpCanhBaoMaKhongGia_(row, warns);

  row.warns = warns;
  row.warnJson = warns.length ? JSON.stringify(warns) : '';
  return row;
}

/*──────── ② MÁY XU — bảng tiền theo vị trí ────────*/

function jpCalcCoinRow_(row) {
  var warns = [];

  var cAct = jpMeterDelta_(row.cBefore, row.cAfter, 'COIN', warns);
  var mAct = jpMeterDelta_(row.mBefore, row.mAfter, 'MONEY', warns);

  var byCoin  = cAct * JP_RATE_COIN;
  var byMoney = mAct * JP_RATE_COIN_MONEY;

  row.cActual = cAct;
  row.mActual = mAct;
  row.amount  = byCoin;
  row.collection = 0;

  if (byCoin !== byMoney) {
    warns.push(jpWarn_(JP_WARN.COIN_VS_MONEY,
      'COIN ' + jpMoney_(byCoin) + 'đ vs MONEY ' + jpMoney_(byMoney) + 'đ'));
  }
  var pay = jpNum_(row.cash) + jpNum_(row.bank);
  if (pay !== byCoin) {
    warns.push(jpWarn_(JP_WARN.VS_PAYBOX,
      'Đồng hồ ' + jpMoney_(byCoin) + 'đ vs thu ' + jpMoney_(pay) + 'đ'));
  }

  /* --- DÒNG COIN KHÔNG GIỮ HÀNG ---------------------------------------------
     Máy xu tách hẳn hai bảng: dòng COIN = tiền theo vị trí, dòng STOCK = hàng
     theo mã. Bảng nhập của nhân viên cũng chỉ có cột tiền ở dòng COIN.
     Trước đây chỗ này vẫn tính `soldQty`/`stockLeftCalc` cho dòng COIN từ các ô
     luôn rỗng, nên ra 0 — vô hại trên màn hình nhưng thành bẫy khi kho xuất giá
     vốn: cộng `soldQty` mọi dòng là cộng cả dòng COIN. Ép về 0 cho rõ, và kho
     lọc theo `rowKind` chứ không dựa vào việc số này tình cờ bằng 0.          */
  row.xuTong        = 0;
  row.soldQty       = 0;
  row.stockLeftCalc = 0;

  row.warns = warns;
  row.warnJson = warns.length ? JSON.stringify(warns) : '';
  return row;
}

/*──────── ③ MÁY XU — bảng hàng tồn theo mã hàng ────────*/

function jpCalcStockRow_(row) {
  var warns = [];

  /* Tổng số xu kiểm các ngày trong kỳ */
  var days = [];
  try { days = row.xuDaysJson ? JSON.parse(row.xuDaysJson) : []; } catch (e) { days = []; }
  var tong = 0;
  days.forEach(function (x) { tong += jpNum_(x); });
  row.xuTong = tong;

  /* Hàng bán = tổng xu kiểm ÷ giá xu */
  var gia = jpNum_(row.giaXu);
  var ban = gia > 0 ? Math.floor(tong / gia) : 0;
  row.soldQty = ban;

  if (gia > 0 && tong % gia !== 0) {
    warns.push(jpWarn_(JP_WARN.COIN_REMAINDER,
      'Tổng xu ' + tong + ' không chia hết cho giá xu ' + gia + ' (dư ' + (tong % gia) + ')',
      tong % gia));
  }

  /* Hàng còn = tồn đầu + bổ sung − xu lạ − bán − lỗi/mẫu − trả kho */
  var left = jpNum_(row.stockOpen) + jpNum_(row.addQty1) + jpNum_(row.addQty2)
           - jpNum_(row.xuLa) - ban - jpNum_(row.defectQty) - jpNum_(row.returnQty);
  row.stockLeftCalc = left;

  if (row.stockActual !== '' && row.stockActual !== null && row.stockActual !== undefined) {
    var act = jpNum_(row.stockActual);
    if (act !== left) {
      warns.push(jpWarn_(JP_WARN.STOCK_MISMATCH,
        'Tính ' + left + ' · đếm ' + act + ' · lệch ' + (act - left)));
    }
  }

  row.amount = 0;
  /* Dòng STOCK giữ hàng (JP_ROW_GIU_HANG) nên PHẢI qua phép W14 — xem hàm đó */
  jpCanhBaoMaKhongGia_(row, warns);

  row.warns = warns;
  row.warnJson = warns.length ? JSON.stringify(warns) : '';
  return row;
}

/*──────── ③b KHO NGOÀI — hàng cơ sở giữ ngoài máy ────────*/

/**
 * Kiểm kê chỗ hàng nằm NGOÀI máy (Andy chốt 04/08/2026).
 *
 *   Còn lại = Tồn đầu + Nhập thêm − Cấp ra máy − Trả về kho
 *
 * `addQty1` = nhập thêm trong kỳ · `stockOut` = cấp ra máy · `returnQty` = trả về kho
 * tổng · `stockActual` = số đếm thực tế.
 *
 * ⚠️ **KHÔNG sinh tiền, KHÔNG sinh giá vốn.** Ép `soldQty`/`amount` về 0 y như
 * `jpCalcCoinRow_` làm với dòng COIN: hàng bán ra đã đếm ở dòng MONEY / STOCK, cộng
 * thêm ở đây là **xuất kho hai lần**. "Cấp ra máy" cũng không phải bán — nó chỉ là
 * chuyển chỗ trong cùng một cơ sở, và số đó đã nằm trong "Ban đầu" của ô máy.
 */
function jpCalcNgoaiRow_(row) {
  var warns = [];

  var open = jpNum_(row.stockOpen);
  var them = jpNum_(row.addQty1);
  var raMay = jpNum_(row.stockOut);
  var tra  = jpNum_(row.returnQty);
  var left = open + them - raMay - tra;

  row.stockLeftCalc = left;
  row.soldQty = 0;                 // xem chú thích trên — đừng "sửa" thành số bán
  row.amount = 0;
  row.xuTong = 0;
  /* Cả `cash` và `bank` cũng phải về 0. Bảng kho ngoài không có ô nào nhập tiền, nên
     bình thường chúng vốn trống — nhưng nếu một dòng ĐỔI loại (nhân viên sửa bảng)
     thì số tiền cũ còn nằm lại trong ô, và `jpCalcReport_` bỏ qua dòng NGOAI nên
     không ai xoá hộ. Ép về 0 ngay đây thì bất biến "không sinh tiền" đúng cả với
     dữ liệu cũ, không phụ thuộc chỗ khác nhớ lọc. */
  row.cash = 0;
  row.bank = 0;

  if (raMay < 0 || them < 0 || tra < 0) {
    warns.push(jpWarn_(JP_WARN.STOCK_MISMATCH, 'Có số âm trong dòng kho ngoài'));
  }

  if (!jpBlank_(row.stockActual)) {
    var act = jpNum_(row.stockActual);
    if (act !== left) {
      warns.push(jpWarn_(JP_WARN.STOCK_MISMATCH,
        'Kho ngoài: tính ' + left + ' (đầu ' + open + ' + nhập ' + them +
        ' − cấp ra máy ' + raMay + ' − trả kho ' + tra + ')' +
        ' · đếm ' + act + ' · ' + (act > left ? 'THỪA ' + (act - left)
                                              : 'THIẾU ' + (left - act))));
    }
  }

  row.warns = warns;
  row.warnJson = warns.length ? JSON.stringify(warns) : '';
  return row;
}

/*──────── ③c MẪU TÁCH — dòng MÁY, chỉ tiền ────────*/

/**
 * Ô máy trong mẫu TÁCH: chỉ đồng hồ tiền, **không giữ hàng**.
 *
 * Giống `jpCalcMoneyRow_` ở phần tiền, nhưng bỏ hẳn phần hàng — vì hàng đã có bảng
 * riêng theo mã. Cột `bank` vẫn có: tiền vào tài khoản là tiền của ĐÚNG ô máy đó.
 *
 * ⚠️ **`soldQty` PHẢI về 0.** `jpCalcMoneyRow_` suy số bán từ tiền khi chưa có đồng
 * hồ trứng (`amount ÷ giá`); giữ nguyên là hàng bán bị đếm ở cả dòng `MAY` và dòng
 * `HANG` ⇒ `jpXuatKhoBaoCao_` **xuất kho hai lần**. Ép về 0 ngay đây chứ không dựa
 * vào chỗ khác nhớ lọc — đúng cách `jpCalcCoinRow_` và `jpCalcNgoaiRow_` đã làm.
 *
 * Vẫn giữ cảnh báo "dư tiền so với giá": ở mẫu này nó là dấu hiệu duy nhất tại chỗ
 * cho biết tiền của ô máy không chia hết cho giá trứng.
 */
function jpCalcMayRow_(row) {
  var warns = [];

  /*
   * SBPQ: NHÂN VIÊN TỰ ĐIỀN HẾT (Andy chốt 12/08/2026)
   * -------------------------------------------------------------------------
   * SBPQ không có đồng hồ để suy tiền — số nằm trên app của máy, nhân viên đọc rồi
   * gõ vào. Nên `amount` · `cash` · `bank` · `cashReal` đều là ô GÕ, và hàm này
   * **không được ghi đè** chúng.
   *
   * ⚠️ Nguyên tắc #1 của POSH v3 ("mọi con số tiền do máy chủ tính từ chỉ số máy")
   * **không còn áp cho bảng này** — không có chỉ số nào để tính. Nhưng phép kiểm
   * tiền-vs-hàng VẪN còn giá trị: tiền đi từ app, hàng đi từ **kiểm đếm** ⇒ vẫn là
   * hai đường độc lập (xem `jpTienVsHang_`).
   *
   * ⚠️ Đồng hồ để TRỐNG thì `mActual`/`collection` phải về 0, đừng để số cũ nằm lại —
   * cột `COLLECTION` không còn in ra nhưng nó vẫn chảy vào bản xuất MISA.
   */
  /* CÓ đồng hồ thì suy tiền từ nó y như cũ (báo cáo SBPQ đời trước 12/08/2026), và
     thiếu "chỉ số sau" vẫn phải cảnh báo W8. KHÔNG có đồng hồ thì GIỮ NGUYÊN số
     nhân viên gõ, và **không được** cảnh báo thiếu chỉ số — bảng đó không còn ô
     đồng hồ nào, báo thiếu là báo sai trên mọi dòng.
     ⚠️ Phải qua `jpNumOrBlank_`, đừng `jpNum_`: `jpNum_('')` ra **0**, tức ô CHƯA GÕ
     thành số 0 ngay trước lượt lưu ⇒ `jpThieuChiSo_` đọc lại chỉ thấy 0 nên **không
     chặn nộp nữa**. Đúng lỗi đã mắc ở `jpCalcHangRow_` với ô "Đã bán". */
  var goTay = jpMayGoTay_(row);
  var mAct = goTay ? 0 : jpMeterDelta_(row.mBefore, row.mAfter, 'Đồng hồ tiền', warns);
  row.mActual    = mAct;
  if (!goTay) {
    /* ⚠️ Giá 1 xung LẤY THEO KHU y như `jpCalcMoneyRow_` — hai nhánh này cùng suy tiền
       từ đồng hồ nên phải cùng một giá. Sửa một nhánh quên nhánh kia là báo cáo SBPQ
       đời cũ (còn đồng hồ) tính theo giá khác báo cáo mẫu CHUNG cùng khu. */
    row.amount = mAct * jpGiaXung_(row.giaXung);
    row.cash   = row.amount - jpNum_(row.bank);
  } else {
    row.amount = jpNumOrBlank_(row.amount);
    row.cash   = jpNumOrBlank_(row.cash);
  }
  /* ⚠️ `collection` suy TỪ TIỀN, không giữ `mAct / 2` — với máy 10.000đ/xung nó phải
     bằng CHÍNH số xung. Xem chú thích ở `jpCalcMoneyRow_`. Nhánh gõ tay không có đồng
     hồ nên `amount` là số nhân viên gõ; chia 10.000 vẫn ra đúng "đơn vị 10.000đ". */
  row.collection = jpNum_(row.amount) / 10000;

  /* LỆCH TIỀN MẶT — `jpLechTM_` (00_Config) là nguồn duy nhất của phép trừ này. */
  row.lechTM = jpLechTM_(row);

  /*
   * Phép kiểm thứ hai, ĐỘC LẬP với phép trên: app phải tự cộng khớp.
   *
   *     Thành tiền  =?=  Tiền mặt app + QR
   *
   * Đây là hai con số nhân viên đọc từ hai chỗ khác nhau trên app, nên so được thật.
   * ⚠️ Và nó **phải** khớp để `Σ tiền mặt thực tế` đúng bằng tiền phải nộp: doanh thu
   * đi theo `amount`, còn lệch đi theo `cash` (xem `jpCalcReport_`). Lệch ở đây thì
   * hai đường đó hụt nhau đúng phần lệch — nên nói ra chứ đừng tự bù.
   */
  if (goTay && !jpBlank_(row.amount) && !jpBlank_(row.cash)) {
    var cong = jpNum_(row.cash) + jpNum_(row.bank);
    if (cong !== jpNum_(row.amount)) {
      warns.push(jpWarn_(JP_WARN.LECH_TM_APP,
        'App không cộng khớp: tiền mặt ' + jpMoney_(row.cash) + 'đ + QR ' +
        jpMoney_(row.bank) + 'đ = ' + jpMoney_(cong) + 'đ, nhưng Thành tiền ghi ' +
        jpMoney_(row.amount) + 'đ'));
    }
  }
  if (row.lechTM !== 0) {
    warns.push(jpWarn_(JP_WARN.LECH_TM_APP,
      'Tiền mặt thực tế ' + jpMoney_(jpNum_(row.cashReal)) + 'đ ' +
      (row.lechTM > 0 ? 'NHIỀU hơn' : 'ÍT hơn') + ' app ' +
      jpMoney_(Math.abs(row.lechTM)) + 'đ'));
  }

  var price = jpGiaDong_(row);          // Misa trước, rồi mới itemCode — xem jpGiaDong_
  row.price = price;

  /* Chỉ soi dư khi tiền suy từ ĐỒNG HỒ — nhân viên gõ tay thì số lẻ là chuyện của app,
     báo dư trên mọi dòng là báo sai vài lần rồi không ai đọc nữa. */
  if (price > 0 && !goTay && mAct > 0) {
    var du = row.amount % price;
    if (du !== 0) {
      warns.push(jpWarn_(JP_WARN.PRICE_REMAINDER,
        'Dư ' + jpMoney_(du) + 'đ so với giá ' + jpMoney_(price) + 'đ/trứng', du));
    }
  }

  /* Không giữ hàng — xem chú thích trên, đừng "sửa" thành số bán */
  row.soldQty = 0;
  row.hLeft = 0;
  row.xuTong = 0;
  row.stockLeftCalc = 0;

  row.warns = warns;
  row.warnJson = warns.length ? JSON.stringify(warns) : '';
  return row;
}

/*──────── ③d MẪU TÁCH — dòng HÀNG, chỉ hàng ────────*/

/**
 * Một mã hàng trong mẫu TÁCH.
 *
 * ⚠️⚠️ **ĐỔI CHIỀU 05/08/2026 — Andy chốt.** Trước đó nhân viên đếm TỒN CUỐI và web
 * suy ra số bán. Nay Andy chốt: *"còn lại và tồn cuối máy tính"* ⇒ **nhân viên gõ ĐÃ
 * BÁN**, web tính tồn cuối:
 *
 *   Tồn cuối (tính) = Tồn đầu + Nhập thêm − Trả kho − Lỗi/mẫu − **Đã bán (NV gõ)**
 *
 * ⚠️ **Đánh đổi đã nói rõ với Andy và anh chấp nhận:** phép kiểm tiền-vs-hàng YẾU đi.
 * Trước đây tiền đi từ đồng hồ, hàng đi từ kiểm đếm ⇒ hai đường độc lập thật. Nay số
 * bán là số nhân viên gõ, nên nếu họ gõ theo tiền thì `lechTienHang` không bắt được gì.
 *
 * Bù lại bằng cột **`stockActual` = "Đếm thực tế", ĐỂ TRỐNG ĐƯỢC**: gõ vào thì web so
 * với tồn cuối tính ra và cảnh báo lệch. Đó là cách bảng máy tiền (`hLeft` vs `Hàng tồn
 * thực tế`) và bảng kho ngoài đã làm, không phải cơ chế mới. **Đừng bắt buộc cột này** —
 * bắt buộc là quay lại đúng chỗ Andy vừa bỏ.
 *
 * ⚠️ **Đã bán để TRỐNG là CHƯA KHAI, không phải bán được 0.** `jpSubmitReport` chặn nộp
 * qua `jpThieuChiSo_` — cùng luật với ô "chỉ số sau", vì tồn cuối kỳ này là tồn đầu kỳ
 * sau mà ô đó bị khoá. Coi trống là 0 thì tồn cứ nằm đó mãi, kho không bao giờ ra 632.
 *
 * ⚠️ **`amount` PHẢI về 0.** Tiền suy từ hàng nằm ở `jpCalcReport_` (`revHang`) để
 * ĐEM SO; cộng vào `amount` là `jpCalcReport_` cộng luôn vào doanh thu ⇒ **doanh
 * thu gấp đôi**, mà sổ vẫn cân nên không phép kiểm nào bắt được.
 */
function jpCalcHangRow_(row) {
  var warns = [];

  var price = jpGiaDong_(row);          // Misa trước, rồi mới itemCode — xem jpGiaDong_
  row.price = price;

  var open  = jpNum_(row.stockOpen);
  var them  = jpNum_(row.addQty1) + jpNum_(row.addQty2);
  var tra   = jpNum_(row.returnQty);
  var loi   = jpNum_(row.defectQty);
  var coKhai = !jpBlank_(row.soldQty);          // Đã bán — NV GÕ (đổi chiều 05/08)
  var coDem  = !jpBlank_(row.stockActual);      // Đếm thực tế — TUỲ CHỌN

  /* --- Hoàn khách gắn ở đúng mã này ---
     ⚠️ `soldQty` ở đây là số NHÂN VIÊN GÕ (số quả thật đã ra khỏi máy), nên phần
     khách không lấy vốn đã KHÔNG nằm trong đó. Trừ `refundQty` lần nữa là **xuất kho
     thiếu** ⇒ 632 thiếu giá vốn, mà sổ VẪN CÂN nên không phép kiểm nào bắt được.
     Số tiền hoàn vẫn phải ghi để `jpCalcReport_` cộng vào tổng và để bảng tổng
     tiền-vs-hàng cân được. Xem chú thích dài ở `jpHoanTheoMa_`. */
  var hoan = jpHoanTheoMa_(row, price);
  row.refundAmt = hoan.amt;
  row.refundQty = hoan.qty;
  if (hoan.canhBao) warns.push(jpWarn_(JP_WARN.REFUND_REMAINDER, hoan.canhBao));
  if (hoan.amt > 0 && !jpStr_(row.refundRowNote)) {
    warns.push(jpWarn_(JP_WARN.MISSING_REASON,
      'Hoàn khách ' + jpMoney_(hoan.amt) + 'đ ở mã này chưa có lý do'));
  }

  /* --- NV gõ ĐÃ BÁN, web tính TỒN CUỐI (đổi chiều 05/08/2026) --- */
  var ban  = jpNum_(row.soldQty);
  var cuoi = open + them - tra - loi - ban;      // tồn cuối SUY RA
  /* ⚠️ GIỮ Ô TRỐNG là trống. Ghi `row.soldQty = ban` thì `''` thành số 0 ngay trước
     lúc lưu, và `jpThieuChiSo_` đọc lại từ sheet chỉ thấy 0 ⇒ **không chặn nộp nữa**,
     tức mất đúng cái chốt "trống là CHƯA KHAI". Đã mắc lỗi này thật, `day-chuyen.js`
     bắt được. Cùng bẫy `jpNum_` vs `jpNumOrBlank_` mà `jpQuetDayChuyen` đã lo. */
  row.soldQty = coKhai ? ban : '';
  row.stockLeftCalc = cuoi;

  if (!coKhai) {
    warns.push(jpWarn_(JP_WARN.METER_MISSING,
      'Chưa khai số đã bán — để trống là CHƯA KHAI, không phải bán được 0. ' +
      'Chưa khai thì kỳ sau không có tồn đầu và kho không ra được giá vốn.'));
  } else if (cuoi < 0) {
    warns.push(jpWarn_(JP_WARN.STOCK_MISMATCH,
      'Đã bán ' + ban + ' LỚN HƠN số có thể bán (' + (open + them - tra - loi) +
      ' = đầu ' + open + ' + nhập ' + them + ' − trả kho ' + tra + ' − lỗi ' + loi +
      ') ⇒ tồn cuối âm ' + cuoi + '. Sai một trong năm số đó.'));
  }

  /* --- ĐẾM THỰC TẾ: tuỳ chọn, chỉ để đối chiếu --- */
  if (coDem) {
    var dem  = jpNum_(row.stockActual);
    var lech = dem - cuoi;
    if (lech !== 0) {
      warns.push(jpWarn_(JP_WARN.STOCK_MISMATCH,
        'Đếm thực tế ' + dem + ' vs tồn cuối tính ra ' + cuoi + ' (đầu ' + open +
        ' + nhập ' + them + ' − trả kho ' + tra + ' − lỗi ' + loi + ' − bán ' + ban +
        ') · ' + (lech > 0 ? 'THỪA ' + lech : 'THIẾU ' + (-lech))));
    }
  }

  if (open < 0 || them < 0 || tra < 0 || loi < 0 || ban < 0 ||
      (coDem && jpNum_(row.stockActual) < 0)) {
    warns.push(jpWarn_(JP_WARN.STOCK_MISMATCH, 'Có số âm trong dòng hàng'));
  }
  /* W14 đi qua `jpCanhBaoMaKhongGia_` — đừng viết lại câu ở đây, xem chú thích ở đó */
  jpCanhBaoMaKhongGia_(row, warns);

  row.amount = 0;                  // xem chú thích trên — đừng cộng tiền ở đây
  row.cash = 0;
  row.bank = 0;
  row.xuTong = 0;
  row.hLeft = 0;

  row.warns = warns;
  row.warnJson = warns.length ? JSON.stringify(warns) : '';
  return row;
}

/*──────── ④ ĐIỀU PHỐI ────────*/

function jpCalcRow_(row, machineType) {
  var k = jpStr_(row.rowKind);
  if (!k) k = (machineType === JP_TYPE_COIN) ? JP_ROW_COIN : JP_ROW_MONEY;
  row.rowKind = k;

  if (k === JP_ROW_NGOAI) return jpCalcNgoaiRow_(row);
  if (k === JP_ROW_STOCK) return jpCalcStockRow_(row);
  if (k === JP_ROW_COIN)  return jpCalcCoinRow_(row);
  if (k === JP_ROW_HANG)  return jpCalcHangRow_(row);
  if (k === JP_ROW_MAY)   return jpCalcMayRow_(row);
  return jpCalcMoneyRow_(row);
}

/**
 * Một cảnh báo. `so` là **con số để cộng lại khi gộp** (số tiền dư, số xu dư) —
 *
 * ⚠️ Có `so` để `jpGopCanhBao_` **không phải đọc chữ trong `detail`**. Moi số ra từ
 * câu "Dư 10.000đ so với giá 100.000đ/trứng" bằng regex thì đổi một chữ trong câu là
 * tổng ra sai mà không có gì báo — và tổng sai thì trông vẫn y như tổng đúng.
 * Chỉ gắn khi cảnh báo đó ĐƯỢC PHÉP gộp; chỗ khác truyền vào cũng bị bỏ qua.
 */
function jpWarn_(def, detail, so) {
  var w = { code: def.code, part: def.part, msg: def.msg, detail: detail || '' };
  if (def.gop) { w.gop = def.gop; w.so = jpNum_(so); }
  return w;
}

/*──────── ⑤ TỔNG KẾT BÁO CÁO ────────*/

function jpCalcReport_(head, rows) {
  var revMeter = 0, revBank = 0;
  /* Mẫu TÁCH: tiền suy từ HÀNG, đường thứ hai để đem so — KHÔNG cộng vào doanh thu */
  var revHang = 0, coHang = false;

  var hoanDong = 0;                // Σ hoàn khách GẮN THEO MÃ, mọi loại dòng
  rows.forEach(function (r) {
    var k_ = jpStr_(r.rowKind);
    hoanDong += Math.abs(jpNum_(r.refundAmt));
    if (k_ === JP_ROW_HANG) {
      coHang = true;
      revHang += jpNum_(r.soldQty) * jpNum_(r.price);
      return;                      // dòng hàng KHÔNG sinh tiền — xem jpCalcHangRow_
    }
    /* Bảng tồn kho và KHO NGOÀI không sinh tiền */
    if (k_ === JP_ROW_STOCK || k_ === JP_ROW_NGOAI) return;
    revMeter += jpNum_(r.amount);
    revBank  += jpNum_(r.bank);
  });

  var revCashMeter = revMeter - revBank;

  /*
   * SBPQ — *"tiền thực thu mới chính là tiền thu được"* (Andy chốt 12/08/2026).
   *
   * Bảng tiền của mẫu TÁCH có cột **Tiền mặt thực tế** nhân viên đếm được. Đó mới là
   * tiền cơ sở phải giao về, không phải `Thành tiền` app báo.
   *
   * ⚠️ Cách nối vào đường tiền cũ mà **không đổi một công thức nào phía sau**: đặt
   * `adjMachine = Σ lệch từng dòng`. Khai triển ra thì đúng bằng điều cần:
   *
   *     cashActual = revCashMeter + adj − refund
   *                = Σ (Thành tiền − QR) + Σ (thực tế − tiền mặt app) − refund
   *                = Σ TIỀN MẶT THỰC TẾ − refund            ✓
   *
   * Nhờ vậy `totalSubmit`, sổ công nợ, và cặp bút toán `711`/`811` cho lệch máy đều
   * chạy y như cũ — không phải viết nhánh riêng cho SBPQ ở bốn chỗ khác nhau.
   *
   * ⚠️ Dấu `✓` ở dòng cuối chỉ đúng **khi app tự cộng khớp** từng dòng
   * (`Thành tiền = tiền mặt app + QR`) — vế đầu đi theo `amount`, vế sau đi theo
   * `cash`. Không khớp thì hai đường hụt nhau đúng phần lệch, và `jpCalcMayRow_` đã
   * **nói ra ở đúng dòng đó** (W10). Cố bù cho khớp là che mất một con số app báo sai.
   *
   * ⚠️ Và vì `adjMachine` bị TÍNH RA ở đây, ô "Lệch máy" ở header **không còn là ô gõ**
   * với mẫu TÁCH. Cộng thêm số gõ tay vào là **đếm hai lần** đúng phần lệch. Client
   * phải khoá ô đó lại và nói ra vì sao.
   *
   * ⚠️⚠️ Chỉ ghi khi có dòng `MAY` loại **GÕ TAY** (`jpMayGoTay_`). Hai chỗ hỏng nếu
   * nới điều kiện ra:
   *   ① mẫu chung không có dòng `MAY` nào — ghi 0 là **xoá số lệch máy vừa gõ**;
   *   ② báo cáo SBPQ đời cũ (tiền suy từ đồng hồ) thì "Lệch máy" **là ô nhân viên
   *      gõ** cho máy ăn tiền — ghi 0 là mất đúng khoản đó, im lặng, sổ vẫn cân.
   * Cộng `jpLechTM_` trên MỌI dòng `MAY`: dòng có đồng hồ mà chưa đếm tiền thì hàm
   * đó trả 0, nên không cần lọc lần hai.
   */
  /*
   * ⚠️⚠️ TỪ 30/08/2026 mẫu CHUNG cũng có cột "Thực thu" theo TỪNG MÃ (Andy: *"phải có
   * cột thực thu từng mã đối với báo cáo máy tiền"*). Đây là ĐỔI QUYẾT ĐỊNH so với
   * 29/08 (*"đếm gộp cả ca thôi"*) — cảnh báo ngưỡng `W15` vẫn giữ, nó đo ô tổng.
   *
   * ⚠️ Nên vòng này nay tính cả dòng `MONEY`, và `adjMachine` được SUY RA khi
   * **thật sự có ô thực thu nào được đếm** — chứ không phải cứ là mẫu CHUNG là suy.
   * Điều kiện đó bảo vệ đúng hai thứ:
   *   ① báo cáo ĐỜI CŨ (chưa có cột này, mọi `cashReal` trống) ⇒ `coThucThu = false`
   *      ⇒ **không ghi đè** số "Lệch máy" nhân viên đã gõ tay. Ghi đè là mất tiền im
   *      lặng — đúng cảnh báo đã ghi ở khối trên;
   *   ② nhân viên chưa đếm mã nào ⇒ ô tổng vẫn là ô gõ, dùng như trước.
   *
   * ⚠️ Có ô thực thu rồi thì ô "Lệch máy" ở header **thôi là ô gõ** — client phải khoá
   * lại. Cộng thêm số gõ tay vào Σ lệch là **đếm hai lần** đúng phần lệch.
   */
  var lechTM = 0, coGoTay = false, coThucThu = false;
  rows.forEach(function (r) {
    var k = jpStr_(r.rowKind);
    if (k !== JP_ROW_MAY && k !== JP_ROW_MONEY) return;
    if (k === JP_ROW_MAY && jpMayGoTay_(r)) coGoTay = true;
    if (!jpBlank_(r.cashReal)) coThucThu = true;
    lechTM += jpLechTM_(r);
  });
  head.coThucThu = coThucThu;
  if (coGoTay || coThucThu) head.adjMachine = lechTM;

  var adj    = jpNum_(head.adjMachine);

  /*
   * HOÀN KHÁCH có HAI nguồn, và tổng phải là TỔNG HAI NGUỒN (Andy chốt 05/08/2026):
   *
   *   ① `refundAmt` trên từng DÒNG   — gắn được vào mã hàng, nên quy ra được số quả
   *   ② `refundCustomer` ở HEADER    — khoản không gắn được mã nào (máy kẹt, bù thiện chí)
   *
   * ⚠️ Đừng "dọn" thành lấy một trong hai. Lấy một là mất tiền thật: báo cáo cũ chỉ có
   * ① = 0 nên tổng = header, đúng như trước (tương thích ngược); báo cáo mới có cả hai
   * thì cộng lại mới ra đúng số tiền đã trả cho khách.
   *
   * ⚠️ Và đừng tự động ghi `refundCustomer = Σ dòng`: header là ô NHÂN VIÊN GÕ, ghi đè
   * lên là mất khoản không gắn mã mà không ai biết.
   */
  head.refundRows  = hoanDong;
  var refund       = jpHoanTong_(head);      // = refundCustomer + refundRows
  head.refundTotal = refund;

  head.revMeter     = revMeter;
  head.revBank      = revBank;
  head.revCashMeter = revCashMeter;
  head.cashActual   = revCashMeter + adj - refund;
  head.totalSubmit  = head.cashActual + revBank;

  /*
   * BẢNG TỔNG của mẫu TÁCH (Andy chốt 04/08/2026): *"có phần chênh lệch tiền giữa
   * 2 phần này để nhìn so sánh"*.
   *
   *   lechTienHang = tiền theo ĐỒNG HỒ − tiền theo HÀNG ĐẾM ĐƯỢC
   *
   * Dương = đồng hồ thu nhiều hơn số hàng đếm được giải thích ⇒ **thiếu hàng**
   * (hoặc đếm sai). Âm = hàng ra nhiều hơn tiền thu ⇒ **thiếu tiền**.
   *
   * ⚠️ **Vẫn KHÔNG dùng `totalSubmit`** — nó cộng cả **lệch máy**, mà lệch máy là
   * chuyện đếm tiền trong két, không liên quan gì tới hàng. Đưa vào là lệch hàng và
   * lệch tiền trộn vào nhau, không lần ra được cái nào gây ra cái nào.
   *
   * ⚠️ **NHƯNG PHẢI TRỪ HOÀN KHÁCH** (Andy chốt 05/08/2026). Hoàn khách khác lệch máy:
   * nó **đúng là phần tiền đồng hồ đã thu mà không có hàng đi ra**. Đúng ví dụ của Andy:
   *
   *     đồng hồ 500.000đ − hoàn 200.000đ = 300.000đ  ⇔  3 quả × 100.000đ  ⇒ lệch 0 ✓
   *
   * Không trừ thì báo cáo nào có hoàn khách cũng báo lệch đúng bằng số tiền hoàn — một
   * khoản **đã được giải thích rõ ràng**. Báo sai vài lần là không ai đọc nữa, đúng
   * bệnh `canBang` cũ.
   *
   * Trừ cả `refundTotal` (dòng + header), không chỉ phần theo dòng: khoản không gắn
   * được mã cũng là tiền thu mà không có hàng ra.
   *
   * ⚠️ Chỉ gắn khi báo cáo THẬT SỰ có dòng hàng. Báo cáo mẫu chung không có dòng
   * `HANG` nào; gắn `lechTienHang = revMeter` cho nó là **mọi báo cáo máy tiền cũ
   * đều báo lệch** — báo sai vài lần là không ai đọc nữa.
   */
  head.revHang      = coHang ? revHang : 0;
  head.revMeterRong = revMeter - refund;         // tiền đồng hồ SAU khi trừ hoàn khách
  head.lechTienHang = coHang ? (head.revMeterRong - revHang) : 0;
  head.coBangTong   = coHang;

  var warns = jpCanhBaoHead_(head);

  var rowWarn = 0;
  rows.forEach(function (r) { rowWarn += (r.warns || []).length; });
  head.warnCount = rowWarn + warns.length;
  head.warns = warns;
  return head;
}

/**
 * Cảnh báo ở cấp BÁO CÁO — tách riêng để `jpCalcReport_` (lúc lưu) và `jpGetReport`
 * (lúc mở lại) dùng **CÙNG MỘT NGUỒN CHỮ**.
 *
 * ⚠️ Head warns **không được lưu** vào sheet (khác `warnJson` của từng dòng), nên mở
 * lại báo cáo là chúng mất. Viết lại câu cảnh báo ở chỗ mở báo cáo là hai câu chữ cho
 * cùng một việc, rồi sửa một chỗ quên chỗ kia.
 *
 * ⚠️ Hàm này **CHỈ ĐỌC** `head`, không ghi gì — gọi được trên head thật lấy từ sheet.
 * Đừng thêm phép gán vào đây; `jpCalcReport_` là chỗ duy nhất được ghi vào head.
 */
function jpCanhBaoHead_(head) {
  var warns = [];
  var adj    = jpNum_(head.adjMachine);
  var refund = Math.abs(jpNum_(head.refundCustomer));

  /* Đọc từ CỘT ĐÃ LƯU, không đọc `head.refundTotal` — hàm này chạy cả lúc MỞ LẠI báo
     cáo, lúc đó head lấy thẳng từ sheet nên các trường tạm của `jpCalcReport_` không
     có. Dựa vào trường tạm là câu cảnh báo lúc mở lại in thiếu khoản hoàn. */
  var hoanT = jpHoanTong_(head);
  var rong  = jpNum_(head.revMeter) - hoanT;

  if (adj !== 0 && !jpStr_(head.adjMachineNote)) {
    warns.push(jpWarn_(JP_WARN.MISSING_REASON, 'Lệch máy ' + jpMoney_(adj) + 'đ chưa có lý do'));
  }

  /*
   * LỆCH MÁY VƯỢT NGƯỠNG — Andy chốt 29/08/2026 (*"đếm gộp cả ca thôi, làm cảnh báo
   * vượt ngưỡng đi em"*). Phải qua **CẢ HAI** ngưỡng, xem `JP_LECH_MAY_PCT/SAN`.
   *
   * ⚠️ Câu phải nói ra **HẬU QUẢ**, không chỉ nói trạng thái — bài học `jpKetKy_`
   * (11/08): nói *"lệch 3.000.000đ"* thì người đọc cho là app đã xử lý xong; phải nói
   * rõ số đó **đi thẳng vào tiền phải nộp** và ra bút toán `711`/`811`, tức nó là tiền
   * thật đang thừa/thiếu ở quỹ chứ không phải một con số thống kê.
   *
   * ⚠️ Nói cả **tỷ lệ**, vì cùng một số tiền mang ý nghĩa khác hẳn theo quy mô cơ sở —
   * và kế toán cần con số đó để quyết có đi truy hay không.
   *
   * ⚠️ CẢNH BÁO, KHÔNG CHẶN. Lệch máy là chuyện có thật ngoài điểm bán (máy ăn tiền,
   * đếm sót); chặn nộp là khoá cơ sở giữa ca vì một thứ nhân viên không sửa được —
   * cùng luật với "thiếu ảnh thì cảnh báo, không chặn".
   */
  var dt = jpNum_(head.revMeter);
  if (Math.abs(adj) >= JP_LECH_MAY_SAN && Math.abs(adj) > JP_LECH_MAY_PCT * dt) {
    var ty = dt > 0 ? ' (' + (Math.abs(adj) * 100 / dt).toFixed(1) + '% doanh thu)'
                    : ' (kỳ này KHÔNG có doanh thu theo đồng hồ)';
    warns.push(jpWarn_(JP_WARN.LECH_MAY_LON,
      'Lệch máy ' + jpMoney_(adj) + 'đ' + ty + ' — ' +
      (adj < 0 ? 'đếm được ÍT hơn máy báo, tức quỹ đang THIẾU đúng khoản này'
               : 'đếm được NHIỀU hơn máy báo, tức quỹ đang THỪA đúng khoản này') +
      '. Số này trừ thẳng vào tiền cơ sở phải nộp và ra bút toán ' +
      (adj < 0 ? '811' : '711') + ', nên soát lại trước khi ký'));
  }
  if (refund !== 0 && !jpStr_(head.refundNote)) {
    warns.push(jpWarn_(JP_WARN.MISSING_REASON, 'Hoàn khách ' + jpMoney_(refund) + 'đ chưa có lý do'));
  }

  var lech = jpNum_(head.lechTienHang);
  if (lech !== 0) {
    /* In RA phép trừ hoàn khách, đừng chỉ in con số cuối. Kế toán thấy "tiền đồng hồ
       5.000.000đ vs hàng 4.650.000đ" mà lệch báo 150.000đ thì tưởng bảng sai — phải
       thấy khoản hoàn 200.000đ đứng giữa mới cộng ra được. */
    /* ⚠️ Gọi đúng NGUỒN của con số: mẫu TÁCH sau 12/08/2026 lấy tiền từ APP của máy,
       không còn đồng hồ nào. Câu chữ này là **nguồn duy nhất** (`jpCanhBaoHead_` dùng
       cho cả lúc lưu và lúc mở lại), nên sai ở đây là sai ở cả hai web. */
    var nguonTien = jpBcMau_(head.bcMau) === JP_BC_MAU_TACH ? 'tiền APP' : 'tiền đồng hồ';
    warns.push(jpWarn_(JP_WARN.MONEY_MISMATCH,
      'Bảng tổng LỆCH ' + jpMoney_(Math.abs(lech)) + 'đ · ' + nguonTien + ' ' +
      jpMoney_(jpNum_(head.revMeter)) + 'đ' +
      (hoanT > 0 ? ' − hoàn khách ' + jpMoney_(hoanT) + 'đ = ' +
                   jpMoney_(rong) + 'đ' : '') +
      ' vs tiền theo hàng đếm được ' + jpMoney_(jpNum_(head.revHang)) + 'đ ⇒ ' +
      (lech > 0 ? 'THIẾU HÀNG (tiền thu nhiều hơn hàng ra)'
                : 'THIẾU TIỀN (hàng ra nhiều hơn tiền thu)')));
  }
  return warns;
}

/*──────── ⑤b HAI ĐƯỜNG: TIỀN THEO ĐỒNG HỒ vs HÀNG NHÂN VIÊN TỰ NHẬP ────────
 *
 * Andy 07/08/2026: *"phần báo cáo duyệt của kế toán á em chỉnh thêm có phần lệch
 * doanh thu chỉ số máy với phần nv tự nhập"*.
 *
 * Mẫu TÁCH đã có `lechTienHang` từ 05/08 và màn duyệt in sẵn bảng tổng. Hai mẫu
 * CHUNG thì chưa — mà VC 3/2 (máy xu) đúng là mẫu chung, nên kế toán ký xong vẫn
 * không biết tiền đồng hồ có khớp với hàng nhân viên khai hay không.
 *
 * ⚠️⚠️ CHỈ so được khi HAI ĐƯỜNG THẬT SỰ ĐỘC LẬP. Ba mẫu ba mức khác nhau, và
 * hàm NÓI RA đúng mức của từng cái thay vì để kế toán tưởng phép kiểm mạnh hơn
 * thực tế — nói quá sức một phép kiểm là đúng bệnh `canBang` cũ:
 *
 *   XU    ✔ ĐỘC LẬP — đồng hồ COIN của máy  vs  số xu kiểm NV ghi TAY từng ngày
 *   TÁCH  ✔ ĐỘC LẬP — đồng hồ tiền          vs  kiểm đếm tồn  (dùng lại
 *                     `head.lechTienHang`, KHÔNG tính lại: hai chỗ tính là hai
 *                     con số rồi lệch nhau mà không ai biết chỗ nào đúng)
 *   TIỀN  ~ MỘT PHẦN — chỉ những dòng CÓ đồng hồ đếm trứng (`hAfter`). Dòng
 *                     không có thì `jpCalcMoneyRow_` suy `soldQty = amount ÷ giá`
 *                     — tức suy từ CHÍNH con số đang đem so ⇒ lệch luôn bằng 0
 *                     mà không chứng minh được gì. In "khớp" cho nó là tautology.
 *                     Nên đếm riêng vào `boQua` và in ra số dòng bị bỏ.
 *
 * ⚠️⚠️ CHỈ TRỪ HOÀN KHÁCH KHI VẾ HÀNG KHÔNG DÍNH KHOẢN ĐÓ. Đây là chỗ dễ "dọn
 * cho đồng bộ" nhất, mà dọn là sai tiền:
 *
 *   TIỀN / TÁCH  → TRỪ. Vế hàng đi từ **đồng hồ đếm trứng** (hoặc kiểm đếm tồn)
 *                  nên quả khách không lấy KHÔNG nằm trong đó, còn tiền thì có
 *                  ⇒ không trừ là mọi báo cáo có hoàn đều báo lệch đúng bằng
 *                  khoản hoàn. Chính là lý do `lechTienHang` phải trừ (05/08).
 *
 *   XU           → **KHÔNG TRỪ**. Hai vế của máy xu là CÙNG một hàm tuyến tính
 *                  của cùng những con xu: `amount = cActual × 50.000`, còn hàng
 *                  = `Σ (xu ÷ giáXu) × giá` = `Σ xu × 50.000`. Khác nhau đúng ở
 *                  chỗ một bên là ĐỒNG HỒ, một bên là ĐẾM TAY — đó mới là thứ
 *                  phép kiểm này đi tìm. Trừ hoàn vào một vế là **tự tạo ra một
 *                  khoản lệch không có thật**, đúng bằng tiền hoàn, trên MỌI báo
 *                  cáo máy xu có hoàn khách. Vẫn IN dòng hoàn ra cho kế toán
 *                  thấy, chỉ không đưa vào phép trừ.
 *
 * ⚠️ Chỉ ĐỌC `head`/`rows`, không ghi gì. `jpCalcReport_` vẫn là chỗ duy nhất
 * được ghi vào head; hàm này chỉ để màn duyệt NHÌN.
 */
function jpTienVsHang_(head, rows) {
  var tach = jpStr_(head.bcMau).toUpperCase() === 'TACH';
  var xu   = jpStr_(head.machineType).toUpperCase() === JP_TYPE_COIN;
  var hoan = jpHoanTong_(head);

  var o = { mau: tach ? 'TACH' : (xu ? 'XU' : 'TIEN'), hoan: hoan,
            truHoan: !xu,          // xem chú thích dài ở trên — máy xu KHÔNG trừ
            boQua: 0, tongDong: 0, docLap: true, lyDo: '' };

  if (tach) {
    /* Dùng lại số máy chủ đã tính — đừng tính lần thứ hai.
       ⚠️ Guard bằng CÓ DÒNG `HANG` THẬT, không đọc `head.coBangTong`: cờ đó
       `jpCalcReport_` gắn tạm rồi trả về theo phản hồi lúc lưu, KHÔNG nằm trong
       `JP_REPORT_COLS` nên đọc từ sheet ra là `undefined` ⇒ khối này biến mất ở
       đúng cái mẫu đã có sẵn phép so. */
    (rows || []).forEach(function (r) {
      if (jpStr_(r.rowKind) === JP_ROW_HANG) o.tongDong++;
    });
    o.tienMay  = jpNum_(head.revMeter);
    o.tienHang = jpNum_(head.revHang);
    /* ⚠️ Đổi 12/08/2026: SBPQ không còn đồng hồ, tiền là số nhân viên đọc từ APP của
       máy. Vẫn là HAI ĐƯỜNG ĐỘC LẬP — một bên app báo, một bên kiểm đếm hàng — nên
       phép so còn giá trị. Nhưng câu chữ phải nói đúng nguồn: in "đồng hồ máy" cho
       một con số gõ tay là dạy kế toán tin nó chắc hơn thực tế. */
    o.nhanMay  = 'Tiền theo APP của máy (nhân viên đọc rồi gõ)';
    o.nhanHang = 'Tiền theo hàng ĐẾM ĐƯỢC (bảng hàng theo mã)';
  } else if (xu) {
    var tienXu = 0, tienHangXu = 0;
    (rows || []).forEach(function (r) {
      var k = jpStr_(r.rowKind);
      if (k === JP_ROW_COIN) { tienXu += jpNum_(r.amount); return; }
      if (k !== JP_ROW_STOCK) return;
      o.tongDong++;
      /* Giá suy từ MÃ MISA trước rồi mới mã nội bộ — `jpGiaDong_`. Dòng STOCK
         không mang sẵn `price` (xem `jpCalcStockRow_`), nên phải suy ở đây. */
      tienHangXu += jpNum_(r.soldQty) * jpGiaDong_(r);
    });
    o.tienMay  = tienXu;
    o.tienHang = tienHangXu;
    o.nhanMay  = 'Tiền theo ĐỒNG HỒ XU của máy';
    o.nhanHang = 'Tiền theo SỐ XU KIỂM nhân viên ghi tay từng ngày';
  } else {
    /* Máy tiền mẫu chung — chỉ dòng CÓ đồng hồ đếm trứng mới so được */
    var tienM = 0, tienH = 0;
    (rows || []).forEach(function (r) {
      if (jpStr_(r.rowKind || JP_ROW_MONEY) !== JP_ROW_MONEY) return;
      o.tongDong++;
      if (jpBlank_(r.hAfter)) { o.boQua++; return; }
      tienM += jpNum_(r.amount);
      tienH += jpNum_(r.soldQty) * jpGiaDong_(r);
    });
    o.tienMay  = tienM;
    o.tienHang = tienH;
    o.nhanMay  = 'Tiền theo ĐỒNG HỒ TIỀN';
    o.nhanHang = 'Tiền theo ĐỒNG HỒ ĐẾM TRỨNG';
    if (o.boQua > 0) {
      o.docLap = (o.tongDong > o.boQua);
      o.lyDo = o.boQua + '/' + o.tongDong + ' ô chưa nhập đồng hồ đếm trứng nên ' +
        'KHÔNG đem so được: số bán của mấy ô đó suy ra từ chính số tiền, so lại ' +
        'thì lúc nào cũng khớp mà không chứng minh được gì.';
    }
  }

  /* Không có dòng hàng nào ⇒ không có gì để so. Trả null để giao diện ẩn hẳn khối,
     đừng in một bảng toàn số 0 rồi kết luận "khớp". */
  if (o.tongDong === 0) return null;
  if (!o.docLap) return null;

  o.tienMayRong = o.truHoan ? (o.tienMay - hoan) : o.tienMay;
  o.lech = o.tienMayRong - o.tienHang;
  o.yNghia = o.lech === 0
    ? 'Khớp — tiền đồng hồ đúng bằng hàng nhân viên khai'
    : (o.lech > 0
        ? 'THIẾU HÀNG ' + jpMoney_(Math.abs(o.lech)) + 'đ — tiền thu nhiều hơn hàng ra'
        : 'THIẾU TIỀN ' + jpMoney_(Math.abs(o.lech)) + 'đ — hàng ra nhiều hơn tiền thu');
  return o;
}

/*──────── ⑥ KIỂM ẢNH ────────*/

function jpCheckPhotos_(rows, zones, photos, machineMap, clusterMap, locationId) {
  var warns = [];
  var byRef = {};
  (photos || []).forEach(function (p) {
    var k = jpStr_(p.scope) + '|' + jpStr_(p.refId) + '|' + jpStr_(p.kind);
    byRef[k] = (byRef[k] || 0) + 1;
  });

  (rows || []).forEach(function (r) {
    var kd = jpStr_(r.rowKind);
    /* Bảng tồn kho, kho ngoài, và hai bảng của mẫu tách đều không có đồng hồ
       ⇒ không đòi ảnh chỉ số. Dòng HÀNG cũng không có `machineId` nên `machineMap`
       không tra ra gì ⇒ `need` rơi về 1 ⇒ **đòi ảnh cho từng mã hàng**: 54 mã ở
       SBPQ là 54 cảnh báo thiếu ảnh vô nghĩa mỗi kỳ.
       ⚠️ Dòng `MAY` vào danh sách này từ 12/08/2026, lúc bảng tiền SBPQ bỏ cột ô máy
       (Andy: *"chỉ cần cột mã hàng mã misa thành tiền rồi 3 cột…"*). Không có
       `machineId` thì nó mắc y hệt bệnh của dòng HÀNG. Ô gắn ảnh **vẫn còn** trên
       bảng — ảnh màn hình app là bằng chứng duy nhất cho số gõ tay — chỉ là **không
       đòi**: một ảnh app thường thấy hết các mã, đòi từng dòng là cảnh báo vô nghĩa. */
    if (kd === JP_ROW_STOCK || kd === JP_ROW_NGOAI || kd === JP_ROW_HANG ||
        kd === JP_ROW_MAY) return;
    /* Cấu hình 0 ảnh là cố ý — `jpSoAnh_` giữ 0, chỉ ô TRỐNG mới về mặc định 1 */
    var m = machineMap[String(r.machineId)];
    var need = m ? jpSoAnh_(m.photoCount) : 1;
    if (!need) return;
    var got = byRef['ROW|' + r.id + '|' + JP_PHOTO_METER] || 0;
    if (got < need) {
      warns.push(jpWarn_(JP_WARN.MISSING_PHOTO,
        'Ô ' + (jpStr_(r.machineCode) || jpStr_(r.itemCode)) + ': thiếu ' + (need - got) + ' ảnh chỉ số'));
    }
  });

  (zones || []).forEach(function (z) {
    var c = clusterMap[String(z.clusterId)];
    var need = c ? jpSoAnh_(c.photoCount, jpStr_(c.hasQR) === 'Y' ? 1 : 0) : 0;
    if (!need) return;
    var got = byRef['ZONE|' + z.id + '|' + JP_PHOTO_PAYBOX] || 0;
    if (got < need) {
      warns.push(jpWarn_(JP_WARN.MISSING_PHOTO,
        'Khu vực ' + jpStr_(z.name) + ': thiếu ' + (need - got) + ' ảnh Pay Box'));
    }
  });

  /*
   * ⚠️⚠️ CÓ TIỀN QR MÀ KHÔNG CÓ CHỖ NÀO GẮN ẢNH PAY BOX — W17 (Andy 08/09/2026).
   *
   * Vòng lặp khu vực ở trên chỉ đòi ảnh khi khu **ĐÃ chọn** một cụm mang QR: khu để
   * trống ô "— chọn cụm —" ra `need = 0` và bị **bỏ qua im lặng**. Đó là cái lỗ: dải
   * ảnh Pay Box gắn theo CỤM nên không chọn cụm là **không có chỗ nào để gắn**, và
   * báo cáo vẫn nộp được với tiền QR mà không kèm một tấm bằng chứng nào.
   *
   * ⚠️ Chốt là **KHÔNG khu nào của báo cáo chọn được cụm QR**, chứ KHÔNG phải "có khu
   * nào chưa chọn cụm". Máy nhận QR thường chỉ ở một khu; kêu từng khu còn lại là cảnh
   * báo vặt, mà **báo sai vài lần là không ai đọc nữa** — đúng bệnh `canBang` cũ. Đã
   * có một khu chọn đúng cụm thì chỗ gắn ảnh **tồn tại**, và việc ảnh có được gắn hay
   * chưa thì vòng lặp trên đã lo.
   *
   * ⚠️ Ba điều kiện phải **đủ cả ba**, thiếu một là báo oan:
   *   · `qr > 0` — không có tiền QR thì không cần bằng chứng QR. (Đo Σ `bank` của các
   *     dòng: đó là phần tiền KHÔNG vào tay nhân viên, xem `jpNvPhaiNop_`.)
   *   · cơ sở **có** cụm mang QR đang dùng — chưa cấu hình cụm nào thì nhân viên
   *     **không có gì để chọn**, kêu họ là kêu vào chỗ họ không sửa được. Việc đó là
   *     của kế toán ở Cấu hình → Cụm máy, và `jpTinhTrangDungHeThong` mới là chỗ nói.
   *   · không khu nào chọn được cụm QR.
   *
   * ⚠️ `locationId` **thiếu thì KHÔNG kêu** — nhưng `jpPhotoWarns_` luôn truyền (tự
   * đọc từ head khi người gọi không đưa), nên đây không phải nhánh im lặng bỏ qua.
   */
  var qr = 0;
  (rows || []).forEach(function (r) { qr += jpNum_(r.bank); });

  if (qr > 0 && jpStr_(locationId)) {
    var cumQR = [];
    Object.keys(clusterMap || {}).forEach(function (k) {
      var c = clusterMap[k];
      if (!c || String(c.locationId) !== String(locationId)) return;
      if (jpStr_(c.hasQR).toUpperCase() !== 'Y') return;
      if (jpStr_(c.active).toUpperCase() === 'N') return;      // cụm đã dẹp
      cumQR.push(jpStr_(c.name) || jpStr_(c.id));
    });

    var coKhuQR = (zones || []).some(function (z) {
      var c = clusterMap[String(z.clusterId)];
      return !!(c && jpStr_(c.hasQR).toUpperCase() === 'Y');
    });

    if (cumQR.length && !coKhuQR) {
      /* ⚠️ Nói ra HẬU QUẢ ("không có chỗ gắn ảnh") + VIỆC PHẢI LÀM ("chọn cụm ở đầu
         khu vực"), không chỉ nói trạng thái. Câu chỉ nói trạng thái là câu người đọc
         tin là mình đã xong — đúng bài học của `jpKetKy_`. */
      warns.push(jpWarn_(JP_WARN.THIEU_CUM_QR,
        'Kỳ này có ' + jpMoney_(qr) + 'đ vào tài khoản (QR/CK) mà chưa khu vực nào chọn ' +
        'cụm nhận QR ⇒ KHÔNG có chỗ gắn ảnh Pay Box. Chọn cụm ' +
        (cumQR.length === 1 ? '"' + cumQR[0] + '"' : 'ở danh sách (' + cumQR.join(' · ') + ')') +
        ' ở ô "— chọn cụm —" đầu khu vực.'));
    }
  }

  return warns;
}

/*──────── ⑦ TỰ KIỂM bằng số thật của Andy ────────*/

function jpSelfTest_() {
  var out = [];

  /* --- MÁY TIỀN: form AM BD ngày 31/07/2026 --- */
  var tien = [
    ['100JP111', 1240, 1300, 30, 54, 48, 51, 3, 51],
    ['150JP113',  730,  730,  0, 38, 22, 22, 0, 38],
    ['150JP065', 2276, 2276,  0, 89, 92, 92, 0, 89],
    ['100JP025', 2772, 2812, 20, 83, 87, 89, 2, 81],
    ['100JP046', 1984, 2024, 20, 59, 99,101, 2, 57],
    ['100JP052', 1182, 1182,  0, 71, 59, 59, 0, 71],
    ['100JP023', 3622, 3682, 30, 68,191,194, 3, 65],
    ['100JP026', 2710, 2730, 10, 72, 89, 90, 1, 71],
    ['100JP028', 2526, 2566, 20, 21,118,120, 2, 19],
    ['150JP105', 1270, 1270,  0, 72, 42, 42, 0, 72],
    ['200JP079', 2746, 2826, 40, 73, 29, 31, 2, 71],
    ['100JP051', 1086, 1146, 30, 67, 25, 28, 3, 64],
    ['100JP035', 2666, 2746, 40, 36, 65, 69, 4, 32],
    ['100JP125', 1144, 1144,  0, 43, 37, 37, 0, 43]
  ];
  var tongCol = 0, sai = 0;
  tien.forEach(function (t) {
    var r = jpCalcMoneyRow_({
      rowKind: JP_ROW_MONEY, itemCode: t[0],
      mBefore: t[1], mAfter: t[2],
      hOpen: t[4], hBefore: t[5], hAfter: t[6]
    });
    tongCol += r.collection;
    var ok = (r.collection === t[3]) && (r.soldQty === t[7]) && (r.hLeft === t[8]);
    if (!ok) { sai++; out.push('SAI ' + t[0] + ': col=' + r.collection + '/' + t[3] +
      ' ban=' + r.soldQty + '/' + t[7] + ' conlai=' + r.hLeft + '/' + t[8]); }
  });
  out.push('MÁY TIỀN: ' + (tien.length - sai) + '/' + tien.length + ' dòng khớp · ' +
    'Tổng collection=' + tongCol + ' → ' + jpMoney_(tongCol * 10000) + 'đ (chờ 2.400.000)');

  /* --- MÁY XU: bảng tiền AM TPHU 31/07/2026 --- */
  [[37973, 37979, 189865, 189895, 100000, 200000, 300000],
   [19128, 19142,  95640,  95710, 250000, 450000, 700000]
  ].forEach(function (x, i) {
    var r = jpCalcCoinRow_({
      rowKind: JP_ROW_COIN,
      cBefore: x[0], cAfter: x[1], mBefore: x[2], mAfter: x[3],
      cash: x[4], bank: x[5]
    });
    out.push('MÁY XU vị trí ' + (i + 1) + ': ' + jpMoney_(r.amount) + 'đ (chờ ' +
      jpMoney_(x[6]) + ') ' + (r.amount === x[6] ? 'OK' : 'SAI') +
      ' · cảnh báo=' + r.warns.length);
  });

  /* --- MÁY XU: bảng hàng tồn AE-TPHU 27–31/07 --- */
  var kho = [
    [2, 121, 0, 0, [10, 0, 2, 0, 0, 0, 0],  6, 115],
    [2,  41, 0, 0, [0, 0, 4, 0, 2, 0, 0],   3,  38],
    [2,  44, 34, 0, [10, 0, 4, 0, 0, 0, 0], 7,  71],
    [2,  49, 34, 0, [18, 0, 2, 0, 0, 0, 0], 10, 73]
  ];
  var saiKho = 0;
  kho.forEach(function (k, i) {
    var r = jpCalcStockRow_({
      rowKind: JP_ROW_STOCK, giaXu: k[0], stockOpen: k[1],
      addQty1: k[2], addQty2: k[3], xuDaysJson: JSON.stringify(k[4])
    });
    if (r.soldQty !== k[5] || r.stockLeftCalc !== k[6]) {
      saiKho++;
      out.push('SAI kho ' + i + ': ban=' + r.soldQty + '/' + k[5] +
        ' con=' + r.stockLeftCalc + '/' + k[6]);
    }
  });
  out.push('MÁY XU tồn kho: ' + (kho.length - saiKho) + '/' + kho.length + ' dòng khớp');

  /* --- Tổng kết có lệch máy + hoàn khách --- */
  var head = jpCalcReport_(
    { adjMachine: 210000, adjMachineNote: 'x', refundCustomer: 250000, refundNote: 'y' },
    [{ amount: 11915000, bank: 0 }]
  );
  out.push('Tiền mặt thực nộp = ' + head.cashActual + ' (chờ 11875000) ' +
    (head.cashActual === 11875000 ? 'OK' : 'SAI'));

  Logger.log(out.join('\n'));
  return out.join('\n');
}


