import React, { useState } from "react";
import { Page, Box, Text, Input, Button, useNavigate, useParams, useLocation, useSnackbar } from "zmp-ui";
import { openWebview } from "zmp-sdk";
import { datVe, taoThanhToan, dinhTien, Goi, CongTT } from "../api";
import { luuVe, luuSdt, laySdt, layTen } from "../orders";
import { layTTZalo } from "../zalo";
import PhuongThuc from "../components/pttt";

export default function BuyPage() {
  const navigate = useNavigate();
  const location = useLocation();
  const { id } = useParams<{ id: string }>();
  const veId = Number(id) || 0;
  const goi = (location.state as any)?.goi as Goi | undefined;   // vé đã chọn (để hiện tên + giá)
  const snackbar = useSnackbar();
  const [ten, setTen] = useState(layTen());
  const [sdt, setSdt] = useState(laySdt());
  const [dangGui, setDangGui] = useState(false);
  const [dangLay, setDangLay] = useState(false);
  const [hoiTT, setHoiTT] = useState(false);

  const dienZalo = async () => {
    setDangLay(true);
    try {
      const r = await layTTZalo();
      if (r.ten) setTen(r.ten);
      if (r.sdt) setSdt(r.sdt);
    } catch {
      snackbar.openSnackbar({ text: "Chưa lấy được thông tin Zalo. Nhập tay giúp em nhé.", type: "warning" });
    } finally { setDangLay(false); }
  };

  const moChonTT = () => {
    if (!ten.trim() || !sdt.trim()) {
      snackbar.openSnackbar({ text: "Nhập tên và số điện thoại.", type: "warning" });
      return;
    }
    setHoiTT(true);
  };

  const mua = async (cong: CongTT) => {
    setDangGui(true);
    try {
      const ve = await datVe(veId, ten.trim(), sdt.trim());
      // Lưu vé + SĐT lên máy để tra cứu lại (Đơn hàng) và tra điểm (Cá nhân).
      luuVe({ ma_ve: ve.ma_ve, goi_ten: ve.goi_ten || (goi?.ten || ""), so_tien: ve.so_tien, tao_luc: Date.now() });
      luuSdt(sdt.trim());
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
      // Chuyển sang màn vé, mang theo dữ liệu vé (QR/nội dung/bank) để hiện ngay.
      navigate(`/ticket/${ve.ma_ve}`, { state: { ve, cong } });
    } catch (e: any) {
      snackbar.openSnackbar({ text: String(e.message || e), type: "error" });
    } finally {
      setDangGui(false);
    }
  };

  return (
    <Page className="wrap">
      <Box mb={4}>
        <Text.Title>Mua vé</Text.Title>
        <Text style={{ color: "var(--mut)" }}>
          {goi ? `${goi.ten} · ${dinhTien(goi.tien)}` : "Vé khu vui chơi"}
        </Text>
      </Box>
      <div className="field">
        <button className="gio-zalo" disabled={dangLay} onClick={dienZalo}>
          {dangLay ? "Đang lấy…" : "⚡ Dùng thông tin Zalo (tự điền)"}
        </button>
      </div>
      <div className="field">
        <Input label="Họ tên" placeholder="Tên người mua" value={ten}
          onChange={(e) => setTen(e.target.value)} />
      </div>
      <div className="field">
        <Input label="Số điện thoại" type="number" placeholder="Số Zalo/điện thoại" value={sdt}
          onChange={(e) => setSdt(e.target.value)} />
      </div>
      <Button fullWidth loading={dangGui} onClick={moChonTT}>Thanh toán</Button>
      <p className="note">Bấm để chọn phương thức và tạo vé. Vé được xác nhận sau khi nhận đủ tiền.</p>
      <PhuongThuc open={hoiTT} tong={goi ? dinhTien(goi.tien) : ""} dang={dangGui} onChon={mua} onClose={() => setHoiTT(false)} />
    </Page>
  );
}
