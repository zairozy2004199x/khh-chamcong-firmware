/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * CẤU HÌNH LOẠI CHI PHÍ: DÒNG MIỀN BẮC / MIỀN NAM KHÔNG BỊ KHOÁ MỜ VỚI NGƯỜI ĐƯỢC KHAI.
 *
 * Anh Thắng 23/09/2026: *"có 1 số chi phí bị ẩn"* — ảnh: mọi loại chi phí khối Miền Nam mờ và
 * khoá, dòng khối Văn phòng thì sửa được, với chính tài khoản thấy cả hệ.
 *
 * 🔴 GỐC: `_khoaDongKhoiLa()` và `addCfgLoai()` hỏi `_khoiDuoc()` — tập khối BÀY NÚT trên thanh
 *    đơn (trục ĐƠN: `KHOI_MO=['kvc','vp']` + khối còn sổ). `mb`/`mn` không bao giờ nằm đó, nên
 *    mọi dòng miền là "khối lạ" với TẤT CẢ mọi người; và `saveCfgTkNoMx()` chép nguyên bản cũ
 *    cho dòng có `data-khoi-la` → sửa gì cũng lặng lẽ mất.
 *
 * 🔴 CHẠY THẬT `_khoaDongKhoiLa` / `addCfgLoai` với DOM giả — soi chữ thì `if(false&&…)` vẫn
 *    xanh. Bệ đỡ gieo `KHOI_MO=['kvc','vp']`, `khoiCoDon=[]` (miền CHƯA CÓ ĐƠN NÀO) — đúng cảnh
 *    thật; nếu phép nào cần miền có đơn mới xanh thì phép ấy đang canh sai thứ.
 *
 * Chạy: node tools/test/kiem-cau-hinh-mien-khong-khoa.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const HTML = fs.readFileSync('wordpress/vhcp-chi-phi/templates/app.html', 'utf8');
let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them !== undefined ? (' → ' + JSON.stringify(them)) : '')); } }
function teq(n, mong, thuc) { t(n + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }
const ham = (n) => { const i = HTML.indexOf('  function ' + n + '('); return i < 0 ? '' : HTML.slice(i, HTML.indexOf('\n  }', i) + 4); };
['_khoiSuaDuoc', '_mienSuaDuoc', '_mienDs', '_khoiMo', '_khoiLuuTru', '_khoiBay', '_khoiDuoc', '_khoaDongKhoiLa', 'addCfgLoai']
  .forEach(function (n) { t('⚠️ bốc được `' + n + '`', ham(n).length > 40, n); });
/* Chốt chữ — hai nơi này KHÔNG được quay lại hỏi trục đơn. GỠ CHÚ THÍCH trước khi dò: chú thích
   trong hai hàm ấy kể lại cái bẫy `_khoiDuoc()` — dò nguyên khối là đỏ vì lời văn. */
const sach = (n) => ham(n).replace(/\/\*[\s\S]*?\*\//g, '').replace(/\/\/[^\n]*/g, '');
t('🔴 `_khoaDongKhoiLa` không hỏi `_khoiDuoc()` nữa', !/_khoiDuoc\(/.test(sach('_khoaDongKhoiLa')) && /_khoiSuaDuoc\(/.test(sach('_khoaDongKhoiLa')));
t('🔴 `addCfgLoai` không hỏi `_khoiDuoc()` nữa', !/_khoiDuoc\(/.test(sach('addCfgLoai')) && /_mienSuaDuoc\(/.test(sach('addCfgLoai')));

/* ── bệ đỡ ────────────────────────────────────────────────────────────────────────────────── */
function dong(khoi, ten) {
  const o = { ten: ten, thuoc: {}, style: {}, title: '', sel: { value: khoi }, fields: [] };
  o.fields = [{ disabled: false, thuoc: {}, setAttribute(k, v) { this.thuoc[k] = v; } }, { disabled: false, thuoc: {}, setAttribute(k, v) { this.thuoc[k] = v; } }];
  o.querySelector = (s) => (s.indexOf('data-khoi-o') >= 0 ? o.sel : null);
  o.querySelectorAll = () => o.fields;
  o.setAttribute = (k, v) => { o.thuoc[k] = v; };
  o.getAttribute = (k) => (k in o.thuoc ? o.thuoc[k] : null);
  return o;
}
function be(khoiXem, opt) {
  opt = opt || {};
  const rows = [dong('mn', 'Chi phí cơ sở'), dong('mb', 'Chi phí tháo dỡ'), dong('vp', 'Chi Phí Lương VP'), dong('kvc', 'Khác KVC'), dong('', 'Chưa khai khối')];
  const body = { rows, _them: '', getElementsByTagName: () => rows, getAttribute: (k) => (k === 'data-dau-muc' ? 'Chi phí cơ sở' : null),
    insertAdjacentHTML(vt, h) { this._them += h; }, querySelectorAll: () => [], parentNode: { tagName: 'DIV', parentNode: { tagName: 'DETAILS', open: false } } };
  const G = { toast: [], row: null };
  const moi = {
    window: null,
    BOOT: { khoiXem: khoiXem, khoiCoDon: opt.khoiCoDon || [] },
    KHOI_DS: [{ ma: 'mb', ten: 'Miền Bắc' }, { ma: 'mn', ten: 'Miền Nam' }, { ma: 'kvc', ten: 'Khu vui chơi' }, { ma: 'mtd', ten: 'Máy tự động' }, { ma: 'vp', ten: 'Văn phòng' }],
    KHOI_MO: ['kvc', 'vp'], MIEN_MA: ['mb', 'mn'], KHOI_DANG: opt.KHOI_DANG || 'kvc', MX_LOCK: false,
    _mxBodies: () => [body], _tenKhoi: (m) => m, toggleMxLock() {}, toast: (k, m) => G.toast.push(k + ':' + m),
    _mxRowHtml: (x) => { G.row = x; return '<tr data-khoi-goc="' + x.khoi + '"></tr>'; },
  };
  moi.window = moi;
  const F = new Function('moi', 'with(moi){' + ['_khoiMo', '_mienDs', '_khoiLuuTru', '_khoiBay', '_khoiDuoc', '_khoiSuaDuoc', '_mienSuaDuoc', '_khoaDongKhoiLa', 'addCfgLoai'].map(ham).join('\n')
    + '\nreturn { khoa: _khoaDongKhoiLa, them: addCfgLoai, sua: _khoiSuaDuoc, mien: _mienSuaDuoc, duoc: _khoiDuoc }; }')(moi);
  return { F, rows, body, G, moi };
}
const khoaTen = (b) => b.rows.filter((r) => r.getAttribute('data-khoi-la')).map((r) => r.ten).sort();

/* ── 1. 🔴 CẢNH THẬT CỦA ANH THẮNG: thấy cả hệ, miền chưa có đơn ─────────────────────────── */
{
  const b = be(null);
  teq('   đối chứng: trục ĐƠN thật sự KHÔNG có miền (đây chính là cái bẫy)', ['kvc', 'vp'], b.F.duoc().map((x) => x.ma));
  b.F.khoa();
  teq('🔴 người thấy cả hệ: KHÔNG dòng nào bị khoá — kể cả Miền Nam chưa có đơn', [], khoaTen(b));
  t('   ô của dòng Miền Nam vẫn bật', b.rows[0].fields.every((f) => f.disabled === false && !f.thuoc['data-khong-mo']));
  teq('   độ mờ không bị đặt', undefined, b.rows[0].style.opacity);
  teq('   khai được cả hai miền', ['mb', 'mn'], b.F.mien().map((x) => x.ma));
}
/* ── 2. Người tích Miền Bắc: khoá Miền Nam, mở Miền Bắc dù chưa có đơn ───────────────────── */
{
  const b = be(['mb']);
  b.F.khoa();
  teq('🔴 tích Miền Bắc: khoá đúng dòng Miền Nam (và dòng khối cũ VP/KVC — cùng bảng, không phải của họ)',
    ['Chi Phí Lương VP', 'Chi phí cơ sở', 'Khác KVC'], khoaTen(b));
  t('   dòng Miền Bắc mở dù `khoiCoDon` RỖNG (miền chưa có đơn nào)', !b.rows[1].getAttribute('data-khoi-la'));
  t('   dòng chưa khai khối không khoá', !b.rows[4].getAttribute('data-khoi-la'));
  t('🔴 ô của dòng bị khoá thật sự tắt + đánh dấu `data-khong-mo`', b.rows[0].fields.every((f) => f.disabled === true && f.thuoc['data-khong-mo'] === '1'));
  teq('   dòng khoá có mờ', '.62', b.rows[0].style.opacity);
  teq('   chỉ khai được Miền Bắc', ['mb'], b.F.mien().map((x) => x.ma));
}
/* ── 3. Người đơn vị cũ (K&H → 'kvc'): hai trục khác nhau thì KHÔNG khoá dòng miền ─────────── */
{
  const b = be(['kvc']);
  b.F.khoa();
  teq('🔴 đơn vị nói tiếng cũ (`kvc`): dòng miền KHÔNG khoá, chỉ khoá khối cũ khác (VP)', ['Chi Phí Lương VP'], khoaTen(b));
  teq('   họ khai được cả hai miền', ['mb', 'mn'], b.F.mien().map((x) => x.ma));
}
/* ── 4. Người tích cả hai miền ─────────────────────────────────────────────────────────────── */
{
  const b = be(['mb', 'mn']);
  b.F.khoa();
  teq('   tích cả hai miền: chỉ khoá khối cũ', ['Chi Phí Lương VP', 'Khác KVC'], khoaTen(b));
}
/* ── 5. Dòng mới của "＋ Thêm loại" mang MIỀN, không mang khối thanh đơn ─────────────────── */
{
  let b = be(null, { KHOI_DANG: 'kvc' });
  b.F.them('Chi phí cơ sở');
  teq('🔴 thấy cả hệ, thanh đơn đứng `kvc` → dòng mới mang miền đầu (`mb`), KHÔNG mang `kvc`', 'mb', b.G.row && b.G.row.khoi);
  b = be(null, { KHOI_DANG: 'mn' });
  b.F.them('Chi phí cơ sở');
  teq('   thanh đơn đứng `mn` → dòng mới `mn`', 'mn', b.G.row && b.G.row.khoi);
  b = be(['mn'], { KHOI_DANG: 'kvc' });
  b.F.them('Chi phí cơ sở');
  teq('🔴 người tích Miền Nam → dòng mới `mn`, không lấy miền đầu bảng', 'mn', b.G.row && b.G.row.khoi);
  b = be(['mn'], { KHOI_DANG: 'mb' });
  b.F.them('Chi phí cơ sở');
  teq('🔴 thanh đơn đứng miền họ KHÔNG khai được → vẫn `mn` (dòng mới không được tự khoá ở lượt vẽ sau)', 'mn', b.G.row && b.G.row.khoi);
  b = be(['khong-co-khoi-nao']);
  b.F.them('Chi phí cơ sở');
  t('🔴 người không thuộc khối nào: chối, không thêm dòng', b.G.row === null && b.G.toast.some((x) => x.indexOf('warn:') === 0), b.G.toast);
}
/* ── 6. Thước `_khoiSuaDuoc` từng ca ──────────────────────────────────────────────────────── */
{
  const s = (xem, k) => be(xem).F.sua(k);
  t('   rỗng → sửa được hết', s(null, 'mn') && s([], 'vp'));
  t('   có trong danh sách → sửa được (không phân biệt hoa thường)', s(['mn'], 'MN'));
  t('   miền khác trong cùng trục → không', !s(['mn'], 'mb'));
  t('   khối cũ không có trong danh sách → không', !s(['mb'], 'vp') && !s(['kvc'], 'vp'));
  t('   danh sách toàn tiếng cũ → dòng miền mở', s(['kvc', 'vp'], 'mn'));
  t('🔴 danh sách toàn mã LẠ (không khối nào của từ điển) → không mở gì', !s(['khong-co-khoi-nao'], 'mn') && !s(['khong-co-khoi-nao'], 'kvc'));
}
if (TRUOT.length) { console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):'); TRUOT.forEach(function (x) { console.log('  · ' + x); }); process.exit(1); }
console.log('\n✓ SẠCH — ' + DAT + ' phép: dòng miền không bị khoá oan; khoá theo đúng trục cấu hình, dòng mới mang miền người khai.');
