import React, { useEffect, useState } from "react";
import { useNavigate, useSnackbar } from "zmp-ui";
import { Goi, dinhTien } from "../api";
import { themVaoGio } from "../cart";

/* Bottom-sheet chọn vé: ảnh + giá + số lượng + Thêm vào giỏ / Mua ngay. Bám app FunZone. */
export default function ChonVe({ goi, onClose }: { goi: Goi | null; onClose: () => void }) {
  const navigate = useNavigate();
  const snackbar = useSnackbar();
  const [sl, setSl] = useState(1);
  useEffect(() => { if (goi) setSl(1); }, [goi]);
  if (!goi) return null;

  const sale = goi.gia_goc > goi.tien ? Math.round((1 - goi.tien / goi.gia_goc) * 100) : 0;
  const conGioiHan = goi.so_luong >= 0 ? goi.so_luong : Infinity;
  const themGio = () => {
    themVaoGio(goi, sl);
    snackbar.openSnackbar({ text: "Đã thêm vào giỏ 🛒", type: "success", duration: 1500 });
    onClose();
  };
  const muaNgay = () => { themVaoGio(goi, sl); onClose(); navigate("/giohang"); };

  return (
    <div className="cv-mask" onClick={(e) => { if (e.target === e.currentTarget) onClose(); }}>
      <div className="cv-sheet">
        <button className="cv-x" aria-label="Đóng" onClick={onClose}>×</button>
        <div className="cv-top">
          <div className="cv-img">{goi.anh ? <img src={goi.anh} alt={goi.ten} /> : <span>🎟️</span>}</div>
          <div className="cv-info">
            <div className="cv-ten">{goi.ten}</div>
            <div className="cv-gia">
              <span>{dinhTien(goi.tien)}</span>
              {sale > 0 && <s>{dinhTien(goi.gia_goc)}</s>}
            </div>
            {goi.so_luong >= 0 && <div className="cv-con">Còn {goi.so_luong} vé</div>}
          </div>
        </div>
        <div className="cv-slrow">
          <span>Số lượng</span>
          <div className="cv-step">
            <button onClick={() => setSl(Math.max(1, sl - 1))}>−</button>
            <b>{sl}</b>
            <button onClick={() => setSl(Math.min(conGioiHan, sl + 1))} disabled={sl >= conGioiHan}>+</button>
          </div>
        </div>
        <div className="cv-btns">
          <button className="cv-gio" onClick={themGio}>Thêm vào giỏ hàng</button>
          <button className="cv-mua" onClick={muaNgay}>Mua ngay</button>
        </div>
      </div>
    </div>
  );
}
