/*
 * app.js — Giao diện trang "Ủy nhiệm chi & Công nợ".
 *
 * Toàn bộ chạy trong trình duyệt, không gửi dữ liệu đi đâu. Lưu trên máy bằng localStorage:
 *   unc.recs     danh sách đọc từ Excel (nhập lại là ghi đè)
 *   unc.theoDoi  trạng thái kế toán tự đánh dấu, khoá theo rec.id nên nhập lại file KHÔNG mất
 *   unc.congTy   thông tin công ty để điền vào mẫu biểu
 *   unc.tuyChon  cách hiểu dữ liệu + bộ lọc đang dùng
 *   unc.nhatKy   nhật ký lần nhập gần nhất
 */
(function () {
  'use strict';

  const E = window.UNCEngine;
  const Imp = window.UNCImporter;
  const Exp = window.UNCExporter;
  const Mau = window.UNCMau;
  const SaoKe = window.UNCSaoKe;
  const API = window.UNCApi || null;
  /** Bản WordPress: dữ liệu nằm trên máy chủ, nhiều người dùng chung. */
  const LA_WP = !!(API && API.MODE === 'wp');

  /**
   * Trang xuất file bằng cách tự khởi tạo tải xuống (Excel, .json). Khung xem Artifact của
   * claude.ai chặn mọi lượt tải do trang tự khởi tạo — bấm nút thì KHÔNG có gì xảy ra và cũng
   * không có lỗi nào để bắt. Nút chết im lặng tệ hơn nút không có, nên ở đó nói thẳng ra.
   * Chạy trên GitHub Pages hay trên WordPress thì window.claude không tồn tại → không đổi gì.
   */
  const CHAN_TAI_FILE = typeof window !== 'undefined' && typeof window.claude !== 'undefined';
  const LOI_CHAN_TAI =
    'Khung xem này chặn việc tải file xuống, nên không xuất được.\n\n' +
    'Tra cứu và In / Lưu PDF vẫn chạy bình thường. Muốn xuất Excel thì mở bản đầy đủ ' +
    '(GitHub Pages hoặc plugin trên hosting).';

  /* ===================== Trạng thái ứng dụng ===================== */

  const KHOA = { recs: 'unc.recs', theoDoi: 'unc.theoDoi', congTy: 'unc.congTy', tuyChon: 'unc.tuyChon', nhatKy: 'unc.nhatKy' };

  const S = {
    recs: [],
    theoDoi: {},
    nhatKy: [],
    congTy: {
      ten: '', diaChi: '', mst: '', daiDien: '', chucVu: 'Giám đốc', nguoiLap: '',
      taiKhoan: [
        { loai: 'cu', so: '', nganHang: '' },
        { loai: 'moi', so: '', nganHang: '' },
      ],
    },
    tuyChon: { lenhLaDaDi: true },
    // Chỉ sống trong phiên, không lưu:
    tab: 'tong',
    loc: {},
    sapXep: { cot: 'han', giam: false },
    soHien: 200,
    chon: new Set(),
    mauDangChon: '',
    nccDangChon: null,
    tongKy: '',
    cnGom: 'ncc',
    cnDenNgay: '',
    /* Đối chiếu sao kê — CHỈ SỐNG TRONG PHIÊN, cố ý không lưu.
       Sao kê là bản chụp một lúc của ngân hàng; giữ lại giữa hai lần mở trang thì lần sau
       người ta nhìn một kết quả dựng từ một tệp không còn nhớ là tệp nào. Thứ CẦN giữ là
       trạng thái "đã đi tiền" sau khi bấm áp dụng — và thứ ấy nằm trong `theoDoi`, lưu như mọi
       lượt đánh dấu khác. */
    saoKe: { gd: [], nhatKy: [], nguon: '', kq: null, nhom: 'chac', cuaSo: { truoc: 3, sau: 14 } },
  };

  let DONG = [];      // đã tính trạng thái
  let DONG_LOC = [];  // sau bộ lọc

  /* ===================== Tiện ích DOM ===================== */

  const $ = (id) => document.getElementById(id);
  const el = (tag, attrs, con) => {
    const n = document.createElement(tag);
    if (attrs) for (const k in attrs) {
      if (k === 'class') n.className = attrs[k];
      else if (k === 'html') n.innerHTML = attrs[k];
      else if (k === 'text') n.textContent = attrs[k];
      else if (attrs[k] != null && attrs[k] !== false) n.setAttribute(k, attrs[k]);
    }
    if (con != null) (Array.isArray(con) ? con : [con]).forEach((c) => c && n.appendChild(c));
    return n;
  };
  const esc = Mau.escapeHtml;

  function tienGon(n) {
    const v = Math.round(+n || 0);
    const a = Math.abs(v);
    if (a >= 1e9) return (v / 1e9).toLocaleString('vi-VN', { maximumFractionDigits: 2 }) + ' tỷ';
    if (a >= 1e6) return (v / 1e6).toLocaleString('vi-VN', { maximumFractionDigits: 1 }) + ' tr';
    return v.toLocaleString('vi-VN');
  }
  const tien = (n) => E.dinhDangTien(n, true);
  const ngay = (iso) => E.dinhDangNgay(iso) || '—';

  /* ===================== Lưu / nạp ===================== */

  function luu(khoa, giaTri) {
    try {
      localStorage.setItem(khoa, JSON.stringify(giaTri));
    } catch (e) {
      // Hết chỗ (dữ liệu lớn) — báo một lần, trang vẫn chạy trong phiên.
      if (!luu._daBao) {
        luu._daBao = true;
        alert('Không lưu được vào bộ nhớ trình duyệt (có thể đã đầy).\n' +
          'Dữ liệu vẫn dùng được trong phiên này; nên dùng ⋯ → Lưu toàn bộ ra file (.json).');
      }
    }
  }
  function nap(khoa, macDinh) {
    try {
      const v = localStorage.getItem(khoa);
      return v ? JSON.parse(v) : macDinh;
    } catch (e) {
      return macDinh;
    }
  }
  function napTatCa() {
    S.recs = nap(KHOA.recs, []);
    S.theoDoi = nap(KHOA.theoDoi, {});
    S.nhatKy = nap(KHOA.nhatKy, []);
    S.congTy = Object.assign(S.congTy, nap(KHOA.congTy, {}));
    S.tuyChon = Object.assign(S.tuyChon, nap(KHOA.tuyChon, {}));
  }

  /* ===================== Kho: cục bộ hay máy chủ ===================== */

  /**
   * Bản tĩnh ghi thẳng localStorage. Bản WordPress đẩy lên máy chủ để mọi người
   * thấy cùng một trạng thái — đánh dấu ở máy này thì máy kia tải lại là thấy.
   */
  const Kho = {
    /** @param ids mảng id vừa đổi; null = xoá đánh dấu của các id trong `xoa`. */
    theoDoi(ids, xoa) {
      if (!LA_WP) return luu(KHOA.theoDoi, S.theoDoi);
      const items = (ids || []).map((id) => Object.assign({ id: id }, S.theoDoi[id] || {}));
      return banh(
        items.length ? API.call('datTheoDoi', { items: items }) : Promise.resolve(),
        xoa && xoa.length ? () => API.call('xoaTheoDoi', { ids: xoa }) : null
      );
    },
    danhSach() {
      if (!LA_WP) {
        luu(KHOA.recs, S.recs);
        return luu(KHOA.nhatKy, S.nhatKy);
      }
      return banh(dayDanhSach());
    },
    caiDat() {
      if (!LA_WP) {
        luu(KHOA.congTy, S.congTy);
        return luu(KHOA.tuyChon, S.tuyChon);
      }
      return banh(API.call('datCaiDat', { congTy: S.congTy, tuyChon: S.tuyChon }));
    },
    xoaHet() {
      if (!LA_WP) {
        Object.values(KHOA).forEach((k) => localStorage.removeItem(k));
        return;
      }
      return banh(API.call('xoaHet', {}));
    },
  };

  /** Chạy lời gọi máy chủ, hiện trạng thái lưu và báo lỗi thay vì im lặng mất dữ liệu. */
  function banh(p, tiep) {
    dongBo('dang', 'Đang lưu…');
    return Promise.resolve(p)
      .then(() => (tiep ? tiep() : null))
      .then(() => dongBo('xong', 'Đã lưu lên máy chủ'))
      .catch((e) => {
        dongBo('loi', 'Chưa lưu được');
        alert('Không lưu được lên máy chủ:\n' + (e && e.message) +
          '\n\nThay đổi vừa rồi chỉ có trên màn hình này. Thử lại hoặc tải lại trang.');
        throw e;
      });
  }

  /**
   * Đẩy danh sách lên máy chủ THEO MẺ. File thật ~3.400 khoản ≈ 2,5 MB JSON, mà post_max_size
   * của hosting hay đặt 2–8 MB — gửi một phát là có nơi cắt cụt request rồi ghi đè dữ liệu cũ
   * bằng một danh sách rỗng mà không báo lỗi gì. Mẻ cuối máy chủ mới gộp và thay.
   */
  const MOI_ME = 600;
  async function dayDanhSach() {
    const ds = S.recs || [];
    if (!ds.length) {
      return API.call('datDanhSach', { recs: [], nhatKy: S.nhatKy, dau: true, cuoi: true });
    }
    for (let i = 0; i < ds.length; i += MOI_ME) {
      const cuoi = i + MOI_ME >= ds.length;
      await API.call('datDanhSach', {
        recs: ds.slice(i, i + MOI_ME),
        nhatKy: cuoi ? S.nhatKy : [],
        dau: i === 0,
        cuoi: cuoi,
      });
      if (!cuoi) dongBo('dang', 'Đang lưu… ' + (i + MOI_ME) + '/' + ds.length);
    }
  }

  let hen = null;
  function dongBo(trangThai, chu) {
    const n = $('syncBox');
    if (!n) return;
    n.hidden = false;
    n.className = 'sync ' + trangThai;
    n.textContent = chu;
    if (hen) clearTimeout(hen);
    if (trangThai === 'xong') hen = setTimeout(() => { n.className = 'sync'; n.textContent = tenNguoi(); }, 2500);
  }
  function tenNguoi() {
    const u = API && API.currentUser && API.currentUser();
    return u ? u.ten + ' · ' + u.vai : '';
  }
  /** Ai đang đánh dấu: bản WordPress lấy từ phiên đăng nhập, bản tĩnh lấy ô "Người lập biểu". */
  function tenDangDung() {
    const u = API && API.currentUser && API.currentUser();
    return (u && u.ten) || S.congTy.nguoiLap || '';
  }

  /* ===================== Tính lại ===================== */

  function tinhLai() {
    DONG = E.dungDong(S.recs, S.theoDoi, S.tuyChon);
    ganLoc();
    /* Doi chieu nam NGAY TRONG tinhLai(), khong phai mot loi goi rieng o may cho.
       Moi luot doi so (danh dau tay, nhap lai Excel, xoa trang thai) deu di qua day — de no
       ngoai thi som muon co mot duong quen goi, va man doi chieu bay ra mot ket qua dung cho
       mot cuon so khong con ton tai. Do la kieu sai khong keu tieng nao. */
    tinhLaiSaoKe();
  }

  function ganLoc() {
    DONG_LOC = E.loc(DONG, S.loc);
    sapXep(DONG_LOC);
  }

  const KHOA_SAP = {
    ky: (x) => x.rec.ky,
    bp: (x) => x.rec.bp,
    noiDung: (x) => x.rec.noiDung,
    soTien: (x) => x.rec.soTien,
    thuHuong: (x) => x.rec.thuHuong,
    han: (x) => x.han || '9999-99-99',
    trangThai: (x) => x.trangThai,
    ngayDi: (x) => x.ngayDi || '9999-99-99',
    tkcty: (x) => x.rec.taiKhoanCty,
  };

  function sapXep(ds) {
    const f = KHOA_SAP[S.sapXep.cot] || KHOA_SAP.han;
    const dau = S.sapXep.giam ? -1 : 1;
    ds.sort((a, b) => {
      const va = f(a), vb = f(b);
      if (va === vb) return a.rec.ky < b.rec.ky ? 1 : -1;
      if (typeof va === 'number' && typeof vb === 'number') return (va - vb) * dau;
      return (String(va) < String(vb) ? -1 : 1) * dau;
    });
  }

  /* ===================== Vẽ lại toàn trang ===================== */

  function veLai() {
    const coDL = S.recs.length > 0;
    $('viewEmpty').hidden = coDL;
    ['tong', 'unc', 'congno', 'saoke', 'mau', 'nhatky'].forEach((t) => {
      const v = $('view' + { tong: 'Tong', unc: 'UNC', congno: 'CongNo', saoke: 'SaoKe', mau: 'Mau', nhatky: 'NhatKy' }[t]);
      if (v) v.hidden = !coDL || S.tab !== t;
    });
    document.querySelectorAll('#tabs button').forEach((b) => {
      b.setAttribute('aria-selected', String(b.dataset.tab === S.tab));
    });

    if (!coDL) {
      $('brandSub').textContent = 'Chưa có dữ liệu — nhập file Excel để bắt đầu';
      $('pillUNC').hidden = true;
      $('pillSaoKe').hidden = true;
      return;
    }
    /* Huy hiệu trên tab đếm thứ ĐANG CHỜ NGƯỜI: khớp chắc chưa bấm áp dụng, cộng mấy dòng
       lệch. Đếm cả mấy nhóm kia thì con số lúc nào cũng to và thôi có nghĩa. */
    const cho = S.saoKe.kq ? S.saoKe.kq.tom.chac + S.saoKe.kq.tom.lech : 0;
    $('pillSaoKe').hidden = !cho;
    $('pillSaoKe').textContent = cho;

    const soQuaHan = DONG.filter((d) => d.quaHan && d.rec.loai !== 'ghichu').length;
    $('pillUNC').hidden = !soQuaHan;
    $('pillUNC').textContent = soQuaHan;
    const kys = Array.from(new Set(S.recs.map((r) => r.ky).filter(Boolean))).sort();
    $('brandSub').textContent =
      S.recs.length.toLocaleString('vi-VN') + ' khoản · ' +
      (kys.length ? kys[0] + ' → ' + kys[kys.length - 1] : '') +
      ' · ' + tienGon(DONG.reduce((a, d) => a + d.rec.soTien, 0));

    if (S.tab === 'tong') veTong();
    if (S.tab === 'unc') veUNC();
    if (S.tab === 'congno') veCongNo();
    if (S.tab === 'saoke') veSaoKe();
    if (S.tab === 'mau') veMau();
    if (S.tab === 'nhatky') veNhatKy();
  }

  /* ===================== Tab: Tổng quan ===================== */

  function veTong() {
    const sel = $('tongKy');
    const kys = Array.from(new Set(DONG.map((d) => d.rec.ky).filter(Boolean))).sort().reverse();
    if (sel.dataset.sig !== kys.join()) {
      sel.dataset.sig = kys.join();
      sel.innerHTML = '';
      sel.appendChild(el('option', { value: '' }, document.createTextNode('Tất cả các kỳ')));
      kys.forEach((k) => sel.appendChild(el('option', { value: k }, document.createTextNode(k))));
      sel.value = S.tongKy;
    }

    const ds = DONG.filter((d) => d.rec.loai !== 'ghichu' && (!S.tongKy || d.rec.ky === S.tongKy));
    const t = E.congDon(ds);
    $('tongPhamVi').textContent = S.tongKy
      ? 'Kỳ ' + S.tongKy + ' · ' + ds.length + ' khoản'
      : 'Tất cả ' + ds.length + ' khoản (đã trừ ghi chú nộp / cấn trừ)';

    const kpi = [
      { l: 'Tổng phải chi', v: tienGon(t.tongTien), s: t.soDong + ' khoản', c: '' },
      { l: 'Đã đi tiền', v: tienGon(t.daChi), s: t.theoTrangThai.da_di.soDong + ' khoản', c: 'ok' },
      { l: 'Chưa đi tiền', v: tienGon(t.conNo), s: (t.theoTrangThai.chua_lenh.soDong + t.theoTrangThai.da_lenh.soDong) + ' khoản', c: 'warn', loc: { trangThai: ['chua_lenh', 'da_lenh'] } },
      { l: 'Quá hạn', v: tienGon(t.tienQuaHan), s: t.quaHan + ' khoản', c: t.quaHan ? 'danger' : '', loc: { chiQuaHan: true } },
      { l: 'Chưa lập lệnh', v: tienGon(t.theoTrangThai.chua_lenh.tien), s: t.theoTrangThai.chua_lenh.soDong + ' khoản', c: 'info', loc: { trangThai: ['chua_lenh'] } },
    ];
    const box = $('tongKPI');
    box.innerHTML = '';
    kpi.forEach((k) => {
      const n = el('div', { class: 'kpi ' + k.c + (k.loc ? ' clickable' : '') });
      n.innerHTML = '<div class="k-label">' + esc(k.l) + '</div><div class="k-value">' + esc(k.v) +
        '</div><div class="k-sub">' + esc(k.s) + '</div>';
      if (k.loc) {
        n.onclick = () => {
          S.loc = Object.assign({}, k.loc, S.tongKy ? { ky: [S.tongKy] } : {});
          S.tab = 'unc';
          S.soHien = 200;
          dongBoLocLenForm();
          ganLoc();
          veLai();
        };
      }
      box.appendChild(n);
    });

    // Cảnh báo
    const cb = E.canhBao(ds, { homNay: E.homNayISO() });
    const ul = $('tongCanhBao');
    ul.innerHTML = '';
    cb.slice(0, 25).forEach((c) => {
      const li = el('li', { class: c.muc });
      // <button>, không phải <span>: đây là thứ bấm được, nên nó phải tới được bằng phím Tab
      // và nhận được Enter/Space mà không cần ta tự viết lại. Kế toán dò danh sách này bằng
      // bàn phím là chuyện thường.
      li.innerHTML = esc(c.text) + '<button type="button" class="go">xem →</button>';
      li.querySelector('.go').onclick = () => {
        const d = DONG.find((x) => x.rec.id === c.id);
        if (d) moNgan(d);
      };
      ul.appendChild(li);
    });
    if (!cb.length) ul.appendChild(el('li', { class: 'tin', text: 'Không có gì phải xử lý gấp.' }));
    $('tongCanhBaoThem').hidden = cb.length <= 25;
    $('tongCanhBaoThem').textContent = cb.length > 25 ? 'Và ' + (cb.length - 25) + ' mục khác — xem đầy đủ trong file Excel xuất ra (tab Cảnh báo).' : '';

    // Biểu đồ theo kỳ
    const theoKy = E.theoKy(DONG.filter((d) => d.rec.loai !== 'ghichu')).slice(0, 18);
    const max = Math.max(1, ...theoKy.map((o) => o.tongTien));
    const bd = $('tongBieuDo');
    bd.innerHTML = '';
    theoKy.forEach((o) => {
      const tre = o.tienQuaHan;
      const chua = Math.max(0, o.conNo - tre);
      const tl = (v) => (v / max) * 100 + '%';
      const row = el('div', { class: 'bar-row' });
      row.innerHTML =
        '<span>' + esc(o.khoa) + '</span>' +
        '<span class="bar-track">' +
        '<i class="bar-fill paid" style="width:' + tl(o.daChi) + '"></i>' +
        '<i class="bar-fill due" style="width:' + tl(chua) + '"></i>' +
        '<i class="bar-fill late" style="width:' + tl(tre) + '"></i>' +
        '</span>' +
        '<span class="num" style="text-align:right">' + esc(tienGon(o.tongTien)) + '</span>';
      row.style.cursor = 'pointer';
      row.onclick = () => {
        S.loc = { ky: [o.khoa] };
        S.tab = 'unc';
        S.soHien = 200;
        dongBoLocLenForm();
        ganLoc();
        veLai();
      };
      bd.appendChild(row);
    });
  }

  /* ===================== Tab: Ủy nhiệm chi ===================== */

  const COT = [
    { k: '', ten: '', sort: false, rong: '32px' },
    { k: 'ky', ten: 'Kỳ' },
    { k: 'bp', ten: 'Bộ phận' },
    { k: 'noiDung', ten: 'Nội dung / đơn vị thụ hưởng' },
    { k: 'soTien', ten: 'Số tiền', num: true },
    { k: 'tkcty', ten: 'TK chi' },
    { k: 'han', ten: 'Hạn đi' },
    { k: 'trangThai', ten: 'Trạng thái' },
    { k: 'ngayDi', ten: 'Ngày đi' },
  ];

  function veUNC() {
    dungChonLoc();

    const th = $('uncTHead');
    th.innerHTML = '';
    COT.forEach((c) => {
      const n = el('th', { class: (c.num ? 'num ' : '') + (c.sort === false ? 'nosort' : '') });
      n.innerHTML = esc(c.ten) + (S.sapXep.cot === c.k ? ' <span class="arr">' + (S.sapXep.giam ? '▼' : '▲') + '</span>' : '');
      if (c.rong) n.style.width = c.rong;
      if (c.sort !== false) {
        n.onclick = () => {
          if (S.sapXep.cot === c.k) S.sapXep.giam = !S.sapXep.giam;
          else S.sapXep = { cot: c.k, giam: c.num };
          ganLoc();
          veLai();
        };
      }
      th.appendChild(n);
    });

    const t = E.congDon(DONG_LOC);
    $('uncTomTat').innerHTML =
      '<b>' + t.soDong.toLocaleString('vi-VN') + '</b> khoản · tổng <b>' + esc(tien(t.tongTien)) + ' ₫</b>' +
      ' · đã đi <b style="color:var(--ok)">' + esc(tien(t.daChi)) + '</b>' +
      ' · chưa đi <b style="color:var(--warn)">' + esc(tien(t.conNo)) + '</b>' +
      (t.quaHan ? ' · quá hạn <b style="color:var(--danger)">' + t.quaHan + ' khoản / ' + esc(tien(t.tienQuaHan)) + '</b>' : '');

    const tb = $('uncTBody');
    tb.innerHTML = '';
    const hien = DONG_LOC.slice(0, S.soHien);
    hien.forEach((d) => tb.appendChild(veDong(d)));
    if (!hien.length) {
      const tr = el('tr');
      tr.appendChild(el('td', { colspan: COT.length, class: 'empty', text: 'Không có khoản nào khớp bộ lọc.' }));
      tb.appendChild(tr);
    }

    const tf = $('uncTFoot');
    tf.innerHTML = '';
    if (hien.length) {
      const tr = el('tr');
      tr.appendChild(el('td', { colspan: 4, text: 'Tổng ' + t.soDong.toLocaleString('vi-VN') + ' khoản' }));
      tr.appendChild(el('td', { class: 'num', text: tien(t.tongTien) }));
      tr.appendChild(el('td', { colspan: 4 }));
      tf.appendChild(tr);
    }

    const daChonHet = DONG_LOC.length > 0 && DONG_LOC.every((d) => S.chon.has(d.rec.id));
    $('btnChonHet').textContent = daChonHet
      ? 'Bỏ chọn tất cả'
      : 'Chọn tất cả ' + DONG_LOC.length.toLocaleString('vi-VN') + ' khoản đang lọc';
    $('btnChonHet').disabled = !DONG_LOC.length;

    $('uncPhanTrang').innerHTML = '';
    if (DONG_LOC.length > S.soHien) {
      const b = el('button', { class: 'btn sm', text: 'Xem thêm 200 khoản (còn ' + (DONG_LOC.length - S.soHien).toLocaleString('vi-VN') + ')' });
      b.onclick = () => { S.soHien += 200; veLai(); };
      $('uncPhanTrang').appendChild(b);
    }
    capNhatBulk();
  }

  const NHAN_LOAI = { unc: 'UNC', thue: 'Thuê MB', tienmat: 'Tiền mặt', ghichu: 'Ghi chú' };

  function veDong(d) {
    const r = d.rec;
    const tr = el('tr', { class: S.chon.has(r.id) ? 'chon' : '' });

    const tdChk = el('td');
    const chk = el('input', { type: 'checkbox' });
    chk.checked = S.chon.has(r.id);
    chk.onclick = (ev) => {
      ev.stopPropagation();
      if (chk.checked) S.chon.add(r.id); else S.chon.delete(r.id);
      tr.className = chk.checked ? 'chon' : '';
      capNhatBulk();
    };
    tdChk.appendChild(chk);
    tr.appendChild(tdChk);

    tr.appendChild(el('td', { text: r.ky }));
    tr.appendChild(el('td', { html: esc(r.bp || '—') + (r.loai !== 'unc' ? '<span class="sub tag-loai">' + esc(NHAN_LOAI[r.loai]) + '</span>' : '') }));

    const tdND = el('td', { class: 'rowlink' });
    tdND.innerHTML =
      '<span class="trunc" title="' + esc(r.noiDung) + '">' + esc(r.noiDung || '—') + '</span>' +
      (r.thuHuong ? '<span class="sub trunc" title="' + esc(r.thuHuong) + '">' + esc(r.thuHuong) + '</span>' : '');
    tr.appendChild(tdND);

    tr.appendChild(el('td', { class: 'num', text: tien(r.soTien) }));
    tr.appendChild(el('td', { text: { cu: 'K&H cũ', moi: 'K&H mới', smart: 'Smart' }[r.taiKhoanCty] || '—' }));

    const tdHan = el('td');
    const nhanHan = r.hanDi && r.hanDi.nhan;
    // Chỉ hiện chữ gốc khi nó khác ngày đã chuẩn hoá ("UNC 1/9"), không lặp lại "1/3/2026".
    const hienGoc = nhanHan && r.hanDi.iso && nhanHan !== ngay(r.hanDi.iso);
    tdHan.innerHTML = esc(ngay(d.han)) + (hienGoc ? '<span class="sub">' + esc(nhanHan) + '</span>' : '');
    tr.appendChild(tdHan);

    const st = E.TRANG_THAI.find((x) => x.value === d.trangThai) || { mau: 'muted' };
    const tdTT = el('td');
    tdTT.innerHTML = '<span class="tag ' + st.mau + '">' + esc(d.nhanTrangThai) + '</span>' +
      (d.quaHan ? '<span class="sub"><span class="tag late">trễ ' + d.treNgay + ' ngày</span></span>' : '');
    tr.appendChild(tdTT);

    tr.appendChild(el('td', { html: esc(ngay(d.ngayDi)) + ((d.td && d.td.soUNC) ? '<span class="sub">' + esc(d.td.soUNC) + '</span>' : '') }));

    tr.onclick = () => moNgan(d);
    tr.style.cursor = 'pointer';
    return tr;
  }

  /* ===== Bộ lọc ===== */

  function dungChonLoc() {
    if ($('fKy').dataset.dung) return dongBoLocLenForm();
    const dsKy = Array.from(new Set(S.recs.map((r) => r.ky).filter(Boolean))).sort().reverse();
    const dsBP = Array.from(new Set(S.recs.map((r) => r.bp).filter(Boolean))).sort();
    nhoi('fKy', [['', 'Tất cả']].concat(dsKy.map((k) => [k, k])));
    nhoi('fBP', [['', 'Tất cả']].concat(dsBP.map((k) => [k, k])));
    nhoi('fTrangThai', [['', 'Tất cả']].concat(E.TRANG_THAI.map((t) => [t.value, t.label])));
    nhoi('fTKCty', [['', 'Tất cả'], ['cu', 'K&H cũ'], ['moi', 'K&H mới'], ['smart', 'Smart'], ['__trong', 'Chưa ghi']]);
    nhoi('fLoai', [['', 'Tất cả'], ['unc', 'Ủy nhiệm chi'], ['thue', 'Thuê mặt bằng'], ['tienmat', 'Tiền mặt'], ['ghichu', 'Ghi chú nộp / cấn trừ']]);
    $('fKy').dataset.dung = '1';
    dongBoLocLenForm();
  }

  function nhoi(id, cap) {
    const s = $(id);
    s.innerHTML = '';
    cap.forEach(([v, t]) => s.appendChild(el('option', { value: v }, document.createTextNode(t))));
  }

  function dongBoLocLenForm() {
    const l = S.loc;
    $('fTuKhoa').value = l.tuKhoa || '';
    $('fKy').value = (l.ky && l.ky.length === 1) ? l.ky[0] : '';
    $('fBP').value = (l.bp && l.bp.length === 1) ? l.bp[0] : '';
    $('fTrangThai').value = (l.trangThai && l.trangThai.length === 1) ? l.trangThai[0] : '';
    $('fTKCty').value = (l.taiKhoanCty && l.taiKhoanCty.length === 1) ? (l.taiKhoanCty[0] || '__trong') : '';
    $('fLoai').value = (l.loai && l.loai.length === 1) ? l.loai[0] : '';
    $('fTuNgay').value = l.tuNgay || '';
    $('fDenNgay').value = l.denNgay || '';
    $('fQuaHan').checked = !!l.chiQuaHan;
  }

  function docLocTuForm() {
    const v = (id) => $(id).value;
    const mang = (x) => (x ? [x] : null);
    S.loc = {
      tuKhoa: v('fTuKhoa'),
      ky: mang(v('fKy')),
      bp: mang(v('fBP')),
      trangThai: mang(v('fTrangThai')),
      taiKhoanCty: v('fTKCty') ? [v('fTKCty') === '__trong' ? '' : v('fTKCty')] : null,
      loai: mang(v('fLoai')),
      tuNgay: v('fTuNgay'),
      denNgay: v('fDenNgay'),
      chiQuaHan: $('fQuaHan').checked,
    };
    S.soHien = 200;
    ganLoc();
    veLai();
  }

  /* ===== Chọn hàng loạt ===== */

  function capNhatBulk() {
    const n = S.chon.size;
    $('bulkBar').hidden = !n;
    if (n) {
      const tong = DONG.filter((d) => S.chon.has(d.rec.id)).reduce((a, d) => a + d.rec.soTien, 0);
      $('bulkCnt').textContent = 'Đã chọn ' + n + ' khoản · ' + tien(tong) + ' ₫';
    }
  }

  function apBulk(tt) {
    if (LA_WP && !API.duocSua()) return alert('Tài khoản của anh/chị chỉ được xem, không đánh dấu được.');
    if (S.chon.size > 50 && tt !== 'xoa' &&
        !confirm('Đánh dấu "' + E.NHAN_TRANG_THAI[tt] + '" cho ' + S.chon.size + ' khoản?')) return;
    const ngayDi = $('bulkNgay').value || E.homNayISO();
    const nguoi = tenDangDung();
    const dsId = Array.from(S.chon);
    S.chon.forEach((id) => {
      if (tt === 'xoa') {
        delete S.theoDoi[id];
        return;
      }
      const cu = S.theoDoi[id] || {};
      S.theoDoi[id] = Object.assign({}, cu, {
        trangThai: tt,
        ngayDi: tt === 'da_di' ? ngayDi : (tt === 'huy' ? '' : cu.ngayDi || ''),
        nguoiCapNhat: nguoi,
        capNhatLuc: new Date().toISOString(),
      });
    });
    Kho.theoDoi(tt === 'xoa' ? null : dsId, tt === 'xoa' ? dsId : null);
    S.chon.clear();
    tinhLai();
    veLai();
  }

  /* ===================== Ngăn chi tiết ===================== */

  let nganHienTai = null;

  function moNgan(d) {
    nganHienTai = d;
    const r = d.rec;
    $('drTen').textContent = r.noiDung || '(không có nội dung)';
    const td = d.td || {};
    const nhanTK = { cu: 'K&H cũ', moi: 'K&H mới', smart: 'Smart' };

    const than = $('drThan');
    than.innerHTML =
      '<dl class="dl">' +
      '<dt>Số tiền</dt><dd><b style="font-size:16px">' + esc(tien(r.soTien)) + ' ₫</b><br>' +
      '<span class="hint">' + esc(E.docSoThanhChu(r.soTien)) + '</span></dd>' +
      '<dt>Kỳ</dt><dd>' + esc(r.ky) + ' · <span class="tag-loai">' + esc(NHAN_LOAI[r.loai]) + '</span></dd>' +
      '<dt>Bộ phận</dt><dd>' + esc(r.bpGoc || '—') + (r.khoanMuc ? ' <span class="hint">(' + esc(r.khoanMuc) + ')</span>' : '') + '</dd>' +
      '<dt>Đơn vị thụ hưởng</dt><dd>' + esc(r.thuHuong || '—') + '</dd>' +
      '<dt>Số tài khoản</dt><dd class="mono">' + esc(r.soTaiKhoan || '—') + '</dd>' +
      '<dt>Ngân hàng</dt><dd>' + esc(r.nganHangTH || '—') + '</dd>' +
      '<dt>Tài khoản chi</dt><dd>' + esc(nhanTK[r.taiKhoanCty] || '(chưa ghi)') + '</dd>' +
      '<dt>Ngày cần đi tiền</dt><dd>' + esc(r.hanDi.nhan || '—') + (r.hanDi.iso ? ' → <b>' + esc(ngay(r.hanDi.iso)) + '</b>' : '') + '</dd>' +
      '<dt>Ngày tạo lệnh</dt><dd>' + esc(r.ngayLenh.nhan || '—') + (r.ngayLenh.iso ? ' → <b>' + esc(ngay(r.ngayLenh.iso)) + '</b>' : '') + '</dd>' +
      (r.ghiChu ? '<dt>Ghi chú trong file</dt><dd>' + esc(r.ghiChu) + '</dd>' : '') +
      (r.link ? '<dt>Hoá đơn</dt><dd><a href="' + esc(r.link) + '" target="_blank" rel="noopener">mở link</a></dd>' : '') +
      '<dt>Nguồn</dt><dd class="hint">sheet "' + esc(r.nguon) + '", dòng ' + r.dong + '</dd>' +
      '</dl>' +
      (d.quaHan ? '<p><span class="tag late">Quá hạn ' + d.treNgay + ' ngày</span></p>' : '') +
      '<hr><h3>Kế toán đánh dấu</h3>' +
      '<label class="field"><span>Trạng thái</span><select id="drTT">' +
      E.TRANG_THAI.map((t) => '<option value="' + t.value + '"' + (d.trangThai === t.value ? ' selected' : '') + '>' + esc(t.label) + '</option>').join('') +
      '</select></label>' +
      '<label class="field"><span>Ngày tiền thực đi</span><input type="date" id="drNgay" value="' + esc(td.ngayDi || '') + '"></label>' +
      '<label class="field"><span>Số chứng từ / UNC</span><input id="drSo" value="' + esc(td.soUNC || '') + '"></label>' +
      '<label class="field"><span>Người cập nhật</span><input id="drNguoi" value="' + esc(td.nguoiCapNhat || S.congTy.nguoiLap || '') + '"></label>' +
      '<label class="field"><span>Ghi chú</span><textarea id="drGhiChu">' + esc(td.ghiChu || '') + '</textarea></label>' +
      (td.capNhatLuc ? '<p class="hint">Cập nhật lúc ' + esc(new Date(td.capNhatLuc).toLocaleString('vi-VN')) + '</p>' : '') +
      '<div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:6px">' +
      '<button class="btn primary" id="drLuu">Lưu</button>' +
      '<button class="btn" id="drBoDanhDau">Bỏ đánh dấu</button>' +
      '<button class="btn" id="drInUNC">🖨 In Ủy nhiệm chi</button>' +
      '<button class="btn" id="drInDeNghi">🖨 Giấy đề nghị TT</button>' +
      '</div>';

    $('drLuu').onclick = () => {
      const tt = $('drTT').value;
      S.theoDoi[r.id] = {
        trangThai: tt,
        ngayDi: $('drNgay').value || (tt === 'da_di' ? E.homNayISO() : ''),
        soUNC: $('drSo').value.trim(),
        nguoiCapNhat: $('drNguoi').value.trim(),
        ghiChu: $('drGhiChu').value.trim(),
        capNhatLuc: new Date().toISOString(),
      };
      Kho.theoDoi([r.id]);
      dongNgan();
      tinhLai();
      veLai();
    };
    $('drBoDanhDau').onclick = () => {
      delete S.theoDoi[r.id];
      Kho.theoDoi(null, [r.id]);
      dongNgan();
      tinhLai();
      veLai();
    };
    $('drInUNC').onclick = () => moMau('unc', d);
    $('drInDeNghi').onclick = () => moMau('denghi', d);

    $('drawerBg').hidden = false;
  }

  function dongNgan() {
    // Chỉ ẩn ngăn. KHÔNG xoá nganHienTai: khoản vừa xem chính là ngữ cảnh
    // để dựng Ủy nhiệm chi / Giấy đề nghị thanh toán ở tab Mẫu biểu.
    $('drawerBg').hidden = true;
  }

  /* ===================== Tab: Công nợ ===================== */

  function veCongNo() {
    if (!$('cnDenNgay').value) $('cnDenNgay').value = S.cnDenNgay || E.homNayISO();
    S.cnDenNgay = $('cnDenNgay').value;
    $('cnGom').value = S.cnGom;

    const ds = DONG.filter((d) => d.rec.loai !== 'ghichu');
    // Tính lại trạng thái theo mốc "tính đến ngày" để tuổi nợ đúng thời điểm đối chiếu.
    const theoMoc = E.dungDong(ds.map((d) => d.rec), S.theoDoi, Object.assign({}, S.tuyChon, { homNay: S.cnDenNgay }));

    let nhom;
    let tenCot;
    if (S.cnGom === 'ncc') { nhom = E.congNoNCC(theoMoc); tenCot = 'Nhà cung cấp'; }
    else if (S.cnGom === 'bp') { nhom = E.theoBP(theoMoc); tenCot = 'Bộ phận'; }
    else if (S.cnGom === 'tkcty') { nhom = E.theoTaiKhoanCty(theoMoc); tenCot = 'Tài khoản chi'; }
    else { nhom = E.theoKy(theoMoc); tenCot = 'Kỳ'; }

    const tuoi = E.bangTuoiNo(theoMoc);
    const t = E.congDon(theoMoc);
    const kbox = $('cnKPI');
    kbox.innerHTML = '';
    [
      { l: 'Tổng phát sinh', v: tienGon(t.tongTien), c: '' },
      { l: 'Đã thanh toán', v: tienGon(t.daChi), c: 'ok' },
      { l: 'Còn phải trả', v: tienGon(t.conNo), c: 'warn' },
      { l: 'Trong đó quá hạn', v: tienGon(t.tienQuaHan), c: t.quaHan ? 'danger' : '' },
      { l: 'Quá hạn trên 90 ngày', v: tienGon(tuoi.qh90), c: tuoi.qh90 ? 'danger' : '' },
    ].forEach((k) => {
      const n = el('div', { class: 'kpi ' + k.c });
      n.innerHTML = '<div class="k-label">' + esc(k.l) + '</div><div class="k-value">' + esc(k.v) + '</div>';
      kbox.appendChild(n);
    });

    const cot = E.MOC_TUOI_NO;
    const th = $('cnTHead');
    th.innerHTML = '<th class="nosort">' + esc(tenCot) + '</th><th class="nosort num">Số khoản</th>' +
      '<th class="nosort num">Tổng phát sinh</th><th class="nosort num">Đã thanh toán</th>' +
      '<th class="nosort num">Còn phải trả</th>' +
      cot.map((m) => '<th class="nosort num">' + esc(m.label) + '</th>').join('') +
      '<th class="nosort num">Không rõ hạn</th>';

    const tb = $('cnTBody');
    tb.innerHTML = '';
    nhom.slice(0, 400).forEach((o) => {
      const tr = el('tr', { class: 'rowlink' });
      tr.innerHTML =
        '<td><span class="trunc" title="' + esc(o.nhan) + '">' + esc(o.nhan) + '</span>' +
        (o.treNhatNgay ? '<span class="sub"><span class="tag late">trễ nhất ' + o.treNhatNgay + ' ngày</span></span>' : '') + '</td>' +
        '<td class="num">' + o.soDong + '</td>' +
        '<td class="num">' + esc(tien(o.tongTien)) + '</td>' +
        '<td class="num" style="color:var(--ok)">' + esc(tien(o.daChi)) + '</td>' +
        '<td class="num"><b>' + esc(tien(o.conNo)) + '</b></td>' +
        cot.map((m) => '<td class="num"' + (m.key === 'qh90' && o.tuoi[m.key] ? ' style="color:var(--danger)"' : '') + '>' +
          (o.tuoi[m.key] ? esc(tien(o.tuoi[m.key])) : '') + '</td>').join('') +
        '<td class="num">' + (o.tuoi.khongHan ? esc(tien(o.tuoi.khongHan)) : '') + '</td>';
      tr.onclick = () => moNganNCC(o, theoMoc);
      tb.appendChild(tr);
    });
    if (!nhom.length) {
      tb.innerHTML = '<tr><td colspan="' + (6 + cot.length) + '" class="empty">Không có dữ liệu.</td></tr>';
    }

    const tf = $('cnTFoot');
    tf.innerHTML = '<tr><td>TỔNG CỘNG</td><td class="num">' + theoMoc.length + '</td>' +
      '<td class="num">' + esc(tien(t.tongTien)) + '</td>' +
      '<td class="num">' + esc(tien(t.daChi)) + '</td>' +
      '<td class="num">' + esc(tien(t.conNo)) + '</td>' +
      cot.map((m) => '<td class="num">' + esc(tien(tuoi[m.key])) + '</td>').join('') +
      '<td class="num">' + esc(tien(tuoi.khongHan)) + '</td></tr>';
  }

  function moNganNCC(o, theoMoc) {
    const cua = theoMoc.filter((d) => layKhoaGom(d) === o.khoa);
    S.nccDangChon = { nhom: o, dong: cua };
    $('drTen').textContent = o.nhan;
    const than = $('drThan');
    than.innerHTML =
      '<dl class="dl">' +
      '<dt>Số khoản</dt><dd>' + o.soDong + '</dd>' +
      '<dt>Tổng phát sinh</dt><dd><b>' + esc(tien(o.tongTien)) + ' ₫</b></dd>' +
      '<dt>Đã thanh toán</dt><dd style="color:var(--ok)">' + esc(tien(o.daChi)) + ' ₫</dd>' +
      '<dt>Còn phải trả</dt><dd><b style="font-size:16px">' + esc(tien(o.conNo)) + ' ₫</b><br>' +
      '<span class="hint">' + esc(E.docSoThanhChu(o.conNo)) + '</span></dd>' +
      (o.quaHan ? '<dt>Quá hạn</dt><dd style="color:var(--danger)">' + o.quaHan + ' khoản · ' + esc(tien(o.tienQuaHan)) + ' ₫</dd>' : '') +
      (o.taiKhoan && o.taiKhoan.length ? '<dt>Tài khoản</dt><dd class="mono" style="font-size:12px">' + o.taiKhoan.map(esc).join('<br>') + '</dd>' : '') +
      '</dl>' +
      '<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px">' +
      '<button class="btn primary" id="nccDoiChieu">🖨 Biên bản đối chiếu</button>' +
      '<button class="btn" id="nccBangKe">🖨 Bảng kê công nợ</button>' +
      '<button class="btn" id="nccSo">🖨 Sổ chi tiết 331</button>' +
      '<button class="btn" id="nccLoc">Xem các khoản →</button>' +
      '</div>' +
      '<hr><h3>' + cua.length + ' khoản</h3>' +
      '<div class="tbl-wrap" style="max-height:44vh"><table><thead><tr>' +
      '<th class="nosort">Kỳ</th><th class="nosort">Nội dung</th><th class="nosort num">Số tiền</th>' +
      '<th class="nosort">Hạn</th><th class="nosort">Trạng thái</th></tr></thead><tbody>' +
      cua.slice().sort((a, b) => (a.han || '') < (b.han || '') ? 1 : -1).map((x) =>
        '<tr><td>' + esc(x.rec.ky) + '</td>' +
        '<td><span class="trunc" style="max-width:220px" title="' + esc(x.rec.noiDung) + '">' + esc(x.rec.noiDung) + '</span></td>' +
        '<td class="num">' + esc(tien(x.rec.soTien)) + '</td>' +
        '<td>' + esc(ngay(x.han)) + '</td>' +
        '<td><span class="tag ' + ((E.TRANG_THAI.find((s) => s.value === x.trangThai) || {}).mau || 'muted') + '">' +
        esc(x.nhanTrangThai) + '</span>' + (x.quaHan ? ' <span class="tag late">' + x.treNgay + 'n</span>' : '') + '</td></tr>'
      ).join('') +
      '</tbody></table></div>';

    $('nccDoiChieu').onclick = () => moMau('doichieu');
    $('nccBangKe').onclick = () => moMau('bangke');
    $('nccSo').onclick = () => moMau('sotheodoi');
    $('nccLoc').onclick = () => {
      S.loc = S.cnGom === 'ncc' ? { ncc: [o.khoa] }
        : S.cnGom === 'bp' ? { bp: [o.khoa] }
        : S.cnGom === 'tkcty' ? { taiKhoanCty: [o.khoa] }
        : { ky: [o.khoa] };
      S.tab = 'unc';
      S.soHien = 200;
      dongNgan();
      dongBoLocLenForm();
      ganLoc();
      veLai();
    };
    $('drawerBg').hidden = false;
  }

  function layKhoaGom(d) {
    if (S.cnGom === 'ncc') return d.rec.thuHuongKhoa || E.KHONG_TEN;
    if (S.cnGom === 'bp') return d.rec.bp || '(chưa ghi)';
    if (S.cnGom === 'tkcty') return d.rec.taiKhoanCty;
    return d.rec.ky;
  }

  /* ===================== Tab: Mẫu biểu ===================== */

  function veMau() {
    const box = $('mauDS');
    box.innerHTML = '';
    Mau.DS_MAU.forEach((m) => {
      const c = el('div', { class: 'mau-card', role: 'button', tabindex: '0', 'aria-pressed': String(S.mauDangChon === m.ma) });
      c.innerHTML = '<span class="m-ten">' + esc(m.ten) + '</span><span class="m-ma">' + esc(m.maSo) + '</span>' +
        '<span class="m-mo">' + esc(m.mo) + '</span>';
      const chon = () => { S.mauDangChon = m.ma; veMau(); };
      c.onclick = chon;
      c.onkeydown = (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); chon(); } };
      box.appendChild(c);
    });

    const ctx = nguCanhMau();
    $('mauNguCanh').innerHTML =
      'Đang chọn: ' +
      (nganHienTai || ctx.dong ? '<b>khoản</b> “' + esc((ctx.dong && ctx.dong.rec.noiDung) || '').slice(0, 60) + '”' : '<i>chưa chọn khoản nào</i>') +
      ' · ' +
      (ctx.ncc ? '<b>nhà cung cấp</b> “' + esc(ctx.ncc.nhan) + '”' : '<i>chưa chọn nhà cung cấp</i>') +
      ' · bộ lọc hiện có <b>' + DONG_LOC.length.toLocaleString('vi-VN') + '</b> khoản.';

    $('mauXem').innerHTML = S.mauDangChon
      ? Mau.dung(S.mauDangChon, ctx)
      : '<div class="empty">Chọn một mẫu ở trên.</div>';
  }

  function nguCanhMau() {
    return {
      congTy: S.congTy,
      ngayLap: E.homNayISO(),
      nguoiLap: S.congTy.nguoiLap,
      dong: nganHienTai,
      ncc: S.nccDangChon && S.nccDangChon.nhom,
      dongCuaNCC: S.nccDangChon && S.nccDangChon.dong,
      dsLoc: DONG_LOC,
      denNgay: S.cnDenNgay || E.homNayISO(),
      moTaLoc: moTaLoc(),
      duDauKy: 0,
    };
  }

  function moTaLoc() {
    const l = S.loc;
    const v = [];
    if (l.ky && l.ky.length) v.push('kỳ ' + l.ky.join(', '));
    if (l.bp && l.bp.length) v.push('bộ phận ' + l.bp.join(', '));
    if (l.trangThai && l.trangThai.length) v.push(l.trangThai.map((t) => E.NHAN_TRANG_THAI[t]).join(', '));
    if (l.chiQuaHan) v.push('chỉ khoản quá hạn');
    if (l.tuKhoa) v.push('tìm “' + l.tuKhoa + '”');
    return v.length ? v.join(' · ') : 'Toàn bộ danh sách';
  }

  function moMau(ma, d) {
    if (d) nganHienTai = d;
    S.mauDangChon = ma;
    S.tab = 'mau';
    dongNgan();
    veLai();
    window.scrollTo(0, 0);
  }

  /* ===================== Tab: Nhật ký ===================== */

  function veNhatKy() {
    const ul = $('nhatKyDS');
    ul.innerHTML = '';
    const map = { ok: 'tin', canh: 'canh', loi: 'loi', tin: 'tin' };
    (S.nhatKy || []).forEach((n) => ul.appendChild(el('li', { class: map[n.muc] || 'tin', text: n.text })));
    if (!S.nhatKy.length) ul.appendChild(el('li', { class: 'tin', text: 'Chưa nhập file nào.' }));
  }

  /* ===================== Đối chiếu sao kê ===================== */

  /**
   * Năm nhóm, mỗi nhóm một câu giải thích NGẮN nhưng nói đúng việc người đọc phải làm.
   *
   * ⚠️ HAI NHÓM CUỐI MỚI LÀ THỨ KHÓ THẤY NHẤT, và cố ý đặt cuối chứ không giấu đi:
   *    "ngân hàng trừ mà sổ không có" là tiền đã ra khỏi tài khoản mà không ai đề nghị chi;
   *    "sổ có mà ngân hàng chưa trừ" là khoản còn phải trả thật, sắp tới hạn.
   */
  const SK_NHOM = {
    chac: {
      ten: 'Khớp chắc',
      mo: 'Số tiền khớp đúng đến đồng, SỐ TÀI KHOẢN người thụ hưởng khớp, ngày nằm trong cửa sổ, ' +
        'và chỉ có một ứng viên. Bấm nút ở trên là bật "đã đi tiền" cho cả nhóm.',
    },
    can: {
      ten: 'Cần người nhìn',
      mo: 'Khớp số tiền và tên, hoặc có nhiều khoản cùng số tiền nên máy không chọn hộ được. ' +
        'Bấm một dòng để mở khoản ấy ra rồi tự đánh dấu.',
    },
    lech: {
      ten: 'Lệch — sổ nói khác sao kê',
      mo: 'Kế toán đã đánh tay một trạng thái khác, nhưng ngân hàng vẫn trừ đúng số tiền ấy. ' +
        'Hoặc chuyển nhầm, hoặc đã trả bằng đường khác, hoặc chính lượt đánh tay kia sai — ' +
        'cả ba đều cần người xem, nên trang KHÔNG tự đổi gì.',
    },
    gdle: {
      ten: 'Ngân hàng trừ, sổ không có dòng nào',
      mo: 'Tiền đã ra khỏi tài khoản mà sổ ủy nhiệm chi không có khoản nào khớp. Thường là phí, ' +
        'lãi, thuế nộp thẳng — nhưng cũng là chỗ duy nhất lộ ra một lượt chuyển không ai đề nghị.',
    },
    dongle: {
      ten: 'Sổ có, ngân hàng chưa trừ',
      mo: 'Khoản còn phải trả mà sao kê chưa thấy lượt trừ nào. Nếu đã quá hạn thì đây là ' +
        'danh sách phải xử trước.',
    },
    xong: {
      ten: 'Đã khớp — sổ đã ghi "đã đi"',
      mo: 'Sổ ghi đã đi tiền VÀ ngân hàng có lượt trừ đúng khoản ấy. Không còn việc gì phải làm; ' +
        'giữ lại ở đây để đối chiếu xong còn kể được bao nhiêu khoản đã khớp, không phải chỉ ' +
        'kể mấy khoản còn lệch.',
    },
  };

  function veSaoKe() {
    const coSK = S.saoKe.gd.length > 0;
    $('saoKeTrong').hidden = coSK;
    $('saoKeCo').hidden = !coSK;
    $('skKhoiBang').hidden = !coSK;
    $('btnSaoKeXoa').hidden = !coSK;
    $('skTruoc').value = S.saoKe.cuaSo.truoc;
    $('skSau').value = S.saoKe.cuaSo.sau;
    if (!coSK) return;

    const kq = S.saoKe.kq;
    const t = kq.tom;
    const kpi = $('skKpi');
    kpi.innerHTML = '';
    [
      { l: 'Giao dịch tiền ra', v: t.soGD.toLocaleString('vi-VN'), s: S.saoKe.nguon, c: '' },
      { l: 'Khớp chắc — chờ bật', v: t.chac.toLocaleString('vi-VN'), s: 'bật được một lượt', c: t.chac ? 'ok' : '' },
      { l: 'Đã khớp, sổ đã ghi', v: t.xong.toLocaleString('vi-VN'), s: 'xong, không phải làm gì', c: t.xong ? 'ok' : '' },
      { l: 'Cần người nhìn', v: (t.kha + t.ngo).toLocaleString('vi-VN'), s: 'máy không chọn hộ', c: (t.kha + t.ngo) ? 'warn' : '' },
      { l: 'Lệch với sổ', v: t.lech.toLocaleString('vi-VN'), s: 'kế toán đã đánh khác', c: t.lech ? 'danger' : '' },
      { l: 'NH trừ, sổ không có', v: t.gdLe.toLocaleString('vi-VN'), s: tienGon(t.tienGDLe) + ' ₫', c: t.gdLe ? 'danger' : '' },
      { l: 'Sổ có, NH chưa trừ', v: t.dongLe.toLocaleString('vi-VN'), s: 'còn phải trả', c: t.dongLe ? 'warn' : '' },
    ].forEach((k) => {
      kpi.appendChild(el('div', { class: 'kpi' + (k.c ? ' ' + k.c : '') }, [
        el('div', { class: 'k-label', text: k.l }),
        el('div', { class: 'k-value', text: k.v }),
        el('div', { class: 'k-sub', text: k.s }),
      ]));
    });

    /* Thanh áp dụng chỉ hiện khi CÓ cái để áp — một cái nút bấm vào không xảy ra gì thì
       người ta bấm vài lần rồi thôi tin cả màn. */
    $('skThanh').hidden = !t.chac;
    $('skChacCnt').textContent = t.chac.toLocaleString('vi-VN') + ' khoản';

    $('skNhom').value = S.saoKe.nhom;
    const nhom = SK_NHOM[S.saoKe.nhom] || SK_NHOM.chac;
    $('skNhomTen').textContent = nhom.ten;
    $('skNhomMo').textContent = nhom.mo;
    veSaoKeBang();
  }

  /** Một ô hiện giao dịch ngân hàng — dùng chung cho mọi nhóm, để hai bên luôn đọc giống nhau. */
  function skOGD(g) {
    return '<b>' + esc(tien(g.soTien)) + ' ₫</b><br>' +
      '<span class="hint">' + esc(ngay(g.ngay)) +
      (g.maGD ? ' · ' + esc(g.maGD) : '') + '</span><br>' +
      '<span class="hint">' + esc(g.noiDung || '—') + '</span>';
  }

  function skODong(d) {
    const r = d.rec;
    return '<b>' + esc(tien(r.soTien)) + ' ₫</b><br>' +
      '<span class="hint">' + esc(r.noiDung || '—') + '</span><br>' +
      '<span class="hint">' + esc(r.thuHuong || E.KHONG_TEN) +
      (r.soTaiKhoan ? ' · ' + esc(r.soTaiKhoan) : '') + '</span>';
  }

  function veSaoKeBang() {
    const kq = S.saoKe.kq;
    const bang = $('skBang');
    const n = S.saoKe.nhom;
    let html = '';
    let ds = [];

    if (n === 'gdle') {
      ds = kq.gdKhongKhop;
      html = '<thead><tr><th>Ngày</th><th>Số tiền</th><th>Nội dung</th><th>Đối ứng</th><th>Mã GD</th></tr></thead><tbody>' +
        ds.map((g) =>
          '<tr><td>' + esc(ngay(g.ngay)) + '</td><td class="num"><b>' + esc(tien(g.soTien)) + '</b></td>' +
          '<td>' + esc(g.noiDung || '—') + '</td>' +
          '<td>' + esc([g.tenDoiUng, g.taiKhoanDoiUng].filter(Boolean).join(' · ') || '—') + '</td>' +
          '<td>' + esc(g.maGD || '—') + '</td></tr>').join('') + '</tbody>';
    } else if (n === 'dongle') {
      ds = kq.dongChuaDi;
      html = '<thead><tr><th>Hạn</th><th>Khoản trong sổ</th><th>Trạng thái</th><th>Quá hạn</th></tr></thead><tbody>' +
        ds.map((d) =>
          '<tr data-id="' + esc(d.rec.id) + '"><td>' + esc(ngay(d.han)) + '</td>' +
          '<td>' + skODong(d) + '</td>' +
          '<td>' + esc(d.nhanTrangThai) + '</td>' +
          '<td class="num">' + (d.quaHan ? '<span class="tag late">trễ ' + d.treNgay + ' ngày</span>' : '—') + '</td></tr>').join('') + '</tbody>';
    } else {
      ds = n === 'chac' ? kq.capChac
        : (n === 'lech' ? kq.lech
          : (n === 'xong' ? kq.capXong : kq.capKha.concat(kq.capNgo)));
      html = '<thead><tr><th>Khoản trong sổ</th><th>Ngân hàng trừ</th><th>Vì sao xếp vào đây</th></tr></thead><tbody>' +
        ds.map((c) => {
          const viSao = c.viSao ? esc(c.viSao) : [
            c.cham.khopTK ? 'khớp số tài khoản' : '',
            c.cham.khopTen ? 'khớp tên thụ hưởng' : '',
            c.cham.cach == null ? 'sổ không ghi ngày' : ('lệch ' + Math.abs(c.cham.cach) + ' ngày'),
            (c.soUngVien || 1) > 1 ? ('<b>' + c.soUngVien + ' khoản cùng số tiền</b> — máy không chọn hộ') : '',
          ].filter(Boolean).join(' · ');
          return '<tr data-id="' + esc(c.dong.rec.id) + '"><td>' + skODong(c.dong) + '</td>' +
            '<td>' + skOGD(c.gd) + '</td>' +
            '<td><span class="hint">' + esc(c.cham.nhanMuc) + ' — </span>' + viSao + '</td></tr>';
        }).join('') + '</tbody>';
    }

    bang.innerHTML = ds.length ? html
      : '<tbody><tr><td class="empty">Nhóm này không có dòng nào — tốt.</td></tr></tbody>';

    /* Bấm một dòng là mở đúng khoản ấy trong ngăn chi tiết: chỗ duy nhất đánh dấu tay được,
       và cũng là chỗ in được ủy nhiệm chi. Không có đường ấy thì người ta phải sang tab kia
       rồi tự đi tìm lại bằng số tiền. */
    Array.prototype.forEach.call(bang.querySelectorAll('tr[data-id]'), (tr) => {
      tr.style.cursor = 'pointer';
      tr.onclick = () => {
        const d = DONG.find((x) => x.rec.id === tr.dataset.id);
        if (d) moNgan(d);
      };
    });
  }

  function nhapSaoKe(file) {
    if (!file) return;
    if (!S.recs.length) return alert('Nhập file "Đi ủy nhiệm chi" trước đã — chưa có sổ thì không đối chiếu với cái gì.');
    const fr = new FileReader();
    fr.onload = (ev) => {
      try {
        const wb = XLSX.read(new Uint8Array(ev.target.result), { type: 'array', cellDates: true });
        const kq = SaoKe.docWorkbook(XLSX, wb, { namMacDinh: new Date().getFullYear() });
        if (!kq.gd.length) {
          S.saoKe.nhatKy = kq.nhatKy;
          veNhatKy();
          return alert('Không đọc được giao dịch tiền ra nào từ file này.\n\n' +
            kq.nhatKy.map((x) => '• ' + x.text).join('\n'));
        }
        S.saoKe.gd = kq.gd;
        S.saoKe.nhatKy = kq.nhatKy;
        S.saoKe.nguon = file.name;
        S.saoKe.nhom = 'chac';
        /* Nhật ký đọc sao kê nối vào chính nhật ký đang có: một chỗ để đọc "hệ đã bỏ qua
           những gì", chứ không phải hai chỗ mà người ta chỉ nhớ một. */
        S.nhatKy = (S.nhatKy || []).concat(kq.nhatKy);
        tinhLai();
        S.tab = 'saoke';
        veLai();
      } catch (e) {
        alert('Không đọc được file sao kê: ' + (e && e.message));
      }
    };
    fr.onerror = () => alert('Không mở được file.');
    fr.readAsArrayBuffer(file);
  }

  function tinhLaiSaoKe() {
    /* Gác `SaoKe`: trang vẫn phải chạy khi một tệp không tải được (mạng chập, chặn script).
       Mất tab đối chiếu thì còn thấy được; cả trang trắng vì một `undefined` thì không. */
    S.saoKe.kq = SaoKe && S.saoKe.gd.length
      ? SaoKe.doiChieu(DONG, S.saoKe.gd, { cuaSo: S.saoKe.cuaSo })
      : null;
  }

  function apSaoKe() {
    if (LA_WP && !API.duocSua()) return alert('Tài khoản của anh/chị chỉ được xem, không đánh dấu được.');
    const kq = S.saoKe.kq;
    if (!kq || !kq.capChac.length) return;
    const tong = kq.capChac.reduce((a, c) => a + c.gd.soTien, 0);
    if (!confirm('Bật "đã đi tiền" cho ' + kq.capChac.length + ' khoản khớp chắc (' +
      tien(tong) + ' ₫)?\n\nNgày đi tiền lấy đúng ngày ngân hàng trừ.\n' +
      'Mỗi khoản đều ghi lại là khớp từ sao kê nào, mã giao dịch nào.')) return;

    const capNhat = SaoKe.apDung(kq.capChac, { nguoi: tenDangDung() });
    const dsId = Object.keys(capNhat);
    if (!dsId.length) return alert('Không có khoản nào cần đổi — chúng đã ở trạng thái "đã đi tiền".');
    dsId.forEach((id) => { S.theoDoi[id] = capNhat[id]; });
    Kho.theoDoi(dsId);
    /* `tinhLai()` đối chiếu lại luôn, nên mấy khoản vừa bật tự rời khỏi nhóm "khớp chắc" —
       kẻo bấm nút xong màn vẫn y nguyên và người ta bấm lần nữa. */
    tinhLai();
    veLai();
    alert('Đã bật "đã đi tiền" cho ' + dsId.length + ' khoản.');
  }

  /* ===================== Nhập Excel ===================== */

  function nhapFile(file) {
    if (!file) return;
    const fr = new FileReader();
    fr.onload = (ev) => {
      try {
        const wb = XLSX.read(new Uint8Array(ev.target.result), { type: 'array', cellDates: true });
        const kq = Imp.docWorkbook(wb, XLSX, { namMacDinh: new Date().getFullYear() - 2 });
        if (!kq.recs.length) {
          alert('Không đọc được dòng nào từ file này.\nXem tab Nhật ký để biết vì sao.');
        }
        S.recs = kq.recs;
        S.nhatKy = kq.nhatKy;
        S.chon.clear();
        S.loc = {};
        S.soHien = 200;
        Kho.danhSach();
        const giuLai = Object.keys(S.theoDoi).filter((id) => S.recs.some((r) => r.id === id)).length;
        tinhLai();
        $('fKy').dataset.dung = '';
        S.tab = 'tong';
        veLai();
        const canh = kq.nhatKy.filter((n) => n.muc === 'canh').length;
        alert('Đọc xong ' + kq.recs.length.toLocaleString('vi-VN') + ' khoản từ ' +
          wb.SheetNames.length + ' sheet.' +
          (giuLai ? '\nGiữ lại ' + giuLai + ' khoản đã đánh dấu trước đó.' : '') +
          (canh ? '\nCó ' + canh + ' cảnh báo — xem tab Nhật ký.' : ''));
      } catch (e) {
        alert('Không đọc được file: ' + (e && e.message));
      }
    };
    fr.onerror = () => alert('Không mở được file.');
    fr.readAsArrayBuffer(file);
  }

  /* ===================== Lưu / mở file JSON ===================== */

  function luuJSON() {
    if (CHAN_TAI_FILE) return alert(LOI_CHAN_TAI);
    const goi = { phienBan: 1, luuLuc: new Date().toISOString(), recs: S.recs, theoDoi: S.theoDoi, congTy: S.congTy, tuyChon: S.tuyChon, nhatKy: S.nhatKy };
    const b = new Blob([JSON.stringify(goi)], { type: 'application/json' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(b);
    a.download = 'uy-nhiem-chi-' + E.homNayISO() + '.json';
    a.click();
    setTimeout(() => URL.revokeObjectURL(a.href), 2000);
  }

  function moJSON(file) {
    if (!file) return;
    const fr = new FileReader();
    fr.onload = (ev) => {
      try {
        const g = JSON.parse(ev.target.result);
        if (!g || !Array.isArray(g.recs)) throw new Error('File không đúng định dạng.');
        S.recs = g.recs;
        S.theoDoi = g.theoDoi || {};
        S.congTy = Object.assign(S.congTy, g.congTy || {});
        S.tuyChon = Object.assign(S.tuyChon, g.tuyChon || {});
        S.nhatKy = g.nhatKy || [];
        Kho.danhSach();
        Kho.theoDoi(Object.keys(S.theoDoi));
        Kho.caiDat();
        S.chon.clear();
        S.loc = {};
        $('fKy').dataset.dung = '';
        tinhLai();
        veLai();
        alert('Đã mở ' + S.recs.length.toLocaleString('vi-VN') + ' khoản.');
      } catch (e) {
        alert('Không mở được: ' + (e && e.message));
      }
    };
    fr.readAsText(file);
  }

  /* ===================== Thông tin công ty ===================== */

  function moCty() {
    const c = S.congTy;
    const tk = (l) => (c.taiKhoan || []).find((x) => x.loai === l) || {};
    $('ctyTen').value = c.ten || '';
    $('ctyDiaChi').value = c.diaChi || '';
    $('ctyMST').value = c.mst || '';
    $('ctyDaiDien').value = c.daiDien || '';
    $('ctyChucVu').value = c.chucVu || '';
    $('ctyNguoiLap').value = c.nguoiLap || '';
    $('ctyTkCuSo').value = tk('cu').so || '';
    $('ctyTkCuNH').value = tk('cu').nganHang || '';
    $('ctyTkMoiSo').value = tk('moi').so || '';
    $('ctyTkMoiNH').value = tk('moi').nganHang || '';
    $('optLenh').value = S.tuyChon.lenhLaDaDi ? '1' : '0';
    $('ctyBg').hidden = false;
  }

  function luuCty() {
    S.congTy = {
      ten: $('ctyTen').value.trim(),
      diaChi: $('ctyDiaChi').value.trim(),
      mst: $('ctyMST').value.trim(),
      daiDien: $('ctyDaiDien').value.trim(),
      chucVu: $('ctyChucVu').value.trim(),
      nguoiLap: $('ctyNguoiLap').value.trim(),
      taiKhoan: [
        { loai: 'cu', so: $('ctyTkCuSo').value.trim(), nganHang: $('ctyTkCuNH').value.trim() },
        { loai: 'moi', so: $('ctyTkMoiSo').value.trim(), nganHang: $('ctyTkMoiNH').value.trim() },
      ],
    };
    S.tuyChon.lenhLaDaDi = $('optLenh').value === '1';
    Kho.caiDat();
    $('ctyBg').hidden = true;
    tinhLai();
    veLai();
  }

  /* ===================== Gắn sự kiện ===================== */

  function gan() {
    document.querySelectorAll('#tabs button').forEach((b) => {
      b.onclick = () => { S.tab = b.dataset.tab; veLai(); };
    });

    $('fileInput').onchange = (e) => { nhapFile(e.target.files[0]); e.target.value = ''; };
    $('jsonInput').onchange = (e) => { moJSON(e.target.files[0]); e.target.value = ''; };

    /* ---- Doi chieu sao ke ---- */
    $('saoKeInput').onchange = (e) => { nhapSaoKe(e.target.files[0]); e.target.value = ''; };
    $('btnSaoKeApDung').onclick = apSaoKe;
    $('btnSaoKeXoa').onclick = () => {
      /* Chi bo BAN SAO KE dang xem, khong dung vao trang thai da bat. Gop hai viec vao mot
         nut la bam "bo sao ke" xong mat luon may luot danh dau vua ap, ma khong ai ngo. */
      S.saoKe.gd = [];
      S.saoKe.kq = null;
      S.saoKe.nguon = '';
      veLai();
    };
    $('skNhom').onchange = (e) => { S.saoKe.nhom = e.target.value; veSaoKe(); };
    ['skTruoc', 'skSau'].forEach((id) => {
      $(id).onchange = () => {
        S.saoKe.cuaSo = {
          truoc: Math.max(0, Math.min(60, parseInt($('skTruoc').value, 10) || 0)),
          sau: Math.max(0, Math.min(120, parseInt($('skSau').value, 10) || 0)),
        };
        tinhLaiSaoKe();
        veLai();
      };
    });

    $('btnExport').onclick = () => {
      if (CHAN_TAI_FILE) return alert(LOI_CHAN_TAI);
      if (!DONG.length) return alert('Chưa có dữ liệu để xuất.');
      const ds = DONG_LOC.length && DONG_LOC.length < DONG.length
        ? (confirm('Xuất ' + DONG_LOC.length + ' khoản đang lọc?\n\nBấm Huỷ để xuất toàn bộ ' + DONG.length + ' khoản.') ? DONG_LOC : DONG)
        : DONG;
      try {
        const ten = Exp.xuatExcel(XLSX, ds, { homNay: E.homNayISO() });
        console.log('Đã xuất', ten);
      } catch (e) {
        alert('Không xuất được: ' + (e && e.message));
      }
    };

    const menu = $('moreMenu');
    $('btnMore').onclick = (e) => {
      e.stopPropagation();
      menu.hidden = !menu.hidden;
      $('btnMore').setAttribute('aria-expanded', String(!menu.hidden));
    };
    document.addEventListener('click', (e) => {
      if (!menu.hidden && !menu.contains(e.target)) menu.hidden = true;
    });
    menu.querySelectorAll('button[data-act]').forEach((b) => {
      b.onclick = () => {
        menu.hidden = true;
        const a = b.dataset.act;
        if (a === 'settings') moCty();
        else if (a === 'saveJson') luuJSON();
        else if (a === 'sample') napMau();
        else if (a === 'clearTrack') {
          if (confirm('Xoá toàn bộ trạng thái kế toán đã đánh dấu?\nDữ liệu đọc từ Excel vẫn giữ nguyên.')) {
            const cu = Object.keys(S.theoDoi);
            S.theoDoi = {};
            Kho.theoDoi(null, cu);
            tinhLai();
            veLai();
          }
        } else if (a === 'reset') {
          if (confirm('Xoá TOÀN BỘ dữ liệu trên trang này (kể cả trạng thái đã đánh dấu)?')) {
            Kho.xoaHet();
            Object.values(KHOA).forEach((k) => { try { localStorage.removeItem(k); } catch (e) { /* bỏ qua */ } });
            S.recs = []; S.theoDoi = {}; S.nhatKy = []; S.loc = {}; S.chon.clear();
            $('fKy').dataset.dung = '';
            tinhLai();
            veLai();
          }
        }
      };
    });

    ['fTuKhoa', 'fKy', 'fTrangThai', 'fBP', 'fTKCty', 'fLoai', 'fTuNgay', 'fDenNgay', 'fQuaHan'].forEach((id) => {
      const n = $(id);
      n.addEventListener(id === 'fTuKhoa' ? 'input' : 'change', docLocTuForm);
    });
    $('btnLocXoa').onclick = () => { S.loc = {}; dongBoLocLenForm(); S.soHien = 200; ganLoc(); veLai(); };

    // Đánh dấu cả một kỳ thì không thể tick từng dòng — chọn nguyên danh sách đang lọc.
    $('btnChonHet').onclick = () => {
      if (!DONG_LOC.length) return;
      const daChonHet = DONG_LOC.every((d) => S.chon.has(d.rec.id));
      if (daChonHet) S.chon.clear();
      else DONG_LOC.forEach((d) => S.chon.add(d.rec.id));
      veLai();
    };

    // Mở trang lần đầu mà chưa có file thì vẫn xem được app làm gì.
    $('btnXemThu').onclick = napMau;

    $('bulkBo').onclick = () => { S.chon.clear(); veLai(); };
    document.querySelectorAll('#bulkBar button[data-bulk]').forEach((b) => {
      b.onclick = () => {
        const tt = b.dataset.bulk;
        if (tt === 'xoa' && !confirm('Bỏ đánh dấu ' + S.chon.size + ' khoản?')) return;
        apBulk(tt);
      };
    });

    $('drDong').onclick = dongNgan;
    $('drawerBg').onclick = (e) => { if (e.target === $('drawerBg')) dongNgan(); };
    $('ctyDong').onclick = () => { $('ctyBg').hidden = true; };
    $('ctyBg').onclick = (e) => { if (e.target === $('ctyBg')) $('ctyBg').hidden = true; };
    $('ctyLuu').onclick = luuCty;

    $('tongKy').onchange = () => { S.tongKy = $('tongKy').value; veLai(); };
    $('cnGom').onchange = () => { S.cnGom = $('cnGom').value; veLai(); };
    $('cnDenNgay').onchange = () => { S.cnDenNgay = $('cnDenNgay').value; veLai(); };
    $('btnInMau').onclick = () => window.print();

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        if (!$('drawerBg').hidden) dongNgan();
        else if (!$('ctyBg').hidden) $('ctyBg').hidden = true;
      }
      if ((e.ctrlKey || e.metaKey) && e.key === 'o') { e.preventDefault(); $('fileInput').click(); }
      if ((e.ctrlKey || e.metaKey) && e.key === 's') { e.preventDefault(); $('btnExport').click(); }
    });

    // Kéo thả file vào trang
    let dem = 0;
    document.addEventListener('dragenter', (e) => { e.preventDefault(); dem++; document.body.classList.add('dragging'); });
    document.addEventListener('dragover', (e) => e.preventDefault());
    document.addEventListener('dragleave', () => { if (--dem <= 0) document.body.classList.remove('dragging'); });
    document.addEventListener('drop', (e) => {
      e.preventDefault();
      dem = 0;
      document.body.classList.remove('dragging');
      const f = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
      if (!f) return;
      if (/\.json$/i.test(f.name)) moJSON(f); else nhapFile(f);
    });
  }

  function napMau() {
    if (!window.UNCSampleData) return alert('Không có dữ liệu mẫu.');
    const kq = Imp.docSheets(window.UNCSampleData(), { namMacDinh: new Date().getFullYear() - 1 });
    S.recs = kq.recs;
    S.nhatKy = kq.nhatKy;
    S.loc = {};
    S.chon.clear();
    Kho.danhSach();
    $('fKy').dataset.dung = '';
    S.tab = 'tong';
    tinhLai();
    veLai();
  }

  /* ===================== Khởi động ===================== */

  if (LA_WP) {
    khoiDongWP();
  } else {
    napTatCa();
    gan();
    tinhLai();
    veLai();
  }

  /* ===================== Khởi động bản WordPress ===================== */

  async function khoiDongWP() {
    gan();
    apQuyen();
    veLai();
    if (window.UNCWpUi) window.UNCWpUi.batDau({ onDangNhap: taiTuMayChu });
    try {
      const u = await API.whoami();
      if (!u) {
        if (window.UNCWpUi) window.UNCWpUi.moCong();
        return;
      }
      await taiTuMayChu();
    } catch (e) {
      alert('Không kết nối được máy chủ:\n' + (e && e.message));
    }
  }

  /** Tải dữ liệu dùng chung về. Mọi người mở trang đều thấy cùng một bộ số. */
  async function taiTuMayChu() {
    dongBo('dang', 'Đang tải…');
    try {
      const r = await API.call('layTatCa', {});
      S.recs = r.recs || [];
      S.theoDoi = r.theoDoi || {};
      S.nhatKy = r.nhatKy || [];
      if (r.congTy) S.congTy = Object.assign(S.congTy, r.congTy);
      if (r.tuyChon) S.tuyChon = Object.assign(S.tuyChon, r.tuyChon);
      S.chon.clear();
      $('fKy').dataset.dung = '';
      dongBo('', tenNguoi());
      apQuyen();
      tinhLai();
      veLai();
    } catch (e) {
      dongBo('loi', 'Chưa tải được');
      alert('Không tải được dữ liệu:\n' + (e && e.message));
    }
  }

  /** Vai "Xem" chỉ được tra cứu và in — ẩn hết nút ghi. */
  function apQuyen() {
    if (!LA_WP) return;
    document.body.classList.toggle('chi-xem', !API.duocSua());
    document.body.classList.toggle('la-admin', API.laAdmin());
  }

  // Cho phép nhúng / điều khiển từ ngoài (iframe, trang khác).
  window.UNCApp = {
    getState: () => ({ recs: S.recs, theoDoi: S.theoDoi, congTy: S.congTy, tuyChon: S.tuyChon }),
    setState: (g) => { moJSONTuObject(g); },
    getDong: () => DONG,
    getCongNo: () => E.congNoNCC(DONG.filter((d) => d.rec.loai !== 'ghichu')),
  };

  function moJSONTuObject(g) {
    if (!g || !Array.isArray(g.recs)) return;
    S.recs = g.recs;
    S.theoDoi = g.theoDoi || {};
    if (g.congTy) S.congTy = Object.assign(S.congTy, g.congTy);
    if (g.tuyChon) S.tuyChon = Object.assign(S.tuyChon, g.tuyChon);
    Kho.danhSach();
    Kho.theoDoi(Object.keys(S.theoDoi));
    $('fKy').dataset.dung = '';
    tinhLai();
    veLai();
  }
})();
