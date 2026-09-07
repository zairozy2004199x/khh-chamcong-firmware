/* Giỏ hàng lưu trên máy (localStorage). Không có bí mật, chỉ danh sách vé + số lượng. */
import { Goi } from "./api";

const KHOA = "posh_ve_cart";

export interface MonGio { ma: number; ten: string; tien: number; anh: string; sl: number; }

export function layGio(): MonGio[] {
  try {
    const s = localStorage.getItem(KHOA);
    const a = s ? JSON.parse(s) : [];
    return Array.isArray(a) ? a : [];
  } catch {
    return [];
  }
}

function luu(ds: MonGio[]) {
  try { localStorage.setItem(KHOA, JSON.stringify(ds)); } catch { /* bỏ qua */ }
  try { window.dispatchEvent(new CustomEvent("posh-gio")); } catch {}
}

export function themVaoGio(g: Goi, sl = 1) {
  const ds = layGio();
  const m = ds.find((x) => x.ma === g.ma);
  if (m) { m.sl += sl; }
  else { ds.push({ ma: g.ma, ten: g.ten, tien: g.tien, anh: g.anh, sl }); }
  luu(ds);
}

export function datSoLuong(ma: number, sl: number) {
  let ds = layGio();
  if (sl <= 0) { ds = ds.filter((x) => x.ma !== ma); }
  else { const m = ds.find((x) => x.ma === ma); if (m) m.sl = sl; }
  luu(ds);
}

export function xoaKhoiGio(ma: number) { datSoLuong(ma, 0); }
export function xoaGio() { luu([]); }
export function tongTien(ds = layGio()) { return ds.reduce((s, x) => s + x.tien * x.sl, 0); }
export function demGio(ds = layGio()) { return ds.reduce((s, x) => s + x.sl, 0); }
