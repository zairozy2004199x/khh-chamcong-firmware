import { getUserInfo, getPhoneNumber, getAccessToken } from "zmp-sdk";
import { zaloSdt } from "./api";
import { luuSdt, luuTen } from "./orders";

/* Lấy tên + SĐT thật của khách qua đăng nhập Zalo (xin quyền nếu cần), lưu lại để tự điền. */
export async function layTTZalo(): Promise<{ ten: string; sdt: string; avatar: string }> {
  const info: any = await getUserInfo({ autoRequestPermission: true });
  const ten = info?.userInfo?.name || "";
  const avatar = info?.userInfo?.avatar || "";
  const at: string = await new Promise((res, rej) => (getAccessToken as any)({ success: res, fail: rej }));
  const token: string = await new Promise((res, rej) => (getPhoneNumber as any)({ success: (d: any) => res(d.token), fail: rej }));
  const r = await zaloSdt(token, at);
  if (ten) luuTen(ten);
  if (r.sdt) luuSdt(r.sdt);
  return { ten, sdt: r.sdt, avatar };
}
