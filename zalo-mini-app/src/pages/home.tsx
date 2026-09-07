import React, { useEffect, useMemo, useState } from "react";
import { Page, Spinner, useNavigate } from "zmp-ui";
import { layGoi, dinhTien, Goi } from "../api";
import TabBar from "../components/tabbar";

/* Trang chủ: header vàng + lưới TÍNH NĂNG (icon tròn) + các HÀNG NGANG theo nhóm vé
 * (mỗi nhóm 1 hàng cuộn ngang). Bám mẫu FunZone. */
const TINH_NANG = [
  { ic: "🎟️", ten: "Mua vé", nhom: "" },
  { ic: "🗂️", ten: "Danh mục", nhom: "" },
  { ic: "🎁", ten: "Ưu đãi", nhom: "" },
  { ic: "📅", ten: "Lịch mở cửa", nhom: "" },
  { ic: "📍", ten: "Bản đồ", nhom: "" },
  { ic: "☎️", ten: "Liên hệ", nhom: "" },
];

export default function HomePage() {
  const navigate = useNavigate();
  const [goi, setGoi] = useState<Goi[]>([]);
  const [loi, setLoi] = useState("");
  const [dangTai, setDangTai] = useState(true);

  useEffect(() => {
    layGoi()
      .then((r) => setGoi(r.goi || []))
      .catch((e) => setLoi(String(e.message || e)))
      .finally(() => setDangTai(false));
  }, []);

  // Nhóm vé -> [ [ten_nhom, Goi[] ], ... ] giữ thứ tự xuất hiện.
  const nhomDs = useMemo(() => {
    const m = new Map<string, Goi[]>();
    goi.forEach((g) => {
      const k = g.nhom?.trim() || "Vé";
      if (!m.has(k)) m.set(k, []);
      m.get(k)!.push(g);
    });
    return Array.from(m.entries());
  }, [goi]);

  const the = (g: Goi) => {
    const sale = g.gia_goc > g.tien ? Math.round((1 - g.tien / g.gia_goc) * 100) : 0;
    const moMua = () => navigate(`/buy/${g.ma}`, { state: { goi: g } });
    return (
      <div key={g.ma} className="pcard" onClick={moMua}>
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
            <button className="pcard-add" onClick={(e) => { e.stopPropagation(); moMua(); }}>+</button>
          </div>
        </div>
      </div>
    );
  };

  return (
    <Page className="hp">
      <div className="hp-head">
        <div className="hp-ava" />
        <div>
          <div className="hp-hi">Xin chào 👋</div>
          <div className="hp-name">Khu vui chơi POSH</div>
        </div>
      </div>

      <div className="hp-feat-card">
        <div className="hp-feat">
          {TINH_NANG.map((f) => (
            <div key={f.ten} className="hp-feat-item" onClick={() => navigate("/danhmuc", { state: { nhom: f.nhom } })}>
              <div className="hp-feat-ic">{f.ic}</div>
              <span>{f.ten}</span>
            </div>
          ))}
        </div>
      </div>

      {dangTai && <div className="hp-center"><Spinner /></div>}
      {loi && <div className="hp-loi">{loi}</div>}

      {nhomDs.map(([ten, ds]) => (
        <div key={ten} className="hp-sec">
          <div className="hp-sec-head">
            <b>{ten}</b>
            <span className="hp-all" onClick={() => navigate("/danhmuc", { state: { nhom: ten } })}>Xem tất cả ›</span>
          </div>
          <div className="hp-row">{ds.map(the)}</div>
        </div>
      ))}

      <div style={{ height: 76 }} />
      <TabBar active="home" />
    </Page>
  );
}
