/**
 * FORM NHẬP HẠNG MỤC LÀ HỘP NỔI
 * =============================================================================================
 *
 * Anh Thắng 22/09/2026: *"tạo form nhập liệu rời mà, bấm Thêm hạng mục là nó nổi lên form nhập
 * liệu"*.
 *
 * Trước đây form nằm THƯỜNG TRỰC giữa trang, chiếm gần nửa màn kể cả khi người ta chỉ muốn đọc
 * lại mấy dòng đã nhập — mà đọc lại là việc làm nhiều hơn hẳn việc nhập.
 *
 * 🔴 BÀI NÀY CANH MỘT LỖ DO CHÍNH LƯỢT DỜI FORM MỞ RA, và nó là lỗ nguy nhất ở đây:
 *
 *      Dòng `if(el('lineFormCard')) … display = CUR.stChot ? 'none' : ''` vốn giấu form khi đơn
 *      ĐÃ CHỐT SỔ. Dời form vào hộp nổi thì cái card ấy nằm TRONG một hộp vốn đã `display:none`
 *      — giấu nó là giấu một thứ đang ẩn sẵn, tức KHÔNG NGĂN ĐƯỢC GÌ. Nút "＋ Thêm hạng mục"
 *      vẫn sáng và vẫn mở được form trên một đơn đã chốt.
 *
 *      Lỗ ấy không kêu tiếng nào: màn trông y hệt bản cũ, chỉ khác là cái cửa đã mở sẵn.
 *
 * Chạy: node tools/test/kiem-hop-nhap-hang-muc.js
 */
const fs = require('fs'), path = require('path');
const GOC = path.resolve(__dirname, '..', '..');
const BAN = ['vhcp-chi-phi', 'vhcp-chi-phi-hn', 'vhcp-chi-phi-mtd', 'vhcp-chi-phi-vp'];

let dat = 0; const truot = [];
function t(ten, dk, them) {
  if (dk) { dat++; return; }
  truot.push(ten + (them === undefined ? '' : '\n      → ' + String(them).slice(0, 300)));
}
/* Tước chú thích trước khi soi — chú thích thiết kế ở đây nhắc tên đúng những thứ đang canh. */
function chiMa(h) {
  return h.replace(/<!--[\s\S]*?-->/g, ' ')
          .replace(/\/\*[\s\S]*?\*\//g, ' ')
          .replace(/(^|[^:])\/\/[^\n]*/g, '$1');
}
function than(h, ten) {
  const i = h.indexOf('function ' + ten + '(');
  if (i < 0) return '';
  let j = h.indexOf('{', i), d = 0;
  for (let k = j; k < h.length; k++) {
    if (h[k] === '{') d++;
    else if (h[k] === '}') { d--; if (!d) return h.slice(i, k + 1); }
  }
  return '';
}

BAN.forEach(function (b) {
  const f = path.join(GOC, 'wordpress', b, 'templates/app.html');
  if (!fs.existsSync(f)) { t('có ' + b + '/app.html', false); return; }
  const raw = fs.readFileSync(f, 'utf8');
  const h = chiMa(raw);

  // ── 1. HỘP NỔI, THEO ĐÚNG NẾP ĐÃ CÓ ─────────────────────────────────────────────────────
  t('🔴 ' + b + ': form nhập nằm trong hộp nổi `#lineModal`', h.indexOf('id="lineModal"') >= 0);
  const hop = raw.slice(raw.indexOf('id="lineModal"'), raw.indexOf('id="lineModal"') + 400);
  t('🔴 ' + b + ': hộp ẩn MẶC ĐỊNH — không thì nó che trang ngay lúc mở app',
    /display:none/.test(hop), hop.slice(0, 160));
  t(b + ': và phủ kín màn như `#pinModal` đã làm', /position:fixed;inset:0/.test(hop), hop.slice(0, 160));
  t('🔴 ' + b + ': có nút MỞ hộp', h.indexOf('onclick="moLineForm()"') >= 0);
  ['moLineForm', 'dongLineForm'].forEach(function (fn) {
    t(b + ': có ' + fn + '()', h.indexOf('function ' + fn + '(') >= 0);
  });
  t(b + ': đóng được bằng phím Esc', /e\.key!=='Escape'/.test(h) || /Escape/.test(than(h, '') || h));

  // ── 2. 🔴 ĐƠN ĐÃ CHỐT SỔ THÌ KHÔNG MỞ ĐƯỢC — GÁC HAI LỚP ─────────────────────────────────
  t('🔴 ' + b + ': đơn chốt sổ thì GIẤU NÚT MỞ (giấu card bên trong hộp là giấu thứ đang ẩn sẵn)',
    /btnMoLineForm.*stChot|stChot.*btnMoLineForm/s.test(h)
    && h.indexOf("el('lineFormCard').style.display= CUR.stChot") < 0, '');
  const mo = than(h, 'moLineForm');
  t('🔴 ' + b + ': và `moLineForm()` tự chối — giấu nút mới là một lớp, gọi thẳng vẫn qua',
    /CUR\.stChot/.test(mo), mo.slice(0, 300));

  // ── 3. 🔴 ĐÓNG HỘP PHẢI BỎ LƯỢT SỬA ĐANG DỞ ─────────────────────────────────────────────
  /* Không bỏ thì lần mở sau hộp còn mang `lineId` của dòng cũ — bấm Lưu một cái là GHI ĐÈ lên
     dòng ấy trong khi người ta tưởng mình vừa thêm một dòng mới. */
  const dong = than(h, 'dongLineForm');
  t('🔴 ' + b + ': đóng hộp thì BỎ lượt sửa đang dở, không để `lineId` sót lại',
    /lineId/.test(dong) && /resetLineForm\(\)/.test(dong), dong.slice(0, 300));

  // ── 4. 🔴 CHỈ ĐÓNG KHI MÁY CHỦ NHẬN ─────────────────────────────────────────────────────
  /* Đóng ngay lúc bấm là dòng gõ dở biến mất trước khi biết có lưu được không — máy chủ chối
     thì người ta còn đúng một câu toast đỏ và không còn gì để bấm lại. */
  /* ⚠️ SOI `_saveLine` (RUỘT), không soi `saveLine` (VỎ). Từ 1.269.0 `saveLine()` chỉ còn là
     một lớp try/catch bọc ngoài — mọi cửa chặn và lời gọi máy chủ nằm trong `_saveLine()`. */
  const luu = than(h, '_saveLine');
  const iOk = luu.indexOf('res.success');
  const iDong = luu.indexOf('dongLineForm()');
  t('🔴 ' + b + ': hộp chỉ đóng KHI LƯU ĐƯỢC, không đóng ngay lúc bấm',
    iOk >= 0 && iDong > iOk, luu.slice(Math.max(0, iOk - 40), iOk + 240));

  // ── 5. SỬA MỘT DÒNG CŨNG MỞ HỘP ─────────────────────────────────────────────────────────
  const sua = than(h, 'editLine');
  t('🔴 ' + b + ': bấm ✏️ sửa một dòng thì hộp MỞ RA — không thì bấm xong màn không đổi gì',
    /moLineForm\(\)/.test(sua), sua.slice(0, 300));
});

if (truot.length) {
  console.log('HỎNG: ' + truot.length);
  truot.forEach(x => console.log('  ✗ ' + x));
  console.log('ĐẠT: ' + dat);
  process.exit(1);
}
console.log('ĐẠT: ' + dat + ' phép — form nhập nổi lên đúng lúc, và đơn đã chốt sổ thì không mở được.');
