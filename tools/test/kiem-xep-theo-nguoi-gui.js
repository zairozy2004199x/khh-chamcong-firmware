/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * MÀN DUYỆT: TRONG TUẦN XẾP THEO NGƯỜI GỬI (GỬI SỚM NHẤT TRÊN) · ĐƠN NHÁP NẰM CUỐI, MỜ.
 *
 * Anh Thắng 23/09/2026: *"Sắp xếp theo người gửi, Ai Gửi Sớm nhất nằm trên"*, rồi *"Nếu đơn
 * nháp của tuần đó thì cứ nằm phía dưới dạng link mờ để kế toán biết là cơ sở đó sắp gửi xin
 * tạm ứng mà quên gửi"*.
 *
 * 🔴 CHẠY THẬT `_dvGomTuan` với đơn giả mang `guiLuc` — đọc lại thứ tự `data-ma` trong HTML.
 *    Soi chữ `.sort(` thì sort theo gì cũng xanh.
 *
 * Chạy: node tools/test/kiem-xep-theo-nguoi-gui.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const HTML = fs.readFileSync('wordpress/vhcp-chi-phi/templates/app.html', 'utf8');
const DON = fs.readFileSync('wordpress/vhcp-chi-phi/includes/class-vhcp-don.php', 'utf8');
let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them !== undefined ? (' → ' + JSON.stringify(them)) : '')); } }
function teq(n, mong, thuc) { t(n + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }
const ham = (n) => { const i = HTML.indexOf('  function ' + n + '('); return i < 0 ? '' : HTML.slice(i, HTML.indexOf('\n  }', i) + 4); };
['_tenNguoiGui', '_xepTheoNguoiGui', '_dvHangNhapHtml', '_dvGomTuan', '_dvNhomHdr', '_tachDonVi', '_kyVal'].forEach((n) => t('⚠️ bốc được `' + n + '`', ham(n).length > 30, n));

const NEN = ['_kyVal', '_tachDonVi', '_tenNguoiGui', '_xepTheoNguoiGui', '_dvHangNhapHtml', '_dvNhomHdr', '_dvGomTuan'].map(ham).join('\n');
function ve(list, opts) {
  return new Function('BOOT', 'esc', 'money', 'canDo', 'list', 'opts',
    NEN + '\nreturn _dvGomTuan(list, function(d, gid, gap){ return "<tr class=\\"dvm "+gid+"\\" data-ma=\\""+d.maDon+"\\"></tr>"; }, opts);')(
    { nhieuDonVi: false }, (x) => String(x == null ? '' : x), (x) => String(Number(x) || 0), () => true, list, opts || {});
}
const XEP = new Function(ham('_tenNguoiGui') + ham('_xepTheoNguoiGui') + '\nreturn _xepTheoNguoiGui;')();
const thuTu = (h) => (h.match(/data-ma="([^"]+)"/g) || []).map((x) => x.replace(/.*"([^"]+)"/, '$1'));
const K = 'T9/2026 (21/9-27/9/2026)';
const D = (ma, nguoi, gui, st, extra) => Object.assign({ maDon: ma, ky: K, nguoiLap: nguoi, guiLuc: gui, trangThai: st || 'Chờ cấp tạm ứng', tamUng: 100, coso: 'CS ' + ma }, extra || {});

/* ── 1. 🔴 Xếp theo người gửi, người gửi sớm nhất trên, cụm liền nhau ───────────────────── */
{
  /* Huy gửi 1 (08:50) · Tiên gửi 2 (09:05 ký tên thường, 09:20 ký tên HOA) · Thảo gửi 3
     (09:10, 09:40, 10:30). Danh sách đầu vào cố ý XÁO.
     🔴 Tiên ký hai kiểu HOA/thường là CỐ Ý, và giờ chọn sao cho phân biệt được: coi là MỘT người
        thì cụm Tiên (sớm nhất 09:05) đứng TRƯỚC cụm Thảo (09:10) và N1 dính sát N2; coi là hai
        người thì N1 (09:20) rơi xuống SAU cả cụm Thảo. Phá thử 23/09/2026: bản đầu của ca này
        để N1/N2 đều muộn hơn Thảo nên bỏ hạ chữ thường mà bài vẫn xanh. */
  const list = [
    D('T2', 'Huỳnh Thị Thu Thảo', '2026-09-21 09:40:00'),
    D('N1', 'NGUYỄN THỊ MỸ TIÊN', '2026-09-21 09:20:00'),
    D('H1', 'Nguyễn Gia Huy', '2026-09-21 08:50:00'),
    D('T3', 'Huỳnh Thị Thu Thảo', '2026-09-21 10:30:00'),
    D('N2', 'Nguyễn Thị Mỹ Tiên', '2026-09-21 09:05:00'),
    D('T1', 'Huỳnh Thị Thu Thảo', '2026-09-21 09:10:00'),
  ];
  teq('🔴 cụm theo người, người gửi sớm nhất trên; trong cụm theo mốc gửi',
    ['H1', 'N2', 'N1', 'T1', 'T2', 'T3'], XEP(list).map((d) => d.maDon));
  teq('🔴 qua `_dvGomTuan` ra đúng thứ tự ấy', ['H1', 'N2', 'N1', 'T1', 'T2', 'T3'], thuTu(ve(list)));
  t('🔴 tên viết HOA / thường vẫn là MỘT người (N1 dính sát N2, không rơi xuống sau Thảo)',
    XEP(list).map((d) => d.maDon).join(',').indexOf('N2,N1,T1') >= 0);
  t('   không đụng mảng gốc', list[0].maDon === 'T2');
}
/* ── 2. Thiếu mốc gửi → xuống cuối; cùng mốc → giữ thứ tự cũ ──────────────────────────── */
{
  const list = [D('X', 'Anh', ''), D('B', 'Bình', '2026-09-22 08:00:00'), D('Y', 'Anh', '')];
  teq('🔴 người không có mốc nào xuống cuối', ['B', 'X', 'Y'], XEP(list).map((d) => d.maDon));
  const list2 = [D('A2', 'Anh', '2026-09-22 09:00:00'), D('A1', 'Anh', ''), D('B1', 'Bình', '2026-09-22 09:30:00')];
  teq('   trong cụm: đơn thiếu mốc đứng sau đơn có mốc', ['A2', 'A1', 'B1'], XEP(list2).map((d) => d.maDon));
  const list3 = [D('P', 'Cúc', '2026-09-22 09:00:00'), D('Q', 'An', '2026-09-22 09:00:00')];
  teq('   hai người cùng mốc → theo tên', ['Q', 'P'], XEP(list3).map((d) => d.maDon));
}
/* ── 3. 🔴 Đơn nháp: cuối nhóm tuần, mờ, bấm mở được, không cộng tổng ────────────────────── */
{
  const cho = [D('C1', 'Thảo', '2026-09-21 09:00:00', 'Chờ duyệt tạm ứng')];
  const nhap = [D('NH1', 'Mai Anh', '', 'Nháp', { coso: 'FARM PHAN THIẾT', tamUng: 3746000 }), D('NH2', 'Huy', '', 'Nháp', { coso: 'NHÀ MA BÀ RỊA', tamUng: 0 })];
  const h = ve(cho, { tien: 'dvk', nhap: nhap });
  t('🔴 dòng nháp có mặt', /class="dvNhap dvk0"/.test(h) && /viewDon\('NH1'\)/.test(h) && /viewDon\('NH2'\)/.test(h), h);
  t('🔴 nằm SAU mọi dòng đơn thật', h.indexOf('data-ma="C1"') < h.indexOf("viewDon('NH1')"), '');
  t('🔴 mờ (opacity) và nói rõ là Nháp chưa gửi', /opacity:\.55/.test(h) && /đơn <b>Nháp<\/b>, chưa gửi xin tạm ứng/.test(h));
  t('   nêu cơ sở + người lập', /FARM PHAN THIẾT<\/b> · Mai Anh/.test(h) && /NHÀ MA BÀ RỊA/.test(h));
  t('   có số đang nhập thì nói; 0 thì thôi', /đang nhập 3746000đ/.test(h) && !/đang nhập 0đ/.test(h));
  t('🔴 KHÔNG có ô tích, không nút duyệt trên dòng nháp', !/dvChk/.test(h) && !/doDuyetTU/.test(h));
  t('🔴 tiêu đề tuần: 1 đơn · tạm ứng 100 (nháp KHÔNG cộng vào), và đếm "2 nháp chưa gửi"',
    /· 1 đơn · tạm ứng 100đ/.test(h) && /2 nháp chưa gửi/.test(h), h.slice(0, 700));
  t('   dòng nháp mang class nhóm để gập/xổ cùng tuần', (h.match(/class="dvNhap dvk0"/g) || []).length === 2);
  /* Tuần chỉ có nháp: vẫn mọc nhóm — đó là tuần "quên gửi". */
  const h2 = ve([], { tien: 'dvk', nhap: nhap });
  t('🔴 tuần chỉ có nháp vẫn mọc nhóm tuần', /dvGrpHdr/.test(h2) && /viewDon\('NH1'\)/.test(h2) && /0 đơn/.test(h2), h2.slice(0, 400));
  /* Tuần cũ gập → dòng nháp cũng ẩn theo. */
  const K2 = 'T9/2026 (14/9-20/9/2026)';
  const h3 = ve([D('C1', 'Thảo', '2026-09-21 09:00:00', 'Chờ duyệt tạm ứng')], { tien: 'dvk', nhap: [D('NHc', 'Huy', '', 'Nháp', { ky: K2 })] });
  t('   nháp của tuần CŨ (gập) thì ẩn theo nhóm', /class="dvNhap dvk1" style="opacity:\.55;display:none"/.test(h3), h3);
  /* Không truyền nhap → y như cũ. */
  const h4 = ve(cho, { tien: 'dck' });
  t('   bảng không truyền `nhap` → không dòng nháp, không chữ "nháp"', !/dvNhap/.test(h4) && !/nháp/.test(h4));
}
/* ── 4. renderDuyet: chỉ bảng chờ duyệt nhận nháp, và không lấy đơn luồng trực tiếp ──────── */
{
  const sach = ham('renderDuyet').replace(/\/\*[\s\S]*?\*\//g, ' ').replace(/\/\/[^\n]*/g, ' ');
  t('🔴 `renderDuyet` gom đơn Nháp qua cùng bộ lọc tháng/tuần/cơ sở', /var lNhap=all\.filter\(function\(d\)\{ return quaLoc\(d\) && d\.trangThai==='Nháp'/.test(sach), '');
  t('🔴 và loại đơn luồng TRỰC TIẾP (không qua tạm ứng)', /_luongDon\(d\)!=='tt'/.test(sach));
  t('🔴 truyền vào ĐÚNG bảng chờ duyệt (tiền tố dvk, cùng recon)', /\{recon:true, tien:'dvk', nhap:lNhap\}/.test(sach));
  t('   hai bảng chờ chi / đang mua KHÔNG nhận nháp', !/nhap/.test(ham('_dvVeBang')));
  t('   ô "chưa có đơn" ẩn khi chỉ có nháp', /\(lCho\.length\|\|lNhap\.length\)\?'none':'block'/.test(sach));
}
/* ── 5. Máy chủ gửi mốc xuống ─────────────────────────────────────────────────────────────── */
t('🔴 danh sách đơn mang `guiLuc`', /'guiLuc'\s*=>\s*self::gui_luc_xep\(\s*\$r\s*\)/.test(DON));
t('🔴 bấm Gửi duyệt tạm ứng thì đóng dấu `ngay_gui`', /'ngay_gui'\s*=>\s*VHCP_Util::now_sql\(\)/.test(DON.slice(DON.indexOf('function gui_duyet_tam_ung('), DON.indexOf('function gui_duyet_tam_ung(') + 4000)));

if (TRUOT.length) { console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):'); TRUOT.forEach(function (x) { console.log('  · ' + x); }); process.exit(1); }
console.log('\n✓ SẠCH — ' + DAT + ' phép: trong tuần xếp theo người gửi sớm nhất; đơn nháp nằm cuối, mờ, bấm mở được.');
