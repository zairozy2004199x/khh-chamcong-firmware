import React, { useEffect, useState } from "react";
import { useNavigate } from "zmp-ui";
import { demGio } from "../cart";

/* Thanh tab dưới 5 mục (dùng chung cho các màn chính). Tab Giỏ hàng có badge số lượng. */
const TABS = [
  { key: "home", icon: "🏠", ten: "Trang chủ", path: "/" },
  { key: "danhmuc", icon: "🗂️", ten: "Danh mục", path: "/danhmuc" },
  { key: "giohang", icon: "🛒", ten: "Giỏ hàng", path: "/giohang" },
  { key: "tinnhan", icon: "💬", ten: "Tin nhắn", path: "/tinnhan" },
  { key: "canhan", icon: "👤", ten: "Cá nhân", path: "/canhan" },
];

export default function TabBar({ active }: { active: string }) {
  const nav = useNavigate();
  const [dem, setDem] = useState(0);

  useEffect(() => {
    const nap = () => setDem(demGio());
    nap();
    window.addEventListener("posh-gio", nap);
    return () => window.removeEventListener("posh-gio", nap);
  }, []);

  return (
    <div className="tabbar">
      {TABS.map((t) => (
        <div key={t.key} className={"tabbar-i" + (t.key === active ? " on" : "")} onClick={() => nav(t.path)}>
          <div className="tabbar-ic">
            {t.icon}
            {t.key === "giohang" && dem > 0 && <span className="tabbar-badge">{dem}</span>}
          </div>
          <span>{t.ten}</span>
        </div>
      ))}
    </div>
  );
}
