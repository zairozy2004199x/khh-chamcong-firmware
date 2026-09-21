/*
 * saoke.js — Đối chiếu SAO KÊ NGÂN HÀNG với sổ ủy nhiệm chi.
 *
 * ═══════════════════════════════════════════════════════════════════════════════════════════
 * VIỆC NÀY ĐANG LÀM BẰNG MẮT, VÀ ĐÓ LÀ CHỖ SAI TIỀN
 * ═══════════════════════════════════════════════════════════════════════════════════════════
 * Cuối tháng kế toán mở hai thứ cạnh nhau: file "Đi ủy nhiệm chi" và sao kê tải từ ngân hàng,
 * rồi dò từng dòng xem khoản nào đã ra khỏi tài khoản thật. Một tháng của ba tài khoản là vài
 * trăm dòng mỗi bên. Dò tay thì:
 *   · khoản ĐÃ đi mà quên tích thành công nợ ảo — đi đòi một nhà cung cấp đã được trả;
 *   · khoản CHƯA đi mà tích nhầm thành mất dấu một khoản phải trả, tới hạn mới lộ;
 *   · khoản ngân hàng trừ mà sổ KHÔNG có dòng nào — đó là thứ đáng sợ nhất, và là thứ dò tay
 *     không bao giờ thấy, vì mắt người đi từ sổ sang sao kê chứ không đi ngược lại.
 *
 * ═══════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 BA MỨC CHẮC CHẮN, VÀ CHỈ MỨC CAO NHẤT MỚI ĐƯỢC TỰ BẬT
 * ═══════════════════════════════════════════════════════════════════════════════════════════
 * Tự bật "đã đi tiền" cho một khoản là XOÁ nó khỏi công nợ. Đoán sai theo chiều ấy thì không
 * ai phát hiện ra — bảng công nợ vẫn sạch, chỉ là thiếu một khoản thật. Nên:
 *
 *   'chac'  — số tiền khớp ĐÚNG ĐẾN ĐỒNG, số tài khoản người thụ hưởng khớp, ngày nằm trong
 *             cửa sổ cho phép, và CHỈ CÓ MỘT ứng viên. Mức này mới tự bật.
 *   'kha'   — số tiền khớp đúng đồng + TÊN người thụ hưởng khớp (đã bỏ dấu, bỏ "CÔNG TY TNHH"),
 *             ngày trong cửa sổ. Bày ra cho người bấm, KHÔNG tự bật: tên trùng nhau đầy.
 *   'ngo'   — chỉ khớp số tiền và ngày. Bày ra để người nhìn, không bao giờ tự bật.
 *
 * ⚠️ KHÔNG BAO GIỜ KHỚP THEO SỐ TIỀN LÀM TRÒN. Ngân hàng trừ 25.500.000 thì sổ phải có đúng
 *    25.500.000. Nới ra "lệch dưới 1.000đ cũng coi là khớp" là mở đường cho phí chuyển tiền
 *    nuốt mất một khoản chênh thật.
 *
 * ⚠️ MỘT GIAO DỊCH CHỈ ĐƯỢC KHỚP VỚI MỘT DÒNG SỔ, VÀ NGƯỢC LẠI. Cùng một nhà cung cấp, cùng
 *    số tiền, hai tháng liền — không ràng buộc một-một thì cả hai dòng sổ cùng bám vào một
 *    lượt chuyển, và tháng sau thành "đã đi" oan.
 *
 * ⚠️ KHÔNG ĐỤNG VÀO DÒNG KẾ TOÁN ĐÃ TỰ ĐÁNH. Người ta tích tay là người ta BIẾT một điều mà
 *    sao kê không nói (tiền mặt, cấn trừ, chuyển nhầm rồi hoàn). Máy đè lên là xoá mất điều ấy.
 *    Chỗ nào máy nghĩ khác người thì BÀY RA thành một dòng "lệch", để người quyết.
 *
 * Lõi doiChieu() không đụng DOM, chạy được ở Node để kiểm thử.
 */
(function (root, factory) {
  if (typeof module === 'object' && module.exports) module.exports = factory(require('./engine.js'));
  else root.UNCSaoKe = factory(root.UNCEngine);
})(typeof self !== 'undefined' ? self : this, function (E) {
  'use strict';

  /* ===================== Hằng số ===================== */

  /** Cửa sổ ngày mặc định: lệnh lập hôm nay, ngân hàng trừ trong vài ngày làm việc tới. */
  const CUA_SO_NGAY = { truoc: 3, sau: 14 };

  const MUC = {
    chac: { key: 'chac', nhan: 'Khớp chắc', diem: 3 },
    kha: { key: 'kha', nhan: 'Khớp khá', diem: 2 },
    ngo: { key: 'ngo', nhan: 'Có thể khớp', diem: 1 },
  };

  /* ===================== Nhận diện cột sao kê ===================== */

  function chuanTieuDe(v) {
    return E.boDau(v == null ? '' : v)
      .toLowerCase()
      .replace(/\s+/g, ' ')
      .replace(/[^a-z0-9 /()+-]+/g, '')
      .trim();
  }

  /**
   * Tên cột sao kê → trường. Mỗi ngân hàng đặt tên một kiểu, và chính họ cũng đổi theo bản
   * xuất (web / app / tệp gửi kèm email), nên danh sách này là danh sách MỞ: thêm một cách
   * viết mới thì thêm một chuỗi, không phải viết lại bộ đọc.
   *
   * ⚠️ `ghiNo` và `ghiCo` PHẢI TÁCH RIÊNG, kể cả khi sao kê gộp thành một cột có dấu âm.
   *    Ủy nhiệm chi là tiền RA. Gộp hai chiều vào một cột rồi lấy trị tuyệt đối là một khoản
   *    tiền VỀ đúng bằng số tiền phải trả sẽ khớp ngon lành — và khoản phải trả biến mất.
   */
  const DONG_NGHIA = {
    ngay: ['ngay giao dich', 'ngay gd', 'ngay hach toan', 'ngay', 'transaction date', 'trans date', 'date', 'ngay hieu luc'],
    ghiNo: ['ghi no', 'no', 'debit', 'so tien ghi no', 'phat sinh no', 'tien ra', 'rut ra', 'withdrawal', 'so tien rut'],
    ghiCo: ['ghi co', 'co', 'credit', 'so tien ghi co', 'phat sinh co', 'tien vao', 'deposit', 'so tien nop'],
    soTien: ['so tien', 'amount', 'so tien gd', 'gia tri gd'],
    noiDung: ['noi dung', 'noi dung giao dich', 'dien giai', 'mo ta', 'description', 'remark', 'chi tiet giao dich', 'noi dung chuyen khoan'],
    taiKhoanDoiUng: ['tai khoan doi ung', 'so tai khoan doi ung', 'tk doi ung', 'tai khoan thu huong', 'so tk nhan', 'tk nhan', 'beneficiary account'],
    tenDoiUng: ['ten doi ung', 'don vi doi ung', 'ten nguoi nhan', 'ten thu huong', 'nguoi thu huong', 'beneficiary name', 'ten tai khoan doi ung'],
    maGD: ['so tham chieu', 'ma giao dich', 'so but toan', 'so chung tu', 'reference', 'ref no', 'so gd', 'transaction id'],
    soDu: ['so du', 'so du cuoi', 'balance', 'so du sau gd'],
  };

  /** Dòng tiêu đề nằm đâu đó trong 15 dòng đầu — sao kê hay có 5–10 dòng thông tin tài khoản. */
  function timDongTieuDe(rows) {
    let tot = { dong: -1, diem: 0, banDo: null };
    const han = Math.min(rows.length, 15);
    for (let r = 0; r < han; r++) {
      const kq = doBanDo(rows[r] || []);
      if (kq.diem > tot.diem) tot = { dong: r, diem: kq.diem, banDo: kq.banDo };
    }
    /* Ít nhất phải nhận ra NGÀY và MỘT cột tiền — thiếu một trong hai thì bộ đọc chỉ đang
       đoán, và đoán sai ở đây là đọc nhầm cả tệp mà không có dòng nào đỏ. */
    if (tot.diem < 2 || !tot.banDo) return null;
    const coTien = tot.banDo.ghiNo != null || tot.banDo.soTien != null;
    if (tot.banDo.ngay == null || !coTien) return null;
    return tot;
  }

  function doBanDo(row) {
    const banDo = {};
    let diem = 0;
    (row || []).forEach((o, i) => {
      const h = chuanTieuDe(o);
      if (!h) return;
      for (const truong in DONG_NGHIA) {
        if (banDo[truong] != null) continue;
        if (DONG_NGHIA[truong].indexOf(h) >= 0) {
          banDo[truong] = i;
          diem += 1;
          return;
        }
      }
      /* Khớp "chứa" chỉ dùng khi khớp trọn ô không ra gì — tên cột thật hay có đuôi
         ("Số tiền ghi nợ (VND)"), nhưng để nó chạy TRƯỚC thì "Số tiền" nuốt luôn "Số tiền
         ghi có" và cả chiều tiền về lọt vào cột tiền ra. */
      for (const truong in DONG_NGHIA) {
        if (banDo[truong] != null) continue;
        for (const ten of DONG_NGHIA[truong]) {
          if (ten.length >= 5 && h.indexOf(ten) >= 0) {
            banDo[truong] = i;
            diem += 1;
            return;
          }
        }
      }
    });
    return { banDo: banDo, diem: diem };
  }

  /* ===================== Đọc sao kê ===================== */

  function lay(row, i) {
    return i == null || !row ? '' : row[i];
  }

  /**
   * Đọc một sheet sao kê thành danh sách giao dịch TIỀN RA.
   *
   * @param rows mảng các mảng ô (đã lấy ra từ SheetJS hoặc dựng tay để kiểm thử)
   * @param tuyChon { nguon: tên sheet/tệp, namMacDinh }
   * @return { gd:[], nhatKy:[] }
   */
  function docSheet(rows, tuyChon) {
    const opt = tuyChon || {};
    const nhatKy = [];
    const ten = opt.nguon || 'sao kê';
    const td = timDongTieuDe(rows || []);
    if (!td) {
      nhatKy.push({
        muc: 'canh',
        text: 'Sheet "' + ten + '": không tìm ra dòng tiêu đề có cột Ngày và cột tiền — bỏ qua sheet này.',
      });
      return { gd: [], nhatKy: nhatKy };
    }
    const bd = td.banDo;
    const gd = [];
    let boQuaCo = 0;
    let boQuaRong = 0;

    for (let r = td.dong + 1; r < rows.length; r++) {
      const row = rows[r] || [];
      if (!row.length) continue;

      const ngay = E.docNgay(lay(row, bd.ngay), opt.namMacDinh || '');
      const noiDung = E.gonTrang(lay(row, bd.noiDung));

      /* ═══════════════════════════════════════════════════════════════════════════════════
       * 🔴 CHIỀU TIỀN QUYẾT ĐỊNH DÒNG NÀY CÓ ĐƯỢC ĐỌC HAY KHÔNG
       *
       * Ba kiểu bố cục thật:
       *   a) hai cột riêng Ghi nợ / Ghi có  → lấy cột Ghi nợ, có số mới là tiền ra;
       *   b) một cột "Số tiền" mang dấu âm  → số âm là tiền ra;
       *   c) một cột "Số tiền" KHÔNG dấu, chiều nằm ở cột khác hoặc ở nội dung → KHÔNG đoán.
       *
       * Ca (c) là ca nguy hiểm: đọc bừa thành tiền ra thì mọi khoản tiền VỀ cũng thành ứng
       * viên khớp. Thà bỏ qua và nói ra còn hơn khớp nhầm một chiều.
       * ═══════════════════════════════════════════════════════════════════════════════════ */
      let tien = 0;
      if (bd.ghiNo != null) {
        tien = Math.abs(E.docTien(lay(row, bd.ghiNo)));
        if (!tien && bd.ghiCo != null && E.docTien(lay(row, bd.ghiCo))) {
          boQuaCo++;
          continue;
        }
      } else {
        const tho = String(lay(row, bd.soTien) == null ? '' : lay(row, bd.soTien)).trim();
        const am = /^-/.test(tho) || /^\(.*\)$/.test(tho);
        /* `docTien()` bỏ dấu ở một kiểu viết và giữ ở kiểu khác — nó sinh ra để đọc ô TIỀN
           trong sổ, nơi số âm không có nghĩa. Ở đây CHIỀU đã đọc riêng từ hình dạng chuỗi
           thô, nên lấy trị tuyệt đối: để lọt một số âm vào `soTien` là mọi phép so sau này
           lệch dấu, và cặp nào cũng trượt mà không ai hiểu vì sao. */
        const so = Math.abs(E.docTien(tho));
        if (!so) tien = 0;
        else if (am) tien = so;
        else {
          boQuaCo++;
          continue;
        }
      }

      if (!tien) {
        if (noiDung || (ngay && ngay.iso)) boQuaRong++;
        continue;
      }

      const rec = {
        nguon: ten,
        dong: r + 1,
        ngay: ngay && ngay.iso ? ngay.iso : '',
        ngayNhan: ngay ? ngay.nhan : '',
        soTien: Math.round(tien),
        noiDung: noiDung,
        taiKhoanDoiUng: E.chuanSoTK(lay(row, bd.taiKhoanDoiUng)),
        tenDoiUng: E.gonTrang(lay(row, bd.tenDoiUng)),
        maGD: E.gonTrang(lay(row, bd.maGD)),
      };
      /* Nhiều sao kê không có cột tên/số tài khoản riêng — tất cả nằm trong ô Nội dung
         ("CT tu 0501000160370 CTY TNHH ABC toi ..."). Bóc ra thì đối chiếu mới lên được
         mức "chắc" thay vì dừng ở "ngờ". */
      if (!rec.taiKhoanDoiUng) rec.taiKhoanDoiUng = bocSoTK(noiDung);
      rec.id = 'gd_' + E.bam32([ten, rec.ngay, rec.soTien, rec.maGD, rec.taiKhoanDoiUng, noiDung, r].join('|'));
      rec.timKiem = E.boDau([noiDung, rec.tenDoiUng, rec.taiKhoanDoiUng, rec.maGD].join(' ')).toLowerCase();
      gd.push(rec);
    }

    if (boQuaCo) {
      nhatKy.push({
        muc: 'tin',
        text: 'Sheet "' + ten + '": bỏ ' + boQuaCo + ' dòng TIỀN VỀ (ghi có) — ủy nhiệm chi chỉ đối chiếu tiền ra.',
      });
    }
    if (boQuaRong) {
      nhatKy.push({
        muc: 'tin',
        text: 'Sheet "' + ten + '": bỏ ' + boQuaRong + ' dòng không đọc được số tiền ra.',
      });
    }
    nhatKy.push({ muc: 'ok', text: 'Sheet "' + ten + '": đọc được ' + gd.length + ' giao dịch tiền ra.' });
    return { gd: gd, nhatKy: nhatKy };
  }

  /**
   * Số tài khoản nằm lẫn trong nội dung chuyển khoản.
   *
   * ⚠️ CHỈ NHẬN CHUỖI SỐ DÀI 8–20. Ngắn hơn là số hoá đơn, ngày tháng, mã hợp đồng — nhận
   *    bừa thì đối chiếu lên mức "chắc" bằng một con số chẳng phải tài khoản của ai.
   *    Có NHIỀU chuỗi như thế thì KHÔNG đoán: chọn bừa một cái là chọn sai một cách im lặng.
   */
  function bocSoTK(s) {
    if (!s) return '';
    const m = String(s).match(/\b\d{8,20}\b/g);
    return m && m.length === 1 ? m[0] : '';
  }

  /** Lớp bọc mỏng cho SheetJS — chỉ chạy trong trình duyệt. */
  function docWorkbook(XLSX, wb, tuyChon) {
    const gd = [];
    let nhatKy = [];
    (wb.SheetNames || []).forEach((ten) => {
      const rows = XLSX.utils.sheet_to_json(wb.Sheets[ten], { header: 1, raw: true, defval: null });
      const kq = docSheet(rows, Object.assign({}, tuyChon, { nguon: ten }));
      gd.push.apply(gd, kq.gd);
      nhatKy = nhatKy.concat(kq.nhatKy);
    });
    return { gd: gd, nhatKy: nhatKy };
  }

  /* ===================== Đối chiếu ===================== */

  function soNgayCach(a, b) {
    if (!a || !b) return null;
    const t1 = Date.parse(a + 'T00:00:00Z');
    const t2 = Date.parse(b + 'T00:00:00Z');
    if (isNaN(t1) || isNaN(t2)) return null;
    return Math.round((t2 - t1) / 86400000);
  }

  /**
   * Chấm một cặp (dòng sổ, giao dịch). Trả `null` khi không thể là một cặp.
   *
   * ⚠️ SỐ TIỀN LÀ ĐIỀU KIỆN CẦN, KHÔNG PHẢI MỘT ĐIỂM CỘNG. Lệch một đồng là hai khoản khác
   *    nhau — và đúng cái "một đồng" ấy là dấu hiệu của phí chuyển tiền hoặc gõ sai số.
   */
  function cham(d, g, opt) {
    if (Math.round(d.rec.soTien) !== g.soTien) return null;

    /* Cửa sổ ngày tính từ HẠN phải đi tiền: lệnh lập trước, ngân hàng trừ sau. Sổ không có
       ngày nào thì vẫn xét được, chỉ là không bao giờ lên nổi mức "chắc". */
    const moc = d.han || (d.rec.ngayLenh && d.rec.ngayLenh.iso) || '';
    const cach = soNgayCach(moc, g.ngay);
    if (moc && g.ngay) {
      if (cach < -opt.cuaSo.truoc || cach > opt.cuaSo.sau) return null;
    }

    const tkSo = E.chuanSoTK(d.rec.soTaiKhoan);
    const tkGD = E.chuanSoTK(g.taiKhoanDoiUng);
    const khopTK = !!tkSo && !!tkGD && tkSo === tkGD;

    const tenSo = d.rec.thuHuongKhoa || E.khoaNCC(d.rec.thuHuong);
    const tenGD = g.tenDoiUng ? E.khoaNCC(g.tenDoiUng) : '';
    /* Tên trong nội dung chuyển khoản viết liền không dấu, có khi cụt đuôi — nên so bằng
       "chứa", và đòi tối thiểu 6 ký tự để "AN" không khớp với nửa danh bạ. */
    const tenTrongND = !!tenSo && tenSo.length >= 6 &&
      E.boDau(g.noiDung).toUpperCase().replace(/[^A-Z0-9]+/g, ' ').indexOf(tenSo) >= 0;
    const khopTen = (!!tenSo && !!tenGD && (tenSo === tenGD || tenGD.indexOf(tenSo) >= 0 || tenSo.indexOf(tenGD) >= 0)) || tenTrongND;

    let muc = null;
    if (khopTK && moc && g.ngay) muc = MUC.chac;
    else if (khopTen && moc && g.ngay) muc = MUC.kha;
    else if (khopTK || khopTen) muc = MUC.kha;
    else muc = MUC.ngo;

    return {
      muc: muc.key,
      nhanMuc: muc.nhan,
      /* Điểm để xếp thứ tự khi một giao dịch có nhiều ứng viên: chắc hơn thì thắng, cùng mức
         thì GẦN NGÀY hơn thắng. Không có tiêu chí thứ hai thì thứ tự phụ thuộc vào thứ tự
         dòng trong tệp, và cùng một tệp đọc hai lần có thể ra hai kết quả. */
      diem: muc.diem * 1000 - Math.abs(cach == null ? 999 : cach),
      cach: cach,
      khopTK: khopTK,
      khopTen: khopTen,
    };
  }

  /**
   * Đối chiếu cả sổ với cả sao kê.
   *
   * @param dong    mảng từ E.dungDong() — cần `.rec`, `.td`, `.han`, `.trangThai`
   * @param giaoDich mảng từ docSheet()
   * @param tuyChon { cuaSo:{truoc,sau} }
   * @return {
   *   capChac, capKha, capNgo   — mảng { dong, gd, cham } đang CHỜ người quyết
   *   capXong                   — đã khớp và sổ đã ghi "đã đi": việc xong, giữ để đếm lại
   *   lech                      — máy chắc là ĐÃ ĐI nhưng sổ đang ghi khác (kế toán đã đánh tay)
   *   gdKhongKhop               — giao dịch ngân hàng KHÔNG tìm được dòng sổ nào
   *   dongChuaDi                — dòng sổ chưa "đã đi" mà sao kê không có giao dịch nào
   *   tom                       — con số tóm tắt
   * }
   */
  function doiChieu(dong, giaoDich, tuyChon) {
    const opt = Object.assign({ cuaSo: CUA_SO_NGAY }, tuyChon || {});
    opt.cuaSo = Object.assign({}, CUA_SO_NGAY, opt.cuaSo || {});

    /* ═══════════════════════════════════════════════════════════════════════════════════════
     * 🔴 DÒNG ĐÃ HUỶ VẪN PHẢI VÀO ĐỐI CHIẾU — và đây là chỗ dễ làm ngược nhất.
     * Bỏ chúng ra "cho đỡ nhiễu" thì một khoản kế toán đánh huỷ mà ngân hàng VẪN TRỪ đúng số
     * tiền ấy sẽ rơi xuống nhóm "ngân hàng trừ mà sổ không có" — đúng nhóm người ta lướt qua
     * nhanh nhất, vì nó thường toàn phí và lãi. Giữ chúng lại thì lượt trừ ấy dính đúng vào
     * dòng của nó và hiện ra ở nhóm LỆCH, kèm câu nói rõ sổ đang ghi gì.
     *
     * Dòng ghi chú (nộp tiền mặt / cấn trừ) thì khác hẳn: nó không phải một lệnh chuyển khoản
     * nào cả, để vào là bày ra một đống ứng viên mà không lượt nào đúng.
     * ═══════════════════════════════════════════════════════════════════════════════════════ */
    const ds = (dong || []).filter((d) => d.rec.loai !== 'ghichu');
    const gds = (giaoDich || []).slice();

    /* Mọi cặp có thể, chấm điểm rồi xếp. Sổ vài trăm dòng × sao kê vài trăm dòng là vài chục
       nghìn phép so — rẻ hơn nhiều so với một buổi chiều dò tay. */
    const cap = [];
    ds.forEach((d, i) => {
      gds.forEach((g, j) => {
        const c = cham(d, g, opt);
        if (c) cap.push({ iD: i, iG: j, cham: c });
      });
    });
    /* Xếp giảm dần theo điểm; hoà nhau thì theo thứ tự dòng, để cùng một bộ dữ liệu luôn ra
       cùng một kết quả — một bộ đối chiếu "khi thế này khi thế kia" thì không ai tin nổi. */
    cap.sort((a, b) => b.cham.diem - a.cham.diem || a.iD - b.iD || a.iG - b.iG);

    const dungD = Object.create(null);
    const dungG = Object.create(null);
    const demUngVien = Object.create(null);
    cap.forEach((c) => {
      demUngVien[c.iG] = (demUngVien[c.iG] || 0) + 1;
    });

    const capChac = [];
    const capKha = [];
    const capNgo = [];
    const capXong = [];
    const lech = [];

    cap.forEach((c) => {
      if (dungD[c.iD] || dungG[c.iG]) return;
      dungD[c.iD] = 1;
      dungG[c.iG] = 1;
      const d = ds[c.iD];
      const g = gds[c.iG];
      const mot = { dong: d, gd: g, cham: c.cham, soUngVien: demUngVien[c.iG] || 1 };

      /* ═══════════════════════════════════════════════════════════════════════════════════
       * 🔴 KẾ TOÁN ĐÃ ĐÁNH TAY THÌ MÁY KHÔNG ĐÈ LÊN — DÙ CHẮC ĐẾN MẤY.
       * Người ta đánh "huỷ" hay để "chưa lập lệnh" cho một khoản mà ngân hàng vẫn trừ đúng
       * số tiền ấy là một chuyện CẦN NGƯỜI NHÌN: hoặc chuyển nhầm, hoặc trả bằng đường khác,
       * hoặc chính lượt đánh tay kia là sai. Cả ba đều tệ, và cả ba đều mất dấu nếu máy lặng
       * lẽ bật "đã đi".
       * ═══════════════════════════════════════════════════════════════════════════════════ */
      const daDanhTay = !!(d.td && d.td.trangThai);
      if (daDanhTay && d.trangThai !== 'da_di') {
        mot.viSao = 'Kế toán đã đánh "' + (d.nhanTrangThai || d.trangThai) + '" cho khoản này, nhưng sao kê có một lượt trừ đúng số tiền.';
        lech.push(mot);
        return;
      }
      /* ═══════════════════════════════════════════════════════════════════════════════════
       * 🔴 KHOẢN SỔ ĐÃ GHI "ĐÃ ĐI" THÌ RA MỘT NHÓM RIÊNG, KHÔNG NẰM CHUNG VỚI VIỆC CHỜ LÀM.
       * Để chúng trong "khớp chắc" thì nhóm ấy không bao giờ vơi: bấm nút áp dụng xong nhìn
       * lại vẫn thấy đủ ngần ấy dòng, và người ta bấm lần nữa, lần nữa — một cái nút mà bấm
       * vào không thấy gì đổi là cái nút người ta thôi tin. `apDung()` vẫn chặn ở tầng dưới,
       * nhưng chặn im lặng thì chỉ chữa được hậu quả, không chữa được cảm giác hỏng.
       *
       * ⚠️ KHÔNG BỎ CHÚNG ĐI. "Sổ ghi đã đi VÀ ngân hàng có lượt trừ đúng khoản ấy" là câu
       *    trả lời cho chính việc kế toán ngồi làm: đối chiếu xong thì phải kể ra được bao
       *    nhiêu khoản đã khớp, không phải chỉ kể ra mấy khoản còn lệch.
       * ═══════════════════════════════════════════════════════════════════════════════════ */
      if (d.trangThai === 'da_di') capXong.push(mot);
      else if (c.cham.muc === 'chac' && (demUngVien[c.iG] || 1) === 1) capChac.push(mot);
      else if (c.cham.muc === 'chac' || c.cham.muc === 'kha') capKha.push(mot);
      else capNgo.push(mot);
    });

    const gdKhongKhop = [];
    gds.forEach((g, j) => {
      if (!dungG[j]) gdKhongKhop.push(g);
    });
    /* ⚠️ "Chưa đi" là nhóm KHOẢN CÒN PHẢI TRẢ mà sao kê chưa thấy. Khoản đã huỷ thì không
       phải khoản phải trả nữa — kể nó vào đây là mỗi tháng kế toán lại đi soát một danh sách
       toàn thứ đã đóng, và danh sách nào cũng thế thì người ta thôi đọc. */
    const dongChuaDi = [];
    ds.forEach((d, i) => {
      if (!dungD[i] && d.trangThai !== 'da_di' && d.trangThai !== 'huy') dongChuaDi.push(d);
    });

    return {
      capChac: capChac,
      capKha: capKha,
      capNgo: capNgo,
      capXong: capXong,
      lech: lech,
      gdKhongKhop: gdKhongKhop,
      dongChuaDi: dongChuaDi,
      tom: {
        soGD: gds.length,
        soDong: ds.length,
        chac: capChac.length,
        kha: capKha.length,
        ngo: capNgo.length,
        xong: capXong.length,
        lech: lech.length,
        gdLe: gdKhongKhop.length,
        tienGDLe: gdKhongKhop.reduce((a, g) => a + g.soTien, 0),
        dongLe: dongChuaDi.length,
      },
    };
  }

  /* ===================== Áp kết quả vào sổ theo dõi ===================== */

  /**
   * Dựng phần cập nhật `theoDoi` cho những cặp được chọn.
   *
   * ⚠️ TRẢ VỀ BẢN CẬP NHẬT, KHÔNG TỰ GHI. Nơi gọi còn phải lưu xuống kho và vẽ lại màn —
   *    hàm này ghi thẳng thì không kiểm thử được, và cũng không ai hoàn tác được.
   * ⚠️ GIỮ NGUYÊN dòng đã "đã đi": ghi đè là đổi ngày đi tiền kế toán đã xác nhận bằng một
   *    ngày máy đoán, mà hai ngày ấy rơi vào hai tháng là lệch cả báo cáo.
   */
  function apDung(cap, tuyChon) {
    const opt = tuyChon || {};
    const nguoi = opt.nguoi || 'đối chiếu sao kê';
    const luc = opt.luc || new Date().toISOString();
    const ra = {};
    (cap || []).forEach((c) => {
      const d = c.dong;
      if (d.trangThai === 'da_di') return;
      const cu = d.td || {};
      ra[d.rec.id] = Object.assign({}, cu, {
        trangThai: 'da_di',
        ngayDi: c.gd.ngay || cu.ngayDi || '',
        nguonDoiChieu: 'saoke',
        maGD: c.gd.maGD || '',
        nguoiCapNhat: nguoi,
        capNhatLuc: luc,
        ghiChu: ghiChuGop(cu.ghiChu, 'Khớp sao kê ' + (c.gd.nguon || '') + (c.gd.maGD ? ' · mã ' + c.gd.maGD : '')),
      });
    });
    return ra;
  }

  /** Nối ghi chú mới vào ghi chú cũ, không đè — ghi chú kế toán gõ tay là thứ không dựng lại được. */
  function ghiChuGop(cu, moi) {
    const a = E.gonTrang(cu);
    const b = E.gonTrang(moi);
    if (!a) return b;
    if (!b || a.indexOf(b) >= 0) return a;
    return a + ' · ' + b;
  }

  return {
    CUA_SO_NGAY,
    MUC,
    chuanTieuDe,
    timDongTieuDe,
    bocSoTK,
    docSheet,
    docWorkbook,
    doiChieu,
    apDung,
    ghiChuGop,
  };
});
