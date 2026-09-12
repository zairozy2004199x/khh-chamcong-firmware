/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * BẢNG 🧮 LOẠI CHI PHÍ × MẢNG: XẾP MẢNG THEO CHIỀU ĐỨNG.
 *
 * Anh Thắng 10/09/2026: *"Sắp xếp theo chiều đứng cho dễ nhìn"*.
 *
 * =============================================================================================
 * 🔴 TRƯỚC BẢN NÀY MỖI MẢNG KINH DOANH LÀ MỘT CỘT, mà mảng thì hàng chục — bảng dài ra ngang
 *    cả nghìn điểm ảnh. Ô Bộ phận bị bóp còn "Nhà▾" đọc không ra, và muốn xem mã của mảng thứ
 *    mười lăm thì phải kéo ngang mãi. Nay tách hai bảng: bảng trên là thuộc tính (bốn cột, vừa
 *    một màn), bảng dưới XOAY 90° — mỗi mảng một HÀNG.
 *
 * 🔴 LƯU PHẢI ĐỌC THEO `data-loai`, KHÔNG THEO VỊ TRÍ CỘT. Xoá một loại ở bảng trên là mọi cột
 *    sau nó lùi một chỗ; đọc theo vị trí thì mã của cả bảng gán nhầm sang loại bên cạnh — hỏng
 *    im lặng, vì sổ vẫn có mã, chỉ là mã của loại khác.
 *
 * 🔴 ĐỔI TÊN LOẠI KHÔNG ĐƯỢC LÀM BAY MÃ. Bảng dưới mang tên lúc vẽ; không có mốc `data-goc` ở
 *    bảng trên thì lúc lưu không biết cột nào là loại nào, và mã của cả cột mất sạch — cũng im
 *    lặng, vì lưu xong vẽ lại trông vẫn bình thường, chỉ là mọi ô mã trống trơn.
 *
 * 🔴 MỖI ĐƠN VỊ MỘT BẢNG, VÀ LƯU KHÔNG ĐƯỢC XOÁ MÃ CỦA ĐƠN VỊ KHÁC. Người của POSH chỉ thấy
 *    bảng POSH; ghi đè bằng đúng những gì đọc được trên màn là mã của K&H bay sạch chỉ vì bên
 *    kia bấm Lưu một cái — im lặng, và bên bị mất không hề đụng vào màn này.
 *
 * ⚠️ CHẠY THẬT `renderTkNoMatrix()` rồi `saveCfgTkNoMx()` trên cùng một cây DOM giả.
 *
 * Chạy: node tools/test/kiem-bang-ma-dung.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs'), path = require('path');
let DAT = 0; const TRUOT = [];
function t(ten, ok, them) { if (ok) { DAT++; return; }
  TRUOT.push(ten + (them !== undefined ? '\n      → ' + JSON.stringify(them) : '')); }
function teq(ten, mong, thuc) { t(ten + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }

const GOC = path.resolve(__dirname, '..', '..');
const HTML = fs.readFileSync(path.join(GOC, 'wordpress/vhcp-chi-phi/templates/app.html'), 'utf8');
const boc = ten => {
  const i = HTML.indexOf('function ' + ten + '(');
  t('bốc được ' + ten + '()', i >= 0);
  return i < 0 ? '' : HTML.slice(i, HTML.indexOf('\n  }', i) + 4);
};

/* ── BỆ ĐỠ: cây DOM đủ để vẽ HTML thật rồi đọc lại bằng chính hàm lưu ──────────────────────
   Không dùng thư viện ngoài: dựng một bộ phân tích thẻ đủ nhỏ cho <input>/<select> — thứ duy
   nhất hàm lưu đụng tới. Thay hàm vẽ bằng bản giả là bỏ mất đúng chỗ cần soi (hai bảng có
   khớp nhau không). */
/* Dựng lại từng HÀNG của bảng thuộc tính thành một đối tượng biết `querySelector` đúng mấy
   mốc mà hàm lưu dùng. Không bám chỉ số ô — chính vì bám chỉ số mà bảy ô tích của cột Bộ phận
   làm lệch hết, nên hàm lưu đã bỏ cách ấy và bài kiểm cũng phải bỏ theo. */
function docHangTren(html) {
  const rows = [];
  html.split('<tr').slice(1).forEach(tr => {
    const body = tr.slice(0, tr.indexOf('</tr>') + 1);
    const tags = body.match(/<input[^>]*>|<select[\s\S]*?<\/select>/g) || [];
    if (!tags.length) return;
    const o = { ten: null, loai: null, misa: null, bp: [], dv: [] };
    /* Ô tích ĐƠN VỊ (12/09/2026) nằm trong `<div data-dv-nhieu>`, ô tích Bộ phận trong
       `<div data-bp>`. Cắt theo vùng rồi mới gom, không thì hai bộ lẫn vào nhau và phép
       "đọc nhầm ô Bộ phận thành Đơn vị" không bao giờ đỏ. */
    const vungDv = (body.match(/<div data-dv-nhieu[\s\S]*?<\/div>/) || [''])[0];
    tags.forEach(tag => {
      const at = a => { const x = tag.match(new RegExp(a + '="([^"]*)"')); return x ? x[1] : null; };
      if (tag.slice(0, 6) === '<input') {
        const f = { value: at('value') || '', checked: /\schecked/.test(tag), getAttribute: at };
        if (at('data-goc') !== null) o.ten = f;
        else if (at('data-o') === 'misa') o.misa = f;
        else if (at('type') === 'checkbox') (vungDv.indexOf(tag) >= 0 ? o.dv : o.bp).push(f);
      } else if (at('data-o') === 'loaiTt') {
        const m = tag.match(/<option value="([^"]*)"[^>]*selected/);
        o.loai = { value: m ? m[1] : '' };
      }
    });
    if (!o.ten) return;
    o.querySelector = sel => sel.indexOf('data-goc') >= 0 ? o.ten
      : sel.indexOf('loaiTt') >= 0 ? o.loai
      : sel.indexOf('misa') >= 0 ? o.misa : null;
    /* Bộ chọn phải đúng CẢ HAI vế: khoanh vùng `[data-bp]` và lọc `:checked`. Bỏ vế nào cũng
       là lỗi thật — thiếu vế đầu thì quét nhầm ô khác trong hàng, thiếu vế sau thì loại nào
       cũng thành "mọi bộ phận" — nên bệ đỡ không được cho qua bộ chọn thiếu vế. */
    o.querySelectorAll = sel => {
      if (sel.indexOf(':checked') < 0) return [];
      if (sel.indexOf('[data-bp]') >= 0) return o.bp.filter(c => c.checked);
      if (sel.indexOf('[data-dv-nhieu]') >= 0) return o.dv.filter(c => c.checked);
      return [];
    };
    rows.push(o);
  });
  return rows;
}
/* Mọi ô mã của MỌI bảng đơn vị đang hiện. */
function docOMa(html) {
  const ra = [];
  const lay = (re, tong) => (html.match(re) || []).forEach(tag => {
    const at = a => { const x = tag.match(new RegExp(a + '="([^"]*)"')); return x ? x[1] : null; };
    ra.push({ value: at('value') || '', getAttribute: at, __tong: tong });
  });
  lay(/<input[^>]*data-loai="[^"]*"[^>]*>/g, false);
  /* Ô MÃ TỔNG của mảng (12/09/2026) — cùng nằm trong `.mxNoBody` nhưng là bộ chọn khác. */
  lay(/<input[^>]*data-mang-tong="[^"]*"[^>]*>/g, true);
  return ra;
}
function dungBe(loaiChiPhi, tkNoMatrix, coso, mangTk) {
  const KHO = {};
  const O = id => ({ _id: id, innerHTML: '', style: {}, textContent: '', className: '',
    getElementsByTagName: () => [], querySelectorAll: () => [], querySelector: () => null });
  const NK = { luu: null, toast: [] };
  const moi = {
    /* `mangTk` = bảng "Mảng kinh doanh → nhóm tài khoản", nay chính là chỗ ở của MÃ TỔNG
       (anh Thắng 12/09/2026: *"TUTU MN (6410)"*). Bệ đỡ phải có nó, không thì `_mangTong()`
       nổ ngay dòng đầu và cả bài đỏ vì một lý do chẳng liên quan tới bảng mã. */
    CFG: { loaiChiPhi: loaiChiPhi, tkNoMatrix: tkNoMatrix, coso: coso, mangTk: (mangTk || []) },
    MX_LOCK: true, TKNAME: {}, TKCHART: [],
    /* 🔴 CHẾ ĐỘ SẮP MẢNG (12/09/2026) phải có trong bệ đỡ, kể cả khi bài này không canh nó:
       `renderTkNoMatrix()` đọc `MX_SAP` để dựng dải nút, thiếu là hàm chết ngay dòng ấy và
       MỌI phép bên dưới đỏ vì một lý do chẳng liên quan gì tới bảng mã.
       Để 'mang' — đúng mặc định, và là chế độ mà phần lớn phép của bài này giả định. Luật sắp
       xếp có bài riêng canh: `kiem-sap-mang-theo-tk.js`. */
    MX_SAP: 'mang',
    mxDatSap: () => {},
    el: id => (KHO[id] = KHO[id] || O(id)),
    esc: x => String(x == null ? '' : x).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;'),
    toast: (k, m) => NK.toast.push([k, m]),
    toggleMxLock: () => {},
    _delBtn: () => '<td></td>',
    _bpDs: () => ['Cơ sở', 'Kỹ thuật', 'Setup', 'Marketing'],
    _bpNhan: b => b,
    _saveCfg: (p) => { NK.luu = p; },
    BOOT: { donVi: ['K&H', 'POSH'], xemDonVi: null },
    _dvChuan: v => String(v == null ? '' : v).trim() || 'K&H',
    /* Trả ô CHỈ KHI bộ chọn thật sự trỏ vào bảng mã. Trả bừa là đục hỏng bộ chọn trong mã
       thật mà bài kiểm vẫn xanh — bệ đỡ dễ dãi thì phép nào đi qua nó cũng vô nghĩa. */
    /* Hai bộ chọn khác nhau trên cùng một bảng: `.mxNoBody input` (mọi ô, kể cả ô mã tổng)
       và `.mxNoBody input[data-mang-tong]` (chỉ ô mã tổng). Trả chung một rổ là phép lưu mã
       đọc nhầm ô mã tổng thành mã của một loại chi phí — hỏng im lặng. */
    document: { querySelectorAll: sel => {
      if (!/\.mxNoBody\b/.test(sel)) return [];
      const het = NK.oMa || [];
      return /data-mang-tong/.test(sel) ? het.filter(o => o.__tong) : het.filter(o => !o.__tong);
    } },
    NK, KHO,
  };
  /* `boc('_loaiSel')` kết thúc trên cùng một dòng nên lát cắt nuốt luôn mấy hàm đứng sau, kể
     cả `_bpDs()` thật — hàm ấy đọc `window.CFG`. Cứ để nó chạy bản thật (bốc từ nguồn là bốc
     cả họ hàng, đúng tinh thần), chỉ cần cho nó một `window`. */
  moi.window = moi;
  const F = new Function('moi', `with(moi){
    ${boc('_bpTach')}\n${boc('_bpSelNhieu')}\n${boc('_inp')}\n${boc('_loaiSel')}
    ${boc('_dvSelNhieu')}\n${boc('_loaiChoDv')}\n${boc('_mangTong')}\n${boc('_mangTongDoan')}\n${boc('_mxMaGoc')}\n${boc('_mxSapCols')}\n${boc('_mxCols')}\n${boc('_mxNhomDv')}\n${boc('_xemDuocDv')}\n${boc('_mxRowHtml')}\n${boc('renderTkNoMatrix')}\n${boc('saveCfgTkNoMx')}
    return { ve: renderTkNoMatrix, luu: saveCfgTkNoMx }; }`)(moi);
  return { moi, NK, KHO, F };
}

const LOAI = [
  { ten: 'Chi phí cơ sở',  loaiTt: '',       boPhan: 'Cơ sở' },
  { ten: 'Chi phí setup',  loaiTt: 'ncc',    boPhan: 'Kỹ thuật, Setup' },
  { ten: 'Chi phí lương',  loaiTt: 'canhan', boPhan: '', tenMisa: 'Tên xuất MISA' },
];
const MX = [
  { nhom: 'Chi phí cơ sở', pll: 'Funzone', tkNo: '64126' },
  { nhom: 'Chi phí cơ sở', pll: 'Farm',    tkNo: '64166' },
  { nhom: 'Chi phí setup', pll: 'Funzone', tkNo: '2413' },
  { nhom: 'Chi phí cơ sở', pll: 'Rạp',     tkNo: '64199' },   // mảng của POSH
];
const COSO = [
  { ten: 'AEON',      phanLoaiLon: 'Funzone', donVi: 'K&H' },
  { ten: 'FARM NT',   phanLoaiLon: 'Farm',    donVi: 'K&H' },
  { ten: 'CGV BÌNH DƯƠNG', phanLoaiLon: 'Rạp', donVi: 'POSH' },
];
/* MÃ TỔNG của mảng — bảng "Mảng kinh doanh → nhóm tài khoản". "Rạp" thuộc POSH nên KHÔNG hiện
   trên màn của người K&H; nó có mặt ở đây để canh chuyện lưu không được xoá nó. Hai cột
   `tuKhoa`/`note` cũng vậy: lưu mã tổng mà làm mất chúng là mất dữ liệu người ta đã khai. */
const MANG_TK = [
  { pll: 'Funzone', nhomTk: '6412', tuKhoa: 'Funzone', note: 'ghi chú FZ' },
  { pll: 'Rạp',     nhomTk: '6415', tuKhoa: 'Rạp',     note: 'của POSH' },
];

/* ── 1. HAI BẢNG, VÀ MẢNG XẾP DỌC ──────────────────────────────────────────────────────── */
{
  const b = dungBe(LOAI, MX, COSO, MANG_TK);
  b.F.ve();
  const h = b.moi.el('cfgTkNoMx').innerHTML;
  t('🔴 có bảng thuộc tính (cfgMxBody)', h.indexOf('id="cfgMxBody"') >= 0);
  t('🔴 và bảng mã riêng, MỘT BẢNG MỖI ĐƠN VỊ', h.indexOf('class="mxNoBody" data-dv="K&amp;H"') >= 0);
  t('🔴 ô Bộ phận là HỘP TÍCH, không phải danh sách phải giữ Ctrl',
    h.indexOf('<div data-bp') >= 0 && h.indexOf('type="checkbox" value="Kỹ thuật" checked') >= 0
    && h.indexOf('<select multiple') < 0, h.slice(0, 600));
  t('   nói rõ không tích gì = mọi bộ phận', h.indexOf('không tích = mọi bộ phận') >= 0);
  /* Cột đầu nay bọc tên mảng trong một `<div>` để nhét thêm ô MÃ TỔNG xuống dưới (anh Thắng
     12/09/2026: *"TUTU MN (6410)"*), nên đừng canh `>Funzone</td>` nữa — canh tên có mặt ở
     cột đầu là đủ, còn thẻ bọc là chuyện trình bày. */
  t('🔴 bảng mã: MỖI MẢNG MỘT HÀNG — tên mảng nằm ở cột đầu',
    h.indexOf('>Mảng kinh doanh</th>') >= 0 && />Funzone[ <]/.test(h) && />Farm[ <]/.test(h), h.slice(0, 300));
/* Mảng ĐÃ khai mã tổng thì bày nó trong ngoặc ngay cạnh tên — anh Thắng: *"mở ngoặc ra"*. */
  t('🔴 mảng đã khai mã tổng bày số ấy trong ngoặc cạnh tên', />Funzone <span[^>]*>\(6412\)</.test(h),
    (h.match(/>Funzone[^<]*<[^>]*>[^<]*</) || [''])[0]);
  t('   mảng chưa khai thì chỉ có tên, không ngoặc rỗng', !/>Farm <span[^>]*>\(\)</.test(h));
  /* Ô trống phải GỢI Ý con số đoán được — Farm khai 64166 ở cột đầu nên đoán ra 6416. */
  t('🔴 ô mã tổng còn trống gợi ý sẵn con số đoán được',
    /data-mang-tong="Farm"[^>]*placeholder="mã tổng\? VD 6416"/.test(h)
    || /placeholder="mã tổng\? VD 6416"[^>]*data-mang-tong="Farm"/.test(h),
    (h.match(/<input[^>]*data-mang-tong="Farm"[^>]*>/) || [''])[0]);
  t('🔴 mỗi mảng có ô khai MÃ TỔNG',
    h.indexOf('data-mang-tong="Funzone"') >= 0 && h.indexOf('data-mang-tong="Farm"') >= 0, h.slice(0, 400));
  t('🔴 và loại chi phí thành CỘT của bảng ấy',
    h.indexOf('<th style="width:128px">Chi phí cơ sở</th>') >= 0, h.slice(0, 400));
  t('   bảng thuộc tính KHÔNG còn cột mảng nào (đó là chỗ nó phình ngang)',
    h.slice(0, h.indexOf('mxNoBody')).indexOf('Funzone') < 0);
  t('   bảng mã có tiêu đề nói rõ nó là gì, kèm tên đơn vị', h.indexOf('🔢 TK Nợ · <span style="color:#0f766e">K&amp;H</span>') >= 0, h.slice(h.indexOf('mxNoBody')-400, h.indexOf('mxNoBody')));
  /* Dính CẢ ô tiêu đề LẪN ô tên mảng. Dính mỗi tiêu đề thì kéo ngang vẫn mất tên mảng — đúng
     lúc đang dò mã là lúc cần nó nhất. */
  /* MỌI hàng đều phải dính, không phải "có chỗ nào đó dính". Dính mỗi ô tiêu đề thì kéo
     ngang vẫn mất tên mảng — đúng lúc đang dò mã là lúc cần nó nhất. */
  t('🔴 cột "Mảng kinh doanh" dính lại khi kéo ngang (dò mã mà mất tên mảng thì dò mò)',
    (h.match(/<td style="font-weight:600;position:sticky;left:0/g) || []).length
      === (h.match(/<tr><td style="font-weight:600/g) || []).length
    && (h.match(/<th style="text-align:left;min-width:220px;position:sticky;left:0/g) || []).length
      === (h.match(/class="mxNoBody"/g) || []).length,
    h.match(/position:sticky[^"]*/g));
  t('   cột Bộ phận rộng ra cho vừa hàng ô tích (trước bị bóp còn "Nhà▾")',
    h.indexOf('<th style="width:320px">Bộ phận') >= 0, h.slice(0, 400));
  t('🔴 mỗi ô mã mang data-loai + data-pll (mốc để lưu, thay cho vị trí cột)',
    h.indexOf('data-loai="Chi phí cơ sở" data-pll="Funzone"') >= 0);
  t('🔴 ô tên loại mang data-goc (mốc để nhận ra loại vừa đổi tên)',
    h.indexOf('data-goc="Chi phí cơ sở"') >= 0);
  t('   mã đã lưu hiện đúng ô của nó', h.indexOf('value="64166" placeholder="—" style="width:100%" data-loai="Chi phí cơ sở" data-pll="Farm"') >= 0
    || /value="64166"[^>]*data-loai="Chi phí cơ sở"[^>]*data-pll="Farm"/.test(h), h.match(/<input[^>]*64166[^>]*>/));
}

/* ── 2. VẼ RỒI LƯU LẠI — KHÔNG ĐƯỢC MẤT GÌ ─────────────────────────────────────────────── */
function veRoiLuu(sua, xemDonVi) {
  const b = dungBe(LOAI, MX, COSO, MANG_TK);
  if (xemDonVi !== undefined) b.moi.BOOT.xemDonVi = xemDonVi;
  b.F.ve();
  const h = b.moi.el('cfgTkNoMx').innerHTML;
  const tren = docHangTren(h.slice(h.indexOf('id="cfgMxBody"'), h.indexOf('mxNoBody')));
  const oMa = docOMa(h.slice(h.indexOf('mxNoBody')));
  if (sua) sua(tren, oMa);
  b.moi.el('cfgMxBody').getElementsByTagName = () => tren;
  b.NK.oMa = oMa;
  b.F.luu();
  return b.NK.luu;
}
{
  const r = veRoiLuu(null);
  teq('🔴 lưu lại: danh mục còn đủ ba loại', 3, r.loaiChiPhi.length);
  teq('   thuộc tính giữ nguyên', { ten: 'Chi phí setup', loaiTt: 'ncc', boPhan: 'Kỹ thuật, Setup' },
    { ten: r.loaiChiPhi[1].ten, loaiTt: r.loaiChiPhi[1].loaiTt, boPhan: r.loaiChiPhi[1].boPhan });
  /* Tên MISA là tên đem đi xuất sổ. Lưu một cái mà nó bay thì bản xuất mang tên khác hẳn, và
     không ai soi ra cho tới lúc đối chiếu với kế toán. */
  teq('🔴 Tên theo MISA cũng phải sống sót qua một lượt lưu', 'Tên xuất MISA',
    r.loaiChiPhi[2].tenMisa);
  teq('🔴 và MÃ TÀI KHOẢN còn đủ ba ô, đúng cặp loại × mảng',
    [['Chi phí cơ sở','Farm','64166'],['Chi phí cơ sở','Funzone','64126'],['Chi phí cơ sở','Rạp','64199'],['Chi phí setup','Funzone','2413']],
    r.tkNoMatrix.map(x => [x.nhom, x.pll, x.tkNo]).sort((a,b)=>a[0].localeCompare(b[0])||a[1].localeCompare(b[1])));
  t('   ô trống không sinh dòng rác', r.tkNoMatrix.length === 4, r.tkNoMatrix);
}
/* 🔴 Đổi tên loại: mã của nó phải đi theo tên mới, không được bay. */
{
  const r = veRoiLuu(tren => { tren[0].ten.value = 'Chi phí cơ sở (mới)'; });
  t('🔴 đổi tên loại → mã đi theo tên MỚI, không mất',
    r.tkNoMatrix.filter(x => x.nhom === 'Chi phí cơ sở (mới)').length === 3, r.tkNoMatrix);
  t('   và không còn dòng nào mang tên cũ',
    r.tkNoMatrix.filter(x => x.nhom === 'Chi phí cơ sở').length === 0, r.tkNoMatrix);
}
/* 🔴 Xoá một loại ở bảng trên: mã của nó bỏ theo, và mã loại KHÁC không được gán nhầm. */
{
  const r = veRoiLuu(tren => { tren.splice(0, 1); });
  teq('🔴 xoá loại đầu → danh mục còn hai', 2, r.loaiChiPhi.length);
  teq('🔴 mã của loại bị xoá bỏ theo, mã loại còn lại KHÔNG bị gán nhầm',
    [['Chi phí setup','Funzone','2413']], r.tkNoMatrix.map(x => [x.nhom, x.pll, x.tkNo]));
}
/* Sửa một ô mã. */
{
  const r = veRoiLuu((tren, duoi) => {
    duoi.forEach(x => {
      if (x.getAttribute('data-loai') === 'Chi phí lương'
        && x.getAttribute('data-pll') === 'Farm') x.value = '6421';
    });
  });
  t('🔴 gõ mã mới vào ô đang trống → lưu xuống đúng cặp',
    r.tkNoMatrix.some(x => x.nhom === 'Chi phí lương' && x.pll === 'Farm' && x.tkNo === '6421'), r.tkNoMatrix);
}
/* Dòng tên rỗng ở bảng trên thì bỏ, không đẻ loại vô danh. */
{
  const r = veRoiLuu(tren => { tren[2].ten.value = '   '; });
  teq('tên loại để trống → bỏ dòng ấy', 2, r.loaiChiPhi.length);
}

/* ── 3. 🔴 TÁCH MẢNG THEO ĐƠN VỊ ───────────────────────────────────────────────────────── */
{
  const b = dungBe(LOAI, MX, COSO, MANG_TK);
  b.F.ve();
  const h = b.moi.el('cfgTkNoMx').innerHTML;
  t('🔴 K&H và POSH là HAI bảng riêng', /data-dv="K&amp;H"/.test(h) && /data-dv="POSH"/.test(h), h.match(/data-dv="[^"]*"/g));
  const kh = h.slice(h.indexOf('data-dv="K&amp;H"'), h.indexOf('data-dv="POSH"'));
  t('🔴 mảng của K&H chỉ nằm trong bảng K&H',
    kh.indexOf('data-pll="Funzone"') >= 0 && kh.indexOf('data-pll="Rạp"') < 0, kh.match(/data-pll="[^"]*"/g));
  const posh = h.slice(h.indexOf('data-dv="POSH"'));
  t('🔴 và mảng của POSH chỉ nằm trong bảng POSH',
    posh.indexOf('data-pll="Rạp"') >= 0 && posh.indexOf('data-pll="Funzone"') < 0, posh.match(/data-pll="[^"]*"/g));
  t('   mỗi bảng nói rõ nó có bao nhiêu mảng', /— 2 mảng/.test(h) && /— 1 mảng/.test(h), h.match(/— \d+ mảng/g));
}
/* 🔴 Mảng có cơ sở của CẢ HAI nhà thì đứng ở CẢ HAI khối. Cắt bớt là một bên khai mã mà bên
   kia không thấy, rồi tưởng mình chưa khai. */
{
  const b = dungBe(LOAI, MX, COSO.concat([{ ten: 'FZ POSH', phanLoaiLon: 'Funzone', donVi: 'POSH' }]));
  b.F.ve();
  const h = b.moi.el('cfgTkNoMx').innerHTML;
  const kh = h.slice(h.indexOf('data-dv="K&amp;H"'), h.indexOf('data-dv="POSH"'));
  const posh = h.slice(h.indexOf('data-dv="POSH"'));
  t('🔴 mảng dùng chung hai nhà → hiện ở CẢ HAI bảng',
    kh.indexOf('data-pll="Funzone"') >= 0 && posh.indexOf('data-pll="Funzone"') >= 0,
    { kh: kh.match(/data-pll="[^"]*"/g), posh: posh.match(/data-pll="[^"]*"/g) });
}
/* Người chỉ xem được POSH thì chỉ thấy bảng POSH. */
{
  const b = dungBe(LOAI, MX, COSO);
  b.moi.BOOT.xemDonVi = ['POSH'];
  b.F.ve();
  const h = b.moi.el('cfgTkNoMx').innerHTML;
  t('🔴 chỉ xem được POSH → không thấy bảng K&H', h.indexOf('data-dv="K&amp;H"') < 0, h.match(/data-dv="[^"]*"/g));
  t('   nhưng vẫn thấy bảng của mình', h.indexOf('data-dv="POSH"') >= 0);
}
/* 🔴 CÁI BẪY: người POSH bấm Lưu thì mã của K&H phải CÒN NGUYÊN. */
{
  const r = veRoiLuu(null, ['POSH']);
  t('🔴 người POSH lưu → mã của K&H KHÔNG bay (họ có thấy bảng ấy đâu mà xoá)',
    r.tkNoMatrix.filter(x => x.pll === 'Funzone' || x.pll === 'Farm').length === 3, r.tkNoMatrix);
  t('   và mã của POSH vẫn lưu bình thường',
    r.tkNoMatrix.some(x => x.pll === 'Rạp' && x.tkNo === '64199'), r.tkNoMatrix);
}
/* Nhưng loại chi phí bị XOÁ thì mã của nó đi theo, kể cả ở đơn vị không hiện. */
{
  const r = veRoiLuu(tren => { tren.splice(0, 1); }, ['POSH']);
  t('🔴 xoá loại ở bảng trên → mã của nó bỏ ở MỌI đơn vị, kể cả đơn vị đang ẩn',
    r.tkNoMatrix.every(x => x.nhom !== 'Chi phí cơ sở'), r.tkNoMatrix);
}
/* Đổi tên loại: mã của đơn vị đang ẩn cũng phải đi theo tên mới. */
{
  const r = veRoiLuu(tren => { tren[0].ten.value = 'Chi phí cơ sở (mới)'; }, ['POSH']);
  t('🔴 đổi tên loại → mã của đơn vị đang ẩn cũng theo tên mới, không kẹt lại tên cũ',
    r.tkNoMatrix.filter(x => x.nhom === 'Chi phí cơ sở').length === 0
    && r.tkNoMatrix.filter(x => x.nhom === 'Chi phí cơ sở (mới)').length === 3, r.tkNoMatrix);
}
/* `xemDonVi` = null nghĩa là XEM CẢ, không phải "không xem được gì". */
{
  const b = dungBe(LOAI, MX, COSO);
  b.moi.BOOT.xemDonVi = null;
  b.F.ve();
  const h = b.moi.el('cfgTkNoMx').innerHTML;
  t('🔴 xemDonVi rỗng = XEM CẢ (hiểu ngược là màn trắng trơn cho chính Admin)',
    h.indexOf('data-dv="K&amp;H"') >= 0 && h.indexOf('data-dv="POSH"') >= 0);
}
/* Mảng còn trong bảng mã mà cơ sở đã xoá → vẫn hiện, kèm mã, ở khối "chưa rõ đơn vị". */
{
  const b = dungBe(LOAI, [{ nhom: 'Chi phí cơ sở', pll: 'Mảng cũ', tkNo: '64111' }], COSO);
  b.F.ve();
  const h = b.moi.el('cfgTkNoMx').innerHTML;
  t('🔴 mảng chỉ còn trong bảng mã vẫn hiện (giấu đi là mã còn trong sổ mà không ai sửa được)',
    h.indexOf('(chưa rõ đơn vị)') >= 0 && h.indexOf('value="64111"') >= 0, h.match(/data-dv="[^"]*"/g));
}

/* ── 2a. MỖI NHÀ MỘT BỘ CỘT LOẠI CHI PHÍ ───────────────────────────────────────────────
 * Anh Thắng 12/09/2026: *"Đối với POSH sẽ có cột Chi Phí Khác, Chi Phí Chung, Chi Phí Cơ Sở,
 * Chi Phí Setup"*. Bảng 81 mảng của POSH trước nay bày cả chín cột của KVC, toàn dấu "—".
 *
 * 🔴 CHƯA TÍCH Ô ĐƠN VỊ = MỌI NHÀ, y như luật của cột Bộ phận. Danh mục dựng từ sổ cũ nên gần
 *    như mọi dòng còn bỏ trống ô này — hiểu ngược lại là ngày bản này lên, MỌI bảng mã trắng
 *    trơn và không ai đoán ra vì sao.
 * ─────────────────────────────────────────────────────────────────────────────────────── */
{
  const LOAI2 = [
    { ten: 'Chi phí cơ sở', boPhan: 'Cơ sở', donVi: '' },          // chưa tích -> mọi nhà
    { ten: 'Chi phí setup', boPhan: 'Setup', donVi: 'K&H' },        // riêng K&H
    { ten: 'Chi phí rạp',   boPhan: '',      donVi: 'POSH' },       // riêng POSH
  ];
  const b = dungBe(LOAI2, MX, COSO, MANG_TK);
  b.moi.BOOT.xemDonVi = null;
  b.F.ve();
  const h = b.moi.el('cfgTkNoMx').innerHTML;
  const khoi = t => { const i = h.indexOf('data-dv="' + t + '"'); const j = h.indexOf('</table>', i);
                      return i < 0 ? '' : h.slice(h.lastIndexOf('<thead', i) < i ? h.lastIndexOf('🔢 TK Nợ', i) : i, j); };
  const kKH = khoi('K&amp;H'), kPO = khoi('POSH');
  t('🔴 khối K&H có cột riêng của nó', kKH.indexOf('>Chi phí setup</th>') >= 0, kKH.slice(0, 400));
  t('🔴 khối K&H KHÔNG có cột của POSH', kKH.indexOf('>Chi phí rạp</th>') < 0, kKH.slice(0, 400));
  t('🔴 khối POSH có cột riêng của nó', kPO.indexOf('>Chi phí rạp</th>') >= 0, kPO.slice(0, 400));
  t('🔴 khối POSH KHÔNG có cột của K&H', kPO.indexOf('>Chi phí setup</th>') < 0, kPO.slice(0, 400));
  t('🔴 loại CHƯA tích đơn vị hiện ở CẢ HAI nhà',
    kKH.indexOf('>Chi phí cơ sở</th>') >= 0 && kPO.indexOf('>Chi phí cơ sở</th>') >= 0);
  t('   bảng thuộc tính có ô tích Đơn vị', h.indexOf('data-dv-nhieu') >= 0 && h.indexOf('>Đơn vị <span') >= 0, h.slice(0, 700));
  /* ⚠️ Cắt TRỌN khối ô tích rồi mới soi, đừng lấy một cửa sổ đếm ký tự — mỗi nhãn dài hơn
     trăm ký tự nên cửa sổ 400 chưa tới được nhà thứ hai, và phép đỏ vì chính cái cửa sổ. */
  const oTichDv = (h.match(/<div data-dv-nhieu[\s\S]*?<\/div>/) || [''])[0];
  t('   ô tích bày đúng những nhà đang có',
    oTichDv.indexOf('value="K&amp;H"') >= 0 && oTichDv.indexOf('value="POSH"') >= 0, oTichDv.slice(0, 300));
  t('   và tích sẵn nhà đã khai', /value="K&amp;H" checked/.test(h), (h.match(/<input type="checkbox" value="[^"]*"[^>]*>/g) || []).slice(0, 6));
}
/* 🔴 MÃ CỦA CỘT BỊ ẨN KHÔNG ĐƯỢC MẤT. Một mảng ĐANG HIỆN vẫn có những loại không có ô nào ở
   khối ấy (khác nhà). Xét theo MẢNG như bản cũ là mã của chúng bay sạch ngay lượt Lưu đầu —
   im lặng, và chỉ lộ ra khi ai đó tích lại ô Đơn vị rồi thấy bảng trống trơn. */
{
  const LOAI3 = [
    { ten: 'Chi phí cơ sở', boPhan: 'Cơ sở', donVi: 'K&H' },
    { ten: 'Chi phí setup', boPhan: 'Setup', donVi: 'POSH' },   // không có cột nào ở khối K&H
  ];
  const MX3 = [
    { nhom: 'Chi phí cơ sở', pll: 'Funzone', tkNo: '64126' },
    { nhom: 'Chi phí setup', pll: 'Funzone', tkNo: '2413' },     // mảng Funzone ĐANG hiện
  ];
  const b = dungBe(LOAI3, MX3, COSO, MANG_TK);
  b.moi.BOOT.xemDonVi = ['K&H'];
  b.F.ve();
  const h = b.moi.el('cfgTkNoMx').innerHTML;
  const tren = docHangTren(h.slice(h.indexOf('id="cfgMxBody"'), h.indexOf('mxNoBody')));
  const oMa = docOMa(h.slice(h.indexOf('mxNoBody')));
  b.moi.el('cfgMxBody').getElementsByTagName = () => tren;
  b.NK.oMa = oMa;
  b.F.luu();
  const g = {}; (b.NK.luu.tkNoMatrix || []).forEach(x => { g[x.nhom + '|' + x.pll] = x.tkNo; });
  teq('🔴 mã của cột KHÁC NHÀ (không có ô trên màn) vẫn còn', '2413', g['Chi phí setup|Funzone']);
  teq('   mã của cột đang hiện vẫn đúng', '64126', g['Chi phí cơ sở|Funzone']);
  teq('   và không nhân đôi dòng nào', (b.NK.luu.tkNoMatrix || []).length,
    Object.keys(g).length);
  /* 🔴 Ô TRỐNG PHẢI XOÁ ĐƯỢC, kể cả khi màn chỉ bày cột của một nhà. `hienO` trả lời "ô này
     CÓ trên màn không", không phải "ô này có mã không" — đánh dấu sau khi lọc ô trống thì mã
     người ta vừa xoá bị lôi về, và không ai xoá nổi một mã gõ nhầm. */
  const b2 = dungBe(LOAI3, MX3, COSO, MANG_TK);
  b2.moi.BOOT.xemDonVi = ['K&H'];
  b2.F.ve();
  const h2 = b2.moi.el('cfgTkNoMx').innerHTML;
  const tren2 = docHangTren(h2.slice(h2.indexOf('id="cfgMxBody"'), h2.indexOf('mxNoBody')));
  const oMa2 = docOMa(h2.slice(h2.indexOf('mxNoBody')));
  oMa2.forEach(o => { if (!o.__tong && o.getAttribute('data-loai') === 'Chi phí cơ sở') o.value = ''; });
  b2.moi.el('cfgMxBody').getElementsByTagName = () => tren2;
  b2.NK.oMa = oMa2;
  b2.F.luu();
  const g2 = {}; (b2.NK.luu.tkNoMatrix || []).forEach(x => { g2[x.nhom + '|' + x.pll] = x.tkNo; });
  t('🔴 xoá trắng ô trên màn thì mã ấy mất thật', !g2['Chi phí cơ sở|Funzone'], g2);
  teq('   nhưng mã của cột khác nhà vẫn còn nguyên', '2413', g2['Chi phí setup|Funzone']);
}
/* Cột "Đơn vị" vừa tích phải đi được xuống sổ — tích xong bấm Lưu mà không gửi thì lượt vẽ
   sau ô lại trống, và người khai tưởng mình bấm hụt. */
{
  /* Tích POSH cho "Chi phí setup" rồi Lưu — gói phải mang đúng giá trị ấy. Bản đầu chỉ canh
     "có khoá donVi không", mà khoá ấy luôn có (dù rỗng), nên phép rỗng ruột: phá thử "lưu
     không gửi cột Đơn vị" sống sót. */
  const p = veRoiLuu((tren) => {
    tren.forEach(tr => {
      if (tr.ten && tr.ten.value === 'Chi phí setup') tr.dv.forEach(c => { if (c.value === 'POSH') c.checked = true; });
    });
  });
  const m = {}; (p.loaiChiPhi || []).forEach(x => { m[x.ten] = x; });
  teq('🔴 tích Đơn vị rồi Lưu thì giá trị ấy đi xuống sổ', 'POSH', (m['Chi phí setup'] || {}).donVi);
  teq('   loại không tích gì vẫn rỗng = mọi nhà', '', (m['Chi phí cơ sở'] || {}).donVi);
  /* 🔴 KHÔNG ĐƯỢC ĐỌC NHẦM Ô TÍCH BỘ PHẬN. Hai bộ ô tích nằm cùng một hàng; lấy nhầm là cột
     Đơn vị mang tên bộ phận, và loại ấy biến mất khỏi mọi bảng vì không khớp nhà nào. */
  t('🔴 cột Đơn vị KHÔNG dính tên bộ phận',
    String((m['Chi phí cơ sở'] || {}).donVi || '').indexOf('Cơ sở') < 0
    && String((m['Chi phí setup'] || {}).donVi || '').indexOf('Setup') < 0,
    [(m['Chi phí cơ sở'] || {}).donVi, (m['Chi phí setup'] || {}).donVi]);
  t('   còn cột Bộ phận vẫn đúng của nó',
    String((m['Chi phí setup'] || {}).boPhan || '').indexOf('Setup') >= 0, (m['Chi phí setup'] || {}).boPhan);
}

/* ── 2b. MÃ TỔNG CỦA MẢNG — LƯU LÀ GỘP, KHÔNG PHẢI GHI ĐÈ ──────────────────────────────
 * Anh Thắng 12/09/2026: *"Chỗ mảng mình sẽ mở ngoặc ra. Tức kiểu nó là số tổng"*.
 *
 * 🔴 ĐÂY LÀ CHỖ NGUY NHẤT CỦA CẢ BẢN VÁ. Mã tổng ghi vào cột "Nhóm TK" của bảng `mangTk` —
 *    bảng ấy còn hai cột nữa (Từ khóa · Ghi chú) và còn dòng của những mảng KHÔNG hiện trên
 *    màn này. Người K&H chỉ thấy bảng K&H; gửi đúng những gì đọc được là dòng "Rạp" của POSH
 *    bay sạch chỉ vì bên K&H bấm Lưu một cái — im lặng, và bên bị mất không hề đụng vào màn.
 * ─────────────────────────────────────────────────────────────────────────────────────── */
{
  const p = veRoiLuu((tren, oMa) => {
    oMa.forEach(o => { if (o.__tong && o.getAttribute('data-mang-tong') === 'Farm') o.value = '6416'; });
  });
  const m = {}; (p.mangTk || []).forEach(x => { m[x.pll] = x; });
  t('🔴 gửi kèm bảng mangTk', !!p.mangTk, Object.keys(p || {}));
  teq('🔴 mã tổng vừa gõ cho Farm được ghi', '6416', (m['Farm'] || {}).nhomTk);
  teq('   mã tổng cũ của Funzone giữ nguyên', '6412', (m['Funzone'] || {}).nhomTk);
  t('🔴 dòng "Rạp" của POSH KHÔNG bị xoá dù không hiện trên màn K&H', !!m['Rạp'], Object.keys(m));
  teq('   và giữ nguyên mã tổng của nó', '6415', (m['Rạp'] || {}).nhomTk);
  teq('🔴 cột Từ khóa không bị thổi bay', 'Funzone', (m['Funzone'] || {}).tuKhoa);
  teq('   cột Ghi chú cũng vậy', 'ghi chú FZ', (m['Funzone'] || {}).note);
  teq('   và của mảng ngoài tầm nhìn', 'của POSH', (m['Rạp'] || {}).note);
}
/* ⚠️ Ô ĐỂ TRỐNG = XOÁ mã tổng của đúng mảng ấy. Giữ lại giá trị cũ khi ô trống thì không ai
   xoá được một mã tổng gõ nhầm — mà gõ nhầm ở đây kéo lệch cả thứ tự bảng. */
{
  const p = veRoiLuu((tren, oMa) => {
    oMa.forEach(o => { if (o.__tong && o.getAttribute('data-mang-tong') === 'Funzone') o.value = ''; });
  });
  const m = {}; (p.mangTk || []).forEach(x => { m[x.pll] = x; });
  teq('🔴 xoá trắng ô mã tổng thì mã ấy mất thật', '', (m['Funzone'] || {}).nhomTk);
  teq('   nhưng dòng vẫn còn, kèm Từ khóa', 'Funzone', (m['Funzone'] || {}).tuKhoa);
  teq('   và mảng ngoài tầm nhìn không hề gì', '6415', (m['Rạp'] || {}).nhomTk);
}
/* Ô mã tổng KHÔNG được lẫn vào mã của loại chi phí — hai bộ chọn khác nhau trên cùng bảng. */
{
  const p = veRoiLuu((tren, oMa) => {
    oMa.forEach(o => { if (o.__tong) o.value = '9999'; });
  });
  const lan = (p.tkNoMatrix || []).filter(x => String(x.tkNo) === '9999');
  teq('🔴 mã tổng KHÔNG chui vào bảng mã của loại chi phí', 0, lan.length);
}

/* ── 3. LỜI CHÚ THÍCH NÓI ĐÚNG BỐ CỤC MỚI ──────────────────────────────────────────────── */
t('🔴 chú thích dưới bảng nói rõ bảng dưới xếp theo mảng',
  HTML.indexOf('mỗi hàng là một mảng kinh doanh') >= 0);

/* ═════════════════════════════════════════════════════════════════════════════════════════ */
if (TRUOT.length) {
  console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):');
  TRUOT.forEach(x => console.log('  · ' + x));
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + DAT + ' phép: mảng xếp dọc, và vẽ rồi lưu lại không mất mã nào.');
