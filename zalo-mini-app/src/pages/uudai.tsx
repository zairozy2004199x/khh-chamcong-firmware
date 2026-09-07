import React, { useEffect, useState } from "react";
import { Page, Spinner, useNavigate } from "zmp-ui";
import { layUudai, Uudai } from "../api";

/* Ưu đãi/voucher hiện cho khách (do admin web tạo). */
export default function UudaiPage() {
  const navigate = useNavigate();
  const [ds, setDs] = useState<Uudai[]>([]);
  const [tai, setTai] = useState(true);

  useEffect(() => {
    layUudai().then((r) => setDs(r.uudai || [])).catch(() => {}).finally(() => setTai(false));
  }, []);

  return (
    <Page className="tp">
      <div className="tp-head">🎁 Ưu đãi</div>
      {tai && <div className="tp-empty"><Spinner /></div>}
      {!tai && ds.length === 0 && (
        <div className="tp-empty">
          <div className="tp-empty-ic">🎁</div>
          <div className="tp-empty-t">Chưa có ưu đãi</div>
          <div className="tp-empty-s">Ưu đãi mới sẽ xuất hiện ở đây.</div>
          <button className="tp-btn" onClick={() => navigate("/")}>Về trang chủ</button>
        </div>
      )}
      <div className="ud-list">
        {ds.map((u) => (
          <div key={u.id} className="ud-item">
            <div className="ud-img">{u.anh ? <img src={u.anh} alt={u.ten} /> : <span>🎁</span>}</div>
            <div className="ud-body">
              <div className="ud-ten">{u.ten}</div>
              {u.mo_ta && <div className="ud-mota">{u.mo_ta}</div>}
              <div className="ud-meta">
                {u.hang && <span className="ud-tag">Hạng {u.hang}</span>}
                {u.han && <span className="ud-han">HSD: {u.han}</span>}
              </div>
            </div>
          </div>
        ))}
      </div>
      <div style={{ height: 20 }} />
    </Page>
  );
}
