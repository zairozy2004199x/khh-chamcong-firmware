import React, { useEffect, useState } from "react";
import { Page, useNavigate } from "zmp-ui";
import { getUserInfo } from "zmp-sdk";
import TabBar from "../components/tabbar";

/* Cá nhân: lấy tên/ảnh Zalo (nếu cho phép) + banner đăng ký thành viên + menu tiện ích. */
interface NguoiDung { name?: string; avatar?: string; }

export default function CaNhanPage() {
  const navigate = useNavigate();
  const [nd, setNd] = useState<NguoiDung>({});

  useEffect(() => {
    getUserInfo({ autoRequestPermission: false })
      .then((r: any) => setNd(r?.userInfo || {}))
      .catch(() => {});
  }, []);

  const MENU = [
    { ic: "🧾", ten: "Đơn hàng của tôi", di: () => navigate("/donhang") },
    { ic: "🎁", ten: "Lịch sử tích điểm", di: () => {} },
    { ic: "📍", ten: "Sổ địa chỉ", di: () => {} },
    { ic: "☎️", ten: "Liên hệ hỗ trợ", di: () => navigate("/tinnhan") },
  ];

  return (
    <Page className="cn">
      <div className="cn-head">
        <div className="cn-ava">{nd.avatar ? <img src={nd.avatar} alt="" /> : "👤"}</div>
        <div>
          <div className="cn-name">{nd.name || "Khách"}</div>
          <div className="cn-sub">Thành viên khu vui chơi POSH</div>
        </div>
      </div>

      <div className="cn-banner">
        <div>
          <div className="cn-banner-t">Đăng ký thành viên</div>
          <div className="cn-banner-s">Tích điểm mỗi lần mua vé, đổi quà & ưu đãi.</div>
        </div>
        <button className="cn-banner-b">Đăng ký</button>
      </div>

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
