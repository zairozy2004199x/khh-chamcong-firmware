import React, { useEffect, useState } from "react";
import { Page, Spinner, useSnackbar } from "zmp-ui";
import { openWebview } from "zmp-sdk";
import { qlDangNhap, qlBaoCao, qlDonHang, qlCapNhat, dinhTien, TRANG_QL, BaoCao, DonQL } from "../api";

const PINKEY = "posh_ql_pin";
const NHAN: Record<string, string> = { cho: "Chờ", da_tt: "Đã thanh toán", da_dung: "Đã dùng", huy: "Đã huỷ" };
const LOCS: [string, string][] = [["", "Tất cả"], ["cho", "Chờ"], ["da_tt", "Đã TT"], ["da_dung", "Đã dùng"], ["huy", "Huỷ"], ["zalo", "📱 Zalo"], ["web", "🌐 Web"]];

/* Khu quản lý (nhân viên) — vào bằng PIN khai ở admin web. */
export default function QuanLyPage() {
  const snackbar = useSnackbar();
  const [pin, setPin] = useState<string>(() => { try { return localStorage.getItem(PINKEY) || ""; } catch { return ""; } });
  const [nhapPin, setNhapPin] = useState("");
  const [authed, setAuthed] = useState(false);
  const [tab, setTab] = useState<"baocao" | "don" | "soat">("baocao");

  useEffect(() => {
    if (!pin) return;
    qlBaoCao(pin).then(() => setAuthed(true)).catch(() => { setAuthed(false); setPin(""); try { localStorage.removeItem(PINKEY); } catch {} });
  }, [pin]);

  const dangNhap = async () => {
    const p = nhapPin.trim();
    if (!p) return;
    try {
      await qlDangNhap(p);
      try { localStorage.setItem(PINKEY, p); } catch {}
      setPin(p); setAuthed(true);
    } catch (e: any) {
      snackbar.openSnackbar({ text: String(e.message || e), type: "error" });
    }
  };
  const thoat = () => { try { localStorage.removeItem(PINKEY); } catch {}; setPin(""); setAuthed(false); setNhapPin(""); };

  if (!authed) {
    return (
      <Page className="tp">
        <div className="tp-head">🔒 Quản lý</div>
        <div className="ql-login">
          <div className="ql-login-ic">🔒</div>
          <div className="ql-login-t">Nhập mã PIN nhân viên</div>
          <input className="gio-in" inputMode="numeric" type="password" placeholder="Mã PIN" value={nhapPin}
            onChange={(e) => setNhapPin(e.target.value)} onKeyDown={(e) => e.key === "Enter" && dangNhap()} />
          <button className="tp-btn" onClick={dangNhap}>Vào quản lý</button>
          <div className="tp-empty-s">PIN do quản lý khai trong trang admin web (Vé khu vui chơi).</div>
        </div>
      </Page>
    );
  }

  return (
    <Page className="tp">
      <div className="tp-head">Quản lý <span className="ql-thoat" onClick={thoat}>Thoát</span></div>
      <div className="ql-tabs">
        <button className={tab === "baocao" ? "on" : ""} onClick={() => setTab("baocao")}>📊 Báo cáo</button>
        <button className={tab === "don" ? "on" : ""} onClick={() => setTab("don")}>🧾 Đơn vé</button>
        <button className={tab === "soat" ? "on" : ""} onClick={() => setTab("soat")}>🎫 Soát vé</button>
      </div>
      {tab === "baocao" && <BaoCaoTab pin={pin} />}
      {tab === "don" && <DonTab pin={pin} />}
      {tab === "soat" && <SoatTab pin={pin} />}
      <div className="ql-body">
        <button className="ql-taove" onClick={() => { openWebview({ url: TRANG_QL }).catch(() => { try { (window as any).open(TRANG_QL, "_blank"); } catch (e) {} }); }}>
          ＋ Tạo / sửa vé (mở trang web)
        </button>
        <div className="tp-empty-s" style={{ textAlign: "center", marginTop: 6 }}>Trang quản trị vé trên web — cần nhập lại PIN.</div>
      </div>
      <div style={{ height: 20 }} />
    </Page>
  );
}

function BaoCaoTab({ pin }: { pin: string }) {
  const [bc, setBc] = useState<BaoCao | null>(null);
  useEffect(() => { qlBaoCao(pin).then(setBc).catch(() => {}); }, [pin]);
  if (!bc) return <div className="tp-empty"><Spinner /></div>;
  const cards = [
    ["Doanh thu hôm nay", dinhTien(bc.dt_hnay)], ["Doanh thu tháng", dinhTien(bc.dt_thang)],
    ["Vé bán hôm nay", String(bc.ve_hnay)], ["Tổng vé đã bán", String(bc.ve_ban)], ["Vé đang chờ", String(bc.ve_cho)],
  ] as [string, string][];
  return (
    <div className="ql-body">
      <div className="ql-cards">
        {cards.map(([l, v]) => <div key={l} className="ql-card"><div className="ql-card-l">{l}</div><div className="ql-card-v">{v}</div></div>)}
      </div>
      <div className="ql-sec">Bán theo kênh</div>
      <div className="ql-tbl">
        <div className="ql-tr ql-th"><span>Kênh</span><b>Vé</b><b>Doanh thu</b></div>
        <div className="ql-tr"><span>📱 Zalo Mini App</span><b>{bc.ve_zalo}</b><b>{dinhTien(bc.dt_zalo)}</b></div>
        <div className="ql-tr"><span>🌐 Web</span><b>{bc.ve_web}</b><b>{dinhTien(bc.dt_web)}</b></div>
      </div>
      <div className="ql-sec">Vé bán chạy</div>
      <div className="ql-tbl">
        <div className="ql-tr ql-th"><span>Vé</span><b>SL</b><b>Doanh thu</b></div>
        {bc.top.map((t, i) => (
          <div key={i} className="ql-tr"><span>{t.ten}</span><b>{t.sl}</b><b>{dinhTien(t.dt)}</b></div>
        ))}
        {bc.top.length === 0 && <div className="ql-tr"><span>Chưa có vé bán</span><b></b><b></b></div>}
      </div>
    </div>
  );
}

function DonTab({ pin }: { pin: string }) {
  const snackbar = useSnackbar();
  const [loc, setLoc] = useState("");
  const [tim, setTim] = useState("");
  const [ds, setDs] = useState<DonQL[]>([]);
  const [tai, setTai] = useState(true);
  const nap = () => { setTai(true); qlDonHang(pin, loc, tim).then((r) => setDs(r.don || [])).catch(() => {}).finally(() => setTai(false)); };
  useEffect(() => { nap(); }, [loc]);

  const capNhat = async (ma: string, tt: string) => {
    try {
      const r = await qlCapNhat(pin, ma, tt);
      snackbar.openSnackbar({ text: "Đã cập nhật" + (r.diem_cong ? ` (+${r.diem_cong} điểm)` : ""), type: "success", duration: 1500 });
      nap();
    } catch (e: any) { snackbar.openSnackbar({ text: String(e.message || e), type: "error" }); }
  };

  return (
    <div className="ql-body">
      <div className="ql-timrow">
        <input className="gio-in" placeholder="Tìm mã vé / SĐT" value={tim}
          onChange={(e) => setTim(e.target.value)} onKeyDown={(e) => e.key === "Enter" && nap()} />
        <button className="tp-btn" onClick={nap}>Tìm</button>
      </div>
      <div className="ql-filters">
        {LOCS.map(([k, t]) => <button key={k} className={loc === k ? "on" : ""} onClick={() => setLoc(k)}>{t}</button>)}
      </div>
      {tai && <div className="tp-empty"><Spinner /></div>}
      {!tai && ds.length === 0 && <div className="tp-empty-s" style={{ textAlign: "center", padding: 20 }}>Không có đơn.</div>}
      {ds.map((d) => (
        <div key={d.ma_ve} className="ql-don">
          <div className="ql-don-top"><b>{d.dv_ten}</b><span className={"badge " + d.trang_thai}>{NHAN[d.trang_thai] || d.trang_thai}</span></div>
          <div className="ql-don-mid">
            <span>{d.ten_khach} · {d.sdt}</span><span className="ql-don-tien">{dinhTien(d.so_tien)}</span>
          </div>
          <div className="ql-don-ma">{d.nguon === "zalo" ? "📱 Zalo" : "🌐 Web"} · Mã: {d.ma_ve} · {d.tao_luc}</div>
          <div className="ql-don-act">
            {d.trang_thai === "cho" && <>
              <button className="ql-b ok" onClick={() => capNhat(d.ma_ve, "da_tt")}>Đã thanh toán</button>
              <button className="ql-b huy" onClick={() => capNhat(d.ma_ve, "huy")}>Huỷ</button>
            </>}
            {d.trang_thai === "da_tt" && <>
              <button className="ql-b ok" onClick={() => capNhat(d.ma_ve, "da_dung")}>Đã dùng (soát)</button>
              <button className="ql-b huy" onClick={() => capNhat(d.ma_ve, "huy")}>Huỷ</button>
            </>}
          </div>
        </div>
      ))}
    </div>
  );
}

function SoatTab({ pin }: { pin: string }) {
  const [ma, setMa] = useState("");
  const [kq, setKq] = useState<string>("");
  const [dang, setDang] = useState(false);
  const soat = async (tt: string) => {
    const m = ma.trim().toUpperCase();
    if (!m) return;
    setDang(true); setKq("");
    try {
      const r = await qlCapNhat(pin, m, tt);
      setKq(`✅ Vé ${r.ma_ve} → ${NHAN[r.trang_thai] || r.trang_thai}` + (r.diem_cong ? ` (+${r.diem_cong} điểm)` : ""));
    } catch (e: any) { setKq("✖ " + String(e.message || e)); }
    finally { setDang(false); }
  };
  return (
    <div className="ql-body">
      <div className="ql-sec">Soát vé tại cổng</div>
      <input className="gio-in" placeholder="Nhập mã vé" value={ma}
        onChange={(e) => setMa(e.target.value)} onKeyDown={(e) => e.key === "Enter" && soat("da_dung")} />
      <div className="ql-soat-btns">
        <button className="ql-b ok" disabled={dang} onClick={() => soat("da_dung")}>Đánh dấu ĐÃ DÙNG</button>
        <button className="ql-b" disabled={dang} onClick={() => soat("da_tt")}>Xác nhận đã thanh toán</button>
      </div>
      {kq && <div className="ql-kq">{kq}</div>}
      <div className="tp-empty-s" style={{ marginTop: 10 }}>Nhập mã khách đọc/đưa, bấm “Đã dùng” khi cho vào cổng.</div>
    </div>
  );
}
