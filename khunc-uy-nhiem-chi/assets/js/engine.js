/*
 * engine.js — Lõi theo dõi Ủy nhiệm chi & Công nợ. KHÔNG phụ thuộc giao diện.
 *
 * Chạy được cả trong trình duyệt (window.UNCEngine) lẫn Node (module.exports)
 * để kiểm thử tự động.
 *
 * Mô hình dữ liệu
 * ---------------
 * rec (một dòng đề nghị chi / ủy nhiệm chi, đọc từ Excel — CHỈ ĐỌC, nhập lại là ghi đè):
 *   { id, ky:'YYYY-MM', nguon, dong, loai:'unc'|'tienmat'|'thue',
 *     bp, bpGoc, khoanMuc, noiDung, noiDungKhongDau, soTien,
 *     thuHuong, thuHuongKhoa, soTaiKhoan, nganHangTH, taiKhoanCty:'cu'|'moi'|'smart'|'',
 *     hanDi:{iso,nhan,truoc}, ngayLenh:{iso,nhan}, ghiChu, link }
 *
 * td (theo dõi — phần KẾ TOÁN NHẬP, lưu riêng theo rec.id nên nhập lại Excel không mất):
 *   { trangThai:'chua_lenh'|'da_lenh'|'da_di'|'huy', ngayDi:'YYYY-MM-DD',
 *     soUNC, nguoiCapNhat, capNhatLuc, ghiChu }
 *
 * Trạng thái khi kế toán chưa đụng tới thì suy ra từ file: có "Ngày tạo lệnh" → đã lập lệnh.
 */
(function (root, factory) {
  if (typeof module === 'object' && module.exports) module.exports = factory();
  else root.UNCEngine = factory();
})(typeof self !== 'undefined' ? self : this, function () {
  'use strict';

  /* ===================== Hằng số ===================== */

  const TRANG_THAI = [
    { value: 'chua_lenh', label: 'Chưa lập lệnh', ngan: 'Chưa lập', mau: 'wait' },
    { value: 'da_lenh', label: 'Đã lập lệnh', ngan: 'Đã lập lệnh', mau: 'info' },
    { value: 'da_di', label: 'Đã đi tiền', ngan: 'Đã đi', mau: 'ok' },
    { value: 'huy', label: 'Huỷ / không nộp', ngan: 'Huỷ', mau: 'muted' },
  ];
  const NHAN_TRANG_THAI = TRANG_THAI.reduce((a, t) => ((a[t.value] = t.label), a), {});

  const TAI_KHOAN_CTY = [
    { value: 'cu', label: 'K&H cũ' },
    { value: 'moi', label: 'K&H mới' },
    { value: 'smart', label: 'Smart' },
    { value: '', label: '(chưa ghi)' },
  ];

  /** Các mốc tuổi nợ (ngày) — theo cách kế toán hay chia bảng công nợ. */
  const MOC_TUOI_NO = [
    { key: 'chuaDenHan', label: 'Chưa đến hạn', min: -Infinity, max: 0 },
    { key: 'qh1_30', label: 'Quá hạn 1–30 ngày', min: 1, max: 30 },
    { key: 'qh31_60', label: 'Quá hạn 31–60 ngày', min: 31, max: 60 },
    { key: 'qh61_90', label: 'Quá hạn 61–90 ngày', min: 61, max: 90 },
    { key: 'qh90', label: 'Quá hạn trên 90 ngày', min: 91, max: Infinity },
  ];

  /* ===================== Tiện ích chuỗi / số ===================== */

  /** Bỏ dấu tiếng Việt để so khớp và tìm kiếm ("Nguyễn" → "Nguyen"). */
  function boDau(s) {
    return String(s == null ? '' : s)
      .normalize('NFD')
      .replace(/[̀-ͯ]/g, '')
      .replace(/đ/g, 'd')
      .replace(/Đ/g, 'D');
  }

  function gonTrang(s) {
    return String(s == null ? '' : s).replace(/\s+/g, ' ').trim();
  }

  function pad2(n) {
    return (n < 10 ? '0' : '') + n;
  }

  /** Băm chuỗi thành khoá ngắn, ổn định giữa các lần nhập file. */
  function bam32(s) {
    let h = 5381;
    for (let i = 0; i < s.length; i++) h = ((h * 33) ^ s.charCodeAt(i)) >>> 0;
    return h.toString(36);
  }

  /**
   * Đọc số tiền từ ô Excel. Chịu được 25,500,000 / 25.500.000 / '24557500.0' / ' 1 836 000 '.
   *
   * Kế toán hay gõ nhiều khoản vào chung một ô, xuống dòng hoặc ngăn bằng dấu '+'
   * ("33,000,000\n11,000,000") — những ô đó được CỘNG lại, không nối thành một số khổng lồ.
   */
  function docTien(v) {
    if (v == null || v === '') return 0;
    if (typeof v === 'number') return isFinite(v) ? v : 0;
    const raw = String(v);
    if (/[\n\r;+]/.test(raw)) {
      const ve = raw.split(/[\n\r;+]+/).map(motSo).filter((n) => n !== 0);
      if (ve.length > 1) return ve.reduce((a, b) => a + b, 0);
    }
    return motSo(raw);
  }

  /** Đọc đúng MỘT số tiền từ chuỗi. */
  function motSo(v) {
    let s = String(v).trim();
    if (!s) return 0;
    // Ô ngày tháng lọt vào cột tiền: '2025-11-08T00:00:00' mà bóc số sẽ ra 20251108000000.
    if (/\d{4}-\d{1,2}-\d{1,2}/.test(s) || /\d:\d{2}/.test(s)) return 0;
    const am = /^\(.*\)$/.test(s) || s.indexOf('-') === 0;
    s = s.replace(/[^\d,.]/g, '');
    if (!s) return 0;
    const soCham = (s.match(/\./g) || []).length;
    const soPhay = (s.match(/,/g) || []).length;
    if (soCham && soPhay) {
      // Dấu nào đứng sau cùng là dấu thập phân.
      if (s.lastIndexOf(',') > s.lastIndexOf('.')) s = s.replace(/\./g, '').replace(',', '.');
      else s = s.replace(/,/g, '');
    } else if (soPhay) {
      const ve = s.split(',');
      // '25,500,000' hoặc '1,500' → dấu phân nhóm; '3,5' → thập phân.
      s = soPhay > 1 || ve[ve.length - 1].length === 3 ? s.replace(/,/g, '') : s.replace(',', '.');
    } else if (soCham > 1) {
      s = s.replace(/\./g, '');
    } else if (soCham === 1) {
      const ve = s.split('.');
      if (ve[1].length === 3) s = s.replace('.', ''); // '25.500' kiểu Việt Nam
    }
    const n = parseFloat(s);
    if (!isFinite(n)) return 0;
    return am ? -n : n;
  }

  /* ===================== Ngày tháng ===================== */

  const RE_NGAY = /(\d{1,2})\s*[/.\-]\s*(\d{1,2})(?:\s*[/.\-]\s*(\d{2,4}))?/;

  function isoTuDate(d) {
    if (!(d instanceof Date) || isNaN(d.getTime())) return '';
    return d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate());
  }

  /** Số sê-ri ngày của Excel (1900 system) → Date. */
  function tuSerialExcel(n) {
    if (!isFinite(n) || n <= 0 || n > 60000) return null;
    const ms = Math.round((n - 25569) * 86400 * 1000);
    const d = new Date(ms);
    return isNaN(d.getTime()) ? null : new Date(d.getUTCFullYear(), d.getUTCMonth(), d.getUTCDate());
  }

  /**
   * Đọc ngày từ ô kiểu "UNC 1/9", "PAYMENT 31/8", "trước 14/12", "30-31/12",
   * "2025-11-08 00:00:00", Date, hoặc số sê-ri Excel.
   *
   * @param v   giá trị ô
   * @param ky  {thang, nam} của sheet, dùng để đoán năm khi ô chỉ ghi ngày/tháng
   * @returns   {iso:'YYYY-MM-DD'|'', nhan: chuỗi gốc, truoc: bool}
   */
  function docNgay(v, ky) {
    const out = { iso: '', nhan: '', truoc: false };
    if (v == null || v === '') return out;
    if (v instanceof Date) {
      out.iso = isoTuDate(v);
      out.nhan = dinhDangNgay(out.iso);
      return out;
    }
    if (typeof v === 'number') {
      const d = tuSerialExcel(v);
      if (d) {
        out.iso = isoTuDate(d);
        out.nhan = dinhDangNgay(out.iso);
      }
      return out;
    }
    const raw = gonTrang(v);
    out.nhan = raw;
    if (!raw) return out;
    const t = boDau(raw).toLowerCase();
    out.truoc = /\btruoc\b|\bden\s+ngay\b|\bcham\s+nhat\b/.test(t);

    // Dạng ISO có sẵn (openpyxl/SheetJS đôi khi trả chuỗi): 2025-11-08 00:00:00
    const iso = t.match(/(\d{4})-(\d{1,2})-(\d{1,2})/);
    if (iso) {
      out.iso = iso[1] + '-' + pad2(+iso[2]) + '-' + pad2(+iso[3]);
      return out;
    }

    // Khoảng ngày "30-31/12" → lấy mốc cuối cho an toàn.
    let s = t;
    const khoang = s.match(/(\d{1,2})\s*-\s*(\d{1,2})\s*[/.]\s*(\d{1,2})/);
    if (khoang) s = khoang[2] + '/' + khoang[3];

    const m = s.match(RE_NGAY);
    if (!m) return out;
    let ngay = +m[1];
    let thang = +m[2];
    let nam = m[3] ? +m[3] : null;
    if (nam != null && nam < 100) nam += 2000;
    if (thang > 12 && ngay <= 12) {
      const tmp = ngay;
      ngay = thang;
      thang = tmp;
    }
    if (thang < 1 || thang > 12 || ngay < 1 || ngay > 31) return out;
    if (nam == null) {
      nam = ky && ky.nam ? ky.nam : new Date().getFullYear();
      if (ky && ky.thang) {
        // Sheet "Tháng 1.2026" ghi "UNC 28/12" → là 12/2025, và ngược lại.
        if (thang - ky.thang >= 6) nam -= 1;
        else if (ky.thang - thang >= 6) nam += 1;
      }
    }
    out.iso = nam + '-' + pad2(thang) + '-' + pad2(ngay);
    return out;
  }

  function dinhDangNgay(iso) {
    if (!iso) return '';
    const p = String(iso).split('-');
    if (p.length !== 3) return String(iso);
    return +p[2] + '/' + +p[1] + '/' + p[0];
  }

  function homNayISO() {
    return isoTuDate(new Date());
  }

  /** Số ngày từ iso đến moc (dương = iso đã qua). */
  function soNgayQua(iso, moc) {
    if (!iso) return null;
    const a = new Date(iso + 'T00:00:00');
    const b = new Date((moc || homNayISO()) + 'T00:00:00');
    if (isNaN(a.getTime()) || isNaN(b.getTime())) return null;
    return Math.round((b - a) / 86400000);
  }

  /* ===================== Chuẩn hoá dữ liệu bẩn ===================== */

  /**
   * "K&H cũ", "K VÀ H CŨ", "KH CŨ", "K va H cu" → 'cu'; "…mới/moi" → 'moi'.
   * File gốc có 16 cách viết cho đúng 2 tài khoản.
   */
  function docTaiKhoanCty(s) {
    const t = boDau(s).toUpperCase().replace(/[^A-Z0-9]+/g, ' ').trim();
    if (!t) return '';
    if (/\bMOI\b/.test(t)) return 'moi';
    if (/\bCU\b/.test(t)) return 'cu';
    if (/SMART/.test(t)) return 'smart';
    return '';
  }

  /**
   * Tách cột BP: "FZ SC-gà rán" → {bp:'FZ SC', khoanMuc:'gà rán'}.
   * Gộp hoa/thường ("posh" và "POSH" là một).
   */
  function tachBP(s) {
    const raw = gonTrang(s);
    if (!raw) return { bp: '', khoanMuc: '', bpGoc: '' };
    const vt = raw.search(/\s*[-–—_]\s*/);
    let bp = raw;
    let khoanMuc = '';
    if (vt > 0) {
      const m = raw.match(/^(.*?)\s*[-–—_]\s*(.*)$/);
      if (m && m[2]) {
        bp = m[1];
        khoanMuc = m[2];
      }
    }
    return { bp: gonTrang(bp).toUpperCase(), khoanMuc: gonTrang(khoanMuc), bpGoc: raw };
  }

  /** Khoá gộp nhà cung cấp: bỏ dấu, bỏ loại hình doanh nghiệp, bỏ ký tự lạ. */
  function khoaNCC(s) {
    let t = boDau(s).toUpperCase().replace(/[^A-Z0-9]+/g, ' ').trim();
    if (!t) return '';
    const bo = /^(CONG TY|CTY|CT|CHI NHANH|CN|CO PHAN|CP|TNHH|MTV|MOT THANH VIEN|TM|SX|DV|TMDV|TM DV|SX TM|THUONG MAI|DICH VU|SAN XUAT|LIEN DOANH|HO KINH DOANH|HKD|DOANH NGHIEP TU NHAN|DNTN)\s+/;
    let truoc = '';
    while (truoc !== t) {
      truoc = t;
      t = t.replace(bo, '');
    }
    return gonTrang(t);
  }

  /** Bỏ ký tự rác trong số tài khoản: "'VAE2001000359", "\t0501000160370", "22789677.0". */
  function chuanSoTK(v) {
    if (v == null) return '';
    let s = String(v).trim().replace(/^['\s\t]+/, '').replace(/\s+$/, '');
    if (/^\d+\.0$/.test(s)) s = s.slice(0, -2); // Excel biến số TK thành số thực
    return gonTrang(s);
  }

  /* ===================== Khoá dòng ===================== */

  /**
   * Khoá ổn định cho mỗi dòng: nhập lại đúng file đó thì trạng thái kế toán đã đánh vẫn còn.
   * Dòng trùng hệt nhau được đánh số thứ tự để không đè lên nhau.
   */
  function ganKhoa(recs) {
    const dem = Object.create(null);
    recs.forEach((r) => {
      const goc = [
        r.ky,
        boDau(r.bpGoc).toUpperCase(),
        boDau(r.noiDung).toLowerCase().replace(/\s+/g, ' ').trim(),
        Math.round(r.soTien),
        String(r.soTaiKhoan || '').replace(/\D/g, ''),
      ].join('|');
      const n = (dem[goc] = (dem[goc] || 0) + 1);
      r.id = 'r' + bam32(goc) + (n > 1 ? '_' + n : '');
    });
    return recs;
  }

  /* ===================== Trạng thái & lọc ===================== */

  /**
   * Trạng thái hiệu lực của một dòng.
   * Kế toán đã đánh dấu thì nghe kế toán; chưa thì suy ra từ file.
   */
  function trangThai(rec, td, tuyChon) {
    if (td && td.trangThai) return td.trangThai;
    const coLenh = !!(rec.ngayLenh && (rec.ngayLenh.iso || rec.ngayLenh.nhan));
    if (!coLenh) return 'chua_lenh';
    return tuyChon && tuyChon.lenhLaDaDi ? 'da_di' : 'da_lenh';
  }

  /** Ngày tiền thực đi (kế toán nhập, không có thì lấy ngày tạo lệnh trong file). */
  function ngayDiHieuLuc(rec, td) {
    if (td && td.ngayDi) return td.ngayDi;
    return (rec.ngayLenh && rec.ngayLenh.iso) || '';
  }

  /** Hạn phải đi tiền: ưu tiên "Ngày cần đi tiền", không có thì lấy "Ngày tạo lệnh". */
  function hanHieuLuc(rec) {
    if (rec.hanDi && rec.hanDi.iso) return rec.hanDi.iso;
    if (rec.ngayLenh && rec.ngayLenh.iso) return rec.ngayLenh.iso;
    return '';
  }

  /**
   * Dựng danh sách đã tính trạng thái, quá hạn, tuổi nợ.
   * @param recs    dòng đọc từ Excel
   * @param theoDoi { [id]: td }
   * @param tuyChon { lenhLaDaDi, homNay }
   */
  function dungDong(recs, theoDoi, tuyChon) {
    const td = theoDoi || {};
    const opt = tuyChon || {};
    const moc = opt.homNay || homNayISO();
    return (recs || []).map((rec) => {
      const t = td[rec.id] || null;
      const st = trangThai(rec, t, opt);
      const han = hanHieuLuc(rec);
      const xong = st === 'da_di' || st === 'huy';
      const treNgay = !xong && han ? soNgayQua(han, moc) : null;
      return {
        rec: rec,
        td: t,
        trangThai: st,
        nhanTrangThai: NHAN_TRANG_THAI[st] || st,
        han: han,
        ngayDi: ngayDiHieuLuc(rec, t),
        quaHan: treNgay != null && treNgay > 0,
        treNgay: treNgay,
        conNo: st !== 'da_di' && st !== 'huy' ? rec.soTien : 0,
        daChi: st === 'da_di' ? rec.soTien : 0,
      };
    });
  }

  function mocTuoi(treNgay) {
    if (treNgay == null) return 'khongHan';
    for (const m of MOC_TUOI_NO) if (treNgay >= m.min && treNgay <= m.max) return m.key;
    return 'khongHan';
  }

  /* ===================== Lọc ===================== */

  /**
   * @param loc { tuKhoa, ky:[], bp:[], trangThai:[], taiKhoanCty:[], ncc:[],
   *              tuNgay, denNgay, tuTien, denTien, chiQuaHan, loai:[] }
   */
  function loc(dong, loc_) {
    const f = loc_ || {};
    const tk = f.tuKhoa ? boDau(f.tuKhoa).toLowerCase().trim() : '';
    const tuKhoaVe = tk ? tk.split(/\s+/) : [];
    const co = (mang, v) => !mang || !mang.length || mang.indexOf(v) >= 0;
    return dong.filter((d) => {
      const r = d.rec;
      if (!co(f.ky, r.ky)) return false;
      if (!co(f.bp, r.bp)) return false;
      if (!co(f.trangThai, d.trangThai)) return false;
      if (!co(f.taiKhoanCty, r.taiKhoanCty)) return false;
      if (!co(f.ncc, r.thuHuongKhoa)) return false;
      if (!co(f.loai, r.loai)) return false;
      if (f.chiQuaHan && !d.quaHan) return false;
      if (f.tuTien != null && f.tuTien !== '' && r.soTien < +f.tuTien) return false;
      if (f.denTien != null && f.denTien !== '' && r.soTien > +f.denTien) return false;
      if (f.tuNgay && (!d.han || d.han < f.tuNgay)) return false;
      if (f.denNgay && (!d.han || d.han > f.denNgay)) return false;
      if (tuKhoaVe.length) {
        const kho = r.timKiem || '';
        for (const v of tuKhoaVe) if (kho.indexOf(v) < 0) return false;
      }
      return true;
    });
  }

  /* ===================== Tổng hợp ===================== */

  function congDon(dong) {
    const t = {
      soDong: dong.length,
      tongTien: 0,
      daChi: 0,
      conNo: 0,
      quaHan: 0,
      tienQuaHan: 0,
      theoTrangThai: {},
    };
    TRANG_THAI.forEach((s) => (t.theoTrangThai[s.value] = { soDong: 0, tien: 0 }));
    dong.forEach((d) => {
      t.tongTien += d.rec.soTien;
      t.daChi += d.daChi;
      t.conNo += d.conNo;
      if (d.quaHan) {
        t.quaHan += 1;
        t.tienQuaHan += d.rec.soTien;
      }
      const o = t.theoTrangThai[d.trangThai] || (t.theoTrangThai[d.trangThai] = { soDong: 0, tien: 0 });
      o.soDong += 1;
      o.tien += d.rec.soTien;
    });
    return t;
  }

  /** Gom theo một khoá bất kỳ, trả mảng đã sắp giảm dần theo tiền còn nợ rồi tổng tiền. */
  function gomTheo(dong, layKhoa, layNhan) {
    const map = new Map();
    dong.forEach((d) => {
      const k = layKhoa(d);
      if (k == null) return;
      let o = map.get(k);
      if (!o) {
        o = {
          khoa: k,
          nhan: layNhan ? layNhan(d) : String(k),
          soDong: 0,
          tongTien: 0,
          daChi: 0,
          conNo: 0,
          quaHan: 0,
          tienQuaHan: 0,
          tuoi: {},
          treNhatNgay: null,
        };
        MOC_TUOI_NO.forEach((m) => (o.tuoi[m.key] = 0));
        o.tuoi.khongHan = 0;
        map.set(k, o);
      }
      o.soDong += 1;
      o.tongTien += d.rec.soTien;
      o.daChi += d.daChi;
      o.conNo += d.conNo;
      if (d.quaHan) {
        o.quaHan += 1;
        o.tienQuaHan += d.rec.soTien;
        if (o.treNhatNgay == null || d.treNgay > o.treNhatNgay) o.treNhatNgay = d.treNgay;
      }
      if (d.conNo) o.tuoi[mocTuoi(d.treNgay)] += d.rec.soTien;
    });
    return Array.from(map.values()).sort((a, b) => b.conNo - a.conNo || b.tongTien - a.tongTien);
  }

  /** Công nợ phải trả theo nhà cung cấp — bảng kế toán hay phải làm. */
  /** Nhãn cho các dòng không ghi đơn vị thụ hưởng (sheet thuê mặt bằng không có cột này). */
  const KHONG_TEN = '(không ghi đơn vị thụ hưởng — xem theo Bộ phận)';

  function congNoNCC(dong) {
    const ds = gomTheo(
      dong,
      (d) => d.rec.thuHuongKhoa || KHONG_TEN,
      (d) => d.rec.thuHuong || KHONG_TEN
    );
    // Lấy tên hiển thị dài nhất (đầy đủ nhất) và gom số tài khoản của từng NCC.
    const ten = new Map();
    const tk = new Map();
    dong.forEach((d) => {
      const k = d.rec.thuHuongKhoa || KHONG_TEN;
      const t = gonTrang(d.rec.thuHuong);
      if (t && (!ten.has(k) || t.length > ten.get(k).length)) ten.set(k, t);
      if (d.rec.soTaiKhoan) {
        if (!tk.has(k)) tk.set(k, new Set());
        tk.get(k).add(d.rec.soTaiKhoan + (d.rec.nganHangTH ? ' — ' + d.rec.nganHangTH : ''));
      }
    });
    ds.forEach((o) => {
      if (ten.has(o.khoa)) o.nhan = ten.get(o.khoa);
      o.taiKhoan = tk.has(o.khoa) ? Array.from(tk.get(o.khoa)) : [];
    });
    return ds;
  }

  function theoKy(dong) {
    return gomTheo(dong, (d) => d.rec.ky).sort((a, b) => (a.khoa < b.khoa ? 1 : -1));
  }

  function theoBP(dong) {
    return gomTheo(dong, (d) => d.rec.bp || '(chưa ghi)');
  }

  function theoTaiKhoanCty(dong) {
    const nhan = TAI_KHOAN_CTY.reduce((a, t) => ((a[t.value] = t.label), a), {});
    return gomTheo(
      dong,
      (d) => d.rec.taiKhoanCty,
      (d) => nhan[d.rec.taiKhoanCty] || '(chưa ghi)'
    );
  }

  /** Bảng tuổi nợ tổng — cột chuẩn của bảng kê công nợ. */
  function bangTuoiNo(dong) {
    const t = { tong: 0 };
    MOC_TUOI_NO.forEach((m) => (t[m.key] = 0));
    t.khongHan = 0;
    dong.forEach((d) => {
      if (!d.conNo) return;
      t[mocTuoi(d.treNgay)] += d.rec.soTien;
      t.tong += d.rec.soTien;
    });
    return t;
  }

  /* ===================== Cảnh báo / kiểm tra ===================== */

  /**
   * Những thứ kế toán cần biết ngay: quá hạn, trùng chi, thiếu thông tin chuyển khoản.
   */
  function canhBao(dong, tuyChon) {
    const opt = tuyChon || {};
    const moc = opt.homNay || homNayISO();
    const ds = [];

    const quaHan = dong.filter((d) => d.quaHan).sort((a, b) => b.treNgay - a.treNgay);
    quaHan.forEach((d) => {
      ds.push({
        muc: d.treNgay > 30 ? 'loi' : 'canh',
        loai: 'qua_han',
        id: d.rec.id,
        text:
          'Quá hạn ' + d.treNgay + ' ngày: ' + (d.rec.thuHuong || d.rec.noiDung) +
          ' — ' + dinhDangTien(d.rec.soTien) + ' (hạn ' + dinhDangNgay(d.han) + ')',
      });
    });

    // Sắp đến hạn trong 3 ngày tới.
    dong.forEach((d) => {
      if (d.quaHan || !d.han || d.conNo === 0) return;
      const con = -soNgayQua(d.han, moc);
      if (con >= 0 && con <= 3) {
        ds.push({
          muc: 'tin',
          loai: 'sap_han',
          id: d.rec.id,
          text:
            (con === 0 ? 'Hôm nay phải đi: ' : 'Còn ' + con + ' ngày: ') +
            (d.rec.thuHuong || d.rec.noiDung) + ' — ' + dinhDangTien(d.rec.soTien),
        });
      }
    });

    // Nghi trùng chi: cùng số tài khoản + cùng số tiền + cùng kỳ.
    const nhom = new Map();
    dong.forEach((d) => {
      const r = d.rec;
      if (!r.soTien || d.trangThai === 'huy') return;
      const k = [r.ky, String(r.soTaiKhoan || '').replace(/\D/g, '') || r.thuHuongKhoa, Math.round(r.soTien)].join('|');
      if (!nhom.has(k)) nhom.set(k, []);
      nhom.get(k).push(d);
    });
    nhom.forEach((v) => {
      if (v.length < 2) return;
      // Cùng nội dung hệt nhau mới đáng ngờ; khác nội dung có thể là 2 khoản thật.
      const nd = new Set(v.map((d) => boDau(d.rec.noiDung).toLowerCase().replace(/\s+/g, ' ').trim()));
      ds.push({
        muc: nd.size === 1 ? 'canh' : 'tin',
        loai: 'trung_chi',
        id: v[0].rec.id,
        ids: v.map((d) => d.rec.id),
        text:
          'Nghi trùng chi ' + v.length + ' dòng: ' + (v[0].rec.thuHuong || '(không tên)') +
          ' — ' + dinhDangTien(v[0].rec.soTien) + ' (kỳ ' + v[0].rec.ky + ')',
      });
    });

    // Thiếu thông tin để chuyển tiền.
    dong.forEach((d) => {
      if (d.trangThai === 'da_di' || d.trangThai === 'huy') return;
      const thieu = [];
      if (!d.rec.thuHuong) thieu.push('tên đơn vị thụ hưởng');
      if (!d.rec.soTaiKhoan) thieu.push('số tài khoản');
      if (!d.rec.nganHangTH) thieu.push('ngân hàng thụ hưởng');
      if (!d.rec.taiKhoanCty) thieu.push('tài khoản chi (K&H cũ/mới)');
      if (thieu.length) {
        ds.push({
          muc: 'canh',
          loai: 'thieu_tt',
          id: d.rec.id,
          text: 'Thiếu ' + thieu.join(', ') + ': ' + (d.rec.noiDung || '(không nội dung)').slice(0, 70),
        });
      }
    });

    const uuTien = { loi: 0, canh: 1, tin: 2 };
    return ds.sort((a, b) => uuTien[a.muc] - uuTien[b.muc]);
  }

  /* ===================== Định dạng ===================== */

  function dinhDangTien(n, khongDonVi) {
    const v = Math.round(+n || 0);
    const s = v.toLocaleString('vi-VN');
    return khongDonVi ? s : s + ' ₫';
  }

  const HANG_DON_VI = ['', 'nghìn', 'triệu', 'tỷ'];
  const CHU_SO = ['không', 'một', 'hai', 'ba', 'bốn', 'năm', 'sáu', 'bảy', 'tám', 'chín'];

  function docBaChuSo(n, dayDu) {
    const tram = Math.floor(n / 100);
    const chuc = Math.floor((n % 100) / 10);
    const dv = n % 10;
    let s = '';
    if (tram > 0 || dayDu) s += CHU_SO[tram] + ' trăm';
    if (chuc === 0) {
      if (dv > 0 && (tram > 0 || dayDu)) s += ' linh ' + CHU_SO[dv];
      else if (dv > 0) s += CHU_SO[dv];
    } else if (chuc === 1) {
      s += ' mười';
      if (dv === 1) s += ' một';
      else if (dv === 5) s += ' lăm';
      else if (dv > 0) s += ' ' + CHU_SO[dv];
    } else {
      s += ' ' + CHU_SO[chuc] + ' mươi';
      if (dv === 1) s += ' mốt';
      else if (dv === 4) s += ' tư';
      else if (dv === 5) s += ' lăm';
      else if (dv > 0) s += ' ' + CHU_SO[dv];
    }
    return s.trim();
  }

  /** Số tiền bằng chữ — bắt buộc trên Ủy nhiệm chi và Phiếu chi. */
  function docSoThanhChu(n) {
    let v = Math.round(Math.abs(+n || 0));
    if (v === 0) return 'Không đồng';
    const am = (+n || 0) < 0;
    const nhom = [];
    while (v > 0) {
      nhom.unshift(v % 1000);
      v = Math.floor(v / 1000);
    }
    const ve = [];
    nhom.forEach((g, i) => {
      if (g === 0) return;
      const bac = nhom.length - 1 - i;
      const dayDu = i > 0; // nhóm sau nhóm đầu phải đọc đủ "không trăm"
      ve.push(gonTrang(docBaChuSo(g, dayDu) + ' ' + (HANG_DON_VI[bac % 4] || '')));
    });
    let s = gonTrang(ve.join(' '));
    s = s.charAt(0).toUpperCase() + s.slice(1);
    return (am ? 'Âm ' + s.toLowerCase() : s) + ' đồng';
  }

  /* ===================== Xuất API ===================== */

  return {
    TRANG_THAI,
    NHAN_TRANG_THAI,
    TAI_KHOAN_CTY,
    MOC_TUOI_NO,
    boDau,
    gonTrang,
    bam32,
    docTien,
    docNgay,
    dinhDangNgay,
    isoTuDate,
    tuSerialExcel,
    homNayISO,
    soNgayQua,
    docTaiKhoanCty,
    tachBP,
    khoaNCC,
    chuanSoTK,
    ganKhoa,
    trangThai,
    hanHieuLuc,
    ngayDiHieuLuc,
    dungDong,
    mocTuoi,
    loc,
    congDon,
    gomTheo,
    congNoNCC,
    theoKy,
    theoBP,
    theoTaiKhoanCty,
    bangTuoiNo,
    canhBao,
    dinhDangTien,
    docSoThanhChu,
    KHONG_TEN,
  };
});
