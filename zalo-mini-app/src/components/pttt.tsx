import React from "react";
import { CongTT } from "../api";

/* Bottom-sheet chọn phương thức thanh toán. Hiện khi bấm "Thanh toán". */
const CACH: { id: CongTT; ic: string; ten: string; mo: string }[] = [
  { id: "qr", ic: "🏦", ten: "Chuyển khoản QR ngân hàng", mo: "Quét mã bằng app ngân hàng bất kỳ" },
  { id: "momo", ic: "🟣", ten: "Ví Momo", mo: "Mở app Momo để thanh toán" },
  { id: "vnpay", ic: "🔵", ten: "VNPay", mo: "Thẻ/ứng dụng ngân hàng qua VNPay" },
];

export default function PhuongThuc({
  open, tong, dang, onChon, onClose,
}: {
  open: boolean; tong: string; dang: boolean;
  onChon: (c: CongTT) => void; onClose: () => void;
}) {
  if (!open) return null;
  return (
    <div className="cv-mask" onClick={(e) => { if (e.target === e.currentTarget && !dang) onClose(); }}>
      <div className="cv-sheet">
        <button className="cv-x" aria-label="Đóng" onClick={onClose} disabled={dang}>×</button>
        <div className="ptt-head">Chọn phương thức thanh toán</div>
        <div className="ptt-tong">Tổng thanh toán: <b>{tong}</b></div>
        <div className="ptt-list">
          {CACH.map((c) => (
            <button key={c.id} className="ptt-i" disabled={dang} onClick={() => onChon(c.id)}>
              <span className="ptt-ic">{c.ic}</span>
              <span className="ptt-mid">
                <span className="ptt-ten">{c.ten}</span>
                <span className="ptt-mo">{c.mo}</span>
              </span>
              <span className="ptt-mui">›</span>
            </button>
          ))}
        </div>
        {dang && <div className="ptt-load">Đang xử lý…</div>}
      </div>
    </div>
  );
}
