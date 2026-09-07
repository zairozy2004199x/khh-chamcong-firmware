/* Kết nối backend WordPress (vhcp-ghe) — REST công khai vhg/v1/ve/*.
 * ⚠️ ĐỔI BASE cho đúng tên miền web của anh (khmatrix.com). KHÔNG có bí mật ở đây;
 *    số tài khoản nhận tiền do server trả về (vốn công khai trên QR). */
export const BASE = "https://khmatrix.com/wp-json/posh/v1";

export interface Goi { ma: number; ten: string; tien: number; gia_goc: number; nhom: string; mo_ta: string; anh: string; thoi_luong: string; }
export interface BankTT { ten_nh: string; so_tk: string; ten_tk: string; }
export interface Ve {
  ma_ve: string; so_tien: number; goi_ten: string;
  noi_dung: string; qr: string; trang_thai: string; bank: BankTT;
}

async function json<T>(url: string, opt?: RequestInit): Promise<T> {
  const r = await fetch(url, { headers: { "Content-Type": "application/json" }, ...opt });
  const d = await r.json().catch(() => ({}));
  if (!r.ok || d.ok === false) throw new Error(d?.message || d?.code || "Lỗi kết nối máy chủ.");
  return d as T;
}

export function layGoi() {
  return json<{ ok: boolean; goi: Goi[]; bank: BankTT }>(`${BASE}/ve/goi`);
}
export function datVe(id: number, ten: string, sdt: string) {
  return json<Ve & { ok: boolean }>(`${BASE}/ve/dat`, {
    method: "POST",
    body: JSON.stringify({ id, ten, sdt }),
  });
}
export function trangThaiVe(maVe: string) {
  return json<{ ok: boolean; ma_ve: string; goi_ten: string; so_tien: number; phut: number;
    trang_thai: string; tao_luc: string; tt_luc: string | null }>(
    `${BASE}/ve/trangthai?ma_ve=${encodeURIComponent(maVe)}`);
}

export const dinhTien = (n: number) => (Number(n) || 0).toLocaleString("vi-VN") + "đ";
