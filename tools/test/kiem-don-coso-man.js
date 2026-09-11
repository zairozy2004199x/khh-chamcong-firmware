/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * MÀN ĐƠN CHI PHÍ CƠ SỞ: MỘT ĐƠN NHIỀU GIAN.
 *
 * Anh Thắng 11/09/2026: *"tiếp tới chi phí kỹ thuật cơ sở, sẽ giống kiểu chi phí bên POSH,
 * 1 đơn nhiều cơ sở chung 1 đơn"*.
 *
 * =============================================================================================
 * 🔴 SỔ CHUNG XUYÊN SUỐT ≠ ĐƠN CƠ SỞ THEO ĐỢT. Cả hai cùng loại "Chi phí cơ sở". Chốt cũ giấu
 *    nút Đóng / Xoá / Đổi tên theo `isCoSo`, nên giữ nguyên là MỌI đơn mới đều bất động: nhân
 *    viên nhập xong không có cách nào chốt, và không có câu nào nói vì sao.
 *
 * 🔴 BẢNG THEO GIAN LẤY SỐ TỪ MÁY CHỦ. Cộng lại ở màn là khai lần thứ hai luật "hạng mục có
 *    con thì tiền nằm ở con" — lần thứ hai bao giờ cũng lệch, rồi tổng các gian không khớp
 *    tổng đơn mà không ai biết bên nào sai.
 *
 * 🔴 CANH CẶP NHÃN–SỐ, KHÔNG CANH RỜI. Ba gian ba con số cùng có mặt mà gán lộn chỗ thì phép
 *    "có đủ ba số" vẫn xanh, và báo cáo gửi đi sai gian.
 *
 * ⚠️ CHẠY THẬT hàm bốc từ app.html, với DOM giả NGHIÊM (ô không có thật thì nổ, không trả bừa).
 *
 * Chạy: node tools/test/kiem-don-coso-man.js
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
  return i < 0 ? '' : HTML.slice(i, HTML.indexOf('\n  }', i) + 4);
};

/* ── Bệ đỡ: một ô DOM giả duy nhất, và hỏi ô nào KHÔNG có là NỔ ─────────────────────────
   🔴 `el()` trả bừa một vật giả cho mọi tên là đục tên ô trong mã vẫn xanh. */
function chay(r) {
  const box = { style: { display: 'x' }, innerHTML: 'CŨ' };
  const moi = {
    el: id => { if (id !== 'daTheoCoSoBox') throw new Error('hỏi ô lạ: ' + id); return box; },
    money: n => (Number(n) || 0).toLocaleString('vi-VN'),
    esc: s => String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'),
  };
  const src = `${boc('renderDaTheoCoSo')}\n return renderDaTheoCoSo(R);`;
  new Function('moi', 'R', `with(moi){ ${src} }`)(moi, r);
  return box;
}
const nutQT = (r, isAdmin) =>
  new Function('R', 'A', `${boc('daNutQuyTrinh')}\n return daNutQuyTrinh(R, A);`)(r, isAdmin);

/* ── 1. 🔴 BỐN NÚT QUY TRÌNH: SỔ CHUNG BẤT ĐỘNG, ĐƠN THEO ĐỢT THÌ KHÔNG ───────────────── */
const chung = nutQT({ cosoChung: true, isCoSo: true, editable: true, closed: false }, true);
t('🔴 sổ chung: KHÔNG hiện nút Đổi tên', chung.doiTen === false, chung);
t('🔴 sổ chung: KHÔNG hiện nút Đóng',    chung.dong === false, chung);
t('🔴 sổ chung: KHÔNG hiện nút Xoá',     chung.xoa === false, chung);
t('   sổ chung: KHÔNG hiện nút Mở lại',  chung.moLai === false, chung);

const don = nutQT({ cosoChung: false, isCoSo: true, editable: true, closed: false }, false);
t('🔴 đơn cơ sở theo đợt: CÓ nút Đổi tên — chốt cũ theo isCoSo giấu mất', don.doiTen === true, don);
t('🔴 đơn cơ sở theo đợt: CÓ nút Đóng — không có thì nhân viên nhập xong không chốt được', don.dong === true, don);
t('🔴 đơn cơ sở theo đợt: CÓ nút Xoá (người nhập còn sửa được đơn)', don.xoa === true, don);
t('   đơn đang mở thì KHÔNG hiện Mở lại', don.moLai === false, don);
/* 🔴 CẢ VỚI ADMIN. Ca trên có isAdmin=false nên nó xanh ngay cả khi mã quên hỏi "đã đóng
   chưa" — phải có một ca Admin + đơn đang mở thì mới bắt được. */
const moAdmin = nutQT({ cosoChung: false, editable: true, closed: false }, true);
t('🔴 Admin xem đơn ĐANG MỞ vẫn KHÔNG thấy Mở lại', moAdmin.moLai === false, moAdmin);

const dong = nutQT({ cosoChung: false, editable: false, closed: true }, true);
t('   đơn đã đóng: Admin thấy Mở lại', dong.moLai === true, dong);
t('   đơn đã đóng: KHÔNG còn Đóng / Xoá / Đổi tên',
  dong.dong === false && dong.xoa === false && dong.doiTen === false, dong);
const dongNV = nutQT({ cosoChung: false, editable: false, closed: true }, false);
t('   đơn đã đóng: nhân viên KHÔNG thấy Mở lại', dongNV.moLai === false, dongNV);
const moKhongSua = nutQT({ cosoChung: false, editable: false, closed: false }, false);
t('   đơn đang mở mà người này không sửa được: không thấy nút Xoá', moKhongSua.xoa === false, moKhongSua);

/* ── 2. BẢNG THEO GIAN CHỈ HIỆN Ở ĐƠN CƠ SỞ ───────────────────────────────────────────── */
const setup = chay({ isCoSo: false, theoCoSo: [{ coso: 'Gian A', duToan: 1, thucTe: 2, soDong: 1 }] });
t('dự án Setup/Tháo dỡ: bảng theo gian ẨN (gian chính là tên dự án)', setup.style.display === 'none', setup.style.display);
t('   và dọn sạch nội dung cũ, không để lại bảng của đơn vừa xem', setup.innerHTML === '', setup.innerHTML);
const rong = chay({ isCoSo: true, theoCoSo: [] });
t('đơn cơ sở chưa có dòng nào: bảng ẩn', rong.style.display === 'none', rong.style.display);

/* ── 3. 🔴 CANH CẶP NHÃN–SỐ ───────────────────────────────────────────────────────────── */
const H = chay({
  isCoSo: true, cosoChung: false,
  theoCoSo: [
    { coso: 'Gian A', duToan: 5000000, thucTe: 5000000, soDong: 3 },
    { coso: 'Gian B', duToan: 1000000, thucTe: 1200000, soDong: 1 },
    { coso: 'Gian C', duToan: 800000,  thucTe: 800000,  soDong: 1 },
    { coso: '',       duToan: 0,       thucTe: 300000,  soDong: 1 },
  ],
}).innerHTML;
t('bảng hiện ra', H.length > 0);
/* Đọc các ô của HÀNG mang tên gian ấy — không phải "có con số này đâu đó trong bảng". */
function hang(ten) {
  const i = H.indexOf('🏢 ' + ten);
  if (i < 0) return null;
  const het = H.indexOf('</tr>', i);
  return H.slice(i, het < 0 ? H.length : het).match(/>([^<>]*?)<\/td>/g) || null;
}
const A = hang('Gian A'), B = hang('Gian B'), C = hang('Gian C');
t('🔴 HÀNG Gian A mang đúng số của Gian A (3 dòng · 5.000.000 · 5.000.000 · khớp)',
  !!A && A.join('|').includes('3') && A.join('|').includes('5.000.000') && A.join('|').includes('✓ khớp'), A);
t('🔴 HÀNG Gian B mang 1.200.000 và báo VƯỢT 200.000 — không phải số của gian khác',
  !!B && B.join('|').includes('1.200.000') && B.join('|').includes('⚠️ vượt 200.000'), B);
t('   HÀNG Gian C khớp', !!C && C.join('|').includes('800.000') && C.join('|').includes('✓ khớp'), C);
t('🔴 dòng chưa ghi gian KHÔNG biến mất — nó vẫn là tiền thật của đơn',
  /chưa ghi gian/.test(H) && H.includes('300.000'), H.slice(0, 200));
t('   dòng tổng nói rõ đơn rải qua mấy gian', /TỔNG 4 GIAN/.test(H), H.slice(-400));
/* Tổng phải là tổng CỦA CÁC HÀNG, canh bằng con số chứ không bằng chữ "TỔNG". */
t('🔴 dòng tổng = 7.300.000 (5.000.000+1.200.000+800.000+300.000)',
  H.slice(H.indexOf('TỔNG 4 GIAN')).includes('7.300.000'), H.slice(H.indexOf('TỔNG 4 GIAN')));
t('   tổng dự toán = 6.800.000',
  H.slice(H.indexOf('TỔNG 4 GIAN')).includes('6.800.000'), H.slice(H.indexOf('TỔNG 4 GIAN')));

/* ── 4. TÊN GIAN LÀ CHỮ NGƯỜI GÕ -> PHẢI RÀO ────────────────────────────────────────── */
const X = chay({ isCoSo: true, theoCoSo: [{ coso: '<script>x</script>', duToan: 0, thucTe: 1, soDong: 1 }] }).innerHTML;
t('🔴 tên gian có thẻ HTML bị rào, không chạy được', !/<script>/.test(X), X.slice(0, 200));

/* ── 5. MỘT LỐI TẠO ĐƠN CHO CẢ BA LOẠI ───────────────────────────────────────────────────
   Anh Thắng: *"1. Tạo dự án / Tạo đơn. 2. Chọn: Chi Phí Setup / Chi Phí Tháo Dỡ hoặc Chi Phí
   Cơ Sở. 3. Nếu chi phí cơ sở thì chọn Tuần. 4. Nếu Setup / Tháo dỡ thì chọn gian"*. */
function beTao(loai) {
  const NK = { goi: null, toast: [], confirm: [], mo: [] };
  const KHO = {};
  const O = id => ({ _id: id, style: { display: '' }, value: '', textContent: '', innerHTML: '', placeholder: '' });
  const moi = {
    DA_ITEMS: [], DA_TUAN: [], DA_CUR: null, DA_CHO_KEO: false,
    CURUSER: { name: 'KT', role: 'Nhân viên' },
    el: id => (KHO[id] = KHO[id] || O(id)),
    esc: x => String(x == null ? '' : x),
    loading: () => {}, _log: () => {},
    toast: (k, m) => NK.toast.push([k, m]),
    confirm: m => { NK.confirm.push(m); return true; },
    loadDuAn: () => {}, openDuAn: (ma, n) => NK.mo.push([ma, n]),
    google: { script: { run: {
      withSuccessHandler(f) { this._ok = f; return this; },
      withFailureHandler() { return this; },
      /* Ghi ĐỦ năm tham số — thiếu tu/den là đơn lập ra không có tuần, và đó là chính cái
         cần bắt. */
      createDuAn(l, ten, nguoi, tu, den) { NK.goi = { l, ten, nguoi, tu, den };
        this._ok({ success: true, maDA: 'DA9', ten }); },
    } } },
  };
  const src = `${boc('_p2')}\n${boc('_ngayISO')}\n${boc('_mondayOf')}\n${boc('_kyRange')}
    ${boc('_daTenTuan')}\n${boc('_daTuanDs')}\n${boc('daNapTuan')}\n${boc('daOnLoai')}
    ${boc('createDuAnUI')}
    el('daLoai').value = LOAI; daOnLoai(); return { moi: moi, NK: NK, chay: createDuAnUI,
      tuanDs: _daTuanDs, tenTuan: _daTenTuan, mondayOf: _mondayOf };`;
  moi.moi = moi; moi.NK = NK; moi.LOAI = loai;
  return new Function('moi', `with(moi){ ${src} }`)(moi);
}

const CS = beTao('Chi phí cơ sở');
t('🔴 chọn "Chi phí cơ sở": ẩn ô GIAN, hiện ô TUẦN',
  CS.moi.el('daTenBox').style.display === 'none' && CS.moi.el('daTuanBox').style.display === '',
  [CS.moi.el('daTenBox').style.display, CS.moi.el('daTuanBox').style.display]);
t('   ô tuần được nạp sẵn danh sách', /<option value="0"/.test(CS.moi.el('daTuan').innerHTML), CS.moi.el('daTuan').innerHTML.slice(0, 120));

const SU = beTao('Setup lắp đặt');
t('🔴 chọn "Setup": hiện ô GIAN, ẩn ô TUẦN',
  SU.moi.el('daTenBox').style.display === '' && SU.moi.el('daTuanBox').style.display === 'none',
  [SU.moi.el('daTenBox').style.display, SU.moi.el('daTuanBox').style.display]);
t('   nhãn ô đổi theo Setup / Tháo dỡ', SU.moi.el('daTenLbl').textContent.indexOf('Setup') >= 0, SU.moi.el('daTenLbl').textContent);

/* 🔴 TÊN ĐƠN KHÔNG ĐƯỢC CÓ "/" — VHCP_Util::san() thay nó bằng khoảng trắng, tên về tới sổ
   là gãy, và hai tuần khác nhau có thể ra cùng một tên. */
const t1 = CS.tenTuan(new Date(2026, 8, 7), new Date(2026, 8, 13));
const t2 = CS.tenTuan(new Date(2026, 8, 14), new Date(2026, 8, 20));
t('🔴 tên đơn tuần KHÔNG chứa ký tự bị san() băm ( [ ] * ? / \\ : )', !/[\[\]*?/\\:]/.test(t1), t1);
t('   hai tuần khác nhau ra hai tên khác nhau', t1 !== t2, [t1, t2]);
t('   tên mang đủ ngày đầu, ngày cuối và năm', /07\.09/.test(t1) && /13\.09/.test(t1) && /2026/.test(t1), t1);
t('   tên dưới 60 ký tự (san() cắt ở 60)', t1.length <= 60, t1.length);

const ds = CS.tuanDs(8);
t('danh sách có đúng 8 tuần', ds.length === 8, ds.length);
const thuHaiNay = (function () { const m = CS.mondayOf(new Date());
  return m.getFullYear() + '-' + ('0' + (m.getMonth() + 1)).slice(-2) + '-' + ('0' + m.getDate()).slice(-2); })();
t('🔴 mục đầu là TUẦN NÀY (thứ Hai của hôm nay)', ds[0].tu === thuHaiNay, [ds[0].tu, thuHaiNay]);
t('🔴 mỗi mục là một tuần TRÒN: ngày cuối = ngày đầu + 6',
  ds.every(x => (Date.parse(x.den) - Date.parse(x.tu)) / 86400000 === 6), ds.map(x => [x.tu, x.den]));
t('🔴 các tuần lùi dần, không trùng nhau',
  ds.every((x, i) => i === 0 || (Date.parse(ds[i - 1].tu) - Date.parse(x.tu)) / 86400000 === 7), ds.map(x => x.tu));
t('   khoảng ngày là chuỗi ISO đọc được, không rỗng',
  ds.every(x => /^\d{4}-\d{2}-\d{2}$/.test(x.tu) && /^\d{4}-\d{2}-\d{2}$/.test(x.den)), ds[0]);

/* 🔴 LẬP ĐƠN PHẢI GỬI CẢ KHOẢNG NGÀY. Gửi thiếu thì đơn nằm đó không có tuần, màn vẫn báo
   "đã tạo", và không ai biết thiếu cho tới lúc đi tìm đơn của tuần ấy. */
CS.moi.el('daTuan').value = '0';
CS.chay();
const G = CS.NK.goi;
t('lập đơn chi phí cơ sở: có gọi máy chủ', !!G, CS.NK.toast);
t('🔴 gửi đúng loại "Chi phí cơ sở"', !!G && G.l === 'Chi phí cơ sở', G);
t('🔴 gửi KHOẢNG NGÀY của tuần đã chọn, không để rỗng',
  !!G && G.tu === ds[0].tu && G.den === ds[0].den, G);
t('   tên đơn là tên tuần, không phải chữ người gõ', !!G && G.ten === ds[0].ten, G);
t('   tạo xong mở thẳng đơn và kéo tới chỗ nhập', CS.NK.mo.length === 1 && CS.NK.mo[0][1] === true, CS.NK.mo);

/* Chưa chọn tuần thì chối, KHÔNG gửi gì lên máy chủ. */
const CS2 = beTao('Chi phí cơ sở');
CS2.moi.el('daTuan').value = '';
CS2.moi.DA_TUAN = [];
CS2.chay();
t('🔴 chưa chọn tuần: KHÔNG gọi máy chủ', CS2.NK.goi === null, CS2.NK.goi);
t('   và nói cho người dùng biết', CS2.NK.toast.length > 0, CS2.NK.toast);

/* Setup vẫn đi đường cũ: gửi TÊN GIAN, không kèm tuần. */
const SU2 = beTao('Setup lắp đặt');
SU2.moi.el('daTen').value = 'Gian AEON Tân Phú';
SU2.chay();
t('Setup: gửi tên gian đã gõ', !!SU2.NK.goi && SU2.NK.goi.ten === 'Gian AEON Tân Phú', SU2.NK.goi);
t('   Setup KHÔNG kèm khoảng ngày tuần', !!SU2.NK.goi && !SU2.NK.goi.tu && !SU2.NK.goi.den, SU2.NK.goi);
const SU3 = beTao('Setup lắp đặt');
SU3.moi.el('daTen').value = '';
SU3.chay();
t('   Setup bỏ trống tên: không gọi máy chủ', SU3.NK.goi === null, SU3.NK.goi);

/* ═══════════════════════════════════════════════════════════════════════════════════════ */
console.log('');
if (TRUOT.length) {
  console.log('❌ TRƯỢT ' + TRUOT.length + ' / ' + (DAT + TRUOT.length));
  TRUOT.forEach(x => console.log('   • ' + x));
  process.exit(1);
}
console.log('✅ ĐẠT ' + DAT + ' / ' + DAT + ' — màn đơn chi phí cơ sở: một đơn nhiều gian');
process.exit(0);
