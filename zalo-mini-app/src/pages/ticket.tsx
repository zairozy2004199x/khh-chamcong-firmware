import React, { useEffect, useState } from "react";
import { Page, Box, Text, Button, useLocation, useParams, useSnackbar } from "zmp-ui";
import { QRCodeSVG } from "qrcode.react";
import { trangThaiVe, dinhTien, Ve } from "../api";

const NHAN: Record<string, string> = { cho: "Chờ thanh toán", da_tt: "Đã thanh toán", huy: "Đã huỷ" };

export default function TicketPage() {
  const { maVe } = useParams<{ maVe: string }>();
  const location = useLocation();
  const snackbar = useSnackbar();
  const ve = (location.state as any)?.ve as Ve | undefined;   // dữ liệu vé chuyển từ màn Mua
  const [tt, setTt] = useState<string>(ve?.trang_thai || "cho");

  // Hỏi trạng thái mỗi 5s tới khi đã thanh toán/huỷ (để tự cập nhật khi kế toán xác nhận).
  useEffect(() => {
    if (!maVe) return;
    let dừng = false;
    const hoi = async () => {
      try {
        const r = await trangThaiVe(maVe);
        if (!dừng) setTt(r.trang_thai);
        if (r.trang_thai !== "cho") return; // xong thì thôi hỏi
      } catch { /* im lặng, thử lại lần sau */ }
      if (!dừng) setTimeout(hoi, 5000);
    };
    hoi();
    return () => { dừng = true; };
  }, [maVe]);

  const copy = (s: string) => {
    try { navigator.clipboard.writeText(s); snackbar.openSnackbar({ text: "Đã sao chép", type: "success" }); } catch {}
  };

  return (
    <Page className="wrap">
      <Box mb={3} flex justifyContent="center">
        <span className={"badge " + tt}>{NHAN[tt] || tt}</span>
      </Box>

      {!ve && (
        <Text style={{ textAlign: "center", color: "var(--mut)" }}>
          Vé <b>{maVe}</b>. Mở lại từ màn mua để xem mã QR.
        </Text>
      )}

      {ve && (
        <div className="qr-box">
          {tt === "cho" ? (
            <>
              <QRCodeSVG value={ve.qr} size={230} level="M" includeMargin />
              <Text style={{ color: "var(--mut)", fontSize: 12, textAlign: "center" }}>
                Mở app ngân hàng → Quét mã → chuyển khoản (giữ nguyên nội dung).
              </Text>
            </>
          ) : (
            <Text.Title style={{ color: tt === "da_tt" ? "#166534" : "#991b1b" }}>
              {tt === "da_tt" ? "Vé đã thanh toán ✓" : "Vé đã huỷ"}
            </Text.Title>
          )}

          <div className="kv"><span>Mã vé</span><b>{ve.ma_ve}</b></div>
          <div className="kv"><span>Gói</span><b>{ve.goi_ten}</b></div>
          <div className="kv"><span>Số tiền</span><b>{dinhTien(ve.so_tien)}</b></div>
          <div className="kv"><span>Ngân hàng</span><b>{ve.bank?.ten_nh}</b></div>
          <div className="kv" onClick={() => copy(ve.bank?.so_tk)}><span>Số TK (chạm để chép)</span><b>{ve.bank?.so_tk}</b></div>
          <div className="kv"><span>Chủ TK</span><b>{ve.bank?.ten_tk}</b></div>
          <div className="kv" onClick={() => copy(ve.noi_dung)}><span>Nội dung (chạm để chép)</span><b>{ve.noi_dung}</b></div>
        </div>
      )}

      <Box mt={4}>
        <Button fullWidth variant="secondary" onClick={() => history.back()}>Xong</Button>
      </Box>
      <p className="note">Giữ lại mã vé <b>{maVe}</b>. Khi tới cơ sở, đọc mã vé này để nhân viên kích hoạt ghế.</p>
    </Page>
  );
}
