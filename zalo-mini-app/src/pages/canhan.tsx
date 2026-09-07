import React, { useEffect, useState } from "react";
import { Page, useNavigate } from "zmp-ui";
import { getUserInfo } from "zmp-sdk";
import { layDiem, dinhTien, Diem } from "../api";
import { laySdt, luuSdt } from "../orders";
import TabBar from "../components/tabbar";

/* Cá nhân: tên/ảnh Zalo + thẻ điểm/hạng thành viên (tra theo SĐT đã mua) + menu tiện ích. */
interface NguoiDung { name?: string; avatar?: string; }

export default function CaNhanPage() {
  const navigate = useNavigate();
  const [nd, setNd] = useState<NguoiDung>({});
  const [sdt, setSdt] = useState<string>(laySdt());
  const [nhap, setNhap] = useState<string>("");
  const [diem, setDiem] = useState<Diem | null>(null);

  useEffect(() => {
    getUserInfo({ autoRequestPermission: false })
      .then((r: any) => setNd(r?.userInfo || {}))
      .catch(() => {});
  }, []);

  useEffect(() => {
    if (!sdt) { setDiem(null); return; }
    layDiem(sdt).then(setDiem).catch(() => setDiem(null));
  }, [sdt]);

  const luuTra = () => {
    const s = nhap.replace(/[^0-9+]/g, "");
    if (!s) return;
    luuSdt(s); setSdt(s); setNhap("");
  };

  const MENU = [
    { ic: "🧾", ten: "Đơn hàng của tôi", di: () => navigate("/donhang") },
    { ic: "🎁", ten: "Lịch sử tích điểm", di: () => navigate("/donhang") },
    { ic: "📍", ten: "Sổ địa chỉ", di: () => {} },
    { ic: "☎️", ten: "Liên hệ hỗ trợ", di: () => navigate("/tinnhan") },
  ];

  return (
    <Page className="cn">
      <div className="cn-head">
        <div className="cn-ava">{nd.avatar ? <img src={nd.avatar} alt="" /> : "👤"}</div>
        <div>
          <div className="cn-name">{nd.name || diem?.ten || "Khách"}</div>
          <div className="cn-sub">{diem ? "Hạng " + diem.hang : "Thành viên khu vui chơi POSH"}</div>
        </div>
      </div>

      {diem ? (
        <div className="cn-diem">
          <div className="cn-diem-top">
            <div>
              <div className="cn-diem-l">Điểm tích luỹ</div>
              <div className="cn-diem-v">{diem.diem.toLocaleString("vi-VN")}</div>
            </div>
            <div className="cn-hang">{diem.hang}</div>
          </div>
          {diem.hang_ke ? (
            <>
              <div className="cn-bar"><span style={{ width: pct(diem) + "%" }} /></div>
              <div className="cn-diem-note">Còn <b>{diem.con_thieu.toLocaleString("vi-VN")}</b> điểm để lên hạng <b>{diem.hang_ke}</b></div>
            </>
          ) : (
            <div className="cn-diem-note">Bạn đang ở hạng cao nhất 🎉</div>
          )}
          <div className="cn-diem-sub">
            <span>SĐT: {diem.sdt}</span>
            <span>Đã chi: {dinhTien(diem.tong_chi)} · {diem.so_don} đơn</span>
          </div>
          <div className="cn-doi" onClick={() => { luuSdt(""); setSdt(""); }}>Đổi số điện thoại</div>
        </div>
      ) : (
        <div className="cn-banner">
          <div>
            <div className="cn-banner-t">Xem điểm & hạng thành viên</div>
            <div className="cn-banner-s">Nhập SĐT bạn dùng khi mua vé (1 điểm = 1.000đ).</div>
            <div className="cn-nhap">
              <input inputMode="tel" placeholder="Số điện thoại" value={nhap} onChange={(e) => setNhap(e.target.value)} />
              <button onClick={luuTra}>Xem</button>
            </div>
          </div>
        </div>
      )}

      <div className="cn-menu">
        {MENU.map((m) => (
          <div key={m.ten} className="cn-item" onClick={m.di}>
            <span className="cn-item-ic">{m.ic}</span>
            <span className="cn-item-ten">{m.ten}</span>
            <span className="cn-item-mui">›</span>
          </div>
        ))}
      </div>

      <div style={{ height: 76 }} />
      <TabBar active="canhan" />
    </Page>
  );
}

/* % tiến độ tới hạng kế: dựa trên mốc hạng hiện tại → mốc kế. */
function pct(d: Diem): number {
  const moc = d.moc || [];
  const keIdx = moc.findIndex((m) => m.ten === d.hang_ke);
  if (keIdx < 0) return 100;
  const tran = moc[keIdx].moc;
  const san = keIdx > 0 ? moc[keIdx - 1].moc : 0;
  if (tran <= san) return 100;
  return Math.max(0, Math.min(100, Math.round(((d.diem - san) / (tran - san)) * 100)));
}
