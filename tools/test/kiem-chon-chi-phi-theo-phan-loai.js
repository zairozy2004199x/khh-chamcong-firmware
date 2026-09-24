/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * FORM NHẬP: PHÂN LOẠI LỚN → PHÂN LOẠI NHỎ → CƠ SỞ THEO KHỐI CỦA ĐẦU MỤC.
 * Anh Thắng 24/09/2026: *"Chọn Phân Loại Lớn trước, Đến Phân Loại con (Nếu chọn chi phí cơ sở thì
 * sẽ có chọn thêm Cơ Sở) còn không thì nó là chi phí không có cơ sở"*:
 *     Chi Phí Cơ Sở KVC → Chi Phí Cơ Sở (cơ sở KVC) · Chi Phí Marketing
 *     Chi Phí Cơ Sở MTĐ → Chi Phí Cơ Sở (cơ sở MTĐ)
 *     Chi Phí Chung      → Chi Phí Chung MTĐ · KVC · VP-MTĐ · VP-KVC   (không có cơ sở)
 * 🔴 CHẠY THẬT fillDauMuc / onDauMucPick / fillNhom / _oCoSoTheoDauMuc trên DOM giả.
 * Chạy: node tools/test/kiem-chon-chi-phi-theo-phan-loai.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const HTML = fs.readFileSync('wordpress/vhcp-chi-phi/templates/app.html', 'utf8');
let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them !== undefined ? (' → ' + JSON.stringify(them)) : '')); } }
function teq(n, mong, thuc) { t(n + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }
const ham = (n) => { const i = HTML.indexOf('  function ' + n + '('); return i < 0 ? '' : HTML.slice(i, HTML.indexOf('\n  }', i) + 4); };
['_tn', '_dmCoSoCua', '_dmDs', 'fillDauMuc', 'onDauMucPick', '_oCoSoTheoDauMuc', 'fillNhom', '_dauMucCua', '_dauMucCuaTen', 'opts', 'loaiOf', '_tenKhoi', 'renderNhomCp', '_boPhanCuaGian'].forEach((n) => t('⚠️ bốc được `' + n + '`', ham(n).length > 30, n));

const LOAI = [
  { ten: 'Chi phí cơ sở',        dauMuc: 'Chi Phí Cơ Sở KVC', khoi: 'mn' },
  { ten: 'Chi phí marketing',    dauMuc: 'Chi Phí Cơ Sở KVC', khoi: 'mn' },
  { ten: 'Chi phí cơ sở MTĐ',    dauMuc: 'Chi Phí Cơ Sở MTĐ', khoi: 'mn' },
  { ten: 'Chi Phí Chung KVC',    dauMuc: 'Chi Phí Chung',     khoi: 'mn' },
  { ten: 'Chi Phí Chung VP-KVC', dauMuc: 'Chi Phí Chung',     khoi: 'mn' },
  { ten: 'Chi phí lạ',           dauMuc: '',                  khoi: 'mn' },   // chưa xếp
  { ten: 'Chi phí thuê mall',    dauMuc: 'Chi phí tiền thuê', khoi: 'mn' },   // đầu mục KHÔNG khai khối cơ sở → như cũ
];
/* 24/09/2026: gian thuộc bộ phận qua CỘT BỘ PHẬN của bảng Cơ sở (`BOOT.cosoBoPhan`), không qua cột
   Khối (nay là miền) — anh Thắng: *"Chỗ Tỉnh, Bỏ thay vào đó là Bộ Phận (MTD, KVC, VP)… chọn Bộ phận
   thì nó ra cơ sở của bộ phận đó"*. Khối của mọi gian ở đây là miền 'mn' — để chắc là lọc không
   lén đọc cột Khối. */
const COSO_KHOI = { 'aeon tân phú': 'kvc', 'farm nha trang': 'kvc', 'tàu tân phú': 'mtd', 'cali thảo điền': 'mtd', 'vp hcm': 'vp', 'gian chưa khai': '' };
function be(o) {
  o = o || {};
  const KHO = {};
  const sel = (id) => (KHO[id] = KHO[id] || { _id: id, value: '', innerHTML: '', style: {}, disabled: false, textContent: '', options: [],
    set innerHTML(h) { this._h = h; this.options = (h.match(/<option value="([^"]*)"/g) || []).map((x) => ({ value: x.replace(/.*value="([^"]*)"/, '$1') })); },
    get innerHTML() { return this._h || ''; } });
  const moi = {
    BOOT: { dauMucDs: ['Chi Phí Cơ Sở KVC', 'Chi Phí Cơ Sở MTĐ', 'Chi Phí Chung', 'Chi phí tiền thuê', 'Đầu mục rỗng'],
      dauMucCoSo: { 'Chi Phí Cơ Sở KVC': 'kvc', 'Chi Phí Cơ Sở MTĐ': 'mtd', 'Chi Phí Chung': '' }, loaiChiPhi: LOAI,
      /* 🧪 cờ BẬT với người này — bài này canh đường MỚI; đường cũ/cờ có bài riêng kiem-co-tinh-nang-man.js */
      tinhNang: { phanLoaiHaiBac: (o.co === undefined) ? true : o.co }, cosoBoPhan: COSO_KHOI },
    KHOI_DS: [{ ma: 'mb', ten: 'Miền Bắc' }, { ma: 'mn', ten: 'Miền Nam' }, { ma: 'kvc', ten: 'Khu vui chơi' }, { ma: 'mtd', ten: 'Máy tự động' }, { ma: 'vp', ten: 'Văn phòng' }],
    KHOI_DANG: 'kvc', NHOM_CP: 'x', DM_CUR: o.dm || '',
    el: sel, esc: (x) => String(x == null ? '' : x), window: { _COSO_OPTS_GOC: Object.keys(COSO_KHOI).map((k) => k.toUpperCase()) },
    _loaiCpList: () => LOAI.slice(), _gianHopKhoi: (c) => (COSO_KHOI[c.toLowerCase()] || 'kvc') === 'kvc' || !COSO_KHOI[c.toLowerCase()],
    _khoiCuaGian: () => 'mn', _donNhieuCoSo: () => false, _khoaCoSoHint: () => {},
    fillNoiDungList: () => {}, showTkNhom: () => {}, _cacNhomCp: () => ['(cơ sở)', 'Kỹ thuật'], _nhanNhomCp: (k) => k, _veLoaiCpVi: (id) => { sel(id).textContent = '⬆ Chọn CƠ SỞ trước'; sel(id).style.color = '#b45309'; }, _lnDongThem: () => '',
  };
  moi.window.BOOT = moi.BOOT;
  new Function('moi', 'with(moi){' + ['_tn', '_boPhanCuaGian', '_dmCoSoCua', '_dmDs', 'fillDauMuc', 'onDauMucPick', '_oCoSoTheoDauMuc', 'fillNhom', '_dauMucCua', '_dauMucCuaTen', 'opts', 'loaiOf', '_tenKhoi', 'renderNhomCp'].map(ham).join('\n')
    + '\nmoi.F={fillDauMuc:fillDauMuc,onDauMucPick:onDauMucPick,fillNhom:fillNhom,oCoSo:_oCoSoTheoDauMuc,dmCoSo:_dmCoSoCua,dmDs:_dmDs,renderNhomCp:renderNhomCp}; moi.lay=function(){return DM_CUR;}; moi.layNhom=function(){return NHOM_CP;}; }')(moi);
  sel('f_pltt').value = ''; sel('lineId').value = o.lineId || '';
  if (o.cosoKhoa) { sel('f_coso').disabled = true; sel('f_coso').value = o.cosoKhoa; }
  return { moi, KHO, F: moi.F, sel };
}
const opt = (s) => s.options.map((x) => x.value).filter(Boolean);

/* ── 1. 🔴 Danh sách phân loại lớn: theo thứ tự máy chủ, bỏ đầu mục rỗng, thêm ô hứng ─── */
{
  const b = be(); b.F.fillDauMuc('');
  teq('🔴 ô Phân loại lớn = đầu mục CÓ loại, đúng thứ tự, + "Chưa xếp đầu mục"; bỏ "Đầu mục rỗng"',
    ['Chi Phí Cơ Sở KVC', 'Chi Phí Cơ Sở MTĐ', 'Chi Phí Chung', 'Chi phí tiền thuê', 'Chưa xếp đầu mục'], opt(b.sel('f_dauMuc')));
  t('   chưa chọn → nhắc chọn để lọc', /5 phân loại lớn — chọn một/.test(b.sel('f_dauMuc'+'Vi').textContent), b.sel('f_dauMucVi').textContent);
  b.F.fillNhom('');
  teq('   chưa chọn đầu mục → ô Phân loại nhỏ bày ĐỦ (không ép)', 7, opt(b.sel('f_nhom')).length);
  t('   ô Cơ sở hiện như cũ (lọc theo khối đang đứng kvc, gian chưa khai vẫn hiện)', b.sel('fldCoso').style.display === '' && JSON.stringify(opt(b.sel('f_coso'))) === JSON.stringify(['AEON TÂN PHÚ', 'FARM NHA TRANG', 'GIAN CHƯA KHAI']), opt(b.sel('f_coso')));
  b.moi.NHOM_CP = 'Kỹ thuật'; b.sel('f_nhomCp').innerHTML = '<button>cũ</button>'; b.F.renderNhomCp();
  t('🔴 dải nút bộ phận thôi bày: renderNhomCp xoá nút và ép NHOM_CP rỗng (không cắt loại theo nút nữa)', b.moi.layNhom() === '' && b.sel('f_nhomCp').innerHTML === '', b.moi.layNhom());
}
/* ── 2. 🔴 Chọn "Chi Phí Cơ Sở KVC" → loại của nó, ô Cơ sở chỉ KVC ─────────────────────── */
{
  const b = be(); b.F.fillDauMuc(''); b.sel('f_dauMuc').value = 'Chi Phí Cơ Sở KVC'; b.F.onDauMucPick();
  teq('🔴 phân loại nhỏ chỉ còn loại của đầu mục KVC', ['Chi phí cơ sở', 'Chi phí marketing'], opt(b.sel('f_nhom')));
  t('🔴 ô Cơ sở HIỆN, chỉ xổ cơ sở KVC', b.sel('fldCoso').style.display === '' && JSON.stringify(opt(b.sel('f_coso'))) === JSON.stringify(['AEON TÂN PHÚ', 'FARM NHA TRANG']), opt(b.sel('f_coso')));
  t('   nhãn nói rõ bộ phận', /Cơ sở bộ phận Khu vui chơi \*/.test(b.sel('lblCoso').textContent), b.sel('lblCoso').textContent);
  t('   gợi ý nói có cơ sở, bộ phận nào', /Có cơ sở — chỉ xổ gian của bộ phận Khu vui chơi/.test(b.sel('f_dauMucVi').textContent), b.sel('f_dauMucVi').textContent);
}
/* ── 3. 🔴 Chọn "Chi Phí Cơ Sở MTĐ" → cơ sở MTĐ, dù đang đứng khối kvc ────────────────── */
{
  const b = be(); b.F.fillDauMuc(''); b.sel('f_dauMuc').value = 'Chi Phí Cơ Sở MTĐ'; b.F.onDauMucPick();
  teq('🔴 loại MTĐ', ['Chi phí cơ sở MTĐ'], opt(b.sel('f_nhom')));
  teq('🔴 cơ sở chỉ MTĐ theo cột Bộ phận (khối đang đứng kvc, khối gian là miền — không cản)', ['TÀU TÂN PHÚ', 'CALI THẢO ĐIỀN'], opt(b.sel('f_coso')));
  t('   một cơ sở duy nhất thì KHÔNG tự chọn khi có hai', b.sel('f_coso').value === '');
}
/* ── 4. 🔴 "Chi Phí Chung" → không có cơ sở: ẩn ô, cơ sở trống ─────────────────────────── */
{
  const b = be(); b.F.fillDauMuc(''); b.sel('f_coso').value = 'AEON TÂN PHÚ';
  b.sel('f_dauMuc').value = 'Chi Phí Chung'; b.F.onDauMucPick();
  teq('🔴 loại của Chi Phí Chung', ['Chi Phí Chung KVC', 'Chi Phí Chung VP-KVC'], opt(b.sel('f_nhom')));
  t('🔴 ô Cơ sở ẨN', b.sel('fldCoso').style.display === 'none');
  teq('🔴 cơ sở đang chọn bị xoá → dòng không gắn cơ sở', '', b.sel('f_coso').value);
  t('🔴 gợi ý dưới ô loại KHÔNG còn "Chọn CƠ SỞ trước" mà nói "không gắn cơ sở"', /không gắn cơ sở/.test(b.sel('f_nhomVi').textContent) && !/Chọn CƠ SỞ/.test(b.sel('f_nhomVi').textContent), b.sel('f_nhomVi').textContent);
  t('   gợi ý đầu mục', /Chi phí không gắn cơ sở/.test(b.sel('f_dauMucVi').textContent));
  /* quay lại đầu mục có cơ sở → ô hiện lại */
  b.sel('f_dauMuc').value = 'Chi Phí Cơ Sở KVC'; b.F.onDauMucPick();
  t('   đổi sang đầu mục có cơ sở → ô Cơ sở hiện lại', b.sel('fldCoso').style.display === '');
}
/* ── 5. Đầu mục CHƯA khai khối cơ sở / ô hứng → như cũ ────────────────────────────────── */
{
  const b = be(); b.F.fillDauMuc(''); b.sel('f_dauMuc').value = 'Chi phí tiền thuê'; b.F.onDauMucPick();
  teq('🔴 đầu mục không có trong bảng khối cơ sở → "*" (như cũ)', '*', b.F.dmCoSo('Chi phí tiền thuê'));
  t('   ô Cơ sở hiện, lọc theo khối đang đứng', b.sel('fldCoso').style.display === '' && opt(b.sel('f_coso')).indexOf('AEON TÂN PHÚ') >= 0 && opt(b.sel('f_coso')).indexOf('TÀU TÂN PHÚ') < 0);
  teq('   nhãn như cũ', 'Cơ sở *', b.sel('lblCoso').textContent);
  b.sel('f_dauMuc').value = 'Chưa xếp đầu mục'; b.F.onDauMucPick();
  teq('   ô hứng → loại chưa xếp', ['Chi phí lạ'], opt(b.sel('f_nhom')));
  teq('   ô hứng = như cũ', '*', b.F.dmCoSo('Chưa xếp đầu mục'));
  teq('   rỗng = như cũ', '*', b.F.dmCoSo(''));
}
/* ── 6. Đơn đã chốt một cơ sở (ô khoá) → giữ nguyên khoá, đầu mục không đè ───────────── */
{
  const b = be({ cosoKhoa: 'AEON TÂN PHÚ' }); b.F.fillDauMuc(''); b.sel('f_dauMuc').value = 'Chi Phí Cơ Sở MTĐ'; b.F.onDauMucPick();
  teq('🔴 ô khoá → không dựng lại danh sách, cơ sở của đơn còn nguyên', 'AEON TÂN PHÚ', b.sel('f_coso').value);
  b.sel('f_dauMuc').value = 'Chi Phí Chung'; b.F.onDauMucPick();
  teq('   đầu mục không cơ sở mà ô khoá → ẩn ô nhưng KHÔNG xoá cơ sở của đơn', 'AEON TÂN PHÚ', b.sel('f_coso').value);
}
/* ── 7. Sửa dòng cũ mang cơ sở ngoài danh sách của đầu mục → vẫn chọn lại được ───────── */
{
  const b = be({ lineId: '77' }); b.F.fillDauMuc(''); b.sel('f_coso').value = 'VP HCM';
  b.sel('f_dauMuc').value = 'Chi Phí Cơ Sở KVC'; b.F.onDauMucPick();
  t('🔴 dòng đang sửa: cơ sở lạ được thêm vào danh sách, giữ giá trị', opt(b.sel('f_coso')).indexOf('VP HCM') >= 0 && b.sel('f_coso').value === 'VP HCM', opt(b.sel('f_coso')));
  const b2 = be(); b2.F.fillDauMuc(''); b2.sel('f_coso').value = 'VP HCM'; b2.sel('f_dauMuc').value = 'Chi Phí Cơ Sở KVC'; b2.F.onDauMucPick();
  t('   dòng MỚI thì không: cơ sở lạ bị xoá khỏi ô', opt(b2.sel('f_coso')).indexOf('VP HCM') < 0 && b2.sel('f_coso').value === '', opt(b2.sel('f_coso')));
}
/* ── 8. Chỗ nối: lưu dòng, sửa dòng, boot, reset, thẻ Cấu hình ─────────────────────────── */
t('🔴 `_saveLine` chỉ đòi cơ sở khi đầu mục CÓ cơ sở', /if\(!r\.coso && _dmCoSoCua\(DM_CUR\)!==''\)\{_lyDoKhongThem\('Chưa chọn CƠ SỞ/.test(ham('_saveLine')));
t('🔴 `editLine` đặt đầu mục theo loại của dòng rồi mới đặt cơ sở', /DM_CUR=_dauMucCuaTen\(l\.nhom\|\|''\); fillDauMuc\(DM_CUR\);/.test(ham('editLine')) && ham('editLine').indexOf('fillDauMuc(DM_CUR)') < ham('editLine').indexOf("fillNhom(l.nhom||'')"));
t('   `resetLineForm` về đầu mục rỗng', /DM_CUR=''; fillDauMuc\(''\); fillNhom\(''\)/.test(ham('resetLineForm')));
t('   boot dựng ô đầu mục trước ô loại', /fillDauMuc\(DM_CUR\);\n\s*fillNhom\(''\)/.test(HTML));
t('   `_khoaCoSo` áp lại lọc theo đầu mục sau khi dựng ô', /_oCoSoTheoDauMuc\(\)/.test(ham('_khoaCoSo')));
t('   `onPlttChange` vẽ lại đầu mục (loại lọc theo hình thức)', /fillDauMuc\(DM_CUR\); fillNhom\(''\)/.test(ham('onPlttChange')));
t('🔴 form có ô `f_dauMuc` nhãn "Phân loại lớn" đứng TRƯỚC ô `f_nhom` "Phân loại nhỏ"', /<label>Phân loại lớn \*<\/label><select id="f_dauMuc" onchange="onDauMucPick\(\)">/.test(HTML) && HTML.indexOf('id="f_dauMuc"') < HTML.indexOf('id="f_nhom"') && /<span id="lblNhom">Phân loại nhỏ \(loại chi phí\)<\/span> \*/.test(HTML));
t('🔴 thẻ Cấu hình 🗂 Đầu mục: bảng + Thêm + Lưu, nằm trong nhóm Danh mục chi phí', /id="dauMucCard"/.test(HTML) && /onclick="addCfgDauMuc\(\)"/.test(HTML) && /onclick="saveCfgDauMuc\(\)"/.test(HTML) && /id:\['dauMucCard','loaiMxCard'/.test(HTML) && /renderDauMuc\(\);/.test(HTML));
{
  const S = ham('_dmCoSoSel');
  t('   ô Cơ sở của đầu mục: như cũ · không có · từng khối (trừ miền)', /\['\*','— như cũ/.test(S) && /\['','Không có cơ sở/.test(S) && /MIEN_MA\.indexOf\(k\.ma\)<0/.test(S));
  t('   lưu gửi {ten, coso, goc}', /_saveCfg\(\{dauMucDs:data\}/.test(ham('saveCfgDauMuc')) && /coso:String\(s\?\(s\.value==null\?'\*':s\.value\):'\*'\)/.test(ham('saveCfgDauMuc')) && /goc:String\(\(i&&i\.getAttribute\('data-goc'\)\)\|\|''\)/.test(ham('saveCfgDauMuc')));
}
if (TRUOT.length) { console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):'); TRUOT.forEach(function (x) { console.log('  · ' + x); }); process.exit(1); }
console.log('\n✓ SẠCH — ' + DAT + ' phép: phân loại lớn → nhỏ; đầu mục có cơ sở thì lọc theo khối, không thì ẩn ô; đơn chốt cơ sở giữ khoá.');
