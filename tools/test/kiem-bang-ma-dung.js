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
function docThe(html) {
  const rows = [];
  html.split('<tr').slice(1).forEach(tr => {
    const body = tr.slice(0, tr.indexOf('</tr>') + 1);
    const f = [];
    /* Duyệt theo ĐÚNG THỨ TỰ xuất hiện. Gom input rồi mới gom select là đảo chỗ Loại/Bộ phận
       với Tên MISA — bài kiểm tự tạo ra một sai lệch không có thật rồi đi đuổi theo nó. */
    const re = /<input[^>]*>|<select[\s\S]*?<\/select>/g;
    let m;
    while ((m = re.exec(body)) !== null) {
      const tag = m[0];
      if (tag.slice(0, 6) === '<input') {
        const at = a => { const x = tag.match(new RegExp(a + '="([^"]*)"')); return x ? x[1] : null; };
        f.push({ tag: 'INPUT', value: at('value') || '', multiple: false, getAttribute: at });
      } else {
        const chon = (tag.match(/<option[^>]*selected[^>]*>/g) || [])
          .map(o => { const x = o.match(/value="([^"]*)"/); return x ? x[1] : ''; });
        f.push({ tag: 'SELECT', multiple: /<select[^>]*multiple/.test(tag),
          value: chon[0] || '', selectedOptions: chon.map(v => ({ value: v })), getAttribute: () => null });
      }
    }
    if (f.length) rows.push({ f, html: body });
  });
  return rows;
}
function dungBe(loaiChiPhi, tkNoMatrix, coso) {
  const KHO = {};
  const O = id => ({ _id: id, innerHTML: '', style: {}, textContent: '', className: '',
    getElementsByTagName: () => [], querySelectorAll: () => [], querySelector: () => null });
  const NK = { luu: null, toast: [] };
  const moi = {
    CFG: { loaiChiPhi: loaiChiPhi, tkNoMatrix: tkNoMatrix, coso: coso },
    MX_LOCK: true, TKNAME: {}, TKCHART: [],
    el: id => (KHO[id] = KHO[id] || O(id)),
    esc: x => String(x == null ? '' : x).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;'),
    toast: (k, m) => NK.toast.push([k, m]),
    toggleMxLock: () => {},
    _delBtn: () => '<td></td>',
    _bpDs: () => ['Cơ sở', 'Kỹ thuật', 'Setup', 'Marketing'],
    _bpNhan: b => b,
    _saveCfg: (p) => { NK.luu = p; },
    NK, KHO,
  };
  /* `boc('_loaiSel')` kết thúc trên cùng một dòng nên lát cắt nuốt luôn mấy hàm đứng sau, kể
     cả `_bpDs()` thật — hàm ấy đọc `window.CFG`. Cứ để nó chạy bản thật (bốc từ nguồn là bốc
     cả họ hàng, đúng tinh thần), chỉ cần cho nó một `window`. */
  moi.window = moi;
  const F = new Function('moi', `with(moi){
    ${boc('_bpTach')}\n${boc('_bpSelNhieu')}\n${boc('_inp')}\n${boc('_loaiSel')}
    ${boc('_mxCols')}\n${boc('_mxRowHtml')}\n${boc('renderTkNoMatrix')}\n${boc('saveCfgTkNoMx')}
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
];
const COSO = [{ ten: 'AEON', phanLoaiLon: 'Funzone' }, { ten: 'FARM NT', phanLoaiLon: 'Farm' }];

/* ── 1. HAI BẢNG, VÀ MẢNG XẾP DỌC ──────────────────────────────────────────────────────── */
{
  const b = dungBe(LOAI, MX, COSO);
  b.F.ve();
  const h = b.moi.el('cfgTkNoMx').innerHTML;
  t('🔴 có bảng thuộc tính (cfgMxBody)', h.indexOf('id="cfgMxBody"') >= 0);
  t('🔴 và bảng mã riêng (cfgMxNoBody)', h.indexOf('id="cfgMxNoBody"') >= 0);
  t('🔴 bảng mã: MỖI MẢNG MỘT HÀNG — tên mảng nằm ở cột đầu',
    h.indexOf('>Mảng kinh doanh</th>') >= 0 && h.indexOf('>Funzone</td>') >= 0 && h.indexOf('>Farm</td>') >= 0, h.slice(0, 300));
  t('🔴 và loại chi phí thành CỘT của bảng ấy',
    h.indexOf('<th style="width:128px">Chi phí cơ sở</th>') >= 0, h.slice(0, 400));
  t('   bảng thuộc tính KHÔNG còn cột mảng nào (đó là chỗ nó phình ngang)',
    h.slice(0, h.indexOf('cfgMxNoBody')).indexOf('Funzone') < 0);
  t('   bảng mã có tiêu đề nói rõ nó là gì', h.indexOf('🔢 TK Nợ của từng mảng kinh doanh') >= 0);
  /* Dính CẢ ô tiêu đề LẪN ô tên mảng. Dính mỗi tiêu đề thì kéo ngang vẫn mất tên mảng — đúng
     lúc đang dò mã là lúc cần nó nhất. */
  t('🔴 cột "Mảng kinh doanh" dính lại khi kéo ngang (dò mã mà mất tên mảng thì dò mò)',
    (h.match(/position:sticky;left:0/g) || []).length >= 2, h.match(/position:sticky[^"]*/g));
  t('   ô Bộ phận rộng ra, không còn bị bóp còn "Nhà▾"',
    h.indexOf('<th style="width:190px">Bộ phận</th>') >= 0, h.slice(0, 400));
  t('🔴 mỗi ô mã mang data-loai + data-pll (mốc để lưu, thay cho vị trí cột)',
    h.indexOf('data-loai="Chi phí cơ sở" data-pll="Funzone"') >= 0);
  t('🔴 ô tên loại mang data-goc (mốc để nhận ra loại vừa đổi tên)',
    h.indexOf('data-goc="Chi phí cơ sở"') >= 0);
  t('   mã đã lưu hiện đúng ô của nó', h.indexOf('value="64166" placeholder="—" style="width:100%" data-loai="Chi phí cơ sở" data-pll="Farm"') >= 0
    || /value="64166"[^>]*data-loai="Chi phí cơ sở"[^>]*data-pll="Farm"/.test(h), h.match(/<input[^>]*64166[^>]*>/));
}

/* ── 2. VẼ RỒI LƯU LẠI — KHÔNG ĐƯỢC MẤT GÌ ─────────────────────────────────────────────── */
function veRoiLuu(sua) {
  const b = dungBe(LOAI, MX, COSO);
  b.F.ve();
  const h = b.moi.el('cfgTkNoMx').innerHTML;
  const tren = docThe(h.slice(h.indexOf('id="cfgMxBody"'), h.indexOf('id="cfgMxNoBody"')));
  const duoi = docThe(h.slice(h.indexOf('id="cfgMxNoBody"')));
  if (sua) sua(tren, duoi);
  b.moi.el('cfgMxBody').getElementsByTagName = () => tren.map(r => ({
    querySelectorAll: () => r.f, getElementsByTagName: () => r.f.filter(x => x.tag === 'INPUT') }));
  const oMa = [];
  duoi.forEach(r => r.f.forEach(x => { if (x.tag === 'INPUT' && x.getAttribute('data-loai')) oMa.push(x); }));
  b.moi.el('cfgMxNoBody').querySelectorAll = () => oMa;
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
    [['Chi phí cơ sở','Farm','64166'],['Chi phí cơ sở','Funzone','64126'],['Chi phí setup','Funzone','2413']],
    r.tkNoMatrix.map(x => [x.nhom, x.pll, x.tkNo]).sort((a,b)=>a[0].localeCompare(b[0])||a[1].localeCompare(b[1])));
  t('   ô trống không sinh dòng rác', r.tkNoMatrix.length === 3, r.tkNoMatrix);
}
/* 🔴 Đổi tên loại: mã của nó phải đi theo tên mới, không được bay. */
{
  const r = veRoiLuu(tren => { tren[0].f[0].value = 'Chi phí cơ sở (mới)'; });
  t('🔴 đổi tên loại → mã đi theo tên MỚI, không mất',
    r.tkNoMatrix.filter(x => x.nhom === 'Chi phí cơ sở (mới)').length === 2, r.tkNoMatrix);
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
    duoi.forEach(row => row.f.forEach(x => {
      if (x.getAttribute && x.getAttribute('data-loai') === 'Chi phí lương'
        && x.getAttribute('data-pll') === 'Farm') x.value = '6421';
    }));
  });
  t('🔴 gõ mã mới vào ô đang trống → lưu xuống đúng cặp',
    r.tkNoMatrix.some(x => x.nhom === 'Chi phí lương' && x.pll === 'Farm' && x.tkNo === '6421'), r.tkNoMatrix);
}
/* Dòng tên rỗng ở bảng trên thì bỏ, không đẻ loại vô danh. */
{
  const r = veRoiLuu(tren => { tren[2].f[0].value = '   '; });
  teq('tên loại để trống → bỏ dòng ấy', 2, r.loaiChiPhi.length);
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
