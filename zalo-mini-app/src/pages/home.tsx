import React, { useEffect, useMemo, useState } from "react";
import { Page, Spinner, useNavigate } from "zmp-ui";
import { openWebview } from "zmp-sdk";
import { layGoi, layTin, dinhTien, Goi, Tin } from "../api";
import TabBar from "../components/tabbar";

/* Trang chủ: header + carousel vé theo nhóm (hàng cuộn ngang) + mục "Tin tức"
 * (bài viết WordPress trên khmatrix.com). Bám đúng app FunZone thật. */
export default function HomePage() {
  const navigate = useNavigate();
  const [goi, setGoi] = useState<Goi[]>([]);
  const [tin, setTin] = useState<Tin[]>([]);
  const [loi, setLoi] = useState("");
  const [dangTai, setDangTai] = useState(true);

  useEffect(() => {
    layGoi()
      .then((r) => setGoi(r.goi || []))
      .catch((e) => setLoi(String(e.message || e)))
      .finally(() => setDangTai(false));
    layTin().then((r) => setTin(r.tin || [])).catch(() => {});
  }, []);

  const nhomDs = useMemo(() => {
    const m = new Map<string, Goi[]>();
    goi.forEach((g) => {
      const k = g.nhom?.trim() || "Vé";
      if (!m.has(k)) m.set(k, []);
      m.get(k)!.push(g);
    });
    return Array.from(m.entries());
  }, [goi]);

  const moTin = (t: Tin) => {
    openWebview({ url: t.link }).catch(() => { try { (window as any).open(t.link, "_blank"); } catch (e) {} });
  };

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

      {tin.length > 0 && (
        <div className="hp-sec">
          <div className="hp-sec-head">
            <b>Tin tức</b>
            {tin[0] && <span className="hp-all" onClick={() => moTin(tin[0])}>Xem thêm ›</span>}
          </div>
          <div className="hp-news">
            {tin.map((t) => (
              <div key={t.id} className="ncard" onClick={() => moTin(t)}>
                <div className="ncard-img">
                  {t.anh ? <img src={t.anh} alt={t.tieu_de} loading="lazy" /> : <div className="ncard-noimg">📰</div>}
                </div>
                <div className="ncard-t">{t.tieu_de}</div>
                <div className="ncard-m">
                  <span>{t.ngay}</span>
                  {t.luot_xem > 0 && <span>· 👁 {t.luot_xem}</span>}
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      <div style={{ height: 76 }} />
      <TabBar active="home" />
    </Page>
  );
}
