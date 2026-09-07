import React, { useEffect, useMemo, useState } from "react";
import { Page, Spinner, useNavigate, useLocation, useSnackbar } from "zmp-ui";
import { layGoi, dinhTien, Goi } from "../api";
import { themVaoGio } from "../cart";
import TabBar from "../components/tabbar";

/* Màn Danh mục: tìm kiếm + hàng CHIP danh mục (theo `nhom`) để lọc + LƯỚI 2 CỘT thẻ vé. */
export default function DanhMucPage() {
  const navigate = useNavigate();
  const location = useLocation();
  const snackbar = useSnackbar();
  const [goi, setGoi] = useState<Goi[]>([]);
  const [loi, setLoi] = useState("");
  const [dangTai, setDangTai] = useState(true);
  const [nhomChon, setNhomChon] = useState<string>((location.state as any)?.nhom || "");
  const [tim, setTim] = useState("");

  useEffect(() => {
    layGoi()
      .then((r) => {
        const ds = r.goi || [];
        setGoi(ds);
        if (!nhomChon) setNhomChon((ds.find((g) => g.nhom?.trim())?.nhom || "").trim());
      })
      .catch((e) => setLoi(String(e.message || e)))
      .finally(() => setDangTai(false));
  }, []);

  const nhomDs = useMemo(() => {
    const m = new Map<string, string>();
    goi.forEach((g) => { const k = g.nhom?.trim() || "Vé"; if (!m.has(k)) m.set(k, g.anh || ""); });
    return Array.from(m.entries());
  }, [goi]);

  const hienThi = useMemo(() => {
    const t = tim.trim().toLowerCase();
    return goi.filter((g) => {
      const k = g.nhom?.trim() || "Vé";
      return (!nhomChon || k === nhomChon) && (!t || g.ten.toLowerCase().includes(t));
    });
  }, [goi, nhomChon, tim]);

  const the = (g: Goi) => {
    const sale = g.gia_goc > g.tien ? Math.round((1 - g.tien / g.gia_goc) * 100) : 0;
    const het = g.so_luong >= 0 && g.so_luong < 1;
    const moMua = () => { if (!het) navigate(`/buy/${g.ma}`, { state: { goi: g } }); };
    const themGio = () => { themVaoGio(g); snackbar.openSnackbar({ text: "Đã thêm vào giỏ 🛒", type: "success", duration: 1500 }); };
    return (
      <div key={g.ma} className={"gcard" + (het ? " pcard-het" : "")} onClick={moMua}>
        <div className="gcard-img">
          {g.anh ? <img src={g.anh} alt={g.ten} /> : <div className="gcard-noimg">🎟️</div>}
          {sale > 0 && <span className="gcard-sale">-{sale}%</span>}
          {het && <span className="pcard-het-badge">Hết vé</span>}
        </div>
        <div className="gcard-ten">{g.ten}</div>
        {g.so_luong >= 0 && <div className="gcard-con">{het ? "Hết vé" : "Còn " + g.so_luong + " vé"}</div>}
        <div className="gcard-foot">
          <div>
            <span className="gcard-gia">{dinhTien(g.tien)}</span>
            {sale > 0 && <div className="gcard-goc">{dinhTien(g.gia_goc)}</div>}
          </div>
          <button className="gcard-add" disabled={het} onClick={(e) => { e.stopPropagation(); themGio(); }}>+</button>
        </div>
      </div>
    );
  };

  return (
    <Page className="sp">
      <div className="sp-head">
        <div className="sp-home">🏠</div>
        <div className="sp-search">
          <span>🔍</span>
          <input placeholder="Tìm kiếm sản phẩm" value={tim} onChange={(e) => setTim(e.target.value)} />
        </div>
      </div>

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
        <div className="sp-loi">Không có vé phù hợp.</div>
      )}

      <div className="sp-grid">{hienThi.map(the)}</div>
      <div style={{ height: 76 }} />
      <TabBar active="danhmuc" />
    </Page>
  );
}
