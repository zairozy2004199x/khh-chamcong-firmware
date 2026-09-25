/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * FORM NHẬP HẠNG MỤC TRÊN ĐIỆN THOẠI — SỐ CỘT, VÀ Ô NHẬP THẲNG HÀNG.
 *
 * Anh Thắng 22/09/2026 gửi ảnh màn iPhone của hộp "1) Nhập hạng mục xin tạm ứng": *"Hơi lệch ô
 * nhập"*.
 *
 * =============================================================================================
 * 🔴 LỖI LÀ MỘT LUẬT CSS IM LẶNG, KHÔNG PHẢI MỘT CON SỐ GÕ SAI
 * =============================================================================================
 * Hộp nhập mang CẢ HAI lớp: `class="grid grid-nhap"`. Hai lớp cùng một mức riêng (mỗi bên đúng
 * một lớp), nên khi hai luật cùng khớp một bề ngang thì luật **viết sau** thắng.
 *
 *   `.grid`      hạ 6 -> 3 cột ở 1200px, -> 2 ở 900px, -> 1 ở 560px.
 *   `.grid-nhap` (viết SAU) chỉ có một mốc: 1200px -> 3 cột.
 *
 * Nên ở bề ngang 390px của iPhone, cái thắng là `repeat(3,1fr)`: ba cột chen nhau, nhãn dài
 * xuống hai dòng còn nhãn ngắn một dòng, và ô nhập trong cùng một hàng cao thấp so le. Chú
 * thích trong mã nói `.grid-nhap` "nhường lại cho `.grid`" ở màn hẹp — còn trình duyệt thì
 * không nhường gì cả.
 *
 * ⚠️ BÀI NÀY KHÔNG SOI CHỮ. Nó DỰNG LẠI phép chọn của trình duyệt: gom mọi luật đặt
 *    `grid-template-columns` cho hai lớp ấy theo ĐÚNG THỨ TỰ trong tệp, lọc những luật khớp một
 *    bề ngang, rồi lấy luật cuối. Soi chữ thì mốc nào cũng "có mặt" mà vẫn thua ở đúng chỗ.
 *
 * Chạy: node tools/test/kiem-form-nhap-dien-thoai.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs'), path = require('path');
let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them !== undefined ? ('\n      → ' + JSON.stringify(them)) : '')); } }
function teq(n, mong, thuc) { t(n + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }

const GOC = path.resolve(__dirname, '..', '..');
const BAN = ['vhcp-chi-phi', 'vhcp-chi-phi-hn', 'vhcp-chi-phi-mtd', 'vhcp-chi-phi-vp'];

/* Số cột mà một khai báo `grid-template-columns` sinh ra. */
function demCot(v) {
  const m = v.match(/repeat\(\s*(\d+)/);
  if (m) { return Number(m[1]); }
  return v.trim().split(/\s+/).filter(Boolean).length;   // "1fr" -> 1
}

/**
 * Gom mọi luật đặt số cột cho `.grid` / `.grid-nhap`, THEO ĐÚNG THỨ TỰ trong tệp.
 * Mỗi luật: { lop, max (null = không bọc @media), cot, thuTu }.
 */
function docLuat(css) {
  /* 🔴 TƯỚC CHÚ THÍCH TRƯỚC KHI QUÉT. Bộ quét này đếm dấu `{` `}` để biết mình đang ở trong
     `@media` nào; một dấu ngoặc nằm trong LỜI VĂN của chú thích là lệch cả bảng luật, và bài
     kiểm đỏ (hoặc xanh) vì một câu tiếng Việt chứ không vì mã. Lần thứ bảy trong tuần dẫm đúng
     họ bẫy này. */
  css = css.replace(/\/\*[\s\S]*?\*\//g, ' ');
  const ra = [];
  let i = 0, buf = '', mediaMax = null, boQua = 0, sau = 0;
  while (i < css.length) {
    const ch = css[i];
    if ('}' === ch) {
      /* Đóng một khối `@media` — trả điều kiện về không. */
      if (boQua > 0) { boQua--; } else { mediaMax = null; }
      buf = ''; i++; continue;
    }
    if ('{' !== ch) { buf += ch; i++; continue; }

    const pre = buf.trim(); buf = '';
    if ('@' === pre.charAt(0)) {
      const mm = pre.match(/^@media\s*\(\s*max-width\s*:\s*(\d+)px\s*\)$/);
      /* ⚠️ `@media print` và bạn bè: bỏ qua CẢ KHỐI, đừng coi luật bên trong là luật trần —
         một `grid-template-columns` in giấy mà bị tính vào đây là số cột trên màn sai hẳn. */
      if (mm) { mediaMax = Number(mm[1]); } else { boQua++; }
      i++; continue;
    }
    /* Một luật thường: đọc tới dấu đóng của chính nó (luật CSS không lồng nhau ở tệp này). */
    const k = css.indexOf('}', i);
    const than = css.slice(i + 1, k < 0 ? css.length : k);
    const c = (than.match(/grid-template-columns\s*:\s*([^;}]+)/) || [])[1];
    if (c) {
      pre.split(',').forEach(function (sel) {
        const s2 = sel.trim();
        if ('.grid' === s2 || '.grid-nhap' === s2) {
          ra.push({ lop: s2, max: boQua > 0 ? -1 : mediaMax, cot: demCot(c), thuTu: ++sau });
        }
      });
    }
    i = (k < 0) ? css.length : k + 1;
  }
  /* Luật nằm trong `@media` lạ thì không tính vào phép chọn theo bề ngang. */
  return ra.filter(function (x) { return -1 !== x.max; });
}

/* Trình duyệt chọn ra sao: mọi luật KHỚP bề ngang, cùng mức riêng -> lấy cái ĐỨNG SAU CÙNG. */
function soCot(luat, rong) {
  let ra = null;
  luat.forEach(function (x) {
    if (null !== x.max && rong > x.max) { return; }
    ra = x.cot;
  });
  return ra;
}

BAN.forEach(function (ban) {
  const f = path.join(GOC, 'wordpress', ban, 'assets/css/vhcp.css');
  if (!fs.existsSync(f)) { t('có ' + ban + '/vhcp.css', false); return; }
  const CSS = fs.readFileSync(f, 'utf8');
  const luat = docLuat(CSS);
  t(ban + ': đọc được các luật số cột', luat.length >= 6, luat);

  /* ═══ 1. 🔴 SỐ CỘT THẬT SỰ ĂN Ở TỪNG BỀ NGANG ════════════════════════════════════ */
  teq('🔴 ' + ban + ': iPhone dọc (390px) — MỘT cột', 1, soCot(luat, 390));
  teq('🔴 ' + ban + ': iPhone ngang / máy tính bảng dọc (760px) — HAI cột', 2, soCot(luat, 760));
  teq('   máy tính bảng ngang (1100px) — BA cột', 3, soCot(luat, 1100));
  teq('   màn rộng (1600px) — BỐN cột (bản riêng của form nhập)', 4, soCot(luat, 1600));
  /* Đúng mốc, không lệch một điểm ảnh: 560 vẫn một cột, 561 đã hai. */
  teq('   đúng mốc 560px', 1, soCot(luat, 560));
  teq('   và 561px đã sang hai cột', 2, soCot(luat, 561));

  /* ═══ 2. 🔴 PHÉP CANH CHÍNH CÁI BẪY: LUẬT VIẾT SAU THẮNG ════════════════════════
   * Mọi mốc mà `.grid` có, `.grid-nhap` PHẢI có một mốc cùng bề ngang — không thì ở đó luật
   * của `.grid-nhap` (đứng sau) thắng bằng số cột của một màn rộng hơn. Đây đúng là hình dạng
   * của lỗi anh Thắng chụp, và nó không lộ ra ở bất kỳ phép soi chữ nào. */
  const mocGrid = luat.filter(function (x) { return '.grid' === x.lop && null !== x.max; })
    .map(function (x) { return x.max; }).sort(function (a, b) { return a - b; });
  const mocNhap = luat.filter(function (x) { return '.grid-nhap' === x.lop && null !== x.max; })
    .map(function (x) { return x.max; }).sort(function (a, b) { return a - b; });
  t('🔴 ' + ban + ': mọi mốc của `.grid` đều có mốc tương ứng ở `.grid-nhap`',
    mocGrid.every(function (m) { return mocNhap.indexOf(m) >= 0; }), { grid: mocGrid, nhap: mocNhap });
  /* ⚠️ Và `.grid-nhap` phải đứng SAU `.grid` — đó là điều kiện khiến nó thắng. Đảo thứ tự là
     mọi luật riêng của form nhập lặng lẽ thua, form về lại 6 cột giãn hết màn. */
  const cuoiGrid = Math.max.apply(null, luat.filter(function (x) { return '.grid' === x.lop; }).map(function (x) { return x.thuTu; }));
  const dauNhap = Math.min.apply(null, luat.filter(function (x) { return '.grid-nhap' === x.lop; }).map(function (x) { return x.thuTu; }));
  t('⚠️ ' + ban + ': `.grid-nhap` viết SAU `.grid` — nếu không thì nó thua và form về 6 cột',
    dauNhap > cuoiGrid, { cuoiGrid: cuoiGrid, dauNhap: dauNhap });

  /* ═══ 3. HẸP DẦN THÌ ÍT CỘT DẦN, KHÔNG NHẢY NGƯỢC ══════════════════════════════
   * Một mốc gõ nhầm (900 -> 4 cột) không làm phép nào ở mục 1 đỏ nếu nó nằm giữa hai bề ngang
   * đang đo. Quét cả dải thì bắt được. */
  let truoc = 99, nguoc = null;
  for (let w = 1900; w >= 320; w -= 10) {
    const c = soCot(luat, w);
    if (c > truoc) { nguoc = { rong: w, cot: c, truoc: truoc }; break; }
    truoc = c;
  }
  t('🔴 ' + ban + ': màn hẹp dần thì số cột giảm dần, không chỗ nào nhảy ngược', !nguoc, nguoc);

  /* ═══ 4. Ô NHẬP TRONG CÙNG MỘT HÀNG PHẢI THẲNG NHAU ════════════════════════════
   * Nhãn "NGÀY" một dòng, "PHÂN LOẠI (CÁ NHÂN / NCC) *" hai dòng — hai ô nhập cạnh nhau lệch
   * đúng một dòng chữ. Chừa sẵn hai dòng cho MỌI nhãn rồi dán chữ xuống đáy khung ấy. */
  const mL = CSS.match(/\.grid-nhap>\.fld>label\{([^}]*)\}/);
  t('🔴 ' + ban + ': nhãn của form nhập chừa sẵn chỗ đều nhau', !!mL, CSS.indexOf('.grid-nhap>.fld>label'));
  if (mL) {
    t('   đủ hai dòng chữ (min-height >= 24px)',
      Number((mL[1].match(/min-height:(\d+)px/) || [0, 0])[1]) >= 24, mL[1]);
    t('🔴 và chữ DÁN XUỐNG ĐÁY khung ấy — chừa chỗ mà chữ vẫn bám đỉnh thì vẫn lệch y như cũ',
      /align-items:flex-end/.test(mL[1]) && /display:flex/.test(mL[1]), mL[1]);
  }
  /* ⚠️ ĐÈ TRONG `.grid-nhap`, không đè `.fld` dùng chung: lớp ấy dựng cả chục form khác, và
     cộng 11px cho mỗi ô ở mọi nơi là sửa một màn, đổi chín màn. */
  t('⚠️ ' + ban + ': KHÔNG đè lên `.fld label` dùng chung',
    !/[^>]\.fld label\{[^}]*min-height/.test(CSS), (CSS.match(/\.fld label\{[^}]*\}/) || [''])[0]);
});

/* ═════════════════════════════════════════════════════════════════════════════════════════ */
if (TRUOT.length) {
  console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):');
  TRUOT.forEach(function (x) { console.log('  · ' + x); });
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + DAT + ' phép: form nhập xuống một cột trên điện thoại, và ô nhập thẳng hàng.');
