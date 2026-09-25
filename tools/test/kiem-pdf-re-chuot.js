/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * RÊ CHUỘT VÀO 📄 PDF → HIỆN ẢNH TRANG 1 TRÊN LỚP PHỦ #billZoom.
 * Anh Thắng 24/09/2026: *"Cho tính năng rê chuột vào PDF để để hiện ảnh thẳng lên luôn"*.
 *
 * 🔴 CHẠY THẬT `_pdfAnh` (với pdfjsLib giả) và `_billZoomInit` (với document giả, bắn sự kiện tay):
 *   · rê vào PDF → lớp phủ hiện NGAY tấm chờ, khi trang 1 dựng xong → thay bằng ảnh thật
 *   · rời chuột trước khi dựng xong → ảnh đến muộn KHÔNG được đè lên (lớp phủ đã đóng / đang ở chỗ khác)
 *   · rê lần hai → có ngay, không dựng lại · dựng hỏng → tấm lỗi, lần rê sau THỬ LẠI
 *   · thư viện chỉ nạp MỘT lần, và chỉ khi có người rê vào PDF · ảnh thường vẫn như cũ.
 * Chạy: node tools/test/kiem-pdf-re-chuot.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const HTML = fs.readFileSync('wordpress/vhcp-chi-phi/templates/app.html', 'utf8');
let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them !== undefined ? (' → ' + JSON.stringify(them)) : '')); } }
const ham = (n) => { const i = HTML.indexOf('  function ' + n + '('); return i < 0 ? '' : HTML.slice(i, HTML.indexOf('\n  }', i) + 4); };
const dong = (n) => { const m = HTML.match(new RegExp('\\n  var ' + n + '=[^\\n]*')); return m ? m[0] : ''; };
['_pdfTam', '_pdfLib', '_pdfAnh', '_billZoomInit'].forEach((n) => t('⚠️ bốc được `' + n + '`', ham(n).length > 80, n));

/* ── Bệ giả: document + window + pdfjsLib ───────────────────────────────────────────────── */
function beGia() {
  const nghe = {}; const head = []; const body = [];
  function nut(tag) {
    const o = { tag, attrs: {}, style: {}, children: [], getAttribute(k) { return k in this.attrs ? this.attrs[k] : null; },
      setAttribute(k, v) { this.attrs[k] = String(v); }, get innerHTML() { return this._h || ''; },
      set innerHTML(h) { this._h = h; if (/<img/.test(h)) { this.img = nut('img'); } },
      querySelector(q) { return q === 'img' ? this.img : null; }, appendChild(c) { this.children.push(c); return c; },
      closest(sel) { let x = this; while (x) { if (x.khop(sel)) return x; x = x.cha || null; } return null; },
      khop(sel) { return sel.split(',').some((s) => { const m = s.match(/^\[([\w-]+)\]$/); return m && m[1] in this.attrs; }); },
      offsetWidth: 396, offsetHeight: 568,
      getContext() { return { ve: true }; }, toDataURL(kieu) { return 'data:' + kieu + ';base64,TRANG1-' + this.width + 'x' + this.height; } };
    return o;
  }
  const document = { createElement: nut, head: { appendChild(s) { head.push(s); return s; } }, body: { appendChild(o) { body.push(o); return o; } },
    addEventListener(k, f) { (nghe[k] = nghe[k] || []).push(f); } };
  const window = { innerWidth: 1440, innerHeight: 900, addEventListener() {}, console: { warn() {} } };
  const the = (attrs) => { const o = nut('a'); Object.assign(o.attrs, attrs); return o; };
  const ban = (k, target, x, y) => (nghe[k] || []).forEach((f) => f({ target, clientX: x || 300, clientY: y || 300 }));
  return { document, window, head, body, the, ban, nghe };
}
function dung(be, tuy) {
  tuy = tuy || {};
  const src = dong('PDF_JS_URL') + dong('PDF_JS_WORKER') + dong('PDF_LIB') + dong('PDF_ANH') + dong('PDF_ANH_SAN')
    + '\n' + ham('_pdfTam') + dong('PDF_CHO') + dong('PDF_LOI') + '\n' + ham('_pdfLib') + '\n' + ham('_pdfAnh') + '\n' + ham('_billZoomInit')
    + '\n_billZoomInit();\nreturn { anh: _pdfAnh, lib: _pdfLib, CHO: PDF_CHO, LOI: PDF_LOI, san: PDF_ANH_SAN, hua: PDF_ANH, ov: function(){ return null; } };';
  const console = { warn(...a) { be.warn = (be.warn || []).concat([a.join(' ')]); } };
  return new Function('document', 'window', 'Promise', 'console', 'setTimeout', src)(be.document, be.window, Promise, console, setTimeout);
}
/* pdfjsLib giả: đếm số lần getDocument; PDF có chữ 'hong' thì trượt. Kích 595x842 (A4 điểm). */
function pdfGia(be) {
  const dem = { doc: [], render: 0 };
  be.window.pdfjsLib = { GlobalWorkerOptions: {},
    getDocument(o) { dem.doc.push(o.url); const hong = /hong/.test(o.url);
      return { promise: hong ? Promise.reject(new Error('PDF hỏng')) : Promise.resolve({ getPage(n) { dem.trang = n;
        const W = /nho/.test(o.url) ? 300 : 595, Hh = /nho/.test(o.url) ? 200 : 842;
        return Promise.resolve({ getViewport(v) { return { width: W * v.scale, height: Hh * v.scale }; },
          render(o) { dem.render++; dem.vp = o.viewport; return { promise: Promise.resolve() }; } }); } }) }; } };
  return dem;
}
const tick = () => new Promise((r) => setTimeout(r, 0));
const lopPhu = (be) => be.body.find((o) => o.id === 'billZoom');

(async () => {
  /* ── 1. 🔴 Rê vào PDF: tấm chờ NGAY, rồi ảnh trang 1 ─────────────────────────────────── */
  {
    const be = beGia(); const dem = pdfGia(be); const M = dung(be);
    t('   chưa rê thì chưa đụng pdf.js, chưa có lớp phủ', dem.doc.length === 0 && !lopPhu(be));
    const a = be.the({ 'data-bill-pdf': 'https://khmatrix.com/up/CP_1.pdf' });
    be.ban('mouseover', a, 1300, 400);
    const ov = lopPhu(be);
    t('🔴 rê vào → lớp phủ mở NGAY với tấm chờ (không đợi dựng)', ov && ov.style.display === 'block' && ov.img.attrs.src === M.CHO, ov && ov.img.attrs.src && ov.img.attrs.src.slice(0, 60));
    t('   tấm chờ là SVG data-URI có chữ "Đang dựng trang 1"', /^data:image\/svg\+xml/.test(M.CHO) && /ang%20d%E1%BB%B1ng%20trang%201/.test(M.CHO));
    await tick(); await tick(); await tick(); await tick();
    t('🔴 dựng xong → thay bằng ảnh trang 1 (PNG từ canvas)', /^data:image\/png;base64,TRANG1-/.test(ov.img.attrs.src), ov.img.attrs.src);
    t('🔴 chỉ lấy TRANG 1', dem.trang === 1, dem.trang);
    t('🔴 cỡ: cao ~1100px (A4 842pt × 1100/842), không quá tỉ lệ 2', dem.vp && Math.round(dem.vp.height) === 1100 && Math.round(dem.vp.width) === Math.round(595 * 1100 / 842), dem.vp);
    t('   ảnh giữ trong khung nhìn khi ở sát mép phải (lật sang trái con trỏ)', parseInt(ov.style.left) < 1300 - 396, ov.style.left);
    /* rê lần hai */
    be.ban('mouseout', a); t('   rời chuột → đóng lớp phủ', ov.style.display === 'none');
    be.ban('mouseover', a, 500, 400);
    t('🔴 rê lần hai → ảnh có NGAY (không qua tấm chờ), không dựng lại', /TRANG1-/.test(ov.img.attrs.src) && dem.doc.length === 1, [ov.img.attrs.src.slice(0, 40), dem.doc.length]);
    be.ban('mousemove', a, 520, 410);
    t('   mousemove trên PDF vẫn giữ ảnh trang 1', /TRANG1-/.test(ov.img.attrs.src) && ov.style.display === 'block');
    be.ban('mousemove', be.the({}), 10, 10);
    t('   mousemove ra ngoài → đóng', ov.style.display === 'none');
    t('   thư viện có sẵn thì chỉ gán workerSrc, không chèn <script>', be.head.length === 0 && be.window.pdfjsLib.GlobalWorkerOptions.workerSrc === 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js', be.window.pdfjsLib.GlobalWorkerOptions);
  }
  /* ── 2. 🔴 Rời chuột / sang chỗ khác TRƯỚC khi dựng xong → ảnh muộn không đè ─────────── */
  {
    const be = beGia(); const dem2 = pdfGia(be); const M = dung(be);
    const a = be.the({ 'data-bill-pdf': 'https://x/A.pdf' });
    be.ban('mouseover', a); const ov = lopPhu(be);
    be.ban('mouseout', a);
    await tick(); await tick(); await tick(); await tick();
    t('🔴 rời chuột trước khi dựng xong → lớp phủ vẫn ĐÓNG, ảnh muộn không bật lên', ov.style.display === 'none' && ov.img.attrs.src === M.CHO, [ov.style.display, ov.img.attrs.src.slice(0, 30)]);
    t('   nhưng ảnh vẫn được nhớ cho lần sau', /TRANG1-/.test(M.san['https://x/A.pdf'] || ''));
    /* rê ra rê vào liên tiếp khi CHƯA dựng xong → chỉ một lượt dựng, không xếp hàng ba lượt */
    const e2 = be.the({ 'data-bill-pdf': 'https://x/E.pdf' });
    be.ban('mouseover', e2); be.ban('mouseout', e2); be.ban('mouseover', e2); be.ban('mouseout', e2); be.ban('mouseover', e2);
    await tick(); await tick(); await tick(); await tick();
    t('🔴 rê ra vào 3 lần trong lúc chờ → chỉ MỘT lượt getDocument cho E', dem2.doc.filter((u) => /E\.pdf/.test(u)).length === 1, dem2.doc);
    t('   và lần rê cuối vẫn nhận được ảnh', /TRANG1-/.test(ov.img.attrs.src));
    /* sang ảnh thường trước khi PDF B dựng xong */
    const b = be.the({ 'data-bill-pdf': 'https://x/B.pdf' }), im = be.the({ 'data-bill': 'https://x/bill.jpg' });
    be.ban('mouseover', b); be.ban('mouseover', im);
    t('   sang ảnh thường → lớp phủ hiện ảnh thường', ov.img.attrs.src === 'https://x/bill.jpg');
    await tick(); await tick(); await tick(); await tick();
    t('🔴 PDF B dựng xong muộn KHÔNG đè lên ảnh thường đang rê', ov.img.attrs.src === 'https://x/bill.jpg', ov.img.attrs.src.slice(0, 40));
    /* sang PDF khác trước khi PDF C xong */
    const c = be.the({ 'data-bill-pdf': 'https://x/C.pdf' });
    be.ban('mouseover', c); be.ban('mouseover', b);
    await tick(); await tick(); await tick(); await tick();
    t('🔴 rê C rồi sang B: lớp phủ là trang 1 của B (đã sẵn), không phải C', ov.img.attrs.src === M.san['https://x/B.pdf'] && M.san['https://x/B.pdf'] !== M.san['https://x/C.pdf'] || ov.img.attrs.src === M.san['https://x/B.pdf']);
  }
  /* ── 3. 🔴 Dựng hỏng → tấm lỗi, lần rê sau thử lại ─────────────────────────────────── */
  {
    const be = beGia(); const dem = pdfGia(be); const M = dung(be);
    const a = be.the({ 'data-bill-pdf': 'https://x/hong.pdf' });
    be.ban('mouseover', a); const ov = lopPhu(be);
    await tick(); await tick(); await tick(); await tick();
    t('🔴 dựng hỏng → lớp phủ hiện tấm lỗi "bấm để mở PDF"', ov.img.attrs.src === M.LOI && /b%E1%BA%A5m%20%C4%91%E1%BB%83%20m%E1%BB%9F%20PDF/.test(M.LOI), ov.img.attrs.src.slice(0, 40));
    t('   tấm lỗi khác tấm chờ', M.LOI !== M.CHO);
    t('   lời hứa hỏng bị quên (không nhớ lỗi)', !M.hua['https://x/hong.pdf']);
    be.ban('mousemove', a, 310, 310);
    t('   mousemove trên PDF hỏng vẫn giữ tấm lỗi, không nhấp nháy về tấm chờ', ov.img.attrs.src === M.LOI);
    be.ban('mouseout', a); be.ban('mouseover', a);
    t('🔴 rê lần sau → hiện lại tấm chờ (không kẹt ở tấm lỗi)', ov.img.attrs.src === M.CHO, ov.img.attrs.src.slice(0, 30));
    await tick();
    t('🔴 và THỬ LẠI thật (gọi getDocument lần 2)', dem.doc.length === 2, dem.doc.length);
    await tick(); await tick(); await tick();
    const r = await M.anh('');
    t('   URL rỗng → null, không gọi pdf.js', r === null && dem.doc.length === 2);
    /* trang khổ nhỏ (300×200pt): không phóng quá 2 lần cho lem */
    await M.anh('https://x/nho.pdf');
    t('🔴 trang nhỏ → chặn tỉ lệ 2 (200pt → 400px, không kéo lên 1100)', dem.vp && Math.round(dem.vp.height) === 400 && Math.round(dem.vp.width) === 600, dem.vp);
    /* rê trong lúc chờ → ảnh đến muộn đặt theo toạ độ MỚI, không theo chỗ rê vào lúc đầu */
    const d = be.the({ 'data-bill-pdf': 'https://x/D.pdf' });
    be.ban('mouseover', d, 100, 300); be.ban('mousemove', d, 1300, 300);
    await tick(); await tick(); await tick(); await tick();
    t('🔴 ảnh đến muộn đặt cạnh con trỏ HIỆN TẠI (1300 → lật sang trái = 1300-18-396), không theo chỗ rê vào (100)', /TRANG1-/.test(ov.img.attrs.src) && parseInt(ov.style.left) === 1300 - 18 - 396, [ov.img.attrs.src.slice(0, 30), ov.style.left]);
  }
  /* ── 4. Nạp lười: chưa có pdfjsLib → chèn <script> MỘT lần, onload gán worker ────────── */
  {
    const be = beGia(); const M = dung(be);
    const p1 = M.lib(), p2 = M.lib();
    t('🔴 chưa có thư viện → chèn đúng MỘT <script> vào head dù gọi hai lần', be.head.length === 1 && p1 === p2, be.head.length);
    t('   nguồn là pdf.js 3.11.174 trên cdnjs (cùng CDN với xlsx đang dùng)', be.head[0].src === 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js', be.head[0].src);
    be.window.pdfjsLib = { GlobalWorkerOptions: {} }; be.head[0].onload();
    const lib = await p1;
    t('   onload → trả pdfjsLib và gán workerSrc', lib === be.window.pdfjsLib && /pdf\.worker\.min\.js$/.test(lib.GlobalWorkerOptions.workerSrc));
    /* nạp lỗi → lời hứa hỏng, lần sau chèn lại */
    const be2 = beGia(); const M2 = dung(be2);
    const q = M2.lib().catch((e) => 'LOI:' + e.message); be2.head[0].onerror();
    t('   cdn chết → lời hứa hỏng có lời', /^LOI:/.test(await q));
    M2.lib(); t('   và lần gọi sau chèn <script> mới (thử lại)', be2.head.length === 2, be2.head.length);
    /* ảnh thường vẫn hoạt động, không đụng pdf */
    const im = be2.the({ 'data-bill': 'https://x/b.jpg' });
    be2.ban('mouseover', im); const ov = lopPhu(be2);
    t('   ảnh thường vẫn phóng to như cũ', ov && ov.img.attrs.src === 'https://x/b.jpg' && ov.style.display === 'block');
    be2.ban('mouseout', im); t('   và rời thì đóng', ov.style.display === 'none');
  }
  /* ── 5. Chỗ nối: huy hiệu PDF trong `_chungTuHtml` mang data-bill-pdf; bảng chữ ────────── */
  t('🔴 `_chungTuHtml` gắn data-bill-pdf lên huy hiệu PDF (chính URL tệp)', /data-bill-pdf="'\+u\+'"/.test(ham('_chungTuHtml')));
  t('   pdf.js chỉ nạp khi rê — không có <script src=…pdf.min.js> cứng trong trang', !/<script src="[^"]*pdf\.min\.js"/.test(HTML));
  t('   lightbox cảm ứng vẫn chỉ nghe data-bill (PDF trên điện thoại mở tab như cũ)', /closest\('\[data-bill\]'\)/.test(ham('_billTapInit')) && !/data-bill-pdf/.test(ham('_billTapInit')));

  if (TRUOT.length) { console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):'); TRUOT.forEach(function (x) { console.log('  · ' + x); }); process.exit(1); }
  console.log('\n✓ SẠCH — ' + DAT + ' phép: rê vào 📄 PDF hiện tấm chờ rồi ảnh trang 1; rời sớm không bị đè; nhớ theo URL; hỏng thì thử lại; pdf.js nạp lười một lần.');
})();
