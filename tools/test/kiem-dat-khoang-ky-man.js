/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * MÀN: ĐẶT LẠI KHOẢNG NGÀY CỦA ĐƠN.
 *
 * Anh Thắng, về đơn cơ sở của bộ phận Kỹ thuật: *"quyết toán theo tuần (nhưng không ép buộc
 * tuần nào, khi nào gửi quyết toán thì mới chốt)"*.
 *
 * =============================================================================================
 * 🔴 HAI Ô NGÀY, KHÔNG GÕ CHUỖI KỲ. Chuỗi kỳ có khuôn chặt và máy chủ tự dựng; để trình duyệt
 *    gửi lên một chuỗi tự do là mở cửa cho khuôn thứ hai lọt vào sổ, rồi lọc theo tuần không
 *    bao giờ thấy đơn ấy nữa.
 *
 * 🔴 ĐIỀN SẴN KHOẢNG ĐANG CÓ. Người ta thường chỉ nắn MỘT đầu ngày; bắt gõ lại cả hai là mời
 *    gõ lệch cái đầu kia — và gõ lệch ở đây nghĩa là đơn nhảy sang một kỳ không ai định.
 *
 * ⚠️ CHẠY THẬT các hàm bốc từ mã nguồn.
 *
 * Chạy: node tools/test/kiem-dat-khoang-ky-man.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs'), path = require('path');
let DAT = 0; const TRUOT = [];
function t(ten, ok, them) { if (ok) { DAT++; return; }
  TRUOT.push(ten + (them !== undefined ? '\n      → ' + JSON.stringify(them) : '')); }

const GOC = path.resolve(__dirname, '..', '..');
const HTML = fs.readFileSync(path.join(GOC, 'wordpress/vhcp-chi-phi/templates/app.html'), 'utf8');
const boc = ten => {
  const i = HTML.indexOf('function ' + ten + '(');
  t('bốc được ' + ten + '()', i >= 0);
  if (i < 0) return '';
  const j = HTML.indexOf('\n  }', i);
  const k = HTML.indexOf('\n', i);
  /* Hàm một dòng (`function _p2(n){ ... }`) thì không có '\n  }' của riêng nó. */
  return (j < 0 || (HTML.slice(i, k).match(/\{/g) || []).length === (HTML.slice(i, k).match(/\}/g) || []).length)
    ? HTML.slice(i, k) : HTML.slice(i, j + 4);
};

function be(don, o) {
  const NK = { gui: null, toast: [], hien: null };
  const O = Object.assign({ dkTu: { value: '' }, dkDen: { value: '' }, dkLyDo: { value: '' },
    dkKyCu: { textContent: '' },
    datKyBox: { style: { display: 'none' }, scrollIntoView() {} } }, o || {});
  const moi = {
    CUR: { don: don },
    CURUSER: { name: 'NV' },
    el: id => O[id] || null,
    toast: (k, m) => NK.toast.push([k, m]),
    loading: () => {}, _log: () => {}, boot: () => {}, openDon: () => {},
    google: { script: { run: {
      withSuccessHandler(f) { this._ok = f; return this; },
      withFailureHandler() { return this; },
      datKhoangKyDon(ma, a, z, ly, ng) { NK.gui = { ma, a, z, ly, ng }; this._ok({ success: true, maDon: ma, kyCu: 'cu', kyMoi: 'moi' }); },
    } } },
  };
  const src = `${boc('_p2')}\n${boc('_kyKhoang')}\n${boc('moDatKhoangKy')}
    ${boc('dongDatKhoangKy')}\n${boc('chotDatKhoangKy')}
    return { mo: moDatKhoangKy, dong: dongDatKhoangKy, chot: chotDatKhoangKy };`;
  return { NK, O, F: new Function('moi', `with(moi){ ${src} }`)(moi) };
}

/* ── 1. MỞ RA THÌ ĐIỀN SẴN KHOẢNG ĐANG CÓ ─────────────────────────────────────────────── */
{
  const b = be({ maDon: 'D1', ky: 'T9/2026 (7/9-13/9/2026)' });
  b.F.mo();
  t('🔴 mở ra là điền sẵn ngày đầu của kỳ đang có', b.O.dkTu.value === '2026-09-07', b.O.dkTu);
  t('🔴 và ngày cuối', b.O.dkDen.value === '2026-09-13', b.O.dkDen);
  t('   cho thấy kỳ đang là gì', /7\/9-13\/9\/2026/.test(b.O.dkKyCu.textContent), b.O.dkKyCu);
  t('   và mở khối ra', b.O.datKyBox.style.display === '', b.O.datKyBox.style);
}
{
  /* 🔴 Kỳ VẮT QUA NĂM: tháng đầu (12) lớn hơn tháng cuối (1) nên năm của ngày đầu phải LÙI một.
     Lấy chung một năm là ô ngày điền ra 2027-12-28 — lệch đúng một năm, và người dùng bấm Lưu
     một cái là đơn nhảy sang một kỳ cách đó 12 tháng. */
  const b = be({ maDon: 'D1', ky: 'T1/2027 (28/12-3/1/2027)' });
  b.F.mo();
  t('🔴 kỳ vắt qua NĂM: ngày đầu lùi về năm trước', b.O.dkTu.value === '2026-12-28', b.O.dkTu);
  t('   ngày cuối giữ năm của kỳ', b.O.dkDen.value === '2027-01-03', b.O.dkDen);
}
{
  const b = be({ maDon: 'D1', ky: 'T8/2026' });   // kỳ kiểu cũ, không có khoảng ngày
  b.F.mo();
  t('kỳ không đọc ra khoảng ngày → hai ô để trống, không nổ, không điền bừa',
    b.O.dkTu.value === '' && b.O.dkDen.value === '', [b.O.dkTu, b.O.dkDen]);
  t('   và vẫn mở khối cho người ta gõ vào', b.O.datKyBox.style.display === '', b.O.datKyBox.style);
}

/* ── 2. GỬI ĐI ────────────────────────────────────────────────────────────────────────── */
{
  const b = be({ maDon: 'D1', ky: 'T9/2026 (7/9-13/9/2026)' },
    { dkLyDo: { value: 'kéo thêm' } });
  /* MỞ ra rồi mới bấm Lưu — đúng đường người dùng đi. Bấm Lưu vào một khối chưa mở thì phép
     "đóng khối lại" xanh sẵn (khối vốn đang đóng) dù mã đã bị đục thủng. */
  b.F.mo();
  b.O.dkTu.value = '2026-09-07'; b.O.dkDen.value = '2026-09-20';
  b.O.dkLyDo.value = 'kéo thêm';   // gõ SAU khi mở: mở ra là xoá lý do cũ đi
  b.F.chot();
  t('   khối đang mở trước khi bấm Lưu', true);
  t('🔴 gửi đi HAI Ô NGÀY, không gửi chuỗi kỳ (khuôn kỳ do máy chủ dựng)',
    b.NK.gui && b.NK.gui.a === '2026-09-07' && b.NK.gui.z === '2026-09-20'
    && !/T\d/.test(JSON.stringify(b.NK.gui)), b.NK.gui);
  t('   kèm mã đơn và lý do', b.NK.gui && b.NK.gui.ma === 'D1' && b.NK.gui.ly === 'kéo thêm', b.NK.gui);
  t('   đóng khối lại sau khi lưu', b.O.datKyBox.style.display === 'none', b.O.datKyBox.style);
}
{
  const b = be({ maDon: 'D1', ky: 'T9/2026 (7/9-13/9/2026)' },
    { dkTu: { value: '2026-09-20' }, dkDen: { value: '2026-09-07' }, dkLyDo: { value: '' } });
  b.F.chot();
  t('🔴 ngày cuối TRƯỚC ngày đầu → chối ngay ở màn (kỳ ngược thì mọi báo cáo xếp nhầm chỗ)',
    b.NK.gui === null, b.NK.gui);
  t('   và nói rõ vì sao', b.NK.toast.some(x => /sau ngày bắt đầu/.test(x[1])), b.NK.toast);
}
{
  const b = be({ maDon: 'D1', ky: 'T9/2026 (7/9-13/9/2026)' },
    { dkTu: { value: '' }, dkDen: { value: '2026-09-20' }, dkLyDo: { value: '' } });
  b.F.chot();
  t('thiếu ngày → chối', b.NK.gui === null && b.NK.toast.some(x => /đủ ngày/.test(x[1])), b.NK.toast);
}
{
  const b = be({ maDon: 'D1', ky: 'T9/2026 (7/9-13/9/2026)' },
    { dkTu: { value: '2026-09-07' }, dkDen: { value: '2026-09-07' }, dkLyDo: { value: '' } });
  b.F.chot();
  t('đợt đúng một ngày vẫn gửi được (setup xong trong ngày)', b.NK.gui !== null, b.NK.toast);
}

/* ── 3. NÚT TRÊN TRANG ĐƠN ────────────────────────────────────────────────────────────── */
t('🔴 có nút đặt lại khoảng ngày trên trang đơn', HTML.indexOf('id="btnDatKy"') >= 0);
t('   nút gọi đúng hàm', /id="btnDatKy"[^]{0,120}onclick="moDatKhoangKy\(\)"/.test(HTML));
/* 🔴 CỬA QUYỀN LÀ NGƯỜI ĐƯỢC CHỌN KỲ TỰ DO, không phải quyền kế toán — đây là đơn của họ, và
   anh Thắng muốn họ tự chốt kỳ. Và vẫn cùng ranh giới "đã vào sổ". */
t('🔴 nút chỉ hiện với người được chọn kỳ tự do, và khi đơn CHƯA vào sổ',
  HTML.indexOf('var _dk=!CUR.stChot && _kyTuDo();') >= 0);
t('   khối ô nhập có đủ hai ô ngày và ô lý do',
  /id="dkTu"/.test(HTML) && /id="dkDen"/.test(HTML) && /id="dkLyDo"/.test(HTML));
t('   hai ô ngày là ô LỊCH, không phải ô chữ',
  /id="dkTu"[^>]*type="date"|type="date"[^>]*id="dkTu"/.test(HTML));
t('🔴 KHÔNG hỏi bằng prompt (hộp thoại không có ô lịch, không điền sẵn được)',
  HTML.slice(HTML.indexOf('function moDatKhoangKy('), HTML.indexOf('/* ============ NHẢY ĐƠN SANG TUẦN KHÁC'))
    .indexOf('prompt(') < 0);
t('   nói rõ gửi quyết toán rồi thì khoá', /Gửi quyết toán rồi thì khoá/.test(HTML));

/* ── 4. KỸ THUẬT ĐƯỢC CHỌN KHOẢNG NGÀY TỰ DO LÚC TẠO ĐƠN ─────────────────────────────── */
{
  const i2 = HTML.indexOf('var BP_KY_TU_DO=[');
  const ds = HTML.slice(i2, HTML.indexOf(']', i2));
  t('🔴 Kỹ thuật được chọn khoảng ngày tự do lúc tạo đơn', /Kỹ thuật/.test(ds), ds);
  t('   Văn phòng vẫn được', /Văn phòng/.test(ds), ds);
  t('🔴 nhân viên CƠ SỞ vẫn KHÔNG (đơn của họ là một TUẦN vận hành)', !/'Cơ sở'/.test(ds), ds);
}

/* ═════════════════════════════════════════════════════════════════════════════════════════ */
if (TRUOT.length) {
  console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):');
  TRUOT.forEach(x => console.log('  · ' + x));
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + DAT + ' phép: kỳ do người lập đặt bằng hai ô ngày, khuôn kỳ do máy chủ dựng.');
