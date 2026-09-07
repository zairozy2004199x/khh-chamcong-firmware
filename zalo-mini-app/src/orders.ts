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
