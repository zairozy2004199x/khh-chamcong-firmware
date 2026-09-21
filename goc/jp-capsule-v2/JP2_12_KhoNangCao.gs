/*═══════════════════════════════════════════════════════════════════════════
 * JP CAPSULE v2  ·  12_KhoNangCao
 * ---------------------------------------------------------------------------
 * NĂM MÀN web kho KVC có mà JP chưa có. Andy chốt 04/08/2026: "làm cả 5 luôn".
 *
 *   ① Số dư đầu kỳ      — khai tồn + giá vốn lúc bắt đầu dùng hệ thống
 *   ② Kiểm kê kho       — đếm thực tế, hệ thống tự điều chỉnh tồn
 *   ③ Thẻ kho           — lịch sử ra vào của MỘT mã hàng ở MỘT kho
 *   ④ Bảng kê phiếu     — nhập / xuất theo khoảng ngày
 *   ⑤ Công nợ NCC       — phải trả nhà cung cấp + phiếu trả tiền
 *
 * File này KHÔNG có engine riêng. Mọi thứ đi qua đúng bộ máy của `11_Kho`:
 * `jpChiMucLop_` · `jpTruFifo_` · `jpGhiLopDaTru_` · `jpGiaGanNhat_`. Viết FIFO
 * lần thứ hai là chắc chắn hai bản sẽ lệch nhau.
 *
 * HAI QUYẾT ĐỊNH HẠCH TOÁN — đừng đổi mà không nói rõ đổi sang đâu:
 *
 *  1. **Kiểm kê — Andy chốt 04/08/2026.** Bản đầu của em dùng 1381 / 3381 và lập
 *     luận rằng mất mát không được vào giá vốn. Andy sửa lại:
 *       · **THIẾU → Nợ 6321 / Có 1561.** Mất hàng thì **có người đền**, nên đúng
 *         là vào giá vốn. KHÔNG đưa vào 1381 tài sản thiếu chờ xử lý.
 *       · **THỪA → giảm số lượng xuất ra trong kỳ** cho khớp số thực tế. Không
 *         điều chỉnh được thì mới **Nợ 1561 / Có 1388**.
 *     Cách cài: xem `jpKhoKiemKe`. Giảm xuất là ghi **dòng đảo số lượng âm**, dòng
 *     gốc để nguyên — sửa thẳng vào lịch sử là mất căn cứ đối chiếu.
 *
 *  2. **Hàng vào kho có BA đường, chỉ MUA sinh công nợ NCC** (`JP_NHAP_LOAI`).
 *     Tồn đầu kỳ và kiểm kê thừa cũng là hàng vào kho — dùng chung bảng
 *     `JP_KhoNhap` để N-X-T và thẻ kho không phải biết ba đường khác nhau —
 *     nhưng KHÔNG nợ nhà cung cấp nào. Thiếu cột `loaiNhap` là sổ công nợ NCC
 *     cộng cả tồn đầu kỳ vào tiền phải trả.
 *
 * VÀ MỘT BẤT BIẾN CỦA KIỂM KÊ:
 *  3. **Chi tiết kiểm kê giữ CẢ `tonSo` và `tonThuc`**, không chỉ giữ phần lệch.
 *     Tồn sổ sách được suy NGƯỢC từ tồn hiện tại (xem `jpKhoNhapXuatTon`), nên
 *     sáu tháng sau không ai dựng lại được tồn sổ của đúng hôm kiểm kê nữa. Không
 *     lưu lại là mất hẳn căn cứ của biên bản.
 *═══════════════════════════════════════════════════════════════════════════*/

/*──────────────────── DÙNG CHUNG ────────────────────*/

/** Tên kho để in ra: 'TONG' → KHO TỔNG, còn lại là tên cơ sở. */
function jpTenKho_(khoId) {
  var id = jpStr_(khoId);
  if (!id || id === JP_KHO_TONG) return 'KHO TỔNG';
  var l = jpFindOne_(JP_TABS.LOCATIONS, 'id', id);
  return l ? jpStr_(l.name) : ('Kho ' + id);
}

/** Kho có tồn tại không. Kho tổng luôn có; cơ sở phải có trong danh mục. */
function jpKiemKho_(khoId) {
  var id = jpStr_(khoId) || JP_KHO_TONG;
  if (id === JP_KHO_TONG) return id;
  if (!jpFindOne_(JP_TABS.LOCATIONS, 'id', id)) throw new Error('Không tìm thấy kho / cơ sở: ' + id);
  return id;
}

/** Loại nhập của một phiếu, có xử lý dòng đời đầu chưa có cột. */
function jpLoaiNhap_(h) { return jpStr_(h.loaiNhap) || JP_NHAP_MUA; }

/**
 * Ghi một phiếu NHẬP kho bất kỳ đường nào (mua / đầu kỳ / kiểm kê thừa).
 * Trả về {id, soChungTu, soDong, tongTien}. KHÔNG tự khoá — chỗ gọi đã ở trong
 * `jpLock_`, lồng khoá vào nhau là tự chờ chính mình.
 *
 * `dong` = [{itemCode, itemName, qty, unitCost, lotNo, note}] đã lọc sạch.
 */
function jpGhiPhieuNhap_(u, loaiNhap, khoId, ngay, ncc, ghiChu, dong) {
  var id = jpNextId_('NK');
  var soCT = JP_NK_PREFIX + id.replace(/^NK/, '');
  var tong = 0;
  dong.forEach(function (d) { tong += d.qty * d.unitCost; });

  jpAppend_(JP_TABS.KHO_NHAP, {
    id: id, soChungTu: soCT, ngay: ngay, loaiNhap: loaiNhap,
    khoId: khoId, locationId: (khoId === JP_KHO_TONG ? '' : khoId),
    locationName: jpTenKho_(khoId),
    nccMa: jpStr_(ncc && ncc.ma), nccTen: jpStr_(ncc && ncc.ten),
    soDong: dong.length, tongTien: tong, ghiChu: jpStr_(ghiChu),
    createdBy: u.hoTen, createdAt: new Date()
  });

  var ct = [], lop = [];
  dong.forEach(function (d, i) {
    var ctId = id + '-D' + (i + 1);
    ct.push({
      id: ctId, nhapId: id, seq: i + 1,
      itemCode: d.itemCode, itemName: d.itemName, dvt: 'Cái',
      qty: d.qty, unitCost: d.unitCost, amount: jpDong_(d.qty * d.unitCost),
      lotNo: jpStr_(d.lotNo), note: jpStr_(d.note)
    });
    lop.push({
      id: id + '-L' + (i + 1), khoId: khoId,
      locationId: (khoId === JP_KHO_TONG ? '' : khoId),
      itemCode: d.itemCode, ngay: ngay,
      qtyInit: d.qty, qtyRemaining: d.qty, unitCost: d.unitCost,
      nguon: ctId, lotNo: jpStr_(d.lotNo), createdAt: new Date()
    });
  });
  jpAppendMany_(JP_TABS.KHO_NHAP_CT, ct);
  jpAppendMany_(JP_TABS.KHO_LOP, lop);

  return { id: id, soChungTu: soCT, soDong: dong.length, tongTien: tong };
}

/*═══════════════════════ ① SỐ DƯ ĐẦU KỲ ═══════════════════════*/

/**
 * Khai tồn + giá vốn ban đầu cho một kho. Sinh lớp tồn `DAU_KY` để FIFO tiêu thụ
 * trước — nên NGÀY phải sớm hơn mọi chứng từ khác, giao diện mặc định 01/01.
 *
 * p = {khoId, ngay, ghiChu, rows:[{itemCode, qty, unitCost}]}
 *
 * KHÔNG chặn khai hai lần: cơ sở mới mở giữa năm cũng cần khai, và chặn cứng thì
 * gõ sai một dòng là tắc. Nhưng ĐẾM và trả về `daCoTruoc` để giao diện cảnh báo.
 */
function jpKhoSoDuDauKy(token, p) {
  var u = jpNeedKT_(jpAuth_(token));
  p = p || {};
  var khoId = jpKiemKho_(p.khoId);
  var ngay = jpDate_(p.ngay) || jpToday_();

  var itemMap = jpItemMap_();
  var dong = [];
  (p.rows || []).forEach(function (r) {
    var ma = jpStr_(r.itemCode), qty = jpNum_(r.qty), gia = jpNum_(r.unitCost);
    if (!ma || qty <= 0) return;
    if (gia < 0) throw new Error('Đơn giá vốn của ' + ma + ' không hợp lệ');
    dong.push({
      itemCode: ma,
      itemName: jpStr_(r.itemName) || jpStr_((itemMap[ma] || {}).name),
      qty: qty, unitCost: gia, lotNo: '', note: 'Tồn đầu kỳ'
    });
  });
  if (!dong.length) throw new Error('Chưa có dòng nào có mã hàng và số lượng');

  return jpLock_(function () {
    var daCo = jpRows_(JP_TABS.KHO_NHAP).filter(function (h) {
      return jpLoaiNhap_(h) === JP_NHAP_DAU_KY &&
             (jpStr_(h.khoId) || JP_KHO_TONG) === khoId && !jpStr_(h.huyAt);
    }).length;

    var kq = jpGhiPhieuNhap_(u, JP_NHAP_DAU_KY, khoId, ngay, null,
                             jpStr_(p.ghiChu) || 'Khai tồn đầu kỳ', dong);

    jpAudit_(u, 'KHO_DAU_KY', '', kq.id,
             { kho: khoId, soDong: kq.soDong, tongTien: kq.tongTien, daCoTruoc: daCo });

    return {
      ok: true, id: kq.id, soChungTu: kq.soChungTu,
      soDong: kq.soDong, tongTien: kq.tongTien, daCoTruoc: daCo,
      msg: 'Đã khai tồn đầu kỳ ' + kq.soDong + ' mã hàng cho ' + jpTenKho_(khoId) +
           ' · giá trị ' + jpMoney_(kq.tongTien) + 'đ' +
           (daCo ? ' · ⚠ kho này đã có ' + daCo + ' phiếu tồn đầu kỳ trước đó' : '')
    };
  });
}

/** Danh sách phiếu tồn đầu kỳ đã khai — để kế toán thấy mình đã làm gì. */
function jpKhoLichSuDauKy(token) {
  jpNeedKT_(jpAuth_(token));
  return jpRows_(JP_TABS.KHO_NHAP)
    .filter(function (h) { return jpLoaiNhap_(h) === JP_NHAP_DAU_KY; })
    .sort(function (a, b) { return String(a.id) > String(b.id) ? -1 : 1; })
    .map(function (h) {
      return {
        id: String(h.id), soChungTu: jpStr_(h.soChungTu), ngay: jpDate_(h.ngay),
        khoId: jpStr_(h.khoId) || JP_KHO_TONG, locationName: jpStr_(h.locationName),
        soDong: jpNum_(h.soDong), tongTien: jpNum_(h.tongTien),
        ghiChu: jpStr_(h.ghiChu), createdBy: jpStr_(h.createdBy),
        daHuy: !!jpStr_(h.huyAt)
      };
    });
}

/*═══════════════════════ ② KIỂM KÊ KHO ═══════════════════════*/

/**
 * Tồn SỔ SÁCH của một kho để in phiếu kiểm kê. Trả cả mã chưa có tồn (qty 0) nếu
 * `themHetDanhMuc` — kiểm kê mà chỉ hiện mã đang có tồn thì không bao giờ phát
 * hiện được hàng thừa của mã đã hết sổ sách.
 */
function jpKhoKiemKeTon(token, khoId, themHetDanhMuc) {
  jpNeedKT_(jpAuth_(token));
  var kho = jpKiemKho_(khoId);
  var ton = jpTonKho_(kho);
  var itemMap = jpItemMap_();

  var co = {};
  var rows = ton.rows.map(function (r) {
    co[r.itemCode] = 1;
    return {
      itemCode: r.itemCode, itemName: r.itemName,
      misa: jpStr_((itemMap[r.itemCode] || {}).misa),
      tonSo: r.tonQty, giaBinhQuan: r.giaBinhQuan
    };
  });

  if (themHetDanhMuc) {
    jpRows_(JP_TABS.ITEMS).forEach(function (i) {
      var ma = jpStr_(i.code);
      if (!ma || co[ma] || i.active === false) return;
      rows.push({ itemCode: ma, itemName: jpStr_(i.name), misa: jpStr_(i.misa),
                  tonSo: 0, giaBinhQuan: 0 });
    });
    rows.sort(function (a, b) { return a.itemCode < b.itemCode ? -1 : 1; });
  }

  return { ok: true, khoId: kho, tenKho: jpTenKho_(kho), rows: rows };
}

/**
 * Trả số lượng về đúng lớp cũ, KẸP THEO `qtyInit` — một lớp không bao giờ còn
 * nhiều hơn lúc nhập. Cùng bất biến #5 của `jpHoanKhoBaoCao_`.
 * Trả về số thực sự trả được (có thể ít hơn nếu bị kẹp).
 */
function jpTraLaiLop_(lopById, layerId, qty) {
  var l = lopById[String(layerId)];
  if (!l || qty <= 0) return 0;
  var dangCo = (l.__con === undefined) ? jpNum_(l.qtyRemaining) : jpNum_(l.__con);
  var tran = jpNum_(l.qtyInit);
  var moi = Math.min(dangCo + qty, tran);
  l.__con = moi;
  l.__daSua = true;
  return moi - dangCo;
}

/**
 * Lưu một lần kiểm kê. p = {khoId, ngay, ghiChu, cheDoThua, rows:[{itemCode, tonThuc}]}
 *
 * Dòng KHÔNG gõ `tonThuc` (để trống) là **chưa đếm** — bỏ qua hoàn toàn, không
 * coi là đếm được 0. Đếm được 0 và không đếm là hai chuyện khác nhau: coi lẫn là
 * xoá sạch tồn của mọi mã kế toán chưa kịp đếm.
 *
 * HẠCH TOÁN (Andy chốt 04/08/2026 — xem `JP2_00_Config`):
 *
 *   THIẾU → trừ lớp FIFO, ghi dòng sổ **Nợ 6321 / Có 1561**.
 *           Mất hàng thì có người đền, nên vào giá vốn, KHÔNG vào 1381.
 *
 *   THỪA  → ưu tiên **GIẢM SỐ XUẤT TRONG KỲ** cho khớp thực tế: đi ngược các
 *           dòng xuất của đúng kho + mã trong kỳ, MỚI TRƯỚC, ghi **dòng đảo số
 *           lượng ÂM** (`KK_GIAM_XUAT`) và trả số về lớp cũ. Dòng gốc còn nguyên
 *           nên vẫn lần được lịch sử; tổng xuất tự giảm.
 *           Giảm hết mà vẫn còn thừa → phần còn lại ghi tăng **Nợ 1561 / Có 1388**.
 *
 * ⚠️ **Kho tổng hầu như không giảm xuất được.** Hàng ra khỏi kho tổng là phiếu
 * ĐIỀU CHUYỂN xuống cơ sở, không sinh dòng trong `JP_KhoXuat` — mà giảm phiếu đó
 * là phải sửa cả lớp đã sinh ở cơ sở, có thể đã bán rồi. Nên với kho tổng phần
 * thừa thường rơi xuống nhánh 1561/1388. Hàm NÓI RÕ chuyện này trong `msg` và
 * `khongGiamDuoc`, không im lặng.
 */
function jpKhoKiemKe(token, p) {
  var u = jpNeedKT_(jpAuth_(token));
  p = p || {};
  var khoId = jpKiemKho_(p.khoId);
  var ngay = jpDate_(p.ngay) || jpToday_();
  var cheDo = jpStr_(p.cheDoThua).toUpperCase() === JP_KK_GHI_TANG
            ? JP_KK_GHI_TANG : JP_KK_GIAM_XUAT;

  /* Kỳ để giảm xuất = tháng chứa ngày kiểm kê, tính tới ngày kiểm kê.
     Giảm xuất của tháng sau ngày kiểm kê là vô nghĩa: hàng đó chưa xuất lúc đếm. */
  var kyDau = jpDate_(ngay).slice(0, 8) + '01';

  /* Tồn sổ sách tại kho, đọc MỘT lần */
  var tonSo = {};
  jpRows_(JP_TABS.KHO_LOP).forEach(function (l) {
    var kho = jpStr_(l.khoId) || jpStr_(l.locationId) || JP_KHO_TONG;
    if (kho !== khoId) return;
    var ma = jpStr_(l.itemCode);
    if (!ma) return;
    tonSo[ma] = (tonSo[ma] || 0) + jpNum_(l.qtyRemaining);
  });

  var itemMap = jpItemMap_();
  var dem = [];
  (p.rows || []).forEach(function (r) {
    var ma = jpStr_(r.itemCode);
    if (!ma) return;
    if (jpBlank_(r.tonThuc)) return;                  // chưa đếm ≠ đếm được 0
    var thuc = jpNum_(r.tonThuc);
    if (thuc < 0) throw new Error('Số thực đếm của ' + ma + ' không hợp lệ');
    dem.push({ itemCode: ma, tonThuc: thuc, note: jpStr_(r.note) });
  });
  if (!dem.length) throw new Error('Chưa gõ số thực đếm cho mã nào');

  return jpLock_(function () {
    var id = jpNextId_('KK');
    var soCT = JP_KK_PREFIX + id.replace(/^KK/, '');
    var chiMuc = jpChiMucLop_();

    /* Lớp tra theo id — dùng để TRẢ LẠI khi giảm xuất. Cùng object với chỉ mục
       FIFO thì mới không ghi hai lần lên cùng một dòng. */
    var lopById = {};
    Object.keys(chiMuc.theoMa).forEach(function (m) {
      chiMuc.theoMa[m].forEach(function (l) { lopById[String(l.id)] = l; });
    });

    /* Dòng xuất trong kỳ của đúng kho, gom theo mã, MỚI TRƯỚC.
       Kèm `conGiamDuoc[ma]` = tổng xuất trong kỳ **trừ đi phần đã đảo ở những lần
       kiểm kê TRƯỚC**. Không có nó thì lần kiểm kê sau lại giảm tiếp đúng dòng gốc
       cũ (bộ lọc chỉ bỏ dòng âm, còn dòng gốc dương vẫn nguyên) ⇒ **giảm hai lần**
       ⇒ giá vốn thiếu. Không thể un-xuất nhiều hơn số đã xuất. */
    var xuatTrongKy = {}, conGiamDuoc = {};
    jpRows_(JP_TABS.KHO_XUAT).forEach(function (x) {
      var kho = jpStr_(x.khoId) || jpStr_(x.locationId);
      if (kho !== khoId) return;
      var d = jpDate_(x.ngay);
      if (d < kyDau || d > ngay) return;
      var ma = jpStr_(x.itemCode), q = jpNum_(x.qty);
      if (q > 0) {
        (xuatTrongKy[ma] = xuatTrongKy[ma] || []).push(x);
        conGiamDuoc[ma] = jpNum_(conGiamDuoc[ma]) + q;
      } else if (jpStr_(x.loai) === JP_SO_KK_GIAM_XUAT) {
        conGiamDuoc[ma] = jpNum_(conGiamDuoc[ma]) + q;   // q âm ⇒ trừ đi
      }
    });
    Object.keys(xuatTrongKy).forEach(function (ma) {
      xuatTrongKy[ma].sort(function (a, b) {
        var da = jpDate_(a.ngay), db = jpDate_(b.ngay);
        if (da !== db) return da > db ? -1 : 1;        // mới trước
        return String(a.id) > String(b.id) ? -1 : 1;
      });
    });
    /* Đã giảm bao nhiêu ở lần kiểm kê NÀY, theo id dòng gốc */
    var daGiam = {};

    var ct = [], thuaDong = [], soGhi = [], seq = 0;
    var slThua = 0, slThieu = 0, tienThua = 0, tienThieu = 0;
    var slGiamXuat = 0, tienGiamXuat = 0;
    var khongGiamDuoc = [];

    var dongSo = function (loai, tkNo, tkCo, ma, ten, qty, gia, layerId, thieuLop) {
      soGhi.push({
        id: id + '-S' + seq, soChungTu: soCT, ngay: ngay,
        loai: loai, reportId: '', dcId: id,
        khoId: khoId, locationId: (khoId === JP_KHO_TONG ? '' : khoId),
        locationName: jpTenKho_(khoId), maKH: '', unitCode: '',
        itemCode: ma, itemName: ten,
        /* ⚠️ Tiền trên DÒNG SỔ phải là số NGUYÊN ĐỒNG — `jpDong_`. Đơn giá trên lớp
           tồn để lẻ là cố ý (tồn đầu kỳ suy đơn giá bằng `giaTri / soLuong`), nhưng
           `qty × đơn giá lẻ` chảy thẳng vào sổ nhật ký chung và bảng cân đối thì
           MISA làm việc bằng đồng chẵn. Đã lọt: kiểm kê ghi 9.252,239215686275đ. */
        qty: qty, unitCost: gia, amount: jpDong_(qty * gia),
        tkNo: tkNo, tkCo: tkCo,
        layerId: jpStr_(layerId), thieuLop: jpStr_(thieuLop),
        createdBy: u.hoTen, createdAt: new Date()
      });
    };

    dem.sort(function (a, b) { return a.itemCode < b.itemCode ? -1 : 1; })
       .forEach(function (d) {
      var so = jpNum_(tonSo[d.itemCode]);
      var lech = d.tonThuc - so;
      var ten = jpStr_((itemMap[d.itemCode] || {}).name);

      if (lech === 0) {
        seq++;
        ct.push({ id: id + '-D' + seq, kkId: id, seq: seq,
                  itemCode: d.itemCode, itemName: ten,
                  tonSo: so, tonThuc: d.tonThuc, lech: 0,
                  unitCost: 0, amount: 0, cheDo: '', ctGoc: '',
                  tkNo: '', tkCo: '', note: d.note });
        return;
      }

      /*──── THIẾU: Nợ 6321 / Có 1561 ────*/
      if (lech < 0) {
        jpTruFifo_(chiMuc, khoId, d.itemCode, -lech).forEach(function (x) {
          seq++;
          var tien = jpDong_(x.qty * x.unitCost);
          slThieu += x.qty; tienThieu += tien;
          ct.push({ id: id + '-D' + seq, kkId: id, seq: seq,
                    itemCode: d.itemCode, itemName: ten,
                    tonSo: so, tonThuc: d.tonThuc, lech: -x.qty,
                    unitCost: x.unitCost, amount: tien,
                    cheDo: '', ctGoc: '',
                    tkNo: JP_TK_GIA_VON_MAT, tkCo: JP_TK_KHO_KK, note: d.note });
          dongSo(JP_SO_KK_THIEU, JP_TK_GIA_VON_MAT, JP_TK_KHO_KK,
                 d.itemCode, ten, x.qty, x.unitCost, x.layerId, x.thieuLop);
        });
        return;
      }

      /*──── THỪA ────*/
      var conThua = lech;

      /* ① Ưu tiên GIẢM SỐ XUẤT TRONG KỲ — đi ngược dòng xuất, mới trước */
      if (cheDo === JP_KK_GIAM_XUAT) {
        var ds = xuatTrongKy[d.itemCode] || [];
        for (var i = 0; i < ds.length && conThua > 0; i++) {
          if (jpNum_(conGiamDuoc[d.itemCode]) <= 0) break;   // đã đảo hết số đã xuất
          var g = ds[i];
          var conGiam = jpNum_(g.qty) - jpNum_(daGiam[String(g.id)]);
          if (conGiam <= 0) continue;
          var lay = Math.min(conGiam, conThua, jpNum_(conGiamDuoc[d.itemCode]));

          /* Trả hàng về lớp TRƯỚC, rồi mới ghi giảm ĐÚNG số trả lại được.
             `jpTraLaiLop_` trả về 0 khi dòng xuất không có `layerId` (dòng
             `thieuLop` — lúc xuất không có lớp nào để trừ) và trả về ít hơn khi lớp
             đã đầy tới `qtyInit`. Ghi giảm mà hàng không về lớp là **tiền giảm mà
             tồn không tăng** — hàng thừa vẫn còn thừa, chỉ mất giá vốn. Không trả
             được thì để rơi xuống nhánh ② ghi tăng 1561/1388. */
          var traDuoc = jpTraLaiLop_(lopById, g.layerId, lay);
          if (traDuoc <= 0) continue;
          lay = traDuoc;

          daGiam[String(g.id)] = jpNum_(daGiam[String(g.id)]) + lay;
          conGiamDuoc[d.itemCode] = jpNum_(conGiamDuoc[d.itemCode]) - lay;
          conThua -= lay;

          var gia = jpNum_(g.unitCost);
          seq++;
          slGiamXuat += lay; tienGiamXuat += jpDong_(lay * gia);
          ct.push({ id: id + '-D' + seq, kkId: id, seq: seq,
                    itemCode: d.itemCode, itemName: ten,
                    tonSo: so, tonThuc: d.tonThuc, lech: lay,
                    unitCost: gia, amount: jpDong_(lay * gia),
                    cheDo: JP_KK_GIAM_XUAT, ctGoc: jpStr_(g.soChungTu),
                    /* Đảo đúng cặp tài khoản của dòng gốc — giảm xuất là ghi
                       ngược lại chính bút toán đã ghi, không phải bút toán mới */
                    tkNo: jpStr_(g.tkNo) || JP_TK_GIA_VON,
                    tkCo: jpStr_(g.tkCo) || JP_TK_KHO, note: d.note });
          /* Dòng đảo: số lượng ÂM ⇒ tổng xuất và sổ giá vốn tự giảm.
             Hàng đã trả về lớp ở trên rồi — đừng gọi `jpTraLaiLop_` lần nữa ở đây. */
          dongSo(JP_SO_KK_GIAM_XUAT, jpStr_(g.tkNo) || JP_TK_GIA_VON,
                 jpStr_(g.tkCo) || JP_TK_KHO,
                 d.itemCode, ten, -lay, gia, g.layerId, '');
        }
      }

      /* ② Không giảm được hết → Nợ 1561 / Có 1388, sinh lớp mới */
      if (conThua > 0) {
        var giaMoi = jpGiaGanNhat_(chiMuc, khoId, d.itemCode);
        seq++;
        slThua += conThua; tienThua += jpDong_(conThua * giaMoi);
        ct.push({ id: id + '-D' + seq, kkId: id, seq: seq,
                  itemCode: d.itemCode, itemName: ten,
                  tonSo: so, tonThuc: d.tonThuc, lech: conThua,
                  unitCost: giaMoi, amount: jpDong_(conThua * giaMoi),
                  cheDo: JP_KK_GHI_TANG, ctGoc: '',
                  tkNo: JP_TK_KHO_KK, tkCo: JP_TK_PHAI_THU_KHAC, note: d.note });
        thuaDong.push({ itemCode: d.itemCode, itemName: ten, qty: conThua,
                        unitCost: giaMoi, lotNo: '', note: 'Kiểm kê thừa ' + soCT });
        if (cheDo === JP_KK_GIAM_XUAT) {
          khongGiamDuoc.push(d.itemCode + ' × ' + conThua);
        }
      }
    });

    /* Phần thừa ghi tăng đi qua ĐÚNG đường nhập kho — để N-X-T và thẻ kho không
       phải biết thêm một nguồn hàng nào nữa. */
    var nhap = thuaDong.length
      ? jpGhiPhieuNhap_(u, JP_NHAP_KIEM_KE, khoId, ngay, null,
                        'Kiểm kê thừa ' + soCT, thuaDong)
      : null;

    jpAppend_(JP_TABS.KHO_KK, {
      id: id, soChungTu: soCT, ngay: ngay, khoId: khoId,
      locationName: jpTenKho_(khoId), cheDoThua: cheDo,
      soDong: seq, slThua: slThua, slThieu: slThieu,
      tienThua: tienThua, tienThieu: tienThieu,
      slGiamXuat: slGiamXuat, tienGiamXuat: tienGiamXuat,
      nhapId: nhap ? nhap.id : '',
      ghiChu: jpStr_(p.ghiChu), createdBy: u.hoTen, createdAt: new Date()
    });
    jpAppendMany_(JP_TABS.KHO_KK_CT, ct);
    if (soGhi.length) jpAppendMany_(JP_TABS.KHO_XUAT, soGhi);

    /* Ghi lớp: `jpGhiLopDaTru_` chỉ ghi lớp bị TRỪ. Lớp được TRẢ LẠI khi giảm
       xuất phải ghi riêng — cùng một map object nên không đụng nhau hai lần. */
    jpGhiLopDaTru_(chiMuc);
    var capNhat = {};
    Object.keys(lopById).forEach(function (lid) {
      var l = lopById[lid];
      if (!l.__daSua || capNhat[l._row] !== undefined) return;
      capNhat[l._row] = jpNum_(l.__con);
    });
    jpGhiCot_(JP_TABS.KHO_LOP, 'qtyRemaining', capNhat);

    jpAudit_(u, 'KHO_KIEM_KE', '', id,
             { kho: khoId, cheDo: cheDo, soDong: seq,
               slThieu: slThieu, tienThieu: tienThieu,
               slGiamXuat: slGiamXuat, tienGiamXuat: tienGiamXuat,
               slThua: slThua, tienThua: tienThua,
               nhapThua: nhap ? nhap.id : '', khongGiamDuoc: khongGiamDuoc.length });

    var m = 'Đã lưu kiểm kê ' + soCT + ' · ' + jpTenKho_(khoId);
    if (slThieu) {
      m += ' · thiếu ' + slThieu + ' cái (' + jpMoney_(tienThieu) + 'đ, Nợ ' +
           JP_TK_GIA_VON_MAT + ' / Có ' + JP_TK_KHO_KK + ')';
    }
    if (slGiamXuat) {
      m += ' · giảm xuất trong kỳ ' + slGiamXuat + ' cái (' +
           jpMoney_(tienGiamXuat) + 'đ)';
    }
    if (slThua) {
      m += ' · thừa ghi tăng ' + slThua + ' cái (' + jpMoney_(tienThua) + 'đ, Nợ ' +
           JP_TK_KHO_KK + ' / Có ' + JP_TK_PHAI_THU_KHAC + ')';
    }
    if (khongGiamDuoc.length) {
      m += ' · ⚠ ' + khongGiamDuoc.length + ' mã không đủ số xuất trong kỳ để giảm' +
           (khoId === JP_KHO_TONG
             ? ' (kho tổng: hàng ra là phiếu điều chuyển, không giảm được ở đây)'
             : '') + ' — đã ghi tăng theo 1561/1388';
    }
    if (!slThieu && !slGiamXuat && !slThua) m += ' · tồn sổ khớp thực tế, không lệch';

    return {
      ok: true, id: id, soChungTu: soCT, soDong: seq, cheDoThua: cheDo,
      slThieu: slThieu, tienThieu: tienThieu,
      slGiamXuat: slGiamXuat, tienGiamXuat: tienGiamXuat,
      slThua: slThua, tienThua: tienThua,
      khongGiamDuoc: khongGiamDuoc, msg: m
    };
  });
}

/** Lịch sử kiểm kê, kèm chi tiết từng mã. */
function jpKhoLichSuKiemKe(token, limit) {
  jpNeedKT_(jpAuth_(token));
  var n = jpNum_(limit) || 30;

  var ct = {};
  jpRows_(JP_TABS.KHO_KK_CT).forEach(function (d) {
    (ct[String(d.kkId)] = ct[String(d.kkId)] || []).push({
      itemCode: jpStr_(d.itemCode), itemName: jpStr_(d.itemName),
      tonSo: jpNum_(d.tonSo), tonThuc: jpNum_(d.tonThuc), lech: jpNum_(d.lech),
      unitCost: jpNum_(d.unitCost), amount: jpNum_(d.amount),
      cheDo: jpStr_(d.cheDo), ctGoc: jpStr_(d.ctGoc),
      tkNo: jpStr_(d.tkNo), tkCo: jpStr_(d.tkCo), note: jpStr_(d.note)
    });
  });

  return jpRows_(JP_TABS.KHO_KK)
    .sort(function (a, b) {
      var da = jpDate_(a.ngay), db = jpDate_(b.ngay);
      if (da !== db) return da > db ? -1 : 1;
      return String(a.id) > String(b.id) ? -1 : 1;
    })
    .slice(0, n)
    .map(function (h) {
      return {
        id: String(h.id), soChungTu: jpStr_(h.soChungTu), ngay: jpDate_(h.ngay),
        khoId: jpStr_(h.khoId), locationName: jpStr_(h.locationName),
        soDong: jpNum_(h.soDong),
        cheDoThua: jpStr_(h.cheDoThua) || JP_KK_GHI_TANG,
        slThua: jpNum_(h.slThua), slThieu: jpNum_(h.slThieu),
        tienThua: jpNum_(h.tienThua), tienThieu: jpNum_(h.tienThieu),
        slGiamXuat: jpNum_(h.slGiamXuat), tienGiamXuat: jpNum_(h.tienGiamXuat),
        ghiChu: jpStr_(h.ghiChu), createdBy: jpStr_(h.createdBy),
        dong: ct[String(h.id)] || []
      };
    });
}

/*═══════════════════════ ③ THẺ KHO ═══════════════════════*/

/**
 * Thẻ kho của MỘT mã hàng ở MỘT kho: từng chứng từ nhập / xuất theo thứ tự thời
 * gian, kèm số dư luỹ kế.
 *
 * TỒN ĐẦU KỲ SUY RA NGƯỢC, cùng cách với `jpKhoNhapXuatTon` và sổ công nợ:
 *   tồn đầu = tồn HIỆN TẠI − (nhập từ `tuNgay` trở đi) + (xuất từ `tuNgay` trở đi)
 * Nhờ vậy dòng cuối của thẻ kho luôn khớp tồn thật, không cần chốt sổ.
 *
 * p = {khoId, itemCode, tuNgay, denNgay}
 */
/**
 * CHỈ MỤC CHUYỂN ĐỘNG THEO MÃ HÀNG cho MỘT kho — đọc mỗi tab **một lần**.
 *
 * Andy 11/08/2026 (kế toán yêu cầu): *"chị ấy muốn xem nhiều mã một lượt"*. Bản trước
 * `jpKhoTheKho` bắt buộc đúng một mã và **quét cả bảng cho mã đó**; xem 68 mã của một
 * kho là quét lại 68 lần. Tách chỉ mục ra thì xem cả kho tốn **đúng số lượt Sheets như
 * xem một mã** — `jpRows_` cache trong một lần chạy, cái đắt là lượt gọi Sheets chứ
 * không phải vòng lặp JS.
 *
 * ⚠️ Bốn nguồn chuyển động, giữ y hệt bản một mã — đừng thêm/bớt nguồn ở đây mà không
 * sửa `jpKhoNhapXuatTon`: hai hàm phải nhìn cùng một tập chứng từ, lệch nhau là thẻ kho
 * và bảng N-X-T nói hai chuyện khác nhau về cùng một mã.
 */
function jpTheKhoChiMuc_(khoId) {
  var mc = {};
  var them = function (ma, ngay, soCT, dienGiai, nhap, xuat, gia, ghiChu) {
    if (!ma) return;
    (mc[ma] = mc[ma] || []).push({ ngay: ngay, soChungTu: soCT, dienGiai: dienGiai,
      nhap: nhap, xuat: xuat, unitCost: gia, ghiChu: ghiChu || '' });
  };

  /* ── Phiếu nhập (mua / đầu kỳ / kiểm kê thừa) ── */
  var nhapCT = {};
  jpRows_(JP_TABS.KHO_NHAP_CT).forEach(function (d) {
    (nhapCT[String(d.nhapId)] = nhapCT[String(d.nhapId)] || []).push(d);
  });
  jpRows_(JP_TABS.KHO_NHAP).forEach(function (h) {
    if (jpStr_(h.huyAt)) return;
    if ((jpStr_(h.khoId) || JP_KHO_TONG) !== khoId) return;
    var ds = nhapCT[String(h.id)];
    if (!ds) return;
    var ten = (JP_NHAP_LOAI[jpLoaiNhap_(h)] || {}).ten || 'Nhập kho';
    ds.forEach(function (d) {
      them(jpStr_(d.itemCode), jpDate_(h.ngay), jpStr_(h.soChungTu), ten,
           jpNum_(d.qty), 0, jpNum_(d.unitCost),
           jpStr_(h.nccTen) || jpStr_(h.ghiChu));
    });
  });

  /* ── Phiếu xuất / điều chuyển: kho này có thể là bên TRỪ hoặc bên NHẬN ── */
  var dcCT = {};
  jpRows_(JP_TABS.KHO_DC_CT).forEach(function (d) {
    (dcCT[String(d.dcId)] = dcCT[String(d.dcId)] || []).push(d);
  });
  jpRows_(JP_TABS.KHO_DC).forEach(function (h) {
    if (jpStr_(h.huyAt)) return;
    var ds = dcCT[String(h.id)];
    if (!ds) return;
    var laTru = jpStr_(h.khoTu) === khoId, laNhan = jpStr_(h.khoDen) === khoId;
    if (!laTru && !laNhan) return;
    var ten = (JP_XUAT_LOAI[jpStr_(h.loai)] || {}).ten || jpStr_(h.loai);
    ds.forEach(function (d) {
      them(jpStr_(d.itemCode), jpDate_(h.ngay), jpStr_(h.soChungTu),
           ten + (laNhan ? ' (nhận)' : ''),
           laNhan ? jpNum_(d.qty) : 0, laTru ? jpNum_(d.qty) : 0,
           jpNum_(d.unitCost), jpStr_(h.locationName));
    });
  });

  /* ── Dòng sổ: bán ở cơ sở (632), kiểm kê thiếu (6321), điều chỉnh giảm xuất.
       641 đã đếm ở phiếu xuất nên KHÔNG đếm lại ở đây.
       Dòng giảm xuất mang số lượng ÂM — để đúng ở cột Xuất thì số dư luỹ kế
       (`du + nhap − xuat`) tự tăng trở lại, khỏi phải xử lý riêng. ── */
  var tenLoaiSo = {};
  tenLoaiSo[JP_SO_BAN] = 'Bán hàng (632)';
  tenLoaiSo[JP_SO_KK_THIEU] = 'Kiểm kê thiếu (Nợ 6321)';
  tenLoaiSo[JP_SO_KK_GIAM_XUAT] = 'Điều chỉnh giảm xuất (kiểm kê thừa)';
  jpRows_(JP_TABS.KHO_XUAT).forEach(function (x) {
    var kho = jpStr_(x.khoId) || jpStr_(x.locationId);
    if (kho !== khoId) return;
    var loai = jpStr_(x.loai) || JP_SO_BAN;
    if (!tenLoaiSo[loai]) return;
    them(jpStr_(x.itemCode), jpDate_(x.ngay), jpStr_(x.soChungTu), tenLoaiSo[loai],
         0, jpNum_(x.qty), jpNum_(x.unitCost), jpStr_(x.locationName));
  });

  Object.keys(mc).forEach(function (ma) {
    mc[ma].sort(function (a, b) {
      if (a.ngay !== b.ngay) return a.ngay < b.ngay ? -1 : 1;
      return a.soChungTu < b.soChungTu ? -1 : 1;
    });
  });
  return mc;
}

/** Một khối thẻ kho cho đúng một mã. `mc` là mảng chuyển động ĐÃ SẮP của mã đó. */
function jpTheKhoKhoi_(ma, mc, tu, den, tonHienTai, itemMap) {
  mc = mc || [];

  /* Tồn đầu kỳ = tồn hiện tại − mọi chuyển động TỪ `tu` trở đi */
  var tonDau = tonHienTai;
  mc.forEach(function (m) { if (m.ngay >= tu) tonDau = tonDau - m.nhap + m.xuat; });

  /* Và tồn đầu kỳ suy XUÔI từ chứng từ: cộng hết chuyển động TRƯỚC `tu`, bắt đầu từ 0.
     Hai đường độc lập nên so được. Đừng thay bằng phép so `tonDau + nhập − xuất` với
     số dư cuối: `du` ở dưới xuất phát TỪ `tonDau` rồi cộng đúng `nhap`/`xuat` đó, nên
     đẳng thức ấy luôn đúng — nó không kiểm gì cả. */
  var tonDauCT = 0;
  mc.forEach(function (m) { if (m.ngay < tu) tonDauCT += m.nhap - m.xuat; });

  var du = tonDau, rows = [], tongNhap = 0, tongXuat = 0;
  mc.forEach(function (m) {
    if (m.ngay < tu || m.ngay > den) return;
    du = du + m.nhap - m.xuat;
    tongNhap += m.nhap; tongXuat += m.xuat;
    rows.push({ ngay: m.ngay, soChungTu: m.soChungTu, dienGiai: m.dienGiai,
                nhap: m.nhap, xuat: m.xuat, unitCost: m.unitCost,
                conLai: du, ghiChu: m.ghiChu });
  });

  return {
    itemCode: ma, itemName: jpStr_((itemMap[ma] || {}).name),
    misa: jpStr_((itemMap[ma] || {}).misa), dvt: jpDvt_(itemMap[ma]),
    tonDau: tonDau, tongNhap: tongNhap, tongXuat: tongXuat, tonCuoi: du,
    tonHienTai: tonHienTai, rows: rows,
    /* Tồn đầu kỳ theo lớp tồn thật phải KHỚP tồn đầu kỳ theo chứng từ. Lệch nghĩa là
       có dòng xuất mà lớp chưa bị trừ đủ (phiếu nhập về muộn ⇒ nhập bù rồi "Xuất
       lại"), hoặc chứng từ ghi sai kho. */
    tonDauCT: tonDauCT, lech: tonDau - tonDauCT,
    canBang: tonDau === tonDauCT
  };
}

/**
 * THẺ KHO. `p.itemCode` có thì một mã; **để TRỐNG là MỌI MÃ của kho đó**
 * (kế toán yêu cầu 11/08/2026 — xem `jpTheKhoChiMuc_`).
 *
 * ⚠️ Xem một mã thì `rows` · `tonDau` · `tongNhap` … **vẫn nằm ở cấp cao nhất y như
 * trước**, `khoi` là cách nhìn THÊM chứ không thay thế — cùng khuôn với
 * `jpKhoNhapXuatTon`. Đổi hình dạng cũ là hỏng cả màn hiện tại và các test đang ghim.
 *
 * ⚠️ Bỏ mã **không có gì xảy ra VÀ không còn tồn** (cùng luật với N-X-T) — nhưng mã có
 * tồn đầu mà không phát sinh gì thì **GIỮ**, đó đúng là dòng kế toán cần thấy khi đối
 * chiếu: nó chứng minh tồn đứng im, chứ không phải bị bỏ sót.
 */
function jpKhoTheKho(token, p) {
  jpNeedKT_(jpAuth_(token));
  p = p || {};
  var khoId = jpKiemKho_(p.khoId);
  var ma = jpStr_(p.itemCode);

  var tu = jpDate_(p.tuNgay) || '2000-01-01';
  var den = jpDate_(p.denNgay) || '2999-12-31';
  if (tu > den) throw new Error('Từ ngày phải trước đến ngày');

  /* Tồn hiện tại theo MÃ trong đúng kho này */
  var tonKho = {};
  jpRows_(JP_TABS.KHO_LOP).forEach(function (l) {
    var kho = jpStr_(l.khoId) || jpStr_(l.locationId) || JP_KHO_TONG;
    if (kho !== khoId) return;
    var m = jpStr_(l.itemCode);
    tonKho[m] = jpNum_(tonKho[m]) + jpNum_(l.qtyRemaining);
  });

  var mc = jpTheKhoChiMuc_(khoId);
  var itemMap = jpItemMap_();

  var dsMa;
  if (ma) dsMa = [ma];
  else {
    var co = {};
    Object.keys(mc).forEach(function (k) { co[k] = 1; });
    Object.keys(tonKho).forEach(function (k) { co[k] = 1; });
    dsMa = Object.keys(co).sort();
  }

  var khoi = [], boQua = 0;
  dsMa.forEach(function (k) {
    var b = jpTheKhoKhoi_(k, mc[k], tu, den, jpNum_(tonKho[k]), itemMap);
    /* Chỉ bỏ khi KHÔNG có gì cả. Mã được hỏi thẳng thì luôn giữ — hỏi một mã mà trả
       về rỗng không nói được là "mã sai" hay "không có phát sinh". */
    if (!ma && !b.rows.length && !b.tonDau && !b.tonCuoi && !b.lech) { boQua++; return; }
    khoi.push(b);
  });

  var tong = { tonDau: 0, tongNhap: 0, tongXuat: 0, tonCuoi: 0, soDong: 0 };
  var soMaLech = 0, lechTong = 0;
  khoi.forEach(function (b) {
    tong.tonDau += b.tonDau; tong.tongNhap += b.tongNhap;
    tong.tongXuat += b.tongXuat; tong.tonCuoi += b.tonCuoi;
    tong.soDong += b.rows.length;
    /* Lệch tính bằng TRỊ TUYỆT ĐỐI từng mã: thiếu 5 ở mã này mà thừa 5 ở mã kia là
       HAI lỗi, cộng số có dấu thành 0 là che mất cả hai. */
    if (b.lech) { soMaLech++; lechTong += Math.abs(b.lech); }
  });

  var ra = {
    ok: true, khoId: khoId, tenKho: jpTenKho_(khoId),
    tuNgay: tu, denNgay: den,
    moiMa: !ma, soMa: khoi.length, boQua: boQua,
    khoi: khoi, tong: tong,
    soMaLech: soMaLech, lechTong: lechTong, canBang: soMaLech === 0
  };

  /* Giữ NGUYÊN hình dạng cũ khi xem một mã — màn hiện tại và test đang đọc mấy
     trường này ở cấp cao nhất. */
  if (ma) {
    var b0 = khoi[0];
    ra.itemCode = b0.itemCode; ra.itemName = b0.itemName; ra.misa = b0.misa;
    ra.tonDau = b0.tonDau; ra.tongNhap = b0.tongNhap; ra.tongXuat = b0.tongXuat;
    ra.tonCuoi = b0.tonCuoi; ra.tonHienTai = b0.tonHienTai; ra.rows = b0.rows;
    ra.tonDauCT = b0.tonDauCT; ra.lech = b0.lech; ra.canBang = b0.canBang;
  }
  return ra;
}

/*═══════════════════════ ④ BẢNG KÊ PHIẾU ═══════════════════════*/

/** Bảng kê phiếu NHẬP theo khoảng ngày. Có cả phiếu đã huỷ, đánh dấu rõ. */
function jpKhoBangKeNhap(token, tuNgay, denNgay, loaiNhap) {
  jpNeedKT_(jpAuth_(token));
  var tu = jpDate_(tuNgay) || '2000-01-01';
  var den = jpDate_(denNgay) || '2999-12-31';
  var loc = jpStr_(loaiNhap).toUpperCase();

  var rows = jpRows_(JP_TABS.KHO_NHAP).filter(function (h) {
    var d = jpDate_(h.ngay);
    if (d < tu || d > den) return false;
    return !loc || jpLoaiNhap_(h) === loc;
  }).sort(function (a, b) {
    var da = jpDate_(a.ngay), db = jpDate_(b.ngay);
    if (da !== db) return da < db ? -1 : 1;
    return String(a.id) < String(b.id) ? -1 : 1;
  }).map(function (h) {
    var l = jpLoaiNhap_(h);
    return {
      id: String(h.id), soChungTu: jpStr_(h.soChungTu), ngay: jpDate_(h.ngay),
      loaiNhap: l, tenLoai: (JP_NHAP_LOAI[l] || {}).ten || l,
      khoId: jpStr_(h.khoId) || JP_KHO_TONG, locationName: jpStr_(h.locationName),
      nccMa: jpStr_(h.nccMa), nccTen: jpStr_(h.nccTen),
      soDong: jpNum_(h.soDong), tongTien: jpNum_(h.tongTien),
      ghiChu: jpStr_(h.ghiChu), createdBy: jpStr_(h.createdBy),
      daHuy: !!jpStr_(h.huyAt), huyReason: jpStr_(h.huyReason)
    };
  });

  var tong = { soPhieu: 0, soDong: 0, tongTien: 0, soHuy: 0, tienHuy: 0 };
  rows.forEach(function (r) {
    if (r.daHuy) { tong.soHuy++; tong.tienHuy += r.tongTien; return; }
    tong.soPhieu++; tong.soDong += r.soDong; tong.tongTien += r.tongTien;
  });

  return { ok: true, tuNgay: tu, denNgay: den, rows: rows, tong: tong };
}

/** Bảng kê phiếu XUẤT theo khoảng ngày, tách theo loại xuất. */
function jpKhoBangKeXuat(token, tuNgay, denNgay, loai) {
  jpNeedKT_(jpAuth_(token));
  var tu = jpDate_(tuNgay) || '2000-01-01';
  var den = jpDate_(denNgay) || '2999-12-31';
  var loc = jpStr_(loai).toUpperCase();

  var rows = jpRows_(JP_TABS.KHO_DC).filter(function (h) {
    var d = jpDate_(h.ngay);
    if (d < tu || d > den) return false;
    return !loc || jpStr_(h.loai) === loc;
  }).sort(function (a, b) {
    var da = jpDate_(a.ngay), db = jpDate_(b.ngay);
    if (da !== db) return da < db ? -1 : 1;
    return String(a.id) < String(b.id) ? -1 : 1;
  }).map(function (h) {
    var l = jpStr_(h.loai);
    var def = JP_XUAT_LOAI[l] || {};
    return {
      id: String(h.id), soChungTu: jpStr_(h.soChungTu), ngay: jpDate_(h.ngay),
      loai: l, tenLoai: def.ten || l, tk: def.tk || '',
      khoTu: jpStr_(h.khoTu), khoDen: jpStr_(h.khoDen),
      locationName: jpStr_(h.locationName),
      soDong: jpNum_(h.soDong), tongSL: jpNum_(h.tongSL), tongTien: jpNum_(h.tongTien),
      ghiChu: jpStr_(h.ghiChu), createdBy: jpStr_(h.createdBy),
      daHuy: !!jpStr_(h.huyAt), huyReason: jpStr_(h.huyReason)
    };
  });

  var tong = { soPhieu: 0, tongSL: 0, tongTien: 0, soHuy: 0 };
  var theoLoai = {};
  rows.forEach(function (r) {
    if (r.daHuy) { tong.soHuy++; return; }
    tong.soPhieu++; tong.tongSL += r.tongSL; tong.tongTien += r.tongTien;
    if (!theoLoai[r.loai]) theoLoai[r.loai] = { loai: r.loai, tenLoai: r.tenLoai,
                                               tk: r.tk, soPhieu: 0, tongSL: 0, tongTien: 0 };
    theoLoai[r.loai].soPhieu++;
    theoLoai[r.loai].tongSL += r.tongSL;
    theoLoai[r.loai].tongTien += r.tongTien;
  });

  return {
    ok: true, tuNgay: tu, denNgay: den, rows: rows, tong: tong,
    theoLoai: Object.keys(theoLoai).map(function (k) { return theoLoai[k]; })
  };
}

/*═══════════════════════ ⑤ CÔNG NỢ NHÀ CUNG CẤP ═══════════════════════*/

/**
 * Ghi một phiếu trả tiền nhà cung cấp. Đây là bên "đã trả" của sổ công nợ NCC.
 * p = {ngay, nccMa, nccTen, soTien, hinhThuc, ghiChu}
 */
function jpKhoTraNcc(token, p) {
  var u = jpNeedKT_(jpAuth_(token));
  p = p || {};
  var ma = jpStr_(p.nccMa), ten = jpStr_(p.nccTen);
  if (!ma && !ten) throw new Error('Chọn hoặc gõ nhà cung cấp');
  var tien = jpNum_(p.soTien);
  if (tien <= 0) throw new Error('Số tiền trả phải lớn hơn 0');

  return jpLock_(function () {
    var id = jpNextId_('TT');
    var soCT = JP_TT_PREFIX + id.replace(/^TT/, '');
    jpAppend_(JP_TABS.KHO_TRA_NCC, {
      id: id, soChungTu: soCT, ngay: jpDate_(p.ngay) || jpToday_(),
      nccMa: ma, nccTen: ten, soTien: tien,
      hinhThuc: jpStr_(p.hinhThuc) || JP_PAY_CK,
      ghiChu: jpStr_(p.ghiChu), createdBy: u.hoTen, createdAt: new Date()
    });
    jpAudit_(u, 'KHO_TRA_NCC', '', id, { ncc: ma || ten, soTien: tien });
    return { ok: true, id: id, soChungTu: soCT,
             msg: 'Đã ghi phiếu trả ' + jpMoney_(tien) + 'đ cho ' + (ten || ma) };
  });
}

/** Huỷ phiếu trả tiền NCC — ghi sai số thì huỷ, đừng sửa tay trong sheet. */
function jpKhoHuyTraNcc(token, id, reason) {
  var u = jpNeedKT_(jpAuth_(token));
  var ly = jpStr_(reason);
  if (!ly) throw new Error('Nhập lý do huỷ phiếu trả tiền');
  return jpLock_(function () {
    var h = jpFindOne_(JP_TABS.KHO_TRA_NCC, 'id', id);
    if (!h) throw new Error('Không tìm thấy phiếu trả tiền');
    if (jpStr_(h.huyAt)) return { ok: true, msg: 'Phiếu này đã huỷ trước đó' };
    jpFields_(JP_TABS.KHO_TRA_NCC, h._row,
              { huyBy: u.hoTen, huyAt: new Date(), huyReason: ly });
    jpAudit_(u, 'KHO_HUY_TRA_NCC', '', String(id), { reason: ly });
    return { ok: true, msg: 'Đã huỷ phiếu ' + jpStr_(h.soChungTu) };
  });
}

function jpKhoLichSuTraNcc(token, limit) {
  jpNeedKT_(jpAuth_(token));
  var n = jpNum_(limit) || 50;
  return jpRows_(JP_TABS.KHO_TRA_NCC)
    .sort(function (a, b) {
      var da = jpDate_(a.ngay), db = jpDate_(b.ngay);
      if (da !== db) return da > db ? -1 : 1;
      return String(a.id) > String(b.id) ? -1 : 1;
    })
    .slice(0, n)
    .map(function (h) {
      return {
        id: String(h.id), soChungTu: jpStr_(h.soChungTu), ngay: jpDate_(h.ngay),
        nccMa: jpStr_(h.nccMa), nccTen: jpStr_(h.nccTen), soTien: jpNum_(h.soTien),
        hinhThuc: jpStr_(h.hinhThuc), ghiChu: jpStr_(h.ghiChu),
        createdBy: jpStr_(h.createdBy),
        daHuy: !!jpStr_(h.huyAt), huyReason: jpStr_(h.huyReason)
      };
    });
}

/** Danh sách nhà cung cấp đã xuất hiện — không có danh mục NCC riêng. */
function jpKhoDanhSachNcc(token) {
  jpNeedKT_(jpAuth_(token));
  var m = {};
  var them = function (ma, ten) {
    var k = jpStr_(ma) || jpStr_(ten);
    if (!k) return;
    if (!m[k]) m[k] = { ma: jpStr_(ma), ten: jpStr_(ten), soPhieu: 0 };
    if (!m[k].ten && jpStr_(ten)) m[k].ten = jpStr_(ten);
    m[k].soPhieu++;
  };
  jpRows_(JP_TABS.KHO_NHAP).forEach(function (h) {
    if (jpLoaiNhap_(h) !== JP_NHAP_MUA) return;
    them(h.nccMa, h.nccTen);
  });
  jpRows_(JP_TABS.KHO_TRA_NCC).forEach(function (h) { them(h.nccMa, h.nccTen); });
  return Object.keys(m).map(function (k) { return m[k]; })
    .sort(function (a, b) { return (a.ten || a.ma) < (b.ten || b.ma) ? -1 : 1; });
}

/**
 * Sổ công nợ nhà cung cấp của một tháng.
 *
 *   dư cuối kỳ = dư đầu kỳ + phát sinh phải trả − đã trả
 *
 * DƯ ĐẦU KỲ SUY RA, KHÔNG LƯU — đúng cách của `jpSoCongNo` (cơ sở). Cùng con số,
 * không cần cơ chế chốt sổ, không bao giờ lệch với chứng từ gốc. Đánh đổi đã biết:
 * sửa một phiếu nhập cũ thì dư đầu kỳ tháng sau đổi theo.
 *
 * CHỈ phiếu nhập `MUA` sinh phải trả. Tồn đầu kỳ và kiểm kê thừa cũng là hàng vào
 * kho nhưng không nợ ai — xem `JP_NHAP_LOAI`.
 */
function jpCongNoNcc(token, thang, nam) {
  jpNeedKT_(jpAuth_(token));
  var m = jpNum_(thang), y = jpNum_(nam);
  if (m < 1 || m > 12 || y < 2020) throw new Error('Tháng / năm không hợp lệ');

  var soNgay = new Date(y, m, 0).getDate();
  var dau  = y + '-' + ('0' + m).slice(-2) + '-01';
  var cuoi = y + '-' + ('0' + m).slice(-2) + '-' + ('0' + soNgay).slice(-2);

  var gom = {};
  var o = function (ma, ten) {
    var k = jpStr_(ma) || jpStr_(ten) || '(không ghi NCC)';
    if (!gom[k]) {
      gom[k] = { nccMa: jpStr_(ma), nccTen: jpStr_(ten) || k,
                 duDauKy: 0, phatSinh: 0, daTra: 0, duCuoiKy: 0,
                 soPhieuNhap: 0, soPhieuTra: 0 };
    }
    if (!gom[k].nccTen && jpStr_(ten)) gom[k].nccTen = jpStr_(ten);
    return gom[k];
  };

  jpRows_(JP_TABS.KHO_NHAP).forEach(function (h) {
    if (jpStr_(h.huyAt)) return;
    if (jpLoaiNhap_(h) !== JP_NHAP_MUA) return;
    var d = jpDate_(h.ngay), t = jpNum_(h.tongTien);
    var g = o(h.nccMa, h.nccTen);
    if (d < dau) { g.duDauKy += t; return; }
    if (d <= cuoi) { g.phatSinh += t; g.soPhieuNhap++; }
  });

  jpRows_(JP_TABS.KHO_TRA_NCC).forEach(function (h) {
    if (jpStr_(h.huyAt)) return;
    var d = jpDate_(h.ngay), t = jpNum_(h.soTien);
    var g = o(h.nccMa, h.nccTen);
    if (d < dau) { g.duDauKy -= t; return; }
    if (d <= cuoi) { g.daTra += t; g.soPhieuTra++; }
  });

  var rows = Object.keys(gom).map(function (k) {
    var g = gom[k];
    g.duCuoiKy = g.duDauKy + g.phatSinh - g.daTra;
    g.conNo = g.duCuoiKy > 0;
    return g;
  }).filter(function (g) {
    return g.duDauKy || g.phatSinh || g.daTra;
  }).sort(function (a, b) {
    if (b.duCuoiKy !== a.duCuoiKy) return b.duCuoiKy - a.duCuoiKy;   // nợ nhiều lên đầu
    return a.nccTen < b.nccTen ? -1 : 1;
  });

  var tong = { duDauKy: 0, phatSinh: 0, daTra: 0, duCuoiKy: 0 };
  rows.forEach(function (r) {
    tong.duDauKy += r.duDauKy; tong.phatSinh += r.phatSinh;
    tong.daTra += r.daTra; tong.duCuoiKy += r.duCuoiKy;
  });

  var canhBao = [];
  var khongTen = rows.filter(function (r) { return !r.nccMa; }).length;
  if (khongTen) {
    canhBao.push(khongTen + ' nhà cung cấp chưa có mã — MISA cần Mã đối tượng để ' +
                 'khớp công nợ. Gõ mã NCC ở phiếu nhập.');
  }

  return { ok: true, thang: m, nam: y, taiKhoan: JP_TK_NCC,
           rows: rows, tong: tong, canhBao: canhBao };
}

/**
 * GHI MỘT LÔ PHIẾU TRẢ TIỀN NCC — cửa cho import Excel.
 *
 * Andy chốt 06/08/2026: *"mọi phần của kho ấy em phải có phần up data vào bằng
 * excel nha"*. Năm màn nhập liệu kia đã có import; màn này là màn thứ sáu và là
 * màn cuối còn thiếu.
 *
 * ⚠️ Khác năm màn kia ở chỗ căn bản: chúng đổ dữ liệu vào MỘT chứng từ rồi kế toán
 * bấm lưu một lần. Ở đây **mỗi dòng là MỘT phiếu riêng**, nên phải ghi cả lô trong
 * một lượt — để client gọi `jpKhoTraNcc` N lần là N lượt khoá, N lượt gọi Sheets,
 * và hỏng giữa chừng thì không ai biết đã ghi tới dòng nào.
 *
 * ⚠️ **KHÔNG bỏ qua dòng hỏng trong im lặng.** Dòng thiếu nhà cung cấp hoặc số tiền
 * ≤ 0 bị loại và **trả về kèm SỐ DÒNG trong file** — kế toán phải biết file 40 dòng
 * mà chỉ ghi 38 thì hai dòng nào rơi. Im lặng bỏ là tiền trả nhà cung cấp bị thiếu
 * mà sổ công nợ trông vẫn sạch.
 *
 * ⚠️ Ghi được dòng nào thì GIỮ dòng đó, không huỷ cả lô vì một dòng sai. Huỷ cả lô
 * nghe an toàn hơn nhưng thực tế là kế toán sửa một ô rồi nạp lại cả file ⇒ ghi
 * đôi những dòng đã đúng. Sai thì huỷ từng phiếu bằng `jpKhoHuyTraNcc`.
 *
 * @param {Array} rows [{ngay, nccMa, nccTen, soTien, hinhThuc, ghiChu, dong}]
 */
function jpKhoTraNccLo(token, rows) {
  var u = jpNeedKT_(jpAuth_(token));
  rows = rows || [];
  if (!rows.length) throw new Error('Không có dòng nào để ghi');

  var tot = [], bo = [];
  rows.forEach(function (r, i) {
    var ma = jpStr_(r.nccMa), ten = jpStr_(r.nccTen);
    var tien = jpNum_(r.soTien);
    var soDong = jpNum_(r.dong) || (i + 1);
    if (!ma && !ten) { bo.push({ dong: soDong, ly: 'thiếu nhà cung cấp' }); return; }
    if (tien <= 0)   { bo.push({ dong: soDong, ly: 'số tiền phải lớn hơn 0',
                                 ncc: ten || ma }); return; }
    tot.push({ ngay: jpDate_(r.ngay) || jpToday_(), nccMa: ma, nccTen: ten,
               soTien: tien, hinhThuc: jpCachTraNcc_(r.hinhThuc),
               ghiChu: jpStr_(r.ghiChu) });
  });

  if (!tot.length) {
    return { ok: false, ghi: 0, boQua: bo, tongTien: 0,
             msg: 'Không ghi được dòng nào — xem cột lý do bên dưới.' };
  }

  return jpLock_(function () {
    var them = [], tong = 0;
    tot.forEach(function (t) {
      var id = jpNextId_('TT');
      tong += t.soTien;
      them.push({
        id: id, soChungTu: JP_TT_PREFIX + id.replace(/^TT/, ''),
        ngay: t.ngay, nccMa: t.nccMa, nccTen: t.nccTen, soTien: t.soTien,
        hinhThuc: t.hinhThuc, ghiChu: t.ghiChu,
        createdBy: u.hoTen, createdAt: new Date()
      });
    });
    /* Ghi MỘT lượt thay vì append từng dòng — 40 phiếu là 1 lượt gọi Sheets chứ
       không phải 40. Cùng lý do với `jpCfgImportItems`. */
    jpAppendMany_(JP_TABS.KHO_TRA_NCC, them);
    jpAudit_(u, 'KHO_TRA_NCC_LO', '', '', { ghi: them.length, boQua: bo.length,
                                            tongTien: tong });
    return { ok: true, ghi: them.length, boQua: bo, tongTien: tong,
             soChungTu: them.map(function (x) { return x.soChungTu; }),
             msg: 'Đã ghi ' + them.length + ' phiếu trả · tổng ' + jpMoney_(tong) + 'đ'
                  + (bo.length ? ' · BỎ QUA ' + bo.length + ' dòng' : '') };
  });
}

/**
 * Hình thức trả NCC — danh sách ĐÓNG, chỉ `TM` hoặc `CK`.
 *
 * ⚠️ Khác `jpCachThu_` (tiền cơ sở nộp về, bắt buộc chọn, throw nếu trống): ở đây
 * file Excel của nhà cung cấp thường không có cột hình thức, mà thiếu nó thì
 * **không sai tiền** — chỉ là ghi nhầm nhóm. Nên rơi về `CK` giống hệt
 * `jpKhoTraNcc` vẫn làm, không chặn cả lô vì một cột không có.
 */
function jpCachTraNcc_(v) {
  var s = jpStr_(v).toUpperCase();
  if (s.indexOf('TM') >= 0 || s.indexOf('MAT') >= 0 || s.indexOf('MẶT') >= 0
      || s.indexOf('CASH') >= 0) return JP_PAY_TM;
  return JP_PAY_CK;
}

