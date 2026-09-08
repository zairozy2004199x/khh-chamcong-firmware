/* Lưu vé đã đặt vào localStorage của thiết bị (không có bí mật, chỉ mã vé để tra cứu lại).
 * Dùng cho tab "Đơn hàng" trong Cá nhân. */
const KHOA = "posh_ve_orders";

export interface VeLuu {
  ma_ve: string;
  goi_ten: string;
  so_tien: number;
  tao_luc: number; // Date.now()
}

export function dsVe(): VeLuu[] {
  try {
    const s = localStorage.getItem(KHOA);
    const a = s ? JSON.parse(s) : [];
    return Array.isArray(a) ? a : [];
  } catch {
    return [];
  }
}

export function luuVe(v: VeLuu) {
  try {
    const ds = dsVe().filter((x) => x.ma_ve !== v.ma_ve);
    ds.unshift(v);
    localStorage.setItem(KHOA, JSON.stringify(ds.slice(0, 100)));
  } catch {
    /* bỏ qua nếu trình duyệt chặn localStorage */
  }
}

/* SĐT + tên gần nhất khách dùng (từ đăng nhập Zalo) — để tự điền thanh toán + tra điểm. */
const KHOA_SDT = "posh_sdt";
const KHOA_TEN = "posh_ten";
export function luuSdt(sdt: string) { try { localStorage.setItem(KHOA_SDT, sdt); } catch {} }
export function laySdt(): string { try { return localStorage.getItem(KHOA_SDT) || ""; } catch { return ""; } }
export function luuTen(ten: string) { try { localStorage.setItem(KHOA_TEN, ten); } catch {} }
export function layTen(): string { try { return localStorage.getItem(KHOA_TEN) || ""; } catch { return ""; } }
