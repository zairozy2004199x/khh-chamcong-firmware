/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 📦 NHÂN BẢN CẤU HÌNH — PHÍA MÀN: đọc tệp gói, bày bảng cho tích, hỏi rồi mới ghi đè.
 *
 * Anh Thắng 23/09/2026: *"nhân bản cho chi phí hà nội"*.
 * 🔴 CHẠY THẬT `nbNhanGoi` / `nbNhap` / `nbXuat` với DOM giả — soi chữ thì `if(false&&…)` vẫn xanh.
 *
 * Chạy: node tools/test/kiem-nhan-ban-cau-hinh-man.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const HTML = fs.readFileSync('wordpress/vhcp-chi-phi/templates/app.html', 'utf8');
let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them !== undefined ? (' → ' + JSON.stringify(them)) : '')); } }
function teq(n, mong, thuc) { t(n + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }
const ham = (n) => { const i = HTML.indexOf('  function ' + n + '('); return i < 0 ? '' : HTML.slice(i, HTML.indexOf('\n  }', i) + 4); };
['nbXuat', 'nbDocTep', 'nbNhanGoi', 'nbNhap'].forEach((n) => t('⚠️ bốc được `' + n + '`', ham(n).length > 60, n));

/* ── 0. Card có mặt, nằm trong nhóm Cấu hình, nút đúng ─────────────────────────────────── */
t('🔴 có card `nhanBanCard`', /<div class="card" id="nhanBanCard">/.test(HTML));
t('   card khai trong `CH_NHOM` (không rơi vào "Khác")', /id:\['nhanBanCard'\]/.test(HTML));
t('   nút tải gói gọi `nbXuat`', /onclick="nbXuat\(\)"/.test(HTML));
t('   ô chọn tệp chỉ nhận .json và đọc qua `nbDocTep`', /<input type="file" id="nbFile" accept="\.json,application\/json"[^>]*onchange="nbDocTep\(this\)"/.test(HTML));
t('   lời dẫn nói rõ KHÔNG chở người dùng / PIN / quyền', /không<\/b> chở người dùng, PIN, quyền/.test(HTML));

/* ── bệ đỡ ───────────────────────────────────────────────────────────────────────────── */
function be(opt) {
  opt = opt || {};
  const KHO = {}; const G = { toast: [], goi: null, chon: null, hoi: 0, loadCfg: 0, a: null };
  const chks = [];
  const moi = {
    el: (id) => (KHO[id] = KHO[id] || { innerHTML: '', value: '', files: opt.files || [], click() {} }),
    esc: (x) => String(x == null ? '' : x),
    _tenKhoi: (k) => ({ kvc: 'Khu vui chơi', hn: 'Hà Nội' }[k] || ''),
    toast: (k, m) => G.toast.push(k + ':' + m),
    loading() {}, loadCfg() { G.loadCfg++; },
    confirm: () => { G.hoi++; return opt.dap !== false; },
    document: {
      querySelectorAll: (sel) => (sel === '.nbChk:checked' ? chks.filter((c) => c.checked) : []),
      createElement: () => { const a = { click() { G.a = a; }, parentNode: null }; return a; },
      body: { appendChild(a) { a.parentNode = this; }, removeChild() {} },
    },
    URL: { createObjectURL: () => 'blob:x' }, Blob: function (p) { this.p = p; }, setTimeout: (f) => f(),
    google: { script: { run: {
      withSuccessHandler(f) { this._ok = f; return this; }, withFailureHandler() { return this; },
      xuatGoiCauHinh() { this._ok(opt.xuat || { success: true, khoi: 'kvc', luc: '2026-09-23 10:00:00', bang: { CH_LoaiChiPhi: [[1]], CH_BoPhan: [[1]] } }); },
      nhapGoiCauHinh(goi, chon) { G.goi = goi; G.chon = chon; this._ok(opt.kq || { success: true, bang: { CH_LoaiChiPhi: { truoc: 0, sau: 7 } } }); },
    } } },
  };
  const F = new Function('moi', 'with(moi){ var NB_GOI=null; var NB_MAC_DINH_TAT=["CH_CoSo"];\n'
    + ['nbXuat', 'nbDocTep', 'nbNhanGoi', 'nbNhap'].map(ham).join('\n')
    + '\nreturn { xuat: nbXuat, nhan: nbNhanGoi, nhap: nbNhap, goi: function(){ return NB_GOI; } }; }')(moi);
  /* Ô tích giả: dựng lại từ HTML vừa vẽ, để `document.querySelectorAll` trả đúng. */
  const dungChk = () => { chks.length = 0; const h = KHO.nbKq.innerHTML; const re = /<input type="checkbox" class="nbChk" value="([^"]+)"( checked)?>/g; let m; while ((m = re.exec(h))) chks.push({ value: m[1], checked: !!m[2] }); };
  return { F, KHO, G, chks, dungChk };
}
const GOI = { loai: 'goi-cau-hinh-van-hanh-chi-phi', khoi: 'kvc', phienBan: '1.299.0', luc: '2026-09-23 10:00:00',
  nhan: { CH_LoaiChiPhi: 'Loại chi phí', CH_BoPhan: 'Bộ phận & luồng duyệt', CH_CoSo: 'Cơ sở & đơn vị' },
  bang: { CH_LoaiChiPhi: [[1], [2], [3]], CH_BoPhan: [[1], [2]], CH_CoSo: [[1]] } };

/* ── 1. Đọc gói → bày bảng, cơ sở mặc định KHÔNG tích ──────────────────────────────────── */
{
  const b = be();
  b.F.nhan(JSON.stringify(GOI), 'cau-hinh-kvc.json');
  const h = b.KHO.nbKq.innerHTML;
  t('🔴 nhận gói: giữ vào NB_GOI', !!b.F.goi() && b.F.goi().khoi === 'kvc');
  t('   nói gói từ bản nào, phiên bản, giờ, tên tệp', /Khu vui chơi/.test(h) && /1\.299\.0/.test(h) && /cau-hinh-kvc\.json/.test(h), h);
  b.dungChk();
  teq('🔴 ba bảng, số dòng đúng', 3, b.chks.length);
  t('   loại chi phí & bộ phận TÍCH sẵn', b.chks.filter((c) => c.value !== 'CH_CoSo').every((c) => c.checked));
  t('🔴 cơ sở KHÔNG tích sẵn (cơ sở là của từng miền)', b.chks.find((c) => c.value === 'CH_CoSo').checked === false);
  t('   nhãn tiếng người + số dòng', /Loại chi phí <span[^>]*>· 3 dòng/.test(h) && /Bộ phận & luồng duyệt <span[^>]*>· 2 dòng/.test(h), h);
  t('   có nút ghi đè gọi `nbNhap`', /onclick="nbNhap\(\)"/.test(h));
}
/* ── 2. Tệp không phải gói → nói thẳng, không giữ ─────────────────────────────────────── */
{
  const b = be();
  b.F.nhan('{"a":1}', 'x.json');
  t('🔴 JSON lạ → báo không phải gói, NB_GOI rỗng', /không phải gói cấu hình/.test(b.KHO.nbKq.innerHTML) && b.F.goi() === null);
  b.F.nhan('không phải json', 'x.txt');
  t('   chữ hỏng → cũng báo, không nổ', /không phải gói cấu hình/.test(b.KHO.nbKq.innerHTML) && b.F.goi() === null);
}
/* ── 3. Ghi đè: hỏi trước, gửi đúng bảng tích, báo trước→sau, nạp lại cấu hình ───────── */
{
  const b = be();
  b.F.nhan(JSON.stringify(GOI), 'g.json'); b.dungChk();
  b.F.nhap();
  teq('🔴 hỏi confirm đúng một lần', 1, b.G.hoi);
  teq('🔴 gửi ĐÚNG hai bảng đang tích (không có cơ sở)', ['CH_LoaiChiPhi', 'CH_BoPhan'], b.G.chon);
  t('   gửi nguyên gói', b.G.goi && b.G.goi.loai === 'goi-cau-hinh-van-hanh-chi-phi');
  t('   báo kết quả trước → sau', /Loại chi phí \(0 → 7 dòng\)/.test(b.KHO.nbKq.innerHTML), b.KHO.nbKq.innerHTML);
  teq('🔴 nạp lại Cấu hình sau khi ghi', 1, b.G.loadCfg);
  t('   xong thì bỏ gói đang giữ', b.F.goi() === null);
  t('   toast ok', b.G.toast.some((x) => x.indexOf('ok:Đã nhập 1 bảng') === 0), b.G.toast);
}
{
  const b = be({ dap: false });
  b.F.nhan(JSON.stringify(GOI), 'g.json'); b.dungChk();
  b.F.nhap();
  t('🔴 trả lời KHÔNG → không gọi máy chủ, gói còn giữ', b.G.chon === null && !!b.F.goi() && b.G.loadCfg === 0);
}
{
  const b = be();
  b.F.nhan(JSON.stringify(GOI), 'g.json'); b.dungChk(); b.chks.forEach((c) => { c.checked = false; });
  b.F.nhap();
  t('   không tích gì → cảnh báo, không hỏi confirm, không gọi', b.G.hoi === 0 && b.G.chon === null && b.G.toast.some((x) => x.indexOf('warn:') === 0));
  const b2 = be(); b2.F.nhap();
  t('   chưa có gói → cảnh báo', b2.G.toast.some((x) => x.indexOf('warn:') === 0) && b2.G.chon === null);
}
{
  const b = be({ kq: { success: false, error: 'Chỉ Admin' } });
  b.F.nhan(JSON.stringify(GOI), 'g.json'); b.dungChk();
  b.F.nhap();
  t('🔴 máy chủ chối → báo lỗi, KHÔNG nạp lại, gói còn giữ để thử lại', b.G.toast.some((x) => x === 'err:Chỉ Admin') && b.G.loadCfg === 0 && !!b.F.goi());
}
/* ── 4. Tải gói: tên tệp theo khối + ngày, có bấm tải ─────────────────────────────────── */
{
  const b = be();
  b.F.xuat();
  t('🔴 tạo link tải và bấm', !!b.G.a && b.G.a.href === 'blob:x');
  teq('🔴 tên tệp: cau-hinh-<khối>-<yyyymmdd>.json', 'cau-hinh-kvc-20260923.json', b.G.a && b.G.a.download);
  t('   toast chỉ đường sang bản kia', b.G.toast.some((x) => /ok:Đã tải gói 2 bảng/.test(x) && /Nhập gói/.test(x)), b.G.toast);
  const b2 = be({ xuat: { success: false, error: 'lỗi' } }); b2.F.xuat();
  t('   máy chủ chối → báo lỗi, không tải', b2.G.toast.some((x) => x === 'err:lỗi') && b2.G.a === null);
}

if (TRUOT.length) { console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):'); TRUOT.forEach(function (x) { console.log('  · ' + x); }); process.exit(1); }
console.log('\n✓ SẠCH — ' + DAT + ' phép: đọc gói, bày bảng (cơ sở không tích sẵn), hỏi rồi ghi đè đúng bảng, tải gói đúng tên.');
