import React, { useEffect, useMemo, useState } from "react";
import { Page, Spinner, useNavigate } from "zmp-ui";
import { layGoi, dinhTien, Goi } from "../api";

/* Màn bán vé: thanh tìm kiếm + hàng CHIP danh mục (theo `nhom`, có icon) để lọc + LƯỚI 2 CỘT
 * thẻ vé (ảnh + badge -% + giá gốc gạch + nút "+"). Bám mẫu FunZone. */
export default function HomePage() {
  const navigate = useNavigate();
  const [goi, setGoi] = useState<Goi[]>([]);
  const [loi, setLoi] = useState("");
  const [dangTai, setDangTai] = useState(true);
  const [nhomChon, setNhomChon] = useState<string>("");
  const [tim, setTim] = useState("");

  useEffect(() => {
    layGoi()
      .then((r) => {
        const ds = r.goi || [];
        setGoi(ds);
        const dau = (ds.find((g) => g.nhom?.trim())?.nhom || "").trim();
        setNhomChon(dau);
      })
      .catch((e) => setLoi(String(e.message || e)))
      .finally(() => setDangTai(false));
  }, []);

  // Danh sách nhóm (chip) + ảnh đại diện (lấy ảnh sản phẩm đầu tiên của nhóm).
  const nhomDs = useMemo(() => {
    const m = new Map<string, string>();
    goi.forEach((g) => {
      const k = g.nhom?.trim() || "Vé";
      if (!m.has(k)) m.set(k, g.anh || "");
    });
    return Array.from(m.entries()); // [ [ten, anh], ... ]
  }, [goi]);

  const hienThi = useMemo(() => {
    const t = tim.trim().toLowerCase();
    return goi.filter((g) => {
      const k = g.nhom?.trim() || "Vé";
      const hopNhom = !nhomChon || k === nhomChon;
      const hopTim = !t || g.ten.toLowerCase().includes(t);
      return hopNhom && hopTim;
    });
  }, [goi, nhomChon, tim]);

  const the = (g: Goi) => {
    const sale = g.gia_goc > g.tien ? Math.round((1 - g.tien / g.gia_goc) * 100) : 0;
    const moMua = () => navigate(`/buy/${g.ma}`, { state: { goi: g } });
    return (
      <div key={g.ma} className="gcard" onClick={moMua}>
        <div className="gcard-img">
          {g.anh ? <img src={g.anh} alt={g.ten} /> : <div className="gcard-noimg">🎟️</div>}
          {sale > 0 && <span className="gcard-sale">-{sale}%</span>}
        </div>
        <div className="gcard-ten">{g.ten}</div>
        <div className="gcard-foot">
          <div>
            <span className="gcard-gia">{dinhTien(g.tien)}</span>
            {sale > 0 && <div className="gcard-goc">{dinhTien(g.gia_goc)}</div>}
          </div>
          <button className="gcard-add" onClick={(e) => { e.stopPropagation(); moMua(); }}>+</button>
        </div>
      </div>
    );
  };

  return (
    <Page className="sp">
      {/* Header vàng + tìm kiếm */}
      <div className="sp-head">
        <div className="sp-home">🏠</div>
        <div className="sp-search">
          <span>🔍</span>
          <input placeholder="Tìm kiếm sản phẩm" value={tim} onChange={(e) => setTim(e.target.value)} />
        </div>
      </div>

      {/* Chip danh mục */}
      {nhomDs.length > 0 && (
        <div className="sp-chips">
          {nhomDs.map(([ten, anh]) => (
            <div key={ten} className={"sp-chip" + (ten === nhomChon ? " on" : "")} onClick={() => setNhomChon(ten)}>
              <div className="sp-chip-ic">{anh ? <img src={anh} alt={ten} /> : <span>🎟️</span>}</div>
              <span className="sp-chip-ten">{ten}</span>
            </div>
          ))}
        </div>
      )}

      {dangTai && <div className="sp-center"><Spinner /></div>}
      {loi && <div className="sp-loi">{loi}</div>}
      {!dangTai && !loi && hienThi.length === 0 && (
        <div className="sp-loi">Không có vé phù hợp. Vào web quản lý để thêm/sửa dịch vụ.</div>
      )}

      {/* Lưới 2 cột */}
      <div className="sp-grid">{hienThi.map(the)}</div>
      <div style={{ height: 16 }} />
    </Page>
  );
}
