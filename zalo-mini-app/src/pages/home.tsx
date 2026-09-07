import React, { useEffect, useState } from "react";
import { Page, Box, Text, Spinner, useNavigate } from "zmp-ui";
import { layGoi, dinhTien, Goi } from "../api";

export default function HomePage() {
  const navigate = useNavigate();
  const [goi, setGoi] = useState<Goi[]>([]);
  const [loi, setLoi] = useState("");
  const [dangTai, setDangTai] = useState(true);

  useEffect(() => {
    layGoi()
      .then((r) => setGoi(r.goi || []))
      .catch((e) => setLoi(String(e.message || e)))
      .finally(() => setDangTai(false));
  }, []);

  return (
    <Page className="wrap" style={{ background: "var(--bg)" }}>
      <Box mb={4}>
        <Text.Title size="large" style={{ color: "#fff" }}>Chọn vé</Text.Title>
        <Text style={{ color: "#aed8e8" }}>Mua vé khu vui chơi trước — quét mã thanh toán ngay trên điện thoại.</Text>
      </Box>

      {dangTai && <Box flex justifyContent="center" mt={8}><Spinner /></Box>}
      {loi && <Text style={{ color: "#fca5a5" }}>{loi}</Text>}

      <div className="goi-list">
        {goi.map((g) => (
          <div key={g.ma} className="goi-card" onClick={() => navigate(`/buy/${g.ma}`, { state: { goi: g } })}>
            {g.anh ? <img className="goi-anh" src={g.anh} alt={g.ten} /> : <div className="goi-anh goi-anh-trong">🎟️</div>}
            <span className="ten">{g.ten}</span>
            <span className="tien">{dinhTien(g.tien)}</span>
            {g.thoi_luong ? <span className="phut">{g.thoi_luong}</span> : null}
            {g.mo_ta ? <span className="mota">{g.mo_ta}</span> : null}
          </div>
        ))}
      </div>
    </Page>
  );
}
