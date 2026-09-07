/* Kết nối backend WordPress (vhcp-ghe) — REST công khai vhg/v1/ve/*.
 * ⚠️ ĐỔI BASE cho đúng tên miền web của anh (khmatrix.com). KHÔNG có bí mật ở đây;
 *    số tài khoản nhận tiền do server trả về (vốn công khai trên QR). */
export const BASE = "https://khmatrix.com/wp-json/posh/v1";

export interface Goi { ma: number; ten: string; tien: number; gia_goc: number; nhom: string; khu_vuc: string; mo_ta: string; anh: string; thoi_luong: string; so_luong: number; }
export interface CoSo { ten: string; lat: number; lng: number; }
export interface BankTT { ten_nh: string; so_tk: string; ten_tk: string; }
export interface Tin { id: number; tieu_de: string; anh: string; ngay: string; luot_xem: number; link: string; }
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
  return json<{ ok: boolean; goi: Goi[]; co_so: CoSo[]; bank: BankTT }>(`${BASE}/ve/goi`);
}

/* Định vị: tìm cơ sở gần nhất để gợi ý khu vực. Trả "" nếu không lấy được vị trí. */
export function coSoGanNhat(coSo: CoSo[]): Promise<string> {
  return new Promise((resolve) => {
    const ds = (coSo || []).filter((c) => c.lat && c.lng);
    if (!ds.length || !("geolocation" in navigator)) { resolve(""); return; }
    navigator.geolocation.getCurrentPosition(
      (pos) => {
        const { latitude: la, longitude: lo } = pos.coords;
        let best = "", bestD = Infinity;
        for (const c of ds) {
          const dx = c.lat - la, dy = c.lng - lo;
          const d = dx * dx + dy * dy; // khoảng cách gần đúng, đủ để so sánh
          if (d < bestD) { bestD = d; best = c.ten; }
        }
        resolve(best);
      },
      () => resolve(""),
      { timeout: 6000, maximumAge: 300000 }
    );
  });
}
export function layTin() {
  return json<{ ok: boolean; tin: Tin[] }>(`${BASE}/tin`);
}
export function datVe(id: number, ten: string, sdt: string) {
  return json<Ve & { ok: boolean }>(`${BASE}/ve/dat`, {
    method: "POST",
    body: JSON.stringify({ id, ten, sdt }),
  });
}
export function datGio(items: { id: number; sl: number }[], ten: string, sdt: string) {
  return json<Ve & { ok: boolean }>(`${BASE}/ve/dat-gio`, {
    method: "POST",
    body: JSON.stringify({ items, ten, sdt }),
  });
}
export interface Diem {
  ok: boolean; sdt: string; ten: string; diem: number; tong_chi: number; so_don: number;
  hang: string; hang_ke: string; con_thieu: number; moc: { ten: string; moc: number }[];
}
export function layDiem(sdt: string) {
  return json<Diem>(`${BASE}/tv?sdt=${encodeURIComponent(sdt)}`);
}
export function trangThaiVe(maVe: string) {
  return json<{ ok: boolean; ma_ve: string; goi_ten: string; so_tien: number; phut: number;
    trang_thai: string; tao_luc: string; tt_luc: string | null }>(
    `${BASE}/ve/trangthai?ma_ve=${encodeURIComponent(maVe)}`);
}

export const dinhTien = (n: number) => (Number(n) || 0).toLocaleString("vi-VN") + "đ";

/* ── Ưu đãi (khách) ── */
export interface Uudai { id: number; ten: string; mo_ta: string; anh: string; hang: string; han: string; }
export function layUudai() {
  return json<{ ok: boolean; uudai: Uudai[] }>(`${BASE}/uudai`);
}

/* ── Khu quản lý (nhân viên, có PIN) ── */
export interface BaoCao {
  ok: boolean; dt_hnay: number; dt_thang: number; ve_ban: number; ve_cho: number; ve_hnay: number;
  top: { ten: string; sl: number; dt: number }[];
}
export interface DonQL {
  ma_ve: string; dv_ten: string; so_tien: number; ten_khach: string; sdt: string; trang_thai: string; tao_luc: string;
}
export function qlDangNhap(pin: string) {
  return json<{ ok: boolean }>(`${BASE}/ql/dangnhap`, { method: "POST", body: JSON.stringify({ pin }) });
}
export function qlBaoCao(pin: string) {
  return json<BaoCao>(`${BASE}/ql/baocao?pin=${encodeURIComponent(pin)}`);
}
export function qlDonHang(pin: string, loc = "", tim = "") {
  return json<{ ok: boolean; don: DonQL[] }>(
    `${BASE}/ql/donhang?pin=${encodeURIComponent(pin)}&loc=${encodeURIComponent(loc)}&tim=${encodeURIComponent(tim)}`);
}
export function qlCapNhat(pin: string, maVe: string, trangThai: string) {
  return json<{ ok: boolean; ma_ve: string; trang_thai: string; diem_cong: number }>(
    `${BASE}/ql/capnhat`, { method: "POST", body: JSON.stringify({ pin, ma_ve: maVe, trang_thai: trangThai }) });
}
