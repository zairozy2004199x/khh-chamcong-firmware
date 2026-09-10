/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * DANH SÁCH DỰ ÁN: CHIA TRANG 10 DÒNG.
 *
 * Anh Thắng 10/09/2026: *"Chỉ hiện 10 đơn trên 1 trang"*.
 *
 * =============================================================================================
 * 🔴 SỐ TRANG PHẢI BỊ KẸP VÀO KHOẢNG CÓ THẬT. Đang ở trang 4 rồi xoá bớt dự án, hay đổi bộ lọc
 *    sang nhóm ít dự án hơn, là số trang đang đứng vượt ra ngoài — bảng trắng trơn trong khi
 *    danh sách vẫn còn dự án, và chẳng có gì chỉ cho người dùng biết phải bấm về trang trước.
 *    Hỏng kiểu này im lặng: nhìn y như "không có dự án nào".
 *
 * 🔴 THANH TRANG PHẢI NÓI CẢ TỔNG. Chỉ hiện "Trang 1 / 4" thì người ta không biết mình đang bỏ
 *    sót bao nhiêu dự án phía sau — mà đó chính là câu hỏi duy nhất họ cần trả lời.
 *
 * ⚠️ CHẠY THẬT `renderDuAnList()` bốc từ mã nguồn, đếm DÒNG THẬT trong bảng.
 *
 * Chạy: node tools/test/kiem-trang-du-an.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs'), path = require('path');
let DAT = 0; const TRUOT = [];
function t(ten, ok, them) { if (ok) { DAT++; return; }
  TRUOT.push(ten + (them !== undefined ? '\n      → ' + JSON.stringify(them) : '')); }
function teq(ten, mong, thuc) { t(ten + ' (mong ' + JSON.stringify(mong) + ')', mong === thuc, thuc); }

const GOC = path.resolve(__dirname, '..', '..');
const HTML = fs.readFileSync(path.join(GOC, 'wordpress/vhcp-chi-phi/templates/app.html'), 'utf8');
const boc = ten => {
  const i = HTML.indexOf('function ' + ten + '(');
  t('bốc được ' + ten + '()', i >= 0);
  return i < 0 ? '' : HTML.slice(i, HTML.indexOf('\n  }', i) + 4);
};

/* Số dòng mỗi trang đọc từ CHÍNH mã nguồn, rồi mới đối chiếu với con số anh Thắng nói. Ghim
   số 10 vào bài kiểm mà không soi hằng thì đổi hằng đi bài kiểm vẫn xanh. */
const hang = /var DA_TRANG=(\d+), DA_MOI_TRANG=(\d+);/.exec(HTML);
t('🔴 có hằng số dòng mỗi trang', !!hang);
teq('🔴 và đúng 10 dòng như anh Thắng dặn', 10, hang ? Number(hang[2]) : null);
teq('   mở màn ở trang 1', 1, hang ? Number(hang[1]) : null);

function dungBe(n, trangThai) {
  const KHO = {}, NK = { cuon: [] };
  const O = id => ({ _id: id, style: { display: '' }, value: '', innerHTML: '',
    scrollIntoView() { NK.cuon.push(id); } });
  const moi = {
    DA_TRANG: 1, DA_MOI_TRANG: hang ? Number(hang[2]) : 10, DA_CHO_KEO: false,
    DA_ITEMS: Array.from({ length: n }, (_, i) => ({
      maDA: 'DA' + i, ten: 'Dự án ' + i, loai: 'Setup lắp đặt',
      trangThai: trangThai || 'Đang làm', tongDuToan: 0, tongThucTe: 0, chenh: 0 })),
    CURUSER: { role: 'Nhân viên' },
    el: id => (KHO[id] = KHO[id] || O(id)),
    esc: x => String(x == null ? '' : x),
    money: x => String(x),
    daBadge: () => '<span></span>',
    _daKeoToi: () => {},
    NK,
  };
  const F = new Function('moi', `with(moi){ ${boc('_daVePager')}\n${boc('daDoiTrang')}\n${boc('renderDuAnList')}
    return { renderDuAnList: renderDuAnList, daDoiTrang: daDoiTrang }; }`)(moi);
  moi.renderDuAnList = F.renderDuAnList;   // daDoiTrang gọi lại nó
  const demDong = () => (moi.el('daListBody').innerHTML.match(/<tr>/g) || []).length;
  return { moi, KHO, F, demDong, pager: () => moi.el('daListPager') };
}

/* ── 1. CẮT ĐÚNG 10 DÒNG ───────────────────────────────────────────────────────────────── */
{
  const b = dungBe(23); b.F.renderDuAnList();
  teq('🔴 23 dự án → trang 1 chỉ vẽ 10 dòng', 10, b.demDong());
  t('   thanh trang hiện ra', b.pager().style.display === 'flex', b.pager().style.display);
  t('🔴 nói rõ đang xem 1–10 trong 23', /1–10 trong 23/.test(b.pager().innerHTML), b.pager().innerHTML);
  t('   và trang 1 / 3', /Trang <b>1<\/b> \/ 3/.test(b.pager().innerHTML), b.pager().innerHTML);
  t('🔴 ở trang đầu thì nút "Trước" bị khoá', /‹ Trước<\/button>/.test(b.pager().innerHTML.replace(/<button class="btn b-x" disabled[^>]*>/, '<button class="btn b-x" disabled>')) && /disabled/.test(b.pager().innerHTML), b.pager().innerHTML);

  b.F.daDoiTrang(3);
  teq('🔴 trang cuối chỉ còn 3 dòng (23 = 10+10+3)', 3, b.demDong());
  t('   nói rõ đang xem 21–23 trong 23', /21–23 trong 23/.test(b.pager().innerHTML), b.pager().innerHTML);
  t('   và kéo màn về đầu bảng khi đổi trang', b.moi.NK.cuon.indexOf('daListBody') >= 0, b.moi.NK.cuon);

  b.F.daDoiTrang(2);
  teq('trang giữa lại đủ 10 dòng', 10, b.demDong());
  t('   nói rõ đang xem 11–20 trong 23', /11–20 trong 23/.test(b.pager().innerHTML), b.pager().innerHTML);
}

/* ── 2. ÍT DỰ ÁN THÌ ĐỪNG BÀY THANH TRANG ──────────────────────────────────────────────── */
{
  const b = dungBe(10); b.F.renderDuAnList();
  teq('đúng 10 dự án → vẽ đủ 10', 10, b.demDong());
  t('🔴 và KHÔNG bày thanh trang (một trang thì chuyển đi đâu)',
    b.pager().style.display === 'none' && b.pager().innerHTML === '', b.pager().style.display);
}
{
  const b = dungBe(11); b.F.renderDuAnList();
  t('11 dự án → thanh trang hiện ra', b.pager().style.display === 'flex');
  teq('   trang 1 vẫn 10 dòng', 10, b.demDong());
}
{
  const b = dungBe(0); b.F.renderDuAnList();
  teq('không có dự án nào → 0 dòng, không nổ', 0, b.demDong());
  t('   và không bày thanh trang', b.pager().style.display === 'none');
  t('   vẫn báo "chưa có dự án"', b.moi.el('daListEmpty').style.display === 'block');
}

/* ── 3. 🔴 SỐ TRANG VƯỢT KHOẢNG PHẢI BỊ KẸP LẠI ────────────────────────────────────────── */
{
  const b = dungBe(23); b.F.daDoiTrang(3);
  /* Xoá bớt còn 12 dự án: trang 3 không còn tồn tại nữa. */
  b.moi.DA_ITEMS = b.moi.DA_ITEMS.slice(0, 12);
  b.F.renderDuAnList();
  teq('🔴 danh sách ngắn lại → kẹp về trang cuối CÓ THẬT', 2, b.moi.DA_TRANG);
  teq('   và vẽ ra dòng, không để bảng trắng', 2, b.demDong());
}
{
  const b = dungBe(23); b.F.daDoiTrang(99);
  teq('🔴 nhảy đại tới trang 99 → kẹp về trang cuối', 3, b.moi.DA_TRANG);
  t('   vẫn có dòng', b.demDong() > 0, b.demDong());
}
{
  const b = dungBe(23); b.F.daDoiTrang(0);
  teq('🔴 lùi quá trang 1 → kẹp lại trang 1', 1, b.moi.DA_TRANG);
  teq('   và vẽ đủ 10 dòng', 10, b.demDong());
}
{
  const b = dungBe(23); b.F.daDoiTrang(3);
  b.moi.DA_ITEMS = [];
  b.F.renderDuAnList();
  teq('🔴 xoá sạch dự án → về trang 1, không kẹt ở trang rỗng', 1, b.moi.DA_TRANG);
}

/* ── 4. ĐỔI BỘ LỌC THÌ VỀ TRANG 1 ──────────────────────────────────────────────────────── */
t('🔴 đổi bộ lọc trạng thái → nhảy về trang 1 (không giữ trang của danh sách cũ)',
  HTML.indexOf('id="daListFilter" onchange="DA_TRANG=1;renderDuAnList()"') >= 0);

/* ── 5. KHÔNG LÀM HỎNG ĐƯỜNG CŨ ────────────────────────────────────────────────────────── */
{
  const b = dungBe(3); b.F.renderDuAnList();
  const html = b.moi.el('daListBody').innerHTML;
  t('nút "Mở" của từng dòng vẫn còn', /openDuAn\('DA0'\)/.test(html), html.slice(0, 200));
  t('   và trỏ đúng mã dự án của dòng ấy', /openDuAn\('DA2'\)/.test(html));
}

/* ── CỘT SỐ PHẢI THẲNG HÀNG VỚI ĐẦU CỘT ────────────────────────────────────────────────────
 * Anh Thắng 10/09/2026: *"cột chênh lệch đang lệch nhau"* — đầu cột "Chênh lệch" canh PHẢI mà
 * ô bên dưới canh TRÁI, nên "⚠️ vượt 22.350.000" nằm dạt sang mé trái, đọc lên tưởng nó thuộc
 * cột "Thực tế" bên cạnh. Bảng tiền mà nhìn nhầm cột là nhìn nhầm con số.
 *
 * ⚠️ SO ĐẦU CỘT VỚI Ô THẬT, không ghim riêng ô nào. Ghim "ô thứ 5 phải có class money" thì
 *    thêm một cột nữa vào bảng là bài kiểm ghim sai chỗ mà vẫn xanh.
 * ───────────────────────────────────────────────────────────────────────────────────────── */
{
  /* Đầu cột lấy từ THẺ <thead> trong app.html; ô lấy từ HTML mà renderDuAnList() vừa vẽ. */
  const iTb = HTML.indexOf('id="daListBody"');
  const iTh = HTML.lastIndexOf('<thead>', iTb);
  const thead = HTML.slice(iTh, HTML.indexOf('</thead>', iTh));
  const ths = (thead.match(/<th[^>]*>[^<]*<\/th>/g) || []);
  t('đọc được đầu cột của bảng danh sách dự án', ths.length >= 5, ths);
  const phai = ths.map(x => /text-align\s*:\s*right/.test(x));
  const ten = ths.map(x => x.replace(/<[^>]*>/g, '').trim());

  const b = dungBe(3);
  b.moi.renderDuAnList();
  const tr = b.moi.el('daListBody').innerHTML.split('</tr>')[0];
  const tds = (tr.match(/<td[^>]*>/g) || []);
  t('đọc được ô của một dòng', tds.length === ths.length, { th: ths.length, td: tds.length });
  ths.forEach((_, i) => {
    if (!phai[i]) return;
    const o = tds[i] || '';
    t('🔴 cột "' + ten[i] + '" canh phải ở đầu cột thì ô cũng phải canh phải',
      /class="money"/.test(o) || /text-align\s*:\s*right/.test(o), o);
  });
}

{
  /* Bảng Kỹ thuật ở màn Tổng quan — cùng lỗi, khác chỗ vẽ. Sửa một chỗ mà quên chỗ kia thì
     người dùng vẫn thấy cột lệch, chỉ là ở màn khác. */
  const iTb = HTML.indexOf('id="tqKtSetupBody"');
  const iTh = HTML.lastIndexOf('<thead>', iTb);
  const thead = HTML.slice(iTh, HTML.indexOf('</thead>', iTh));
  const ths = (thead.match(/<th[^>]*>[^<]*<\/th>/g) || []);
  t('đọc được đầu cột của bảng Kỹ thuật (Tổng quan)', ths.length >= 4, ths);
  const ten = ths.map(x => x.replace(/<[^>]*>/g, '').trim());
  const NK = {};
  const moi = {
    esc: x => String(x == null ? '' : x),
    money: x => String(x),
    showPage: () => {}, openDuAn: () => {},
    _tqCard: () => '',
    el: id => (NK[id] = NK[id] || { innerHTML: '' }),
  };
  const i = HTML.indexOf('function renderKyThuatTongQuan(');
  const src = HTML.slice(i, HTML.indexOf('\n  }', i) + 4);
  new Function('moi', `with(moi){ ${src}\n return renderKyThuatTongQuan; }`)(moi)([
    { maDA: 'DA1', ten: 'TÀU ESTELLA', loai: 'Setup lắp đặt', tongDuToan: 0,
      tongThucTe: 22350000, chenh: 22350000, trangThai: 'Đang làm' },
  ]);
  const tds = ((NK.tqKtSetupBody.innerHTML.split('</tr>')[0]) .match(/<td[^>]*>/g) || []);
  t('vẽ được một dòng bảng Kỹ thuật', tds.length === ths.length, { th: ths.length, td: tds.length });
  ths.forEach((x, k) => {
    if (!/text-align\s*:\s*right/.test(x)) return;
    const o = tds[k] || '';
    t('🔴 bảng Kỹ thuật: cột "' + ten[k] + '" canh phải ở đầu cột thì ô cũng phải canh phải',
      /class="money"/.test(o) || /text-align\s*:\s*right/.test(o), o);
  });
}

/* ═════════════════════════════════════════════════════════════════════════════════════════ */
if (TRUOT.length) {
  console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):');
  TRUOT.forEach(x => console.log('  · ' + x));
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + DAT + ' phép: 10 dòng một trang, và số trang không bao giờ trỏ ra ngoài.');
