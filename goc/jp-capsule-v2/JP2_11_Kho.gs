/*═══════════════════════════════════════════════════════════════════════════
 * JP CAPSULE v2  ·  11_Kho
 * ---------------------------------------------------------------------------
 * KHO HAI TẦNG, chạy như MISA. Andy chốt 04/08/2026 sau khi đối chiếu hai file
 * kho thật: "Kho Hàng JP HCM Năm 2025" và "Theo dõi kho JP PQ".
 *
 *   Nhà cung cấp
 *        │  phiếu NHẬP (có giá mua)
 *        ▼
 *   KHO TỔNG  (một kho duy nhất — `JP_KHO_TONG`)
 *        │  phiếu XUẤT, có LOẠI:
 *        │    · XUAT_CS      → xuống cơ sở      (điều chuyển, CHƯA sinh giá vốn)
 *        │    · TRA_KHO      ← cơ sở trả về     (ngược chiều)
 *        │    · XE_MAU       → xé trưng bày     ⇒ Nợ 641
 *        │    · TANG_MALL    → tặng mall/khách  ⇒ Nợ 641
 *        │    · DIEU_CHUYEN  → gửi đi tỉnh khác (chỉ giảm tồn)
 *        ▼
 *   CƠ SỞ
 *        │  báo cáo nhân viên HOÀN TẤT = đã bán
 *        ▼
 *   Nợ 632 / Có 1567
 *
 * VÌ SAO PHẢI LÀ HAI TẦNG
 *   Bản đầu em làm kho PHẲNG: nhập thẳng vào cơ sở, mỗi cơ sở một kho độc lập.
 *   Sai mô hình — thực tế hàng về kho tổng rồi mới rải xuống cơ sở, và cái kế toán
 *   cần là báo cáo Nhập–Xuất–Tồn của kho tổng.
 *
 * VÌ SAO LỚP TỒN PHẢI ĐI THEO HÀNG
 *   Xuất xuống cơ sở là điều chuyển nội bộ, chưa bán, nên CHƯA được ghi 632.
 *   Nhưng lúc bán ở cơ sở thì phải biết lô đó mua bao nhiêu. Nên khi xuất, lớp ở
 *   kho tổng bị trừ và sinh LỚP MỚI ở cơ sở giữ NGUYÊN giá mua. Giá vốn khi bán
 *   lấy từ lớp của cơ sở — vẫn là giá mua thật của đúng lô đó.
 *
 * BỐN BẤT BIẾN
 *  1. **Chỉ báo cáo HOÀN TẤT mới ra sổ 632.** Số chưa duyệt không ra sổ.
 *  2. **Không xuất hai lần.** `jpDaXuatKho_` chặn theo `reportId`; trả về / mở lại
 *     thì `jpHoanKhoBaoCao_` hoàn số về đúng lớp cũ rồi mới cho xuất lại.
 *  3. **Thiếu lớp thì vẫn xuất, nhưng gắn cờ `thieuLop`.** Chặn cứng là kế toán
 *     không chốt được sổ chỉ vì một phiếu nhập về muộn; im lặng lại còn tệ hơn.
 *  4. **Một dòng xuất = một dòng sổ.** Bán 5 trứng ăn vào 2 lớp giá khác nhau thì
 *     ra 2 dòng — đúng như MISA, không bình quân lại.
 *
 * VÀ MỘT BẤT BIẾN CỦA LỚP TỒN
 *  5. **`qtyRemaining` không bao giờ lớn hơn `qtyInit`.** Xem `jpHoanKhoBaoCao_`.
 *═══════════════════════════════════════════════════════════════════════════*/

/*──────────────────── ① NHẬP KHO ────────────────────*/

/**
 * Kế toán nhập kho từ nhà cung cấp. Hàng LUÔN về KHO TỔNG — không chọn cơ sở.
 * p = {ngay, nccMa, nccTen, ghiChu, rows:[{itemCode, qty, unitCost, lotNo, note}]}
 *
 * Mỗi dòng sinh một LỚP TỒN riêng — không gộp, vì gộp là mất giá theo lô.
 */
function jpKhoNhap(token, p) {
  var u = jpNeedKT_(jpAuth_(token));
  p = p || {};

  var ngay = jpDate_(p.ngay) || jpToday_();
  var itemMap = jpItemMap_();
  var dong = [];
  (p.rows || []).forEach(function (r) {
    var ma = jpStr_(r.itemCode);
    var qty = jpNum_(r.qty);
    var gia = jpNum_(r.unitCost);
    if (!ma || qty <= 0) return;                       // dòng trống thì bỏ
    if (gia < 0) throw new Error('Giá mua của ' + ma + ' không hợp lệ');
    dong.push({
      itemCode: ma,
      itemName: jpStr_(r.itemName) || jpStr_((itemMap[ma] || {}).name),
      /* Tiền dòng sổ làm tròn về đồng chẵn — xem `jpDong_` ở 00_Config. Đơn giá
         trên LỚP thì để nguyên, không thì tổng tồn đầu kỳ lệch với file MISA. */
      qty: qty, unitCost: gia, amount: jpDong_(qty * gia),
      lotNo: jpStr_(r.lotNo), note: jpStr_(r.note)
    });
  });
  if (!dong.length) throw new Error('Chưa có dòng hàng nào để nhập');

  return jpLock_(function () {
    var id = jpNextId_('NK');
    var tong = 0;
    dong.forEach(function (d) { tong += d.amount; });

    jpAppend_(JP_TABS.KHO_NHAP, {
      id: id, soChungTu: JP_NK_PREFIX + id.replace(/^NK/, ''), ngay: ngay,
      /* MUA là đường DUY NHẤT sinh công nợ nhà cung cấp. Tồn đầu kỳ và kiểm kê
         thừa cũng ghi vào bảng này nhưng `loaiNhap` khác — xem `JP_NHAP_LOAI`. */
      loaiNhap: JP_NHAP_MUA,
      khoId: JP_KHO_TONG, locationId: '', locationName: 'KHO TỔNG',
      nccMa: jpStr_(p.nccMa), nccTen: jpStr_(p.nccTen),
      soDong: dong.length, tongTien: tong, ghiChu: jpStr_(p.ghiChu),
      createdBy: u.hoTen, createdAt: new Date()
    });

    /* Ghi chi tiết + lớp tồn theo KHỐI, không append trong vòng lặp */
    var ct = [], lop = [];
    dong.forEach(function (d, i) {
      var ctId = id + '-D' + (i + 1);
      ct.push({
        id: ctId, nhapId: id, seq: i + 1,
        itemCode: d.itemCode, itemName: d.itemName, dvt: 'Cái',
        qty: d.qty, unitCost: d.unitCost, amount: d.amount,
        lotNo: d.lotNo, note: d.note
      });
      lop.push({
        id: id + '-L' + (i + 1), khoId: JP_KHO_TONG, locationId: '',
        itemCode: d.itemCode,
        ngay: ngay, qtyInit: d.qty, qtyRemaining: d.qty, unitCost: d.unitCost,
        nguon: ctId, lotNo: d.lotNo, createdAt: new Date()
      });
    });
    jpAppendMany_(JP_TABS.KHO_NHAP_CT, ct);
    jpAppendMany_(JP_TABS.KHO_LOP, lop);

    jpAudit_(u, 'KHO_NHAP', '', id,
             { kho: 'TONG', soDong: dong.length, tongTien: tong });

    return { ok: true, id: id, soDong: dong.length, tongTien: tong,
             msg: 'Đã nhập ' + dong.length + ' mã hàng · ' + jpMoney_(tong) + 'đ' };
  });
}

/*──────────────────── ② TỒN KHO THEO LỚP ────────────────────*/

/**
 * Đọc TOÀN BỘ lớp tồn MỘT lần rồi dựng chỉ mục — đây là "sổ kho" trong bộ nhớ
 * mà cả lần xuất kho dùng chung.
 *
 * Vì sao phải là một chỉ mục chứ không phải hàm tra từng mã: `jpRows_` dựng lại
 * mảng object mới ở MỖI lượt gọi (dữ liệu thô thì có cache, việc dựng object thì
 * không). Tra theo mã trong vòng lặp là quét lại cả bảng lớp cho từng mã hàng —
 * đúng cái điều nguyên tắc "đọc sheet một lần" cấm.
 *
 *   .fifo[khoId|itemCode] : lớp CÒN HÀNG ở đúng kho đó, xếp CŨ TRƯỚC
 *   .theoMa[itemCode]     : mọi lớp của mã đó, MỚI TRƯỚC (để suy giá gần nhất)
 *
 * `khoId` là `JP_KHO_TONG` hoặc chính `locationId` — kho tổng và cơ sở dùng CÙNG
 * một bảng lớp, chỉ khác khoá. Nhờ vậy FIFO chỉ có một bản cài đặt cho cả hai tầng.
 *
 * Thứ tự sắp: ngày rồi id. Hai lớp cùng ngày vẫn phải có thứ tự ổn định — chạy
 * lại cùng dữ liệu phải ra cùng kết quả, không thì đối chiếu sổ không bao giờ khớp.
 */
function jpChiMucLop_() {
  var all = jpRows_(JP_TABS.KHO_LOP);

  var cu = function (a, b) {                      // cũ trước
    var da = jpDate_(a.ngay), db = jpDate_(b.ngay);
    if (da !== db) return da < db ? -1 : 1;
    return String(a.id) < String(b.id) ? -1 : 1;
  };

  var fifo = {}, theoMa = {};
  all.forEach(function (l) {
    var ma = jpStr_(l.itemCode);
    if (!ma) return;
    /* Dữ liệu đời đầu chưa có `khoId` — coi như nằm ở cơ sở ghi trong `locationId`,
       không có thì coi là kho tổng. Bỏ qua bước này là mất sạch tồn cũ. */
    l.__kho = jpStr_(l.khoId) || jpStr_(l.locationId) || JP_KHO_TONG;
    (theoMa[ma] = theoMa[ma] || []).push(l);
    if (jpNum_(l.qtyRemaining) > 0) {
      var k = l.__kho + '|' + ma;
      (fifo[k] = fifo[k] || []).push(l);
    }
  });
  Object.keys(fifo).forEach(function (k) { fifo[k].sort(cu); });
  Object.keys(theoMa).forEach(function (m) {
    theoMa[m].sort(cu).reverse();                 // mới trước
  });

  return { fifo: fifo, theoMa: theoMa };
}

/**
 * Giá mua gần nhất của một mã — dùng khi thiếu lớp, để không ghi giá vốn 0.
 * Ưu tiên lớp của đúng cơ sở, không có thì lấy lớp bất kỳ của mã đó.
 */
function jpGiaGanNhat_(chiMuc, khoId, itemCode) {
  var ds = chiMuc.theoMa[jpStr_(itemCode)] || [];
  var kho = String(khoId);
  /* Ưu tiên lớp của đúng kho đang xuất; không có thì lấy lớp mới nhất của mã đó
     ở BẤT KỲ kho nào — hàng cùng mã thì giá mua như nhau, còn hơn ghi giá vốn 0. */
  for (var i = 0; i < ds.length; i++) {
    if (ds[i].__kho === kho) return jpNum_(ds[i].unitCost);
  }
  return ds.length ? jpNum_(ds[0].unitCost) : 0;
}

/** Bảng tồn kho hiện tại: gộp lớp theo cơ sở + mã hàng. */
function jpKhoTonKho(token, khoId) {
  jpNeedKT_(jpAuth_(token));
  return jpTonKho_(khoId);
}

/** Tồn kho theo lớp, gộp theo kho + mã hàng. `khoId` trống = mọi kho. */
function jpTonKho_(khoId) {
  var locName = {};
  jpRows_(JP_TABS.LOCATIONS).forEach(function (l) { locName[String(l.id)] = jpStr_(l.name); });
  locName[JP_KHO_TONG] = 'KHO TỔNG';
  var itemMap = jpItemMap_();

  var gom = {};
  jpRows_(JP_TABS.KHO_LOP).forEach(function (l) {
    var kho = jpStr_(l.khoId) || jpStr_(l.locationId) || JP_KHO_TONG;
    if (khoId && kho !== String(khoId)) return;
    var con = jpNum_(l.qtyRemaining);
    if (con <= 0) return;
    var k = kho + '|' + jpStr_(l.itemCode);
    if (!gom[k]) {
      gom[k] = {
        khoId: kho, laKhoTong: kho === JP_KHO_TONG,
        locationId: kho === JP_KHO_TONG ? '' : kho,
        locationName: locName[kho] || ('Kho ' + kho),
        itemCode: jpStr_(l.itemCode),
        itemName: jpStr_((itemMap[jpStr_(l.itemCode)] || {}).name),
        soLop: 0, tonQty: 0, tonTien: 0
      };
    }
    gom[k].soLop++;
    gom[k].tonQty += con;
    gom[k].tonTien += con * jpNum_(l.unitCost);
  });

  var rows = Object.keys(gom).map(function (k) {
    var g = gom[k];
    g.giaBinhQuan = g.tonQty ? Math.round(g.tonTien / g.tonQty) : 0;
    return g;
  }).sort(function (a, b) {
    /* KHO TỔNG luôn lên đầu — kế toán soát kho tổng trước, rồi mới tới cơ sở */
    if (a.laKhoTong !== b.laKhoTong) return a.laKhoTong ? -1 : 1;
    if (a.locationName !== b.locationName) return a.locationName < b.locationName ? -1 : 1;
    return a.itemCode < b.itemCode ? -1 : 1;
  });

  var tong = { tonQty: 0, tonTien: 0 };
  rows.forEach(function (r) { tong.tonQty += r.tonQty; tong.tonTien += r.tonTien; });

  return { ok: true, rows: rows, tong: tong };
}

/*──────────────────── ③ XUẤT KHO TỔNG (5 loại) ────────────────────*/

/**
 * Phiếu XUẤT từ kho tổng. Đây là chỗ hàng rời kho tổng, dù đi đâu.
 *
 * p = { ngay, loai, locationId?, ghiChu, rows:[{itemCode, qty, note}] }
 *   · `loai` là một trong `JP_XUAT_LOAI` — quyết định hạch toán, xem 00_Config.
 *   · `locationId` bắt buộc với XUAT_CS và TRA_KHO; các loại khác bỏ trống.
 *   · KHÔNG nhận `unitCost` từ client — giá vốn do FIFO quyết, không ai gõ vào.
 *
 * Ba việc một phiếu làm:
 *   1. Trừ lớp ở kho tổng theo FIFO (hoặc CỘNG lại nếu là TRA_KHO).
 *   2. XUAT_CS: sinh LỚP MỚI ở cơ sở, giữ nguyên giá mua ⇒ bán mới ra 632 đúng giá.
 *   3. XE_MAU / TANG_MALL: ghi luôn dòng sổ Nợ 641 — hàng không bán được nữa.
 */
function jpKhoXuat(token, p) {
  var u = jpNeedKT_(jpAuth_(token));
  p = p || {};

  var loai = jpStr_(p.loai).toUpperCase();
  var def = JP_XUAT_LOAI[loai];
  if (!def) throw new Error('Loại xuất không hợp lệ: ' + loai);

  var ngay = jpDate_(p.ngay) || jpToday_();
  var locId = jpStr_(p.locationId);
  var loc = null;
  if (loai === JP_XUAT_CS || loai === JP_XUAT_TRA) {
    if (!locId) throw new Error('Chọn cơ sở cho phiếu ' + def.ten);
    loc = jpFindOne_(JP_TABS.LOCATIONS, 'id', locId);
    if (!loc) throw new Error('Không tìm thấy cơ sở');
  }

  var itemMap = jpItemMap_();
  var can = {}, thuTu = [];
  (p.rows || []).forEach(function (r) {
    var ma = jpStr_(r.itemCode), sl = jpNum_(r.qty);
    if (!ma || sl <= 0) return;
    if (!can[ma]) thuTu.push(ma);
    can[ma] = (can[ma] || 0) + sl;
  });
  if (!thuTu.length) throw new Error('Chưa có dòng hàng nào để xuất');

  return jpLock_(function () {
    var id = jpNextId_('DC');
    var soCT = JP_DC_PREFIX + id.replace(/^DC/, '');
    var chiMuc = jpChiMucLop_();

    /* TRA_KHO đi NGƯỢC: trừ lớp ở CƠ SỞ, cộng trả về kho tổng. */
    var khoTru = (loai === JP_XUAT_TRA) ? locId : JP_KHO_TONG;
    var khoNhan = (loai === JP_XUAT_TRA) ? JP_KHO_TONG
                : (loai === JP_XUAT_CS)  ? locId : '';

    var ct = [], lopMoi = [], soDong = 0, tongSL = 0, tongTien = 0, thieu = [];
    var seq = 0;

    thuTu.sort().forEach(function (ma) {
      jpTruFifo_(chiMuc, khoTru, ma, can[ma]).forEach(function (x) {
        seq++;
        var tien = x.qty * x.unitCost;
        tien = jpDong_(tien);
        tongSL += x.qty; tongTien += tien; soDong++;
        if (x.thieuLop) thieu.push(ma + ' × ' + x.qty);

        /* Hàng sang kho khác thì sinh lớp mới ở đó, GIỮ NGUYÊN giá mua */
        var idLopMoi = '';
        if (khoNhan) {
          idLopMoi = id + '-L' + seq;
          lopMoi.push({
            id: idLopMoi, khoId: khoNhan,
            locationId: (khoNhan === JP_KHO_TONG ? '' : khoNhan),
            itemCode: ma, ngay: ngay,
            qtyInit: x.qty, qtyRemaining: x.qty, unitCost: x.unitCost,
            nguon: id + '-D' + seq, lotNo: '', createdAt: new Date()
          });
        }

        ct.push({
          id: id + '-D' + seq, dcId: id, seq: seq,
          itemCode: ma, itemName: jpStr_((itemMap[ma] || {}).name),
          qty: x.qty, unitCost: x.unitCost, amount: tien,
          layerId: x.layerId, layerMoi: idLopMoi,
          thieuLop: x.thieuLop, note: ''
        });
      });
    });

    jpAppend_(JP_TABS.KHO_DC, {
      id: id, soChungTu: soCT, ngay: ngay, loai: loai,
      khoTu: khoTru, khoDen: khoNhan,
      locationName: loc ? jpStr_(loc.name) : def.ten,
      soDong: soDong, tongSL: tongSL, tongTien: tongTien,
      ghiChu: jpStr_(p.ghiChu), createdBy: u.hoTen, createdAt: new Date()
    });
    jpAppendMany_(JP_TABS.KHO_DC_CT, ct);
    jpAppendMany_(JP_TABS.KHO_LOP, lopMoi);

    /* XÉ MẪU / TẶNG MALL: hàng mất luôn ⇒ ghi sổ Nợ 641 ngay tại đây.
       Bán hàng thì KHÔNG ghi ở đây — chờ báo cáo nhân viên hoàn tất. */
    var soGhi = [];
    if (def.tk) {
      ct.forEach(function (d, i) {
        soGhi.push({
          id: id + '-S' + (i + 1), soChungTu: soCT, ngay: ngay,
          loai: (loai === JP_XUAT_XE_MAU ? JP_SO_XE_MAU : JP_SO_TANG),
          reportId: '', dcId: id,
          khoId: khoTru, locationId: locId,
          locationName: loc ? jpStr_(loc.name) : def.ten,
          maKH: loc ? jpStr_(loc.maKH) : '', unitCode: loc ? jpStr_(loc.unitCode) : '',
          itemCode: d.itemCode, itemName: d.itemName,
          qty: d.qty, unitCost: d.unitCost, amount: d.amount,
          tkNo: def.tk, tkCo: JP_TK_KHO,
          layerId: d.layerId, thieuLop: d.thieuLop,
          createdBy: u.hoTen, createdAt: new Date()
        });
      });
      jpAppendMany_(JP_TABS.KHO_XUAT, soGhi);
    }

    jpGhiLopDaTru_(chiMuc);

    jpAudit_(u, 'KHO_XUAT_PHIEU', '', id,
             { loai: loai, khoTu: khoTru, khoDen: khoNhan,
               soDong: soDong, tongSL: tongSL, tongTien: tongTien,
               thieuLop: thieu.length, vaoSo: soGhi.length });

    return {
      ok: true, id: id, soChungTu: soCT, loai: loai, ten: def.ten,
      soDong: soDong, tongSL: tongSL, tongTien: tongTien,
      thieuLop: thieu, vaoSo: soGhi.length,
      msg: def.ten + ' · ' + tongSL + ' cái · giá vốn ' + jpMoney_(tongTien) + 'đ' +
           (def.tk ? ' → ghi Nợ ' + def.tk : '') +
           (thieu.length ? ' · ⚠ ' + thieu.length + ' dòng thiếu lớp tồn' : '')
    };
  });
}

/**
 * Ghi `qtyRemaining` của mọi lớp đã bị trừ trong chỉ mục. Gọi MỘT lần sau khi
 * tính xong — mỗi lớp đúng một lượt ghi, kể cả khi nhiều mã cùng ăn vào nó.
 */
function jpGhiLopDaTru_(chiMuc) {
  /* GOM rồi ghi MỘT LẦN qua `jpGhiCot_`. Bản trước gọi `jpFields_` trong vòng lặp
     nên trừ N lớp là N lượt gọi sang Sheets — duyệt một báo cáo 50 mã ăn vào 2 lớp
     mỗi mã là 100 lượt. Cùng một `_row` chỉ ghi một lần (map tự lo). */
  var capNhat = {}, n = 0;
  Object.keys(chiMuc.fifo).forEach(function (k) {
    chiMuc.fifo[k].forEach(function (l) {
      if (!l.__daTru || capNhat[l._row] !== undefined) return;
      capNhat[l._row] = jpNum_(l.__con); n++;
    });
  });
  jpGhiCot_(JP_TABS.KHO_LOP, 'qtyRemaining', capNhat);
  return n;
}

/*──────────────────── ④ BÁN Ở CƠ SỞ → SỔ 632 ────────────────────*/

/** Báo cáo này đã xuất kho chưa? */
function jpDaXuatKho_(reportId) {
  return jpFind_(JP_TABS.KHO_XUAT, 'reportId', String(reportId)).length > 0;
}

/**
 * Trừ FIFO cho MỘT mã hàng ở MỘT kho. Trả về {qty, unitCost, layerId, thieuLop}.
 * Chỉ TÍNH, không ghi sheet — để chỗ gọi ghi một khối và còn hoàn lại được.
 * Dùng chung cho cả hai tầng: `khoId` = `JP_KHO_TONG` hoặc `locationId`.
 */
function jpTruFifo_(chiMuc, khoId, itemCode, qtyCan) {
  var canLai = jpNum_(qtyCan);
  var out = [];
  if (canLai <= 0) return out;

  var lop = chiMuc.fifo[String(khoId) + '|' + jpStr_(itemCode)] || [];

  for (var i = 0; i < lop.length && canLai > 0; i++) {
    var con = jpNum_(lop[i].__con === undefined ? lop[i].qtyRemaining : lop[i].__con);
    if (con <= 0) continue;
    var lay = Math.min(con, canLai);
    lop[i].__con = con - lay;                 // giữ trên object để mã sau thấy
    lop[i].__daTru = jpNum_(lop[i].__daTru) + lay;
    canLai -= lay;
    out.push({ qty: lay, unitCost: jpNum_(lop[i].unitCost),
               layerId: String(lop[i].id), thieuLop: '' });
  }

  /* Hết lớp mà vẫn còn phải xuất: ghi tiếp bằng giá mua gần nhất, gắn cờ */
  if (canLai > 0) {
    out.push({ qty: canLai, unitCost: jpGiaGanNhat_(chiMuc, khoId, itemCode),
               layerId: '', thieuLop: 'Y' });
  }
  return out;
}

/**
 * Xuất kho cho một báo cáo đã HOÀN TẤT. Gọi từ `jpKtApprove` khi đủ hai chữ ký.
 * Không throw ra ngoài: xuất kho lỗi thì KHÔNG được làm hỏng việc duyệt — báo cáo
 * đã duyệt là chuyện của kế toán, còn sổ kho thì chạy lại được bằng `jpKhoXuatLai`.
 */
function jpXuatKhoBaoCao_(head, user) {
  try {
    if (!head || jpStr_(head.status) !== JP_ST_DONE) return null;
    if (jpDaXuatKho_(head.id)) return null;               // bất biến #2

    var rows = jpFind_(JP_TABS.ROWS, 'reportId', head.id);
    if (!rows.length) return null;

    var loc = jpFindOne_(JP_TABS.LOCATIONS, 'id', head.locationId) || {};
    var itemMap = jpItemMap_();
    var ngay = jpDate_(head.toDate) || jpToday_();
    var soCT = JP_XK_PREFIX + String(head.id).replace(/^RP/, '');

    /* Gộp số bán theo mã hàng: một mã có thể nằm ở nhiều dòng/ô máy.
       CHỈ lấy dòng thật giữ hàng — máy tiền là dòng MONEY, máy xu là dòng STOCK.
       Dòng COIN chỉ giữ tiền theo vị trí, cộng vào là xuất kho hai lần. */
    var canXuat = {};
    rows.forEach(function (r) {
      /* CHỈ dòng thật giữ hàng mới được xuất kho — `jpDongGiuHang_` là danh sách
         CHO PHÉP duy nhất (xem `00_Config`). Dòng COIN chỉ giữ tiền theo vị trí;
         NGOAI là kiểm kê chỗ hàng ngoài máy; MAY (mẫu tách) chỉ giữ tiền. Cộng
         chúng vào là XUẤT KHO HAI LẦN. Lọc theo `rowKind`, đừng dựa vào việc
         soldQty tình cờ bằng 0 — số 0 đó do các hàm tính ép, đổi là hỏng. */
      if (!jpDongGiuHang_(r.rowKind)) return;
      var ma = jpStr_(r.itemCode);
      var sl = jpNum_(r.soldQty);
      if (!ma || sl <= 0) return;
      canXuat[ma] = (canXuat[ma] || 0) + sl;
    });
    var maList = Object.keys(canXuat);
    if (!maList.length) return null;

    var chiMuc = jpChiMucLop_();
    var ghi = [], thieu = [], tongTien = 0, seq = 0;

    /* Trừ lớp CỦA CƠ SỞ, không phải kho tổng: hàng đã điều chuyển xuống đây rồi,
       và chính lớp đó mới giữ đúng giá mua của lô đang bán. */
    maList.sort().forEach(function (ma) {
      jpTruFifo_(chiMuc, String(head.locationId), ma, canXuat[ma]).forEach(function (x) {
        seq++;
        var tien = x.qty * x.unitCost;
        tien = jpDong_(tien);
        tongTien += tien;
        ghi.push({
          id: head.id + '-X' + seq, soChungTu: soCT, ngay: ngay,
          loai: JP_SO_BAN, reportId: String(head.id), dcId: '',
          khoId: String(head.locationId),
          locationId: String(head.locationId), locationName: jpStr_(head.locationName),
          maKH: jpStr_(loc.maKH), unitCode: jpStr_(loc.unitCode),
          itemCode: ma, itemName: jpStr_((itemMap[ma] || {}).name),
          qty: x.qty, unitCost: x.unitCost, amount: tien,
          tkNo: JP_TK_GIA_VON, tkCo: JP_TK_KHO,
          layerId: x.layerId, thieuLop: x.thieuLop,
          createdBy: (user && user.hoTen) || 'system', createdAt: new Date()
        });
        if (x.thieuLop) thieu.push(ma + ' × ' + x.qty);
      });
    });

    jpAppendMany_(JP_TABS.KHO_XUAT, ghi);

    jpGhiLopDaTru_(chiMuc);

    jpAudit_(user, 'KHO_XUAT', head.id, jpStr_(head.locationName),
             { soDong: ghi.length, tongGiaVon: tongTien, thieuLop: thieu.length });

    return { soDong: ghi.length, tongGiaVon: tongTien, thieuLop: thieu };
  } catch (e) {
    /* Không chặn việc duyệt, nhưng PHẢI nói ra. Nuốt lỗi rồi im lặng thì kế toán
       thấy "HOÀN TẤT" và không bao giờ biết là sổ kho chưa có gì — tới lúc xuất
       632 thiếu dòng mới phát hiện, mà lúc đó không lần ra được báo cáo nào. */
    jpAudit_(user, 'KHO_XUAT_LOI', head && head.id, '', { loi: String(e && e.message) });
    return { loi: String((e && e.message) || e) };
  }
}

/**
 * Hoàn kho khi mở lại báo cáo: trả số về đúng lớp cũ rồi xoá dòng xuất.
 * Dòng `thieuLop` không có lớp để hoàn — chỉ xoá, và nói ra trong audit.
 */
function jpHoanKhoBaoCao_(head, user) {
  try {
    var xuat = jpFind_(JP_TABS.KHO_XUAT, 'reportId', String(head.id));
    if (!xuat.length) return null;

    /* Đọc lớp một lần rồi tra map, không đọc trong vòng lặp */
    var lopById = {};
    jpRows_(JP_TABS.KHO_LOP).forEach(function (l) { lopById[String(l.id)] = l; });

    var hoan = {}, khongHoan = 0;
    xuat.forEach(function (x) {
      var lid = jpStr_(x.layerId);
      if (!lid || !lopById[lid]) { khongHoan++; return; }
      hoan[lid] = (hoan[lid] || 0) + jpNum_(x.qty);
    });

    /* KẸP THEO `qtyInit` — bất biến: một lớp không bao giờ còn nhiều hơn lúc nhập.
       Không phải phòng xa. `jpXuatKhoBaoCao_` ghi dòng xuất TRƯỚC rồi mới trừ lớp
       (phải vậy: trừ trước mà ghi hỏng thì chạy lại là trừ hai lần). Nếu ghi xong
       dòng xuất rồi mới chết giữa chừng lúc trừ, sẽ có lớp CÓ dòng xuất mà CHƯA bị
       trừ. Hoàn theo dòng xuất là cộng trả cho cả lớp đó ⇒ tồn kho phình ra.
       Kẹp lại là tình huống đó tự về đúng. */
    var vuot = 0, capNhat = {};
    Object.keys(hoan).forEach(function (lid) {
      var l = lopById[lid];
      var moi = jpNum_(l.qtyRemaining) + hoan[lid];
      var tran = jpNum_(l.qtyInit);
      if (moi > tran) { vuot++; moi = tran; }
      capNhat[l._row] = moi;
    });
    /* Gom rồi ghi một lần — xem `jpGhiCot_`. Hoàn kho của một báo cáo có thể chạm
       hàng chục lớp; ghi từng lớp là hàng chục lượt gọi. */
    jpGhiCot_(JP_TABS.KHO_LOP, 'qtyRemaining', capNhat);

    /* Xoá dòng xuất: jpDeleteWhere_ gộp khối liền nhau, và dòng của một báo cáo
       luôn được ghi cùng lúc nên nằm liền nhau — một lượt gọi thay vì N lượt. */
    jpDeleteWhere_(JP_TABS.KHO_XUAT, 'reportId', String(head.id));

    jpAudit_(user, 'KHO_HOAN', head.id, jpStr_(head.locationName),
             { soDong: xuat.length, soLopHoan: Object.keys(hoan).length,
               khongHoanDuoc: khongHoan, kepTheoQtyInit: vuot });

    return { soDong: xuat.length, khongHoanDuoc: khongHoan, kep: vuot };
  } catch (e) {
    jpAudit_(user, 'KHO_HOAN_LOI', head && head.id, '', { loi: String(e && e.message) });
    return null;
  }
}

/** Chạy lại xuất kho cho một báo cáo (khi lần tự động bị lỗi, hoặc nhập bù phiếu). */
function jpKhoXuatLai(token, reportId) {
  var u = jpNeedKT_(jpAuth_(token));
  return jpLock_(function () {
    var head = jpFindOne_(JP_TABS.REPORTS, 'id', reportId);
    if (!head) throw new Error('Không tìm thấy báo cáo');
    if (jpStr_(head.status) !== JP_ST_DONE) {
      throw new Error('Chỉ xuất kho cho báo cáo đã HOÀN TẤT');
    }
    if (jpDaXuatKho_(head.id)) {
      jpHoanKhoBaoCao_(head, u);            // hoàn trước rồi xuất lại, khỏi trùng
    }
    var kq = jpXuatKhoBaoCao_(head, u);
    if (!kq) return { ok: false, msg: 'Không có dòng nào để xuất (báo cáo chưa có số bán)' };
    /* Phân biệt "không có gì để xuất" với "chạy hỏng" — hai cái này mà gộp thì
       kế toán bấm Xuất lại, thấy báo màu xanh, tưởng xong. */
    if (kq.loi) throw new Error('Xuất kho lỗi: ' + kq.loi);
    return {
      ok: true, soDong: kq.soDong, tongGiaVon: kq.tongGiaVon,
      thieuLop: kq.thieuLop,
      msg: 'Đã xuất ' + kq.soDong + ' dòng · giá vốn ' + jpMoney_(kq.tongGiaVon) + 'đ' +
           (kq.thieuLop.length ? ' · ⚠ ' + kq.thieuLop.length + ' dòng thiếu lớp tồn' : '')
    };
  });
}

/*──────────────────── ④ SỔ CHI TIẾT 632 CHO MISA ────────────────────*/

/**
 * Dựng đúng cấu trúc "SỔ CHI TIẾT CÁC TÀI KHOẢN" của MISA cho TK 632.
 * Cột lấy y theo file kế toán gửi 03/08/2026 — xem CLAUDE.md mục liên thông MISA.
 *
 * `duNo` là số dư luỹ kế TRONG KỲ (bắt đầu từ `duDauKy` truyền vào, mặc định 0):
 * số dư đầu kỳ thật nằm ở MISA, JP không biết, nên để kế toán truyền vào hoặc bỏ.
 */
function jpSo632(token, thang, nam, duDauKy, taiKhoan) {
  jpNeedKT_(jpAuth_(token));
  /* Cùng một bảng sổ giữ cả 632 (bán) và 641 (xé mẫu, tặng mall) — lọc theo `tkNo`.
     Trộn hai tài khoản vào một sổ là kế toán không đối chiếu được với MISA. */
  var tk = jpStr_(taiKhoan) || JP_TK_GIA_VON;

  var m = jpNum_(thang), y = jpNum_(nam);
  if (m < 1 || m > 12 || y < 2020) throw new Error('Tháng / năm không hợp lệ');
  var soNgay = new Date(y, m, 0).getDate();
  var dau = y + '-' + ('0' + m).slice(-2) + '-01';
  var cuoi = y + '-' + ('0' + m).slice(-2) + '-' + ('0' + soNgay).slice(-2);

  var ds = jpRows_(JP_TABS.KHO_XUAT).filter(function (x) {
    var d = jpDate_(x.ngay);
    if (d < dau || d > cuoi) return false;
    /* Dòng đời đầu chưa có `tkNo` — mặc định là 632, vì hồi đó chỉ có bán hàng */
    return (jpStr_(x.tkNo) || JP_TK_GIA_VON) === tk;
  }).sort(function (a, b) {
    var da = jpDate_(a.ngay), db = jpDate_(b.ngay);
    if (da !== db) return da < db ? -1 : 1;
    if (jpStr_(a.soChungTu) !== jpStr_(b.soChungTu)) {
      return jpStr_(a.soChungTu) < jpStr_(b.soChungTu) ? -1 : 1;
    }
    return String(a.id) < String(b.id) ? -1 : 1;
  });

  var du = jpNum_(duDauKy), tongNo = 0, thieu = 0;
  var rows = ds.map(function (x) {
    var no = jpNum_(x.amount);
    du += no; tongNo += no;
    if (jpStr_(x.thieuLop)) thieu++;
    return {
      ngayHachToan: jpDate_(x.ngay), ngayChungTu: jpDate_(x.ngay),
      soChungTu: jpStr_(x.soChungTu),
      dienGiaiChung: jpDienGiaiSo_(x),
      dienGiai: jpStr_(x.itemName) || jpStr_(x.itemCode),
      tkDoiUng: jpStr_(x.tkCo) || JP_TK_KHO,
      phatSinhNo: no, phatSinhCo: 0,
      duNo: du, duCo: 0,
      maDoiTuong: jpStr_(x.maKH), maDonVi: jpStr_(x.unitCode),
      tenDonVi: jpStr_(x.locationName),
      soLuong: jpNum_(x.qty), giaVon: jpNum_(x.unitCost),
      maHang: jpStr_(x.itemCode), thieuLop: !!jpStr_(x.thieuLop),
      loai: jpStr_(x.loai) || JP_SO_BAN
    };
  });

  var canhBao = [];
  if (thieu) {
    canhBao.push(thieu + ' dòng xuất khi kho chưa có lớp tồn — giá vốn lấy theo giá ' +
                 'mua gần nhất. Nhập bù phiếu nhập rồi bấm "Xuất lại" cho báo cáo đó.');
  }
  var thieuKH = {};
  rows.forEach(function (r) { if (!r.maDoiTuong) thieuKH[r.tenDonVi] = 1; });
  if (Object.keys(thieuKH).length) {
    canhBao.push('Chưa có mã KH: ' + Object.keys(thieuKH).join(', ') +
                 ' — MISA cần Mã đối tượng để khớp công nợ.');
  }

  return {
    ok: true, thang: m, nam: y, taiKhoan: tk, tenTaiKhoan: jpTenTk_(tk),
    duDauKy: jpNum_(duDauKy), tongPhatSinhNo: tongNo, duCuoiKy: du,
    rows: rows, canhBao: canhBao
  };
}

/*──────────────────── ⑤ NHẬT KÝ NHẬP KHO ────────────────────*/

function jpKhoLichSuNhap(token, limit) {
  jpNeedKT_(jpAuth_(token));
  var n = jpNum_(limit) || 50;

  var ct = {};
  jpRows_(JP_TABS.KHO_NHAP_CT).forEach(function (d) {
    (ct[String(d.nhapId)] = ct[String(d.nhapId)] || []).push({
      itemCode: jpStr_(d.itemCode), itemName: jpStr_(d.itemName),
      qty: jpNum_(d.qty), unitCost: jpNum_(d.unitCost), amount: jpNum_(d.amount)
    });
  });

  return jpRows_(JP_TABS.KHO_NHAP)
    .sort(function (a, b) {
      var da = jpDate_(a.ngay), db = jpDate_(b.ngay);
      if (da !== db) return da > db ? -1 : 1;
      return String(a.id) > String(b.id) ? -1 : 1;
    })
    .slice(0, n)
    .map(function (r) {
      return {
        id: String(r.id), soChungTu: jpStr_(r.soChungTu), ngay: jpDate_(r.ngay),
        locationName: jpStr_(r.locationName),
        nccTen: jpStr_(r.nccTen), soDong: jpNum_(r.soDong),
        tongTien: jpNum_(r.tongTien), ghiChu: jpStr_(r.ghiChu),
        createdBy: jpStr_(r.createdBy),
        daHuy: !!jpStr_(r.huyAt), huyReason: jpStr_(r.huyReason),
        dong: ct[String(r.id)] || []
      };
    });
}

/**
 * Huỷ một phiếu nhập. Chỉ huỷ được khi các lớp của nó CHƯA bị bán vào —
 * đã bán rồi thì huỷ là làm giá vốn đã ghi trong sổ 632 thành số không nguồn gốc.
 */
function jpKhoHuyNhap(token, nhapId, reason) {
  var u = jpNeedKT_(jpAuth_(token));
  var ly = jpStr_(reason);
  if (!ly) throw new Error('Nhập lý do huỷ phiếu');

  return jpLock_(function () {
    var head = jpFindOne_(JP_TABS.KHO_NHAP, 'id', nhapId);
    if (!head) throw new Error('Không tìm thấy phiếu nhập');
    if (jpStr_(head.huyAt)) return { ok: true, msg: 'Phiếu này đã huỷ trước đó' };

    var lop = jpRows_(JP_TABS.KHO_LOP).filter(function (l) {
      return String(l.nguon).indexOf(String(nhapId) + '-D') === 0;
    });
    var daBan = lop.filter(function (l) {
      return jpNum_(l.qtyRemaining) < jpNum_(l.qtyInit);
    });
    if (daBan.length) {
      throw new Error('Không huỷ được: ' + daBan.length + ' lớp của phiếu này đã bị bán ' +
                      'vào sổ 632. Nhập phiếu điều chỉnh thay vì huỷ.');
    }

    lop.sort(function (a, b) { return b._row - a._row; })
       .forEach(function (l) { jpDelete_(JP_TABS.KHO_LOP, l._row); });

    jpFields_(JP_TABS.KHO_NHAP, head._row, {
      huyBy: u.hoTen, huyAt: new Date(), huyReason: ly
    });
    jpAudit_(u, 'KHO_HUY_NHAP', '', String(nhapId), { soLop: lop.length, reason: ly });

    return { ok: true, soLop: lop.length,
             msg: 'Đã huỷ phiếu ' + jpStr_(head.soChungTu) + ' · bỏ ' + lop.length + ' lớp tồn' };
  });
}

/** Diễn giải chung của một dòng sổ, theo loại. */
function jpDienGiaiSo_(x) {
  var loai = jpStr_(x.loai) || JP_SO_BAN;
  var ten = jpStr_(x.locationName);
  if (loai === JP_SO_XE_MAU)  return 'Xé mẫu trưng bày ' + ten;
  if (loai === JP_SO_TANG)    return 'Tặng mall / khách ' + ten;
  if (loai === JP_SO_KK_THIEU) return 'Kiểm kê thiếu — hàng mất phải đền ' + ten;
  if (loai === JP_SO_KK_GIAM_XUAT) return 'Điều chỉnh giảm xuất trong kỳ (kiểm kê thừa) ' + ten;
  return 'Xuất kho JP bán hàng ' + ten;
}

/** Tên tài khoản để in lên đầu sổ. */
function jpTenTk_(tk) {
  var t = String(tk);
  if (t === JP_TK_CHI_PHI_BH)     return 'Chi phí bán hàng';
  if (t === JP_TK_GIA_VON_MAT)    return 'Giá vốn hàng bán — hàng mất khi kiểm kê';
  if (t === JP_TK_KHO_KK)         return 'Giá mua hàng hoá';
  if (t === JP_TK_PHAI_THU_KHAC)  return 'Phải thu khác';
  if (t === JP_TK_NCC)            return 'Phải trả người bán';
  /* Hai TK của bản kiểm kê đời đầu (04/08, chỉ tồn tại vài chục phút trên bản
     deploy) — Andy đã chốt bỏ. Giữ tên để dòng cũ nếu có không hiện trống. */
  if (t === '1381')               return 'Tài sản thiếu chờ xử lý (không dùng nữa)';
  if (t === '3381')               return 'Tài sản thừa chờ xử lý (không dùng nữa)';
  return 'Giá vốn hàng bán';
}

/*──────────────────── ⑥ BÁO CÁO NHẬP – XUẤT – TỒN ────────────────────*/

/**
 * Đúng bố cục file kế toán đang dùng ("Kho Hàng JP HCM Năm 2025", khối TỒN KHO):
 *
 *   TỒN ĐẦU THÁNG + TỔNG NHẬP − TỔNG XUẤT = TỒN CUỐI THÁNG
 *
 * Có cho KHO TỔNG và cho TỪNG CƠ SỞ. Cột xuất tách theo loại, vì "xuất xuống cơ
 * sở" và "xé mẫu" là hai chuyện khác nhau hoàn toàn dù cùng làm giảm tồn.
 *
 * TỒN ĐẦU KỲ ĐƯỢC SUY RA, KHÔNG LƯU — cùng cách làm với sổ công nợ:
 *   tồn đầu tháng = tồn HIỆN TẠI − (nhập sau mốc) + (xuất sau mốc)
 * Đi ngược từ tồn hiện tại nên luôn khớp với lớp tồn thật, không cần chốt sổ.
 * Đánh đổi đã biết: sửa một chứng từ cũ thì tồn đầu kỳ tháng sau đổi theo.
 */
function jpKhoNhapXuatTon(token, thang, nam, khoId) {
  jpNeedKT_(jpAuth_(token));
  var m = jpNum_(thang), y = jpNum_(nam);
  if (m < 1 || m > 12 || y < 2020) throw new Error('Tháng / năm không hợp lệ');

  var soNgay = new Date(y, m, 0).getDate();
  var dau  = y + '-' + ('0' + m).slice(-2) + '-01';
  var cuoi = y + '-' + ('0' + m).slice(-2) + '-' + ('0' + soNgay).slice(-2);

  var locName = {};
  jpRows_(JP_TABS.LOCATIONS).forEach(function (l) { locName[String(l.id)] = jpStr_(l.name); });
  locName[JP_KHO_TONG] = 'KHO TỔNG';
  var itemMap = jpItemMap_();

  /* ── Tồn HIỆN TẠI theo kho + mã ── */
  var hienTai = {}, hienTaiGT = {}, coLop = [];
  jpRows_(JP_TABS.KHO_LOP).forEach(function (l) {
    var kho = jpStr_(l.khoId) || jpStr_(l.locationId) || JP_KHO_TONG;
    var ma = jpStr_(l.itemCode);
    var k = kho + '|' + ma;
    if (hienTai[k] === undefined) coLop.push({ kho: kho, ma: ma });
    var con = jpNum_(l.qtyRemaining);
    hienTai[k] = (hienTai[k] || 0) + con;
    /* GIÁ TRỊ tồn hiện tại = Σ (số còn × đơn giá lớp). ⚠️ Đơn giá trên LỚP cố ý để
       LẺ (xem `jpDong_` ở 00_Config) nên số này lẻ đồng — đừng làm tròn từng lớp
       rồi cộng, lệch 1.689đ với file MISA. Chỉ làm tròn khi IN. */
    hienTaiGT[k] = (hienTaiGT[k] || 0) + con * jpNum_(l.unitCost);
  });

  var gom = {};
  var o = function (kho, ma) {
    var k = kho + '|' + ma;
    if (!gom[k]) {
      gom[k] = {
        khoId: kho, laKhoTong: kho === JP_KHO_TONG,
        locationName: locName[kho] || ('Kho ' + kho),
        itemCode: ma, itemName: jpStr_((itemMap[ma] || {}).name),
        misa: jpStr_((itemMap[ma] || {}).misa),
        /* ĐVT và nhóm VTHH — hai cột BẮT BUỘC của form MISA `Tổng hợp Nhập Xuất Tồn`.
           Mã không có trong danh mục (nhân viên gõ mã lạ) thì `itemMap[ma]` là
           `undefined` ⇒ `jpDvt_` rơi về `Quả`. Cố ý: ô ĐVT trống là file không nạp
           được vào MISA, mà mã lạ vẫn phải xuất ra được để kế toán thấy mà bổ sung. */
        dvt: jpDvt_(itemMap[ma]), nhom: JP_NHOM_VTHH,
        tonDau: 0, nhap: 0,
        /* `nhap` là TỔNG bốn nguồn dưới. Tách lẻ vì bốn nguồn này hạch toán khác nhau
           hoàn toàn — `MUA` sinh công nợ NCC (331), `DAU_KY` không nợ ai, `KIEM_KE`
           là 1561/1388, còn nhận điều chuyển chỉ là hàng từ kho tổng xuống. Gộp một
           cột thì không đối chiếu được với sổ 331 hay với phiếu điều chuyển. */
        nhapMua: 0, nhapDauKy: 0, nhapKiemKe: 0, nhapDC: 0,
        xuatCoSo: 0, xuatXeMau: 0, xuatTang: 0, xuatTinh: 0, xuatBan: 0,
        xuatKiemKe: 0, traVe: 0,
        tongXuat: 0, tonCuoi: 0,
        /* ── GIÁ TRỊ, đối xứng hoàn toàn với cột SỐ LƯỢNG (Andy chốt 07/08/2026) ──
           Form MISA `Tổng hợp Nhập Xuất Tồn` có 8 cột số = 4 cặp (Số lượng · Giá
           trị) cho Đầu kỳ · Nhập · Xuất · Cuối kỳ.
           ⚠️ Tiền lấy từ `amount` ĐÃ LƯU trên chứng từ (`JP_KhoNhapCT.amount`,
           `JP_KhoDieuChuyenCT.amount`, `JP_KhoXuat.amount`) — chính con số chảy vào
           `6321`/`1561`. Tính lại theo một đường khác là hai con số cho cùng một
           việc rồi lệch nhau mà không ai biết chỗ nào đúng. */
        gtTonDau: 0, gtNhap: 0, gtTraVe: 0, gtTongXuat: 0, gtTonCuoi: 0,
        nhapSau: 0, xuatSau: 0,           // để suy tồn đầu kỳ, không xuất ra client
        gtNhapSau: 0, gtXuatSau: 0,       // …y vậy cho phần tiền
        nhapTruoc: 0, xuatTruoc: 0,       // để đối chiếu tồn đầu kỳ theo hai đường
        gtNhapTruoc: 0, gtXuatTruoc: 0
      };
    }
    return gom[k];
  };

  /* Mọi (kho, mã) CÓ LỚP TỒN đều phải có một dòng, kể cả khi không tìm thấy chứng từ
     nào của nó. Trước bản này `gom` chỉ sinh ra từ vòng lặp chứng từ, nên lớp tồn mà
     không có chứng từ khớp thì **biến mất khỏi báo cáo** — tổng tồn cuối thiếu hàng
     thật mà bảng vẫn trông sạch sẽ. Đúng là chỗ cần lộ ra nhất: lớp không có chứng từ
     là dữ liệu sai, không phải dữ liệu trống. */
  coLop.forEach(function (x) { o(x.kho, x.ma); });

  /* ── NHẬP từ nhà cung cấp (luôn vào kho tổng) ── */
  var nhapCT = {};
  jpRows_(JP_TABS.KHO_NHAP_CT).forEach(function (d) {
    (nhapCT[String(d.nhapId)] = nhapCT[String(d.nhapId)] || []).push(d);
  });
  jpRows_(JP_TABS.KHO_NHAP).forEach(function (h) {
    if (jpStr_(h.huyAt)) return;                       // phiếu đã huỷ thì không tính
    var d = jpDate_(h.ngay);
    var kho = jpStr_(h.khoId) || JP_KHO_TONG;
    /* Dòng cũ chưa có cột `loaiNhap` thì `jpLoaiNhap_` coi là `MUA` — xem 00_Config */
    var lo = jpLoaiNhap_(h);
    (nhapCT[String(h.id)] || []).forEach(function (ct) {
      var g = o(kho, jpStr_(ct.itemCode)), q = jpNum_(ct.qty);
      var t = jpNum_(ct.amount);
      if (d < dau) { g.nhapTruoc += q; g.gtNhapTruoc += t; }
      if (d >= dau && d <= cuoi) {
        g.nhap += q; g.gtNhap += t;
        if (lo === JP_NHAP_DAU_KY)       g.nhapDauKy += q;
        else if (lo === JP_NHAP_KIEM_KE) g.nhapKiemKe += q;
        else                             g.nhapMua += q;
      }
      if (d > cuoi) { g.nhapSau += q; g.gtNhapSau += t; }
    });
  });

  /* ── XUẤT / ĐIỀU CHUYỂN từ phiếu ── */
  var dcCT = {};
  jpRows_(JP_TABS.KHO_DC_CT).forEach(function (d) {
    (dcCT[String(d.dcId)] = dcCT[String(d.dcId)] || []).push(d);
  });
  jpRows_(JP_TABS.KHO_DC).forEach(function (h) {
    if (jpStr_(h.huyAt)) return;
    var d = jpDate_(h.ngay);
    var loai = jpStr_(h.loai);
    var khoTru = jpStr_(h.khoTu), khoNhan = jpStr_(h.khoDen);
    var trongKy = (d >= dau && d <= cuoi), sauKy = (d > cuoi), truocKy = (d < dau);

    (dcCT[String(h.id)] || []).forEach(function (ct) {
      var ma = jpStr_(ct.itemCode), q = jpNum_(ct.qty);
      var t = jpNum_(ct.amount);

      /* Bên BỊ TRỪ */
      var gt = o(khoTru, ma);
      if (trongKy) {
        gt.gtTongXuat += t;
        if (loai === JP_XUAT_CS)          gt.xuatCoSo += q;
        else if (loai === JP_XUAT_XE_MAU) gt.xuatXeMau += q;
        else if (loai === JP_XUAT_TANG)   gt.xuatTang += q;
        else if (loai === JP_XUAT_DC)     gt.xuatTinh += q;
        else if (loai === JP_XUAT_TRA)    gt.xuatCoSo += q;   // cơ sở trả lên: cơ sở giảm
      }
      if (sauKy) { gt.xuatSau += q; gt.gtXuatSau += t; }
      if (truocKy) { gt.xuatTruoc += q; gt.gtXuatTruoc += t; }

      /* Bên NHẬN (nếu có) */
      if (khoNhan) {
        var gn = o(khoNhan, ma);
        if (trongKy) {
          if (loai === JP_XUAT_TRA) {
            gn.traVe += q;                                    // kho tổng nhận lại
            gn.gtTraVe += t;
          } else {
            gn.nhap += q;                                     // cơ sở nhận hàng
            gn.nhapDC += q;                                   // …từ phiếu điều chuyển
            gn.gtNhap += t;
          }
        }
        if (sauKy) { gn.nhapSau += q; gn.gtNhapSau += t; }
        if (truocKy) gn.gtNhapTruoc += t;
        /* Trước kỳ thì `traVe` và `nhap` gộp làm một: cả hai đều là hàng VÀO kho,
           mà công thức tồn đầu trừ cả hai như nhau. */
        if (truocKy) gn.nhapTruoc += q;
      }
    });
  });

  /* ── BÁN ở cơ sở (632), KIỂM KÊ THIẾU (6321) và ĐIỀU CHỈNH GIẢM XUẤT ──
     Ba loại này chỉ có dòng sổ, KHÔNG có phiếu xuất, nên phải đếm ở đây.
     Còn 641 (xé mẫu / tặng mall) thì đã đếm ở phiếu xuất bên trên — đếm lại ở
     đây là trừ tồn hai lần.
     `KK_GIAM_XUAT` mang số lượng ÂM (dòng đảo của một dòng xuất cũ), nên cộng
     bình thường là tổng xuất tự giảm — đúng ý "điều chỉnh giảm số lượng xuất ra
     trong kỳ để khớp số thực tế". */
  jpRows_(JP_TABS.KHO_XUAT).forEach(function (x) {
    var loai = jpStr_(x.loai) || JP_SO_BAN;
    if (loai !== JP_SO_BAN && loai !== JP_SO_KK_THIEU &&
        loai !== JP_SO_KK_GIAM_XUAT) return;
    var kho = jpStr_(x.khoId) || jpStr_(x.locationId);
    if (!kho) return;
    var d = jpDate_(x.ngay), q = jpNum_(x.qty), t = jpNum_(x.amount);
    var g = o(kho, jpStr_(x.itemCode));
    if (d >= dau && d <= cuoi) {
      if (loai === JP_SO_BAN) g.xuatBan += q; else g.xuatKiemKe += q;
      g.gtTongXuat += t;
    }
    if (d > cuoi) { g.xuatSau += q; g.gtXuatSau += t; }
    if (d < dau) { g.xuatTruoc += q; g.gtXuatTruoc += t; }
  });

  /* ── Chốt số: đi NGƯỢC từ tồn hiện tại về tồn cuối kỳ, rồi ra tồn đầu kỳ ── */
  var rows = Object.keys(gom).map(function (k) {
    var g = gom[k];
    g.tongXuat = g.xuatCoSo + g.xuatXeMau + g.xuatTang + g.xuatTinh +
                 g.xuatBan + g.xuatKiemKe;
    g.tonCuoi = jpNum_(hienTai[k]) - g.nhapSau + g.xuatSau;
    g.tonDau = g.tonCuoi - g.nhap - g.traVe + g.tongXuat;

    /* ── CỘT CHO FORM MISA: suy XUÔI TỪ CHỨNG TỪ ──────────────────────────────
       ⚠️ KHÁC hẳn `tonDau`/`tonCuoi` ở trên, và khác là CÓ CHỦ Ý. Hai cột kia suy
       NGƯỢC từ lớp tồn (để luôn khớp tồn thật, không cần chốt sổ) — giữ nguyên,
       nhiều chỗ đang dựa vào. Nhưng form MISA in số suy XUÔI, và chính vì vậy nó
       hiện được hai thứ mà đường ngược KHÔNG hiện:

         · `100JP025`: đầu 11 − xuất 15 = cuối **(4,00)** — XUẤT QUÁ TỒN. Đường
           ngược ra 0, vì lớp tồn không xuống dưới 0 được, nên chỗ hụt biến mất.
         · `100JP128`: đầu 1.973.827 − xuất 1.973.826 = cuối **1đ** trong khi số
           lượng đã về 0 — rác làm tròn. Đường ngược dồn 1đ đó vào ĐẦU kỳ.

       Nên đừng "dọn" hai cái này về 0: âm là dấu hiệu xuất quá tồn, 1đ là dấu
       hiệu làm tròn — cả hai đều là thứ kế toán cần thấy. */
    g.tonCuoiCT = g.tonDauCT_ = 0;   // gán lại ngay dưới, khai trước cho rõ hình dạng
    g.gtTonDau  = g.gtNhapTruoc - g.gtXuatTruoc;
    g.gtTonCuoi = g.gtTonDau + g.gtNhap + g.gtTraVe - g.gtTongXuat;

    /* Đường ĐỘC LẬP của phần tiền: giá trị lớp tồn thật, lùi về cuối kỳ. So với
       `gtTonCuoi` ở trên thì ra chỗ lệch — cùng ý nghĩa với `lechDauKy` bên lượng. */
    g.gtTonCuoiLop = jpNum_(hienTaiGT[k]) - g.gtNhapSau + g.gtXuatSau;

    /* ── ĐỐI CHIẾU TỒN ĐẦU KỲ THEO HAI ĐƯỜNG ĐỘC LẬP ──
       `tonDau` ở trên suy NGƯỢC từ lớp tồn thật (đi lùi qua các phát sinh).
       `tonDauCT` suy XUÔI từ chứng từ: cộng hết cái vào, trừ hết cái ra, từ ngày
       đầu tiên tới trước kỳ. Cả hai phải ra cùng một số.

       Vì sao phải có đường thứ hai: trước bản này hàm "tự kiểm cân bằng" bằng cách
       so `tonDau + nhap + traVe − tongXuat` với `tonCuoi`, mà `tonDau` lại được
       TÍNH RA từ chính đẳng thức đó ⇒ phép so luôn đúng, không bao giờ báo lệch
       được. Kế toán thấy chữ "cân" và tin là đã kiểm — tệ hơn là không kiểm gì.

       Lệch thì nghĩa là lớp tồn và chứng từ không khớp nhau. Hai nguyên nhân thật:
         · Có dòng xuất mà lớp không bị trừ đủ — đúng tình huống `thieuLop` ở bất
           biến #3 (xuất khi phiếu nhập về muộn). Nhập bù rồi bấm "Xuất lại".
         · Chứng từ ghi sai kho: trừ kho này mà lớp nằm ở kho khác. */
    g.tonDauCT = g.nhapTruoc - g.xuatTruoc;
    g.lechDauKy = g.tonDau - g.tonDauCT;
    /* Số lượng cuối kỳ theo CHỨNG TỪ — cột `Cuối kỳ · Số lượng` của form MISA.
       Âm nghĩa là xuất quá tồn; giữ nguyên dấu. */
    g.tonCuoiCT = g.tonDauCT + g.nhap + g.traVe - g.tongXuat;
    delete g.tonDauCT_;

    delete g.nhapSau; delete g.xuatSau;
    delete g.gtNhapSau; delete g.gtXuatSau;
    delete g.nhapTruoc; delete g.xuatTruoc;
    delete g.gtNhapTruoc; delete g.gtXuatTruoc;
    return g;
  }).filter(function (g) {
    if (khoId && g.khoId !== String(khoId)) return false;
    /* Bỏ dòng không có gì xảy ra và cũng không còn tồn — nếu không bảng dài vô ích.
       Nhưng dòng LỆCH thì luôn giữ, kể cả khi mọi số khác bằng 0: đó chính là dòng
       cần soát, ẩn nó đi là ẩn mất bằng chứng. */
    /* Giữ cả dòng chỉ còn RÁC TIỀN mà số lượng đã về 0 — file MISA in đúng dòng đó
       (`100JP128`: SL cuối trống, giá trị 1đ). Ẩn đi là bảng cộng không ra tổng. */
    return g.tonDau || g.tonCuoi || g.nhap || g.tongXuat || g.traVe || g.lechDauKy ||
           g.gtTonDau || g.gtTonCuoi || g.gtNhap || g.gtTongXuat || g.tonCuoiCT;
  }).sort(function (a, b) {
    if (a.laKhoTong !== b.laKhoTong) return a.laKhoTong ? -1 : 1;
    if (a.locationName !== b.locationName) return a.locationName < b.locationName ? -1 : 1;
    return a.itemCode < b.itemCode ? -1 : 1;
  });

  var COT_SO = ['tonDau', 'nhap', 'nhapMua', 'nhapDauKy', 'nhapKiemKe', 'nhapDC',
                'traVe', 'xuatCoSo', 'xuatXeMau', 'xuatTang',
                'xuatTinh', 'xuatBan', 'xuatKiemKe', 'tongXuat', 'tonCuoi', 'tonDauCT',
                /* Cột tiền cộng chung một cơ chế với cột lượng — nhờ vậy dòng TỔNG
                   của từng khối và tổng toàn bộ tự có luôn, không phải cộng chỗ khác. */
                'gtTonDau', 'gtNhap', 'gtTraVe', 'gtTongXuat', 'gtTonCuoi',
                'tonCuoiCT', 'gtTonCuoiLop'];

  var tong = {};
  COT_SO.forEach(function (c) { tong[c] = 0; });
  rows.forEach(function (r) {
    COT_SO.forEach(function (c) { tong[c] += jpNum_(r[c]); });
  });

  /* ── TÁCH LẺ THEO KHO (Andy chốt 04/08/2026) ──
     Mỗi kho một khối riêng, mỗi khối có dòng TỔNG của chính nó — đúng cách file thật
     `Kho Hàng JP HCM Năm 2025` trình bày (từng cơ sở một khối). Bản đầu dồn kho tổng
     và cả 13 cơ sở vào MỘT bảng, tên kho lặp lại từng dòng: không đọc được tồn của
     một cơ sở mà không tự cộng tay.

     `rows` và `tong` giữ nguyên để phần xuất CSV và các chỗ gọi cũ không đổi —
     `khoi` là cách nhìn thêm, không phải thay thế. `rows` đã sắp kho tổng lên đầu rồi
     nên chỉ cần đi tuần tự là ra đúng thứ tự khối. */
  var khoi = [], viTri = {};
  rows.forEach(function (r) {
    var k = viTri[r.khoId] === undefined ? null : khoi[viTri[r.khoId]];
    if (!k) {
      viTri[r.khoId] = khoi.length;
      k = { khoId: r.khoId, tenKho: r.locationName, laKhoTong: r.laKhoTong,
            rows: [], tong: {}, soDongLech: 0, lech: 0 };
      COT_SO.forEach(function (c) { k.tong[c] = 0; });
      khoi.push(k);
    }
    k.rows.push(r);
    COT_SO.forEach(function (c) { k.tong[c] += jpNum_(r[c]); });
    if (jpNum_(r.lechDauKy) !== 0) {
      k.soDongLech++;
      k.lech += Math.abs(jpNum_(r.lechDauKy));
    }
  });

  /* Lệch tính bằng TRỊ TUYỆT ĐỐI từng dòng, không cộng dồn số có dấu: thiếu 5 ở mã
     này mà thừa 5 ở mã kia là HAI lỗi, cộng lại thành 0 là che mất cả hai. */
  var dongLech = rows.filter(function (r) { return r.lechDauKy !== 0; });
  var lech = 0;
  dongLech.forEach(function (r) { lech += Math.abs(r.lechDauKy); });

  return {
    ok: true, thang: m, nam: y, rows: rows, tong: tong, khoi: khoi,
    canBang: lech === 0, lech: lech, soDongLech: dongLech.length,
    /* Vài dòng đầu để giao diện chỉ thẳng chỗ sai, khỏi bắt kế toán tự dò cả bảng */
    viDuLech: dongLech.slice(0, 5).map(function (r) {
      return { kho: r.locationName, itemCode: r.itemCode,
               tonDau: r.tonDau, tonDauCT: r.tonDauCT, lech: r.lechDauKy };
    })
  };
}

/*──────────────────── ⑦ NHẬT KÝ PHIẾU XUẤT ────────────────────*/

function jpKhoLichSuXuat(token, limit, loai) {
  jpNeedKT_(jpAuth_(token));
  var n = jpNum_(limit) || 50;
  var locLoai = jpStr_(loai).toUpperCase();

  var ct = {};
  jpRows_(JP_TABS.KHO_DC_CT).forEach(function (d) {
    (ct[String(d.dcId)] = ct[String(d.dcId)] || []).push({
      itemCode: jpStr_(d.itemCode), itemName: jpStr_(d.itemName),
      qty: jpNum_(d.qty), unitCost: jpNum_(d.unitCost), amount: jpNum_(d.amount),
      thieuLop: !!jpStr_(d.thieuLop)
    });
  });

  return jpRows_(JP_TABS.KHO_DC)
    .filter(function (r) { return !locLoai || jpStr_(r.loai) === locLoai; })
    .sort(function (a, b) {
      var da = jpDate_(a.ngay), db = jpDate_(b.ngay);
      if (da !== db) return da > db ? -1 : 1;
      return String(a.id) > String(b.id) ? -1 : 1;
    })
    .slice(0, n)
    .map(function (r) {
      var l = jpStr_(r.loai);
      return {
        id: String(r.id), soChungTu: jpStr_(r.soChungTu), ngay: jpDate_(r.ngay),
        loai: l, tenLoai: (JP_XUAT_LOAI[l] || {}).ten || l,
        khoTu: jpStr_(r.khoTu), khoDen: jpStr_(r.khoDen),
        locationName: jpStr_(r.locationName),
        soDong: jpNum_(r.soDong), tongSL: jpNum_(r.tongSL), tongTien: jpNum_(r.tongTien),
        ghiChu: jpStr_(r.ghiChu), createdBy: jpStr_(r.createdBy),
        daHuy: !!jpStr_(r.huyAt), huyReason: jpStr_(r.huyReason),
        dong: ct[String(r.id)] || []
      };
    });
}

/** Danh sách loại xuất cho giao diện — một nguồn duy nhất, khỏi lệch với server. */
function jpKhoDanhSachLoai(token) {
  jpNeedKT_(jpAuth_(token));
  return Object.keys(JP_XUAT_LOAI).map(function (k) {
    return {
      ma: k, ten: JP_XUAT_LOAI[k].ten, tk: JP_XUAT_LOAI[k].tk,
      canCoSo: (k === JP_XUAT_CS || k === JP_XUAT_TRA),
      sangCoSo: !!JP_XUAT_LOAI[k].sangCoSo
    };
  });
}

/*═══════════════════════════════════════════════════════════════════════════
 * ĐỔI TÀI KHOẢN CHO DÒNG KHO CŨ — chạy MỘT LẦN sau khi sửa `JP_TK_*` 06/08/2026
 *
 * Vì sao phải có hàm này: `JP_KhoXuat` **LƯU `tkNo`/`tkCo` vào sheet** lúc ghi
 * dòng. Nên đổi hằng số ở `00_Config` chỉ ăn vào dòng MỚI — dòng cũ vẫn mang
 * `632`/`1567`/`641`, và sổ nhật ký chung sẽ trộn hai bộ tài khoản cho cùng một
 * loại nghiệp vụ. (Khác hẳn bút toán bên TIỀN: cái đó SUY RA mỗi lần gọi nên
 * đổi hằng số là đổi luôn cả quá khứ — đúng chỗ "derive, đừng lưu" trả công.)
 *
 * ⚠️ Đổi theo `JP_TK_DOI_CU`, KHÔNG đổi tất tay: dòng kiểm kê đã ghi đúng
 * `6321`/`1561` từ đầu, quét mù là ghi đè lên số đúng.
 *
 * ⚠️ Mặc định là XEM TRƯỚC (`ghi = false`). Đây là hàm sửa thẳng vào sổ đã
 * chốt — bắt bấm hai lần, lần đầu chỉ đếm.
 *
 * ⚠️ Chạy lại LÀ AN TOÀN: dòng đã đổi rồi thì `JP_TK_DOI_CU` không có khoá cho
 * giá trị mới nên bỏ qua. Không cần cờ "đã chạy".
 *═══════════════════════════════════════════════════════════════════════════*/
function jpDoiTkKhoCu(token, ghi) {
  var u = jpNeedKT_(jpAuth_(token));

  var rows = jpRows_(JP_TABS.KHO_XUAT);
  var doiNo = {}, doiCo = {}, mau = [], demNo = 0, demCo = 0;

  rows.forEach(function (r) {
    var no = jpStr_(r.tkNo), co = jpStr_(r.tkCo);
    var noMoi = JP_TK_DOI_CU[no], coMoi = JP_TK_DOI_CU[co];
    if (!noMoi && !coMoi) return;
    if (noMoi) { doiNo[r._row] = noMoi; demNo++; }
    if (coMoi) { doiCo[r._row] = coMoi; demCo++; }
    if (mau.length < 20) {
      mau.push({
        id: String(r.id), soChungTu: jpStr_(r.soChungTu), ngay: jpDate_(r.ngay),
        loai: jpStr_(r.loai), itemCode: jpStr_(r.itemCode), soTien: jpNum_(r.amount),
        tkNoCu: no, tkNoMoi: noMoi || no, tkCoCu: co, tkCoMoi: coMoi || co
      });
    }
  });

  var soDong = Object.keys(doiNo).length || Object.keys(doiCo).length
             ? new Set(Object.keys(doiNo).concat(Object.keys(doiCo))).size : 0;

  if (!ghi) {
    return {
      ok: true, daGhi: false, soDong: soDong, demNo: demNo, demCo: demCo,
      mau: mau, tongDong: rows.length,
      msg: soDong
        ? 'XEM TRƯỚC — có ' + soDong + ' dòng kho còn mang tài khoản cũ (' +
          demNo + ' ô Nợ, ' + demCo + ' ô Có). CHƯA ghi gì. Bấm "Đổi thật" để ghi.'
        : 'Không có dòng nào mang tài khoản cũ — sổ kho đã dùng đúng ' +
          JP_TK_GIA_VON + ' / ' + JP_TK_KHO + '.'
    };
  }

  if (!soDong) {
    return { ok: true, daGhi: false, soDong: 0, demNo: 0, demCo: 0, mau: [],
             tongDong: rows.length, msg: 'Không có dòng nào cần đổi.' };
  }

  return jpLock_(function () {
    /* `jpGhiCot_` gom dòng LIỀN NHAU thành một lượt ghi — dòng kho hay nằm liền
       khối theo báo cáo nên chỗ này rẻ. Ghi từng ô là 2 lượt × số dòng. */
    var luot = jpGhiCot_(JP_TABS.KHO_XUAT, 'tkNo', doiNo)
             + jpGhiCot_(JP_TABS.KHO_XUAT, 'tkCo', doiCo);
    jpAudit_(u, 'KHO_DOI_TK', '', '',
             { soDong: soDong, demNo: demNo, demCo: demCo, doi: JP_TK_DOI_CU });
    return {
      ok: true, daGhi: true, soDong: soDong, demNo: demNo, demCo: demCo,
      mau: mau, tongDong: rows.length, luotGhi: luot,
      msg: 'Đã đổi ' + soDong + ' dòng kho sang tài khoản JP (' +
           demNo + ' ô Nợ, ' + demCo + ' ô Có).'
    };
  });
}

