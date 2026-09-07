import React from "react";
import { Page, useNavigate } from "zmp-ui";
import TabBar from "../components/tabbar";

/* Giỏ hàng: bản đầu mua từng vé một nên giỏ để trống (chỗ này sẽ nối đa-vé sau). */
export default function GioHangPage() {
  const navigate = useNavigate();
  return (
    <Page className="tp">
      <div className="tp-head">Giỏ hàng</div>
      <div className="tp-empty">
        <div className="tp-empty-ic">🛒</div>
        <div className="tp-empty-t">Giỏ hàng trống</div>
        <div className="tp-empty-s">Chọn vé ở Trang chủ hoặc Danh mục để mua ngay.</div>
        <button className="tp-btn" onClick={() => navigate("/danhmuc")}>Xem danh mục vé</button>
      </div>
      <div style={{ height: 76 }} />
      <TabBar active="giohang" />
    </Page>
  );
}
