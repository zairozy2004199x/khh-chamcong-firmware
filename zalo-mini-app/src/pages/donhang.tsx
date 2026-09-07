import React, { useEffect, useState } from "react";
import { Page, useNavigate } from "zmp-ui";
import { dsVe, VeLuu } from "../orders";
import { trangThaiVe, dinhTien } from "../api";
import TabBar from "../components/tabbar";

const NHAN: Record<string, { t: string; c: string }> = {
  cho: { t: "Chờ thanh toán", c: "cho" },
  da_tt: { t: "Đã thanh toán", c: "da_tt" },
  da_dung: { t: "Đã sử dụng", c: "da_dung" },
  huy: { t: "Đã huỷ", c: "huy" },
};

/* Đơn hàng: liệt kê vé đã đặt (lưu trên máy), hỏi lại trạng thái từ server. */
export default function DonHangPage() {
  const navigate = useNavigate();
  const [ds, setDs] = useState<VeLuu[]>([]);
  const [tt, setTt] = useState<Record<string, string>>({});

  useEffect(() => {
    const d = dsVe();
    setDs(d);
    d.forEach((v) => {
      trangThaiVe(v.ma_ve)
        .then((r) => setTt((o) => ({ ...o, [v.ma_ve]: r.trang_thai })))
        .catch(() => {});
    });
  }, []);

  return (
    <Page className="tp">
      <div className="tp-head">Đơn hàng của tôi</div>
      {ds.length === 0 ? (
        <div className="tp-empty">
          <div className="tp-empty-ic">🧾</div>
          <div className="tp-empty-t">Chưa có đơn nào</div>
          <div className="tp-empty-s">Vé anh mua sẽ hiện ở đây để tra cứu lại.</div>
          <button className="tp-btn" onClick={() => navigate("/danhmuc")}>Mua vé ngay</button>
        </div>
      ) : (
        <div className="dh-list">
          {ds.map((v) => {
            const st = tt[v.ma_ve] || "cho";
            const nh = NHAN[st] || NHAN.cho;
            return (
              <div key={v.ma_ve} className="dh-item"
                onClick={() => navigate(`/ticket/${v.ma_ve}`)}>
                <div className="dh-top">
                  <b>{v.goi_ten}</b>
                  <span className={"badge " + nh.c}>{nh.t}</span>
                </div>
                <div className="dh-bot">
                  <span className="dh-ma">Mã: {v.ma_ve}</span>
                  <span className="dh-tien">{dinhTien(v.so_tien)}</span>
                </div>
              </div>
            );
          })}
        </div>
      )}
      <div style={{ height: 76 }} />
      <TabBar active="canhan" />
    </Page>
  );
}
