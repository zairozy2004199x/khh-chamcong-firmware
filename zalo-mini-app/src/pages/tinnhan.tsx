import React from "react";
import { Page, useNavigate, useSnackbar } from "zmp-ui";
import { openChat } from "zmp-sdk";
import TabBar from "../components/tabbar";

/* Tin nhắn: hỗ trợ khách — chat Official Account + lối tắt hay dùng.
 * ⚠️ Thay OA_ID bằng ID Official Account (Zalo OA / Mini App console). */
const OA_ID = "";

export default function TinNhanPage() {
  const navigate = useNavigate();
  const snackbar = useSnackbar();
  const moChat = () => {
    if (!OA_ID) { snackbar.openSnackbar({ text: "Chưa cấu hình OA (điền OA_ID trong tinnhan.tsx).", type: "warning" }); return; }
    openChat({ type: "oa", id: OA_ID }).catch((e: any) => snackbar.openSnackbar({ text: String(e?.message || e), type: "error" }));
  };

  const TAT = [
    { ic: "🎟️", ten: "Vé của tôi", di: () => navigate("/donhang") },
    { ic: "🎁", ten: "Ưu đãi", di: () => navigate("/uudai") },
    { ic: "🛒", ten: "Giỏ hàng", di: () => navigate("/giohang") },
    { ic: "📣", ten: "Tin tức", di: () => navigate("/") },
  ];

  return (
    <Page className="tp">
      <div className="tp-head">Tin nhắn</div>

      <div className="tn-hero">
        <div className="tn-hero-ic">💬</div>
        <div className="tn-hero-t">Cần hỗ trợ?</div>
        <div className="tn-hero-s">Nhắn tin trực tiếp để được tư vấn vé, giờ mở cửa, ưu đãi.</div>
        <button className="tn-hero-b" onClick={moChat}>Nhắn tin ngay</button>
      </div>

      <div className="tn-sec">Lối tắt</div>
      <div className="tn-grid">
        {TAT.map((t) => (
          <div key={t.ten} className="tn-item" onClick={t.di}>
            <div className="tn-item-ic">{t.ic}</div>
            <span>{t.ten}</span>
          </div>
        ))}
      </div>

      <div className="tn-note">Giờ mở cửa: 9:00–22:00 hằng ngày · Hotline hiển thị trong OA.</div>

      <div style={{ height: 76 }} />
      <TabBar active="tinnhan" />
    </Page>
  );
}
