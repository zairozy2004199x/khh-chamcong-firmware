import React from "react";
import { Page, useSnackbar } from "zmp-ui";
import { openChat } from "zmp-sdk";
import TabBar from "../components/tabbar";

/* Tin nhắn: mở chat với Official Account của khu vui chơi.
 * ⚠️ Thay OA_ID bằng ID Official Account của anh (trong Zalo OA / Mini App console). */
const OA_ID = "";

export default function TinNhanPage() {
  const snackbar = useSnackbar();
  const moChat = () => {
    if (!OA_ID) {
      snackbar.openSnackbar({ text: "Chưa cấu hình OA. Điền OA_ID trong tinnhan.tsx.", type: "warning" });
      return;
    }
    openChat({ type: "oa", id: OA_ID }).catch((e: any) =>
      snackbar.openSnackbar({ text: String(e?.message || e), type: "error" })
    );
  };
  return (
    <Page className="tp">
      <div className="tp-head">Tin nhắn</div>
      <div className="tp-empty">
        <div className="tp-empty-ic">💬</div>
        <div className="tp-empty-t">Cần hỗ trợ?</div>
        <div className="tp-empty-s">Nhắn tin trực tiếp cho khu vui chơi để được tư vấn vé, giờ mở cửa, ưu đãi.</div>
        <button className="tp-btn" onClick={moChat}>Nhắn tin ngay</button>
      </div>
      <div style={{ height: 76 }} />
      <TabBar active="tinnhan" />
    </Page>
  );
}
