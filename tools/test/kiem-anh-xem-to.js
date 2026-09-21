/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * RÊ CHUỘT XEM TO ẢNH: KHÔNG ĐƯỢC BỊ KHUNG BẢNG CẮT
 *
 * Anh Thắng 21/09/2026, ảnh màn Duyệt báo cáo SENSE CITY PHẠM VĂN ĐỒNG: rê vào ảnh của ghế
 * SC-PVD-2 thì ảnh to ra rồi ĐỨT NGANG ở mép dưới khung bảng — chỉ còn nhìn được một dải trên
 * cùng. Số trên đồng hồ nằm giữa tấm ảnh, nên thấy mỗi dải ấy là soát không được gì.
 *
 * 🔴 GỐC. Bảng ghế nằm trong `.table-scroll`. Khai `overflow-x:auto` thì trình duyệt TỰ nâng
 *    `overflow-y` từ `visible` lên `auto` — luật CSS, không phải chỗ ấy khai thiếu. Tức khung
 *    đó cắt cả hai chiều. Cách phóng to cũ (`transform:scale(6)` trên chính thẻ <img> nằm
 *    trong bảng) mãi mãi là con của khung cắt ấy: phóng bao nhiêu cũng chỉ thấy phần lọt trong
 *    khung, và dòng cuối bảng thì gần như không thấy gì.
 *
 * ⚠️ KHÔNG chữa được bằng z-index — z-index xếp thứ tự chồng lớp, không gỡ được việc bị cắt.
 *    Đường duy nhất: ảnh xem phải nằm NGOÀI khung ấy (thẻ nổi gắn vào <body>, position:fixed).
 *
 * ⚠️ BÀI NÀY CHẠY THẬT HÀM ĐẶT TOẠ ĐỘ, không chỉ dò chữ. "Không bị cắt" là một phát biểu về
 *    CON SỐ toạ độ — thẻ nổi phải nằm trọn trong màn ở mọi vị trí thumbnail, kể cả sát mép
 *    phải và sát đáy (đúng hai chỗ hỏng trong ảnh anh Thắng gửi). Dò chữ không nói được điều đó.
 *
 * Chạy: node tools/test/kiem-anh-xem-to.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const NGUON = 'vhcp-ghe/includes/class-vhg-trang.php';
if (!fs.existsSync(NGUON)) { console.error('✗ Không thấy ' + NGUON + ' — chạy từ gốc kho.'); process.exit(2); }
const src = fs.readFileSync(NGUON, 'utf8');

let hong = 0;
function t(ten, ok, them) {
  console.log((ok ? '  ✓ ' : '  ✗ ') + ten + (ok || them === undefined ? '' : ' → ' + JSON.stringify(them)));
  if (!ok) hong++;
}
function than(ten) {
  const i = src.indexOf('function ' + ten + '(');
  if (i < 0) return '';
  let d = 0;
  for (let k = src.indexOf('{', i); k < src.length; k++) {
    if (src[k] === '{') d++;
    else if (src[k] === '}' && --d === 0) return src.slice(i, k + 1);
  }
  return '';
}
/* Luật CSS của một bộ chọn (gộp mọi khối khai cho nó). */
function luat(bo) {
  const re = new RegExp(bo.replace(/[-#.]/g, '\\$&') + '\\s*(?:[,>:][^{]*)?\\{([^}]*)\\}', 'g');
  let ra = '', m;
  while ((m = re.exec(src)) !== null) { ra += m[1] + ';'; }
  return ra;
}

console.log('── Cách phóng to cũ (bị khung cắt) đã bỏ hẳn ────────────────');
const l_zoom = luat('.kt-anh-zoom');
t('lớp .kt-anh-zoom vẫn còn (thumbnail trong bảng dùng nó)', '' !== l_zoom);
t('🔴 KHÔNG còn phóng to tại chỗ bằng transform:scale — đó chính là thứ bị cắt',
  !/transform\s*:\s*scale/.test(l_zoom), l_zoom);

console.log('── Thẻ nổi: fixed, gắn <body>, không ăn chuột ───────────────');
const l_xem = luat('#kt-anh-xem');
t('🔴 #kt-anh-xem là position:fixed (thoát mọi khung overflow của tổ tiên)',
  /position\s*:\s*fixed/.test(l_xem), l_xem);
t('🔴 pointer-events:none — lớp nổi hiện cạnh con trỏ, ăn chuột là nhấp nháy',
  /pointer-events\s*:\s*none/.test(l_xem), l_xem);
t('nằm trên mọi thứ (z-index ≥ 1000)',
  (function () { const m = /z-index\s*:\s*(\d+)/.exec(l_xem); return !!m && Number(m[1]) >= 1000; })(), l_xem);
const hop = than('ktXemHop');
t('🔴 gắn thẳng vào document.body, KHÔNG vào trong bảng',
  /document\.body\.appendChild\(\s*ktXemEl\s*\)/.test(hop), hop.slice(0, 80));
t('cuộn thì tắt, và nghe ở GIAI ĐOẠN BẮT (cuộn trong khung bảng không nổi bọt lên)',
  /addEventListener\(\s*'scroll'\s*,\s*ktXemTat\s*,\s*true\s*\)/.test(hop));
t('một thẻ nổi dùng chung, không dựng lại mỗi lần rê', /if\(ktXemEl\) return ktXemEl/.test(hop));

console.log('── Ảnh xem phải nét hơn thumbnail ───────────────────────────');
const gan = than('ktAnhGan');
t('🔴 lấy bản TO (w1200), không phóng lại chính thumbnail w200 cho nhoè',
  /ktAnhSrc\(u,\s*1200\)/.test(gan) && /sz=w'\+\(w\|\|200\)/.test(src));
t('rời chuột thì tắt', /'mouseleave'\s*,\s*ktXemTat/.test(gan));
t('chờ ảnh tải xong mới hiện, khỏi loé một ô trống',
  /complete&&ktXemImg\.naturalWidth/.test(gan));

console.log('── Cả HAI chỗ có thumbnail đều gắn (bảng + ô sửa) ───────────');
t('bảng Duyệt báo cáo', /a\.appendChild\(img\); ktAnhGan\(a,u\)/.test(src));
t('ô sửa của kế toán (2.122.0)', /ktAnhGan\(im,u\)/.test(src));

/* ─────────────────────────────────────────────────────────────────────────────────────────────
 * CHẠY THẬT: thẻ nổi phải nằm TRỌN trong màn ở mọi vị trí thumbnail
 * ──────────────────────────────────────────────────────────────────────────────────────────── */
console.log('── Chạy thật hàm đặt toạ độ ─────────────────────────────────');
const W = 1280, H = 720, BW = 488, BH = 368, LE = 10;   // le = lề tối thiểu khai trong ktXemDat
function neoTai(left, top) {
  return { getBoundingClientRect: () => ({ left, top, right: left + 44, bottom: top + 44, width: 44, height: 44 }) };
}
/* Dựng môi trường giả rồi gọi thẳng ktXemDat, đọc lại toạ độ nó đặt lên thẻ nổi.
   Thẻ nổi là thẻ document.createElement ĐẦU TIÊN (ktXemHop dựng khung trước, ảnh sau). */
function chay(left, top) {
  const nhat = [];
  function theMoi() {
    const e = {
      style: {}, id: '', _src: '', complete: true, naturalWidth: 900,
      appendChild() {}, addEventListener() {},
      setAttribute(k, v) { if (k === 'src') this._src = v; },
      getAttribute(k) { return k === 'src' ? this._src : null; },
      getBoundingClientRect() { return { left: 0, top: 0, right: BW, bottom: BH, width: BW, height: BH }; },
    };
    nhat.push(e); return e;
  }
  const document = { createElement: theMoi, body: { appendChild() {} } };
  const window = { innerWidth: W, innerHeight: H, addEventListener() {} };
  const ma = 'var ktXemEl=null,ktXemImg=null,ktXemNeo=null;\n'
    + than('ktXemHop') + '\n' + than('ktXemTat') + '\n' + than('ktXemDat') + '\nreturn ktXemDat;';
  const f = new Function('window', 'document', ma)(window, document);
  f(neoTai(left, top));
  const box = nhat[0];
  return { x: parseFloat(box.style.left), y: parseFloat(box.style.top) };
}
function trong(p) { return p.x >= LE && p.y >= LE && p.x + BW <= W - LE && p.y + BH <= H - LE; }
let p;
try {
  p = chay(60, 120);
  t('chỗ rộng rãi: hiện bên PHẢI thumbnail', Math.round(p.x) === 60 + 44 + LE, p);
  t('  …và nằm trọn trong màn', trong(p), p);

  p = chay(1200, 120);
  t('🔴 thumbnail sát mép PHẢI: lật sang trái, không tràn ra ngoài màn', trong(p), p);

  p = chay(60, 660);
  t('🔴 thumbnail sát ĐÁY (đúng ca hỏng trong ảnh anh Thắng): không đứt dưới đáy màn',
    trong(p) && Math.round(p.y) === H - LE - BH, p);

  p = chay(60, 0);
  t('🔴 thumbnail sát ĐỈNH: không đứt trên đỉnh màn', trong(p), p);

  p = chay(1240, 690);
  t('🔴 góc dưới-phải (ô xấu nhất): vẫn trọn trong màn', trong(p), p);
} catch (e) {
  t('🔴 chạy thật được ktXemDat', false, String(e.message));
}

console.log(hong ? '\n🔴 TRƯỢT: ' + hong : '\n✓ SẠCH');
process.exit(hong ? 1 : 0);
