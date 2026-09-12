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
    const o = { ten: null, loai: null, misa: null, bp: [] };
    tags.forEach(tag => {
      const at = a => { const x = tag.match(new RegExp(a + '="([^"]*)"')); return x ? x[1] : null; };
      if (tag.slice(0, 6) === '<input') {
        const f = { value: at('value') || '', checked: /\schecked/.test(tag), getAttribute: at };
        if (at('data-goc') !== null) o.ten = f;
        else if (at('data-o') === 'misa') o.misa = f;
        else if (at('type') === 'checkbox') o.bp.push(f);
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
    o.querySelectorAll = sel => (sel.indexOf('[data-bp]') >= 0 && sel.indexOf(':checked') >= 0)
      ? o.bp.filter(c => c.checked) : [];
    rows.push(o);
  });
  return rows;
}
/* Mọi ô mã của MỌI bảng đơn vị đang hiện. */
function docOMa(html) {
  const ra = [];
  (html.match(/<input[^>]*data-loai="[^"]*"[^>]*>/g) || []).forEach(tag => {
    const at = a => { const x = tag.match(new RegExp(a + '="([^"]*)"')); return x ? x[1] : null; };
    ra.push({ value: at('value') || '', getAttribute: at });
  });
  return ra;
}
function dungBe(loaiChiPhi, tkNoMatrix, coso) {
  const KHO = {};
  const O = id => ({ _id: id, innerHTML: '', style: {}, textContent: '', className: '',
    getElementsByTagName: () => [], querySelectorAll: () => [], querySelector: () => null });
  const NK = { luu: null, toast: [] };
  const moi = {
    CFG: { loaiChiPhi: loaiChiPhi, tkNoMatrix: tkNoMatrix, coso: coso },
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
    document: { querySelectorAll: sel => /\.mxNoBody\b/.test(sel) ? (NK.oMa || []) : [] },
    NK, KHO,
  };
  /* `boc('_loaiSel')` kết thúc trên cùng một dòng nên lát cắt nuốt luôn mấy hàm đứng sau, kể
     cả `_bpDs()` thật — hàm ấy đọc `window.CFG`. Cứ để nó chạy bản thật (bốc từ nguồn là bốc
     cả họ hàng, đúng tinh thần), chỉ cần cho nó một `window`. */
  moi.window = moi;
  const F = new Function('moi', `with(moi){
    ${boc('_bpTach')}\n${boc('_bpSelNhieu')}\n${boc('_inp')}\n${boc('_loaiSel')}
    ${boc('_mxMaGoc')}\n${boc('_mxSapCols')}\n${boc('_mxCols')}\n${boc('_mxNhomDv')}\n${boc('_xemDuocDv')}\n${boc('_mxRowHtml')}\n${boc('renderTkNoMatrix')}\n${boc('saveCfgTkNoMx')}
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

/* ── 1. HAI BẢNG, VÀ MẢNG XẾP DỌC ──────────────────────────────────────────────────────── */
{
  const b = dungBe(LOAI, MX, COSO);
  b.F.ve();
  const h = b.moi.el('cfgTkNoMx').innerHTML;
  t('🔴 có bảng thuộc tính (cfgMxBody)', h.indexOf('id="cfgMxBody"') >= 0);
  t('🔴 và bảng mã riêng, MỘT BẢNG MỖI ĐƠN VỊ', h.indexOf('class="mxNoBody" data-dv="K&amp;H"') >= 0);
  t('🔴 ô Bộ phận là HỘP TÍCH, không phải danh sách phải giữ Ctrl',
    h.indexOf('<div data-bp') >= 0 && h.indexOf('type="checkbox" value="Kỹ thuật" checked') >= 0
    && h.indexOf('<select multiple') < 0, h.slice(0, 600));
  t('   nói rõ không tích gì = mọi bộ phận', h.indexOf('không tích = mọi bộ phận') >= 0);
  t('🔴 bảng mã: MỖI MẢNG MỘT HÀNG — tên mảng nằm ở cột đầu',
    h.indexOf('>Mảng kinh doanh</th>') >= 0 && h.indexOf('>Funzone</td>') >= 0 && h.indexOf('>Farm</td>') >= 0, h.slice(0, 300));
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
  const b = dungBe(LOAI, MX, COSO);
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
  const b = dungBe(LOAI, MX, COSO);
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
