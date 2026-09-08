import React, { useState } from "react";
import { Page, Box, Text, Input, Button, useNavigate, useParams, useLocation, useSnackbar } from "zmp-ui";
import { datVe, dinhTien, Goi } from "../api";
import { luuVe, luuSdt, laySdt, layTen } from "../orders";
import { layTTZalo } from "../zalo";

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

  const mua = async () => {
    if (!ten.trim() || !sdt.trim()) {
      snackbar.openSnackbar({ text: "Nhập tên và số điện thoại.", type: "warning" });
      return;
    }
    setDangGui(true);
    try {
      const ve = await datVe(veId, ten.trim(), sdt.trim());
      // Lưu vé + SĐT lên máy để tra cứu lại (Đơn hàng) và tra điểm (Cá nhân).
      luuVe({ ma_ve: ve.ma_ve, goi_ten: ve.goi_ten || (goi?.ten || ""), so_tien: ve.so_tien, tao_luc: Date.now() });
      luuSdt(sdt.trim());
      // Chuyển sang màn vé, mang theo dữ liệu vé (QR/nội dung/bank); chọn phương thức ở màn vé.
      navigate(`/ticket/${ve.ma_ve}`, { state: { ve } });
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
      <Button fullWidth loading={dangGui} onClick={mua}>Thanh toán</Button>
      <p className="note">Bấm để tạo vé, sau đó chọn phương thức thanh toán (QR ngân hàng / Momo / VNPay).</p>
    </Page>
  );
}
