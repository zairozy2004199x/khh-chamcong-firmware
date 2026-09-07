import React, { useEffect, useMemo, useState } from "react";
import { Page, Spinner, useNavigate, useSnackbar } from "zmp-ui";
import { layGoi, dinhTien, Goi } from "../api";

/* Trang chủ kiểu sàn bán vé (FunZone): header chào khách + hàng tính năng + các NHÓM dịch vụ
 * cuộn ngang, thẻ có ảnh + badge giảm giá + giá gốc gạch + nút "+". Thanh tab dưới (Giai đoạn 1:
 * Giỏ hàng/Thành viên/Ưu đãi… chỉ hiện, làm chức năng ở giai đoạn sau). */

const TINH_NANG = [
  { icon: "🎡", ten: "Dịch vụ" },
  { icon: "🎁", ten: "Ưu đãi" },
  { icon: "🎟️", ten: "Vé combo" },
  { icon: "🎯", ten: "Vòng quay" },
  { icon: "🪪", ten: "Thành viên" },
  { icon: "📣", ten: "Tin tức" },
];

const TAB = [
  { icon: "🏠", ten: "Trang chủ" },
  { icon: "🗂️", ten: "Danh mục" },
  { icon: "🛒", ten: "Giỏ hàng" },
  { icon: "💬", ten: "Tin nhắn" },
  { icon: "👤", ten: "Cá nhân" },
];

export default function HomePage() {
  const navigate = useNavigate();
  const snackbar = useSnackbar();
  const [goi, setGoi] = useState<Goi[]>([]);
  const [loi, setLoi] = useState("");
  const [dangTai, setDangTai] = useState(true);

  useEffect(() => {
    layGoi()
      .then((r) => setGoi(r.goi || []))
      .catch((e) => setLoi(String(e.message || e)))
      .finally(() => setDangTai(false));
  }, []);

  // Gom theo nhóm, giữ thứ tự nhóm xuất hiện. Không có nhóm -> "Vé".
  const nhomDs = useMemo(() => {
    const m = new Map<string, Goi[]>();
    goi.forEach((g) => {
      const k = g.nhom?.trim() || "Vé";
      if (!m.has(k)) m.set(k, []);
      m.get(k)!.push(g);
    });
    return Array.from(m.entries());
  }, [goi]);

  const sapCo = () => snackbar.openSnackbar({ text: "Tính năng đang phát triển.", type: "info" });

  const the = (g: Goi) => {
    const sale = g.gia_goc > g.tien ? Math.round((1 - g.tien / g.gia_goc) * 100) : 0;
    return (
      <div key={g.ma} className="pcard" onClick={() => navigate(`/buy/${g.ma}`, { state: { goi: g } })}>
        <div className="pcard-img">
          {g.anh ? <img src={g.anh} alt={g.ten} /> : <div className="pcard-noimg">🎟️</div>}
          {sale > 0 && <span className="pcard-sale">-{sale}%</span>}
        </div>
        <div className="pcard-body">
          <div className="pcard-ten">{g.ten}</div>
          <div className="pcard-gia-row">
            <div>
              <span className="pcard-gia">{dinhTien(g.tien)}</span>
              {sale > 0 && <span className="pcard-goc">{dinhTien(g.gia_goc)}</span>}
            </div>
            <button className="pcard-add" onClick={(e) => { e.stopPropagation(); navigate(`/buy/${g.ma}`, { state: { goi: g } }); }}>+</button>
          </div>
        </div>
      </div>
    );
  };

  return (
    <Page className="hp">
      {/* Header vàng */}
      <div className="hp-head">
        <div className="hp-ava" />
        <div>
          <div className="hp-hi">Xin chào,</div>
          <div className="hp-name">Khách</div>
        </div>
      </div>

      {/* Thẻ tính năng */}
      <div className="hp-feat-card">
        <div className="hp-feat">
          {TINH_NANG.map((f, i) => (
            <div key={i} className="hp-feat-item" onClick={sapCo}>
              <div className="hp-feat-ic">{f.icon}</div>
              <span>{f.ten}</span>
            </div>
          ))}
        </div>
      </div>

      {dangTai && <div className="hp-center"><Spinner /></div>}
      {loi && <div className="hp-loi">{loi}</div>}
      {!dangTai && !loi && goi.length === 0 && (
        <div className="hp-loi">Chưa có dịch vụ. Vào web quản lý để thêm vé.</div>
      )}

      {/* Các nhóm */}
      {nhomDs.map(([nhom, items]) => (
        <div key={nhom} className="hp-sec">
          <div className="hp-sec-head">
            <b>{nhom}</b>
            <span className="hp-all" onClick={sapCo}>Tất cả</span>
          </div>
          <div className="hp-row">{items.map(the)}</div>
        </div>
      ))}

      <div style={{ height: 70 }} />

      {/* Tab dưới */}
      <div className="hp-tab">
        {TAB.map((t, i) => (
          <div key={i} className={"hp-tab-i" + (i === 0 ? " on" : "")} onClick={i === 0 ? undefined : sapCo}>
            <div className="hp-tab-ic">{t.icon}</div>
            <span>{t.ten}</span>
          </div>
        ))}
      </div>
    </Page>
  );
}
