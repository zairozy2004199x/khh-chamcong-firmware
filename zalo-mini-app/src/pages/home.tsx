import React, { useEffect, useMemo, useState } from "react";
import { Page, Spinner, useNavigate, useSnackbar } from "zmp-ui";
import { openWebview, getUserInfo } from "zmp-sdk";
import { layGoi, layTin, layDiem, coSoGanNhat, dinhTien, Goi, Tin } from "../api";
import { laySdt } from "../orders";
import TabBar from "../components/tabbar";
import ChonVe from "../components/chonve";

/* Trang chủ: header + thẻ Member (điểm/hạng) + lưới 6 tính năng + section vé theo nhóm
 * + Tin tức. Bám đúng app FunZone thật. */
const TINH_NANG = [
  { ic: "🎡", ten: "Dịch vụ", di: (n: any) => n("/danhmuc") },
  { ic: "🎁", ten: "Ưu đãi", di: (n: any) => n("/uudai") },
  { ic: "🎟️", ten: "Vé combo", di: (n: any) => n("/danhmuc", { state: { nhom: "Combo" } }) },
  { ic: "🎯", ten: "Vòng quay may mắn", di: (n: any, s: any) => s() },
  { ic: "👤", ten: "Thành viên", di: (n: any) => n("/canhan") },
  { ic: "📣", ten: "Tin tức", di: () => { const el = document.getElementById("hp-tin"); if (el) el.scrollIntoView({ behavior: "smooth" }); } },
];

export default function HomePage() {
  const navigate = useNavigate();
  const snackbar = useSnackbar();
  const [goi, setGoi] = useState<Goi[]>([]);
  const [tin, setTin] = useState<Tin[]>([]);
  const [loi, setLoi] = useState("");
  const [dangTai, setDangTai] = useState(true);
  const [ten, setTen] = useState("");
  const [avatar, setAvatar] = useState("");
  const [diem, setDiem] = useState(0);
  const [hang, setHang] = useState("Member");
  const [kvGoiY, setKvGoiY] = useState("");   // khu vực gợi ý theo định vị
  const [chon, setChon] = useState<Goi | null>(null);

  useEffect(() => {
    layGoi().then((r) => {
      setGoi(r.goi || []);
      coSoGanNhat(r.co_so || []).then((kv) => { if (kv) setKvGoiY(kv); }).catch(() => {});
    }).catch((e) => setLoi(String(e.message || e))).finally(() => setDangTai(false));
    layTin().then((r) => setTin(r.tin || [])).catch(() => {});
    getUserInfo({ autoRequestPermission: false }).then((r: any) => {
      setTen(r?.userInfo?.name || ""); setAvatar(r?.userInfo?.avatar || "");
    }).catch(() => {});
    const sdt = laySdt();
    if (sdt) layDiem(sdt).then((d) => { setDiem(d.diem); setHang(d.hang); }).catch(() => {});
  }, []);

  const nhomDs = useMemo(() => {
    const loc = goi.filter((g) => !kvGoiY || !(g.khu_vuc || "").trim() || (g.khu_vuc || "").trim() === kvGoiY);
    const m = new Map<string, Goi[]>();
    loc.forEach((g) => { const k = g.nhom?.trim() || "Vé"; if (!m.has(k)) m.set(k, []); m.get(k)!.push(g); });
    return Array.from(m.entries());
  }, [goi, kvGoiY]);

  const moTin = (t: Tin) => { openWebview({ url: t.link }).catch(() => { try { (window as any).open(t.link, "_blank"); } catch (e) {} }); };
  const vongQuay = () => snackbar.openSnackbar({ text: "Vòng quay may mắn sắp ra mắt 🎯", type: "info", duration: 1800 });

  const the = (g: Goi) => {
    const sale = g.gia_goc > g.tien ? Math.round((1 - g.tien / g.gia_goc) * 100) : 0;
    const het = g.so_luong >= 0 && g.so_luong < 1;
    const moChon = () => { if (!het) setChon(g); };
    return (
      <div key={g.ma} className={"pcard" + (het ? " pcard-het" : "")} onClick={moChon}>
        <div className="pcard-img">
          {g.anh ? <img src={g.anh} alt={g.ten} /> : <div className="pcard-noimg">🎟️</div>}
          {sale > 0 && <span className="pcard-sale">-{sale}%</span>}
          {het && <span className="pcard-het-badge">Hết vé</span>}
        </div>
        <div className="pcard-body">
          <div className="pcard-ten">{g.ten}</div>
          {g.so_luong >= 0 && <div className="pcard-con">{het ? "Hết vé" : "Còn " + g.so_luong + " vé"}</div>}
          <div className="pcard-gia-row">
            <div>
              <span className="pcard-gia">{dinhTien(g.tien)}</span>
              {sale > 0 && <span className="pcard-goc">{dinhTien(g.gia_goc)}</span>}
            </div>
            <button className="pcard-add" disabled={het} onClick={(e) => { e.stopPropagation(); moChon(); }}>+</button>
          </div>
        </div>
      </div>
    );
  };

  return (
    <Page className="hp">
      <div className="hp-head">
        <div className="hp-ava">{avatar ? <img src={avatar} alt="" /> : "👤"}</div>
        <div>
          <div className="hp-hi">Xin chào,</div>
          <div className="hp-name">{ten || "Khách"}</div>
        </div>
      </div>

      <div className="hp-mem" onClick={() => navigate("/canhan")}>
        <span className="hp-mem-hang">{hang}</span>
        <span className="hp-mem-diem">⭐ {diem.toLocaleString("vi-VN")} ›</span>
      </div>

      <div className="hp-feat-card">
        <div className="hp-feat">
          {TINH_NANG.map((f) => (
            <div key={f.ten} className="hp-feat-item" onClick={() => f.di(navigate, vongQuay)}>
              <div className="hp-feat-ic">{f.ic}</div>
              <span>{f.ten}</span>
            </div>
          ))}
        </div>
      </div>

      {kvGoiY && (
        <div className="hp-goiy">📍 Gợi ý theo vị trí: <b>{kvGoiY}</b>
          <span onClick={() => setKvGoiY("")}>Xem tất cả</span>
        </div>
      )}

      {dangTai && <div className="hp-center"><Spinner /></div>}
      {loi && <div className="hp-loi">{loi}</div>}

      {nhomDs.map(([ten, ds]) => (
        <div key={ten} className="hp-sec">
          <div className="hp-sec-head">
            <b>{ten}</b>
            <span className="hp-all" onClick={() => navigate("/danhmuc", { state: { nhom: ten } })}>Tất cả ›</span>
          </div>
          <div className="hp-row">{ds.map(the)}</div>
        </div>
      ))}

      {tin.length > 0 && (
        <div className="hp-sec" id="hp-tin">
          <div className="hp-sec-head"><b>Tin tức</b>{tin[0] && <span className="hp-all" onClick={() => moTin(tin[0])}>Tất cả ›</span>}</div>
          <div className="hp-news">
            {tin.map((t) => (
              <div key={t.id} className="ncard" onClick={() => moTin(t)}>
                <div className="ncard-img">{t.anh ? <img src={t.anh} alt={t.tieu_de} loading="lazy" /> : <div className="ncard-noimg">📰</div>}</div>
                <div className="ncard-t">{t.tieu_de}</div>
                <div className="ncard-m"><span>{t.ngay}</span>{t.luot_xem > 0 && <span>· 👁 {t.luot_xem}</span>}</div>
              </div>
            ))}
          </div>
        </div>
      )}

      <div style={{ height: 76 }} />
      <ChonVe goi={chon} onClose={() => setChon(null)} />
      <TabBar active="home" />
    </Page>
  );
}
