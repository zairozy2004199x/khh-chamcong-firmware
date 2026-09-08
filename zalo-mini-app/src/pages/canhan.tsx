import React, { useEffect, useState } from "react";
import { Page, useNavigate, useSnackbar } from "zmp-ui";
import { getUserInfo } from "zmp-sdk";
import { layDiem, dinhTien, Diem } from "../api";
import { laySdt } from "../orders";
import { layTTZalo } from "../zalo";
import TabBar from "../components/tabbar";

/* Cá nhân: đăng nhập Zalo (lấy tên/SĐT) -> quản lý vé & điểm. Bám app FunZone thật. */
interface NguoiDung { name?: string; avatar?: string; }

export default function CaNhanPage() {
  const navigate = useNavigate();
  const snackbar = useSnackbar();
  const [nd, setNd] = useState<NguoiDung>({});
  const [sdt, setSdt] = useState<string>(laySdt());
  const [diem, setDiem] = useState<Diem | null>(null);
  const [dangNhap, setDangNhap] = useState(false);

  useEffect(() => {
    getUserInfo({ autoRequestPermission: false }).then((r: any) => setNd(r?.userInfo || {})).catch(() => {});
  }, []);
  useEffect(() => {
    if (!sdt) { setDiem(null); return; }
    layDiem(sdt).then(setDiem).catch(() => setDiem(null));
  }, [sdt]);

  const dnZalo = async () => {
    setDangNhap(true);
    try {
      const r = await layTTZalo();
      if (r.avatar || r.ten) setNd({ name: r.ten, avatar: r.avatar });
      if (r.sdt) setSdt(r.sdt);
      snackbar.openSnackbar({ text: "Đăng nhập thành công", type: "success", duration: 1500 });
    } catch (e: any) {
      snackbar.openSnackbar({ text: "Chưa lấy được SĐT. Có thể nhập tay bên dưới.", type: "warning" });
    } finally { setDangNhap(false); }
  };

  const DON_TT = [
    { ic: "⏳", ten: "Chờ TT" }, { ic: "✅", ten: "Đã TT" }, { ic: "🎫", ten: "Đã dùng" }, { ic: "✖️", ten: "Đã huỷ" },
  ];
  const MENU = [
    { ic: "🎁", ten: "Ưu đãi", di: () => navigate("/uudai") },
    { ic: "🧾", ten: "Đơn hàng của tôi", di: () => navigate("/donhang") },
    { ic: "🎯", ten: "Lịch sử tích điểm", di: () => navigate("/donhang") },
    { ic: "📍", ten: "Sổ địa chỉ", di: () => {} },
    { ic: "🔒", ten: "Quản lý (nhân viên)", di: () => navigate("/quanly") },
  ];

  return (
    <Page className="cn">
      <div className="cn-head">
        <div className="cn-ava">{nd.avatar ? <img src={nd.avatar} alt="" /> : "👤"}</div>
        <div>
          <div className="cn-name">{nd.name || diem?.ten || "Khách"}</div>
          <div className="cn-sub">{sdt ? sdt : "Chưa đăng nhập"}</div>
        </div>
      </div>

      {diem ? (
        <div className="cn-diem" onClick={() => navigate("/donhang")}>
          <div className="cn-diem-top">
            <div><div className="cn-diem-l">Điểm tích luỹ</div><div className="cn-diem-v">{diem.diem.toLocaleString("vi-VN")}</div></div>
            <div className="cn-hang">{diem.hang}</div>
          </div>
          {diem.hang_ke
            ? <><div className="cn-bar"><span style={{ width: pct(diem) + "%" }} /></div>
                <div className="cn-diem-note">Còn <b>{diem.con_thieu.toLocaleString("vi-VN")}</b> điểm để lên <b>{diem.hang_ke}</b></div></>
            : <div className="cn-diem-note">Bạn đang ở hạng cao nhất 🎉</div>}
          <div className="cn-diem-sub"><span>Đã chi: {dinhTien(diem.tong_chi)}</span><span>{diem.so_don} đơn</span></div>
        </div>
      ) : (
        <div className="cn-banner">
          <div>
            <div className="cn-banner-t">Đăng nhập bằng Zalo</div>
            <div className="cn-banner-s">Để quản lý vé, trạng thái vé và tích điểm.</div>
          </div>
          <button className="cn-banner-b" disabled={dangNhap} onClick={dnZalo}>{dangNhap ? "..." : "Đăng nhập"}</button>
        </div>
      )}

      <div className="cn-don">
        <div className="cn-don-head" onClick={() => navigate("/donhang")}>
          <b>Đơn hàng</b><span>Xem tất cả ›</span>
        </div>
        <div className="cn-don-tt">
          {DON_TT.map((d) => (
            <div key={d.ten} className="cn-don-i" onClick={() => navigate("/donhang")}>
              <div className="cn-don-ic">{d.ic}</div><span>{d.ten}</span>
            </div>
          ))}
        </div>
      </div>

      <div className="cn-menu">
        {MENU.map((m) => (
          <div key={m.ten} className="cn-item" onClick={m.di}>
            <span className="cn-item-ic">{m.ic}</span><span className="cn-item-ten">{m.ten}</span><span className="cn-item-mui">›</span>
          </div>
        ))}
      </div>

      <div style={{ height: 76 }} />
      <TabBar active="canhan" />
    </Page>
  );
}

function pct(d: Diem): number {
  const moc = d.moc || [];
  const keIdx = moc.findIndex((m) => m.ten === d.hang_ke);
  if (keIdx < 0) return 100;
  const tran = moc[keIdx].moc, san = keIdx > 0 ? moc[keIdx - 1].moc : 0;
  if (tran <= san) return 100;
  return Math.max(0, Math.min(100, Math.round(((d.diem - san) / (tran - san)) * 100)));
}
