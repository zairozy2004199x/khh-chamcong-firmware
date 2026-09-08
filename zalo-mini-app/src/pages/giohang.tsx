import React, { useEffect, useState } from "react";
import { Page, useNavigate, useSnackbar } from "zmp-ui";
import { openWebview } from "zmp-sdk";
import { datGio, taoThanhToan, dinhTien, CongTT } from "../api";
import { layGio, datSoLuong, xoaKhoiGio, xoaGio, tongTien, MonGio } from "../cart";
import { luuVe, luuSdt, laySdt, layTen } from "../orders";
import { layTTZalo } from "../zalo";
import PhuongThuc from "../components/pttt";
import TabBar from "../components/tabbar";

/* Giỏ hàng: chỉnh số lượng từng vé + nhập tên/SĐT + thanh toán 1 lần (1 mã QR tổng). */
export default function GioHangPage() {
  const navigate = useNavigate();
  const snackbar = useSnackbar();
  const [ds, setDs] = useState<MonGio[]>([]);
  const [ten, setTen] = useState(layTen());
  const [sdt, setSdt] = useState(laySdt());
  const [dangGui, setDangGui] = useState(false);
  const [dangLay, setDangLay] = useState(false);
  const [hoiTT, setHoiTT] = useState(false);   // hiện bảng chọn phương thức

  const dienZalo = async () => {
    setDangLay(true);
    try {
      const r = await layTTZalo();
      if (r.ten) setTen(r.ten);
      if (r.sdt) setSdt(r.sdt);
      snackbar.openSnackbar({ text: "Đã lấy thông tin Zalo", type: "success", duration: 1200 });
    } catch (e: any) {
      snackbar.openSnackbar({ text: "Chưa lấy được thông tin. Nhập tay giúp em nhé.", type: "warning" });
    } finally { setDangLay(false); }
  };

  const nap = () => setDs(layGio());
  useEffect(() => {
    nap();
    const h = () => nap();
    window.addEventListener("posh-gio", h);
    return () => window.removeEventListener("posh-gio", h);
  }, []);

  const doi = (ma: number, sl: number) => { datSoLuong(ma, sl); nap(); };
  const xoa = (ma: number) => { xoaKhoiGio(ma); nap(); };

  // Bấm "Thanh toán" -> kiểm tra thông tin rồi hỏi phương thức.
  const moChonTT = () => {
    if (ds.length === 0) return;
    if (!ten.trim() || !sdt.trim()) {
      snackbar.openSnackbar({ text: "Nhập tên và số điện thoại.", type: "warning" });
      return;
    }
    setHoiTT(true);
  };

  // Đã chọn phương thức: tạo vé, rồi QR -> màn vé; Momo/VNPay -> mở app thanh toán.
  const thanhToan = async (cong: CongTT) => {
    setDangGui(true);
    try {
      const items = ds.map((x) => ({ id: x.ma, sl: x.sl }));
      const ve = await datGio(items, ten.trim(), sdt.trim());
      luuVe({ ma_ve: ve.ma_ve, goi_ten: ve.goi_ten, so_tien: ve.so_tien, tao_luc: Date.now() });
      luuSdt(sdt.trim());
      xoaGio(); nap();
      if (cong !== "qr") {
        try {
          const r = await taoThanhToan(ve.ma_ve, cong);
          const url = r.deeplink || r.pay_url;
          if (url) { try { await openWebview({ url }); } catch { window.open(url, "_blank"); } }
        } catch (e: any) {
          snackbar.openSnackbar({ text: String(e.message || e) + " — chuyển sang QR ngân hàng.", type: "warning" });
        }
      }
      setHoiTT(false);
      navigate(`/ticket/${ve.ma_ve}`, { state: { ve, cong } });
    } catch (e: any) {
      snackbar.openSnackbar({ text: String(e.message || e), type: "error" });
    } finally {
      setDangGui(false);
    }
  };

  const tong = tongTien(ds);

  return (
    <Page className="tp">
      <div className="tp-head">Giỏ hàng</div>

      {ds.length === 0 ? (
        <div className="tp-empty">
          <div className="tp-empty-ic">🛒</div>
          <div className="tp-empty-t">Giỏ hàng trống</div>
          <div className="tp-empty-s">Bấm dấu “+” trên vé ở Trang chủ hoặc Danh mục để thêm vào giỏ.</div>
          <button className="tp-btn" onClick={() => navigate("/danhmuc")}>Xem danh mục vé</button>
        </div>
      ) : (
        <>
          <div className="gio-list">
            {ds.map((m) => (
              <div key={m.ma} className="gio-item">
                <div className="gio-img">{m.anh ? <img src={m.anh} alt={m.ten} /> : <span>🎟️</span>}</div>
                <div className="gio-mid">
                  <div className="gio-ten">{m.ten}</div>
                  <div className="gio-gia">{dinhTien(m.tien)}</div>
                  <button className="gio-xoa" onClick={() => xoa(m.ma)}>Xoá</button>
                </div>
                <div className="gio-step">
                  <button onClick={() => doi(m.ma, m.sl - 1)}>−</button>
                  <b>{m.sl}</b>
                  <button onClick={() => doi(m.ma, m.sl + 1)}>+</button>
                </div>
              </div>
            ))}
          </div>

          <div className="gio-form">
            <button className="gio-zalo" disabled={dangLay} onClick={dienZalo}>
              {dangLay ? "Đang lấy…" : "⚡ Dùng thông tin Zalo (tự điền)"}
            </button>
            <label className="gio-lb">Họ tên</label>
            <input className="gio-in" placeholder="Tên người mua" value={ten} onChange={(e) => setTen(e.target.value)} />
            <label className="gio-lb">Số điện thoại</label>
            <input className="gio-in" inputMode="tel" placeholder="Số Zalo/điện thoại" value={sdt} onChange={(e) => setSdt(e.target.value)} />
          </div>

          <div style={{ height: 150 }} />
          <div className="gio-bar">
            <div>
              <div className="gio-bar-l">Tổng cộng</div>
              <div className="gio-bar-t">{dinhTien(tong)}</div>
            </div>
            <button className="gio-tt" disabled={dangGui} onClick={moChonTT}>
              {dangGui ? "Đang tạo…" : "Thanh toán"}
            </button>
          </div>
        </>
      )}

      <PhuongThuc open={hoiTT} tong={dinhTien(tong)} dang={dangGui} onChon={thanhToan} onClose={() => setHoiTT(false)} />

      <div style={{ height: 76 }} />
      <TabBar active="giohang" />
    </Page>
  );
}
