/**
 * TRANG TỔNG QUAN — GOM CHI PHÍ THEO ĐẦU MỤC
 * =============================================================================================
 *
 * Anh Thắng 22/09/2026: *"Trang tổng quan: Phân loại theo đầu mục"*.
 *
 * Bảng cũ liệt kê từng LOẠI chi phí chi tiết — bảy dòng cho một đơn bốn nghìn; với danh mục
 * thật (vài chục loại) nó thành một danh sách phải dò, không phải một bức tranh để nhìn.
 *
 * 🔴 PHÉP QUAN TRỌNG NHẤT Ở ĐÂY LÀ "GOM KHÔNG ĐƯỢC LÀM LỆCH TỔNG". Một bảng tiền mà cộng ra
 *    con số khác bảng gốc là thứ hỏng nguy nhất: nó trông vẫn bình thường, vẫn có hàng có cột,
 *    và người đọc tin nó. Sai một dòng cộng thì mọi quyết định đọc từ bảng ấy đều lệch.
 *
 * ⚠️ CHẠY THẬT hàm gom, không soi chữ.
 *
 * Chạy: node tools/test/kiem-tongquan-theo-dau-muc.js
 */
const fs = require('fs'), path = require('path'), vm = require('vm');
const GOC = path.resolve(__dirname, '..', '..');
const BAN = ['vhcp-chi-phi', 'vhcp-chi-phi-hn', 'vhcp-chi-phi-mtd', 'vhcp-chi-phi-vp'];

let dat = 0; const truot = [];
function t(ten, dk, them) {
  if (dk) { dat++; return; }
  truot.push(ten + (them === undefined ? '' : '\n      → ' + JSON.stringify(them)));
}
const teq = (ten, mong, thuc) =>
  t(ten + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc);

/* Đúng bảng trong ảnh anh Thắng gửi, cộng một loại CHƯA khai đầu mục. */
const BY = [
  { nhom: 'Chi Phí Nuôi Thú', xin: 2, thucTe: 2, cl: 0 },
  { nhom: 'Chi Phí Chung Văn Phòng', xin: 1, thucTe: 1, cl: 0 },
  { nhom: 'Chi Phí NVL Đồ Ăn - Mua lẻ', xin: 1, thucTe: 1, cl: 0 },
  { nhom: 'Chi phí cơ sở', xin: 1, thucTe: 0, cl: 1 },
  { nhom: 'Chi Phí Kỹ Thuật', xin: 1, thucTe: 0, cl: 1 },   // chưa khai đầu mục
];
const DANH_MUC = [
  { ten: 'Chi Phí Nuôi Thú', dauMuc: 'Chi phí cơ sở' },
  { ten: 'Chi Phí Chung Văn Phòng', dauMuc: 'Chi phí chung' },
  { ten: 'Chi Phí NVL Đồ Ăn - Mua lẻ', dauMuc: 'Chi phí cơ sở' },
  { ten: 'Chi phí cơ sở', dauMuc: 'Chi phí cơ sở' },
  { ten: 'Chi Phí Kỹ Thuật', dauMuc: '' },
];

BAN.forEach(function (b) {
  const f = path.join(GOC, 'wordpress', b, 'templates/app.html');
  if (!fs.existsSync(f)) { t('có ' + b + '/app.html', false); return; }
  const h = fs.readFileSync(f, 'utf8');
  function than(ten) {
    const i = h.indexOf('  function ' + ten + '(');
    if (i < 0) return '';
    let j = h.indexOf('{', i), d = 0;
    for (let k = j; k < h.length; k++) {
      if (h[k] === '{') d++;
      else if (h[k] === '}') { d--; if (!d) return h.slice(i, k + 1); }
    }
    return '';
  }
  const ctx = { BOOT: { dauMucDs: ['Chi phí chung', 'Chi phí cơ sở', 'Chi phí tiền thuê', 'Khác'],
                        loaiChiPhi: DANH_MUC } };
  vm.createContext(ctx);
  vm.runInContext(than('_dauMucCuaTen') + than('_gomTheoDauMuc') + than('_tqHMDs'), ctx);
  const g = ctx._gomTheoDauMuc(BY);

  // ── 1. 🔴 GOM KHÔNG ĐƯỢC LÀM LỆCH TỔNG ──────────────────────────────────────────────────
  ['xin', 'thucTe', 'cl'].forEach(function (c) {
    teq('🔴 ' + b + ': gom xong tổng `' + c + '` KHÔNG đổi — lệch tổng là bảng tiền nói dối',
      BY.reduce((n, x) => n + x[c], 0), g.reduce((n, x) => n + x[c], 0));
  });

  // ── 2. GOM ĐÚNG NHÓM ────────────────────────────────────────────────────────────────────
  const tim = d => (g.filter(x => x.nhom === d)[0] || null);
  t('🔴 ' + b + ': ba loại thuộc "Chi phí cơ sở" gộp làm MỘT dòng',
    tim('Chi phí cơ sở') && tim('Chi phí cơ sở').xin === 4, g);
  t('   và "Chi phí chung" đứng riêng', tim('Chi phí chung') && tim('Chi phí chung').xin === 1, g);
  /* 🔴 Loại CHƯA khai đầu mục không được biến mất: tiền của nó là tiền thật. */
  t('🔴 ' + b + ': loại chưa khai đầu mục dồn vào ô hứng, KHÔNG mất',
    tim('Chưa xếp đầu mục') && tim('Chưa xếp đầu mục').xin === 1, g);
  t('   và ô hứng đứng CHÓT bảng', g[g.length - 1].nhom === 'Chưa xếp đầu mục', g.map(x => x.nhom));
  /* Thứ tự theo danh sách đầu mục máy chủ khai — cùng nếp ô chọn lúc nhập đơn. */
  teq('🔴 ' + b + ': xếp theo THỨ TỰ máy chủ khai, không theo thứ tự gặp',
    ['Chi phí chung', 'Chi phí cơ sở', 'Chưa xếp đầu mục'], g.map(x => x.nhom));
  t('   gom xong ÍT dòng hơn — đó mới là điểm của việc gom', g.length < BY.length, g.length);

  // ── 3. ĐỔI CHẾ ĐỘ THÌ RA DỮ LIỆU KHÁC, KHÔNG PHẢI GỌI LẠI MÁY CHỦ ───────────────────────
  ctx.TQ_HM_GOM = 'loai';
  teq(b + ': chế độ "theo loại" trả về NGUYÊN danh sách gốc', BY.length, ctx._tqHMDs({ byHangMuc: BY }).length);
  ctx.TQ_HM_GOM = 'dauMuc';
  teq('🔴 ' + b + ': chế độ "theo đầu mục" trả về bản đã gom', g.length, ctx._tqHMDs({ byHangMuc: BY }).length);

  // ── 4. 🔴 BIỂU ĐỒ TRÒN ĐI THEO CÙNG CHẾ ĐỘ VỚI BẢNG ────────────────────────────────────
  /* Để nó bám `byHangMuc` thô là vòng tròn nói một chuyện, bảng ngay dưới nói chuyện khác —
     và người đọc tin cái nào cũng sai. */
  const ve = than('renderTQView');
  t('🔴 ' + b + ': biểu đồ tròn dựng từ CÙNG hàm với bảng',
    /var pit=_tqHMDs\(r\)/.test(ve), (ve.match(/var pit=[^;]*/) || [''])[0]);
  t('   và bảng cũng vậy', /el\('tqHMBody'\)\.innerHTML=hmDs\.map/.test(ve), '');

  // ── 5. CÓ NÚT ĐỔI, VÀ MẶC ĐỊNH LÀ ĐẦU MỤC ──────────────────────────────────────────────
  t(b + ': có nút đổi hai chế độ', /onclick="tqHMDoi\('dauMuc'\)"/.test(h) && /onclick="tqHMDoi\('loai'\)"/.test(h));
  t('🔴 ' + b + ': MẶC ĐỊNH gom theo đầu mục — đúng cái anh Thắng hỏi',
    /var TQ_HM_GOM='dauMuc'/.test(h), '');
  /* Đổi cách NHÌN không phải đổi DỮ LIỆU — đừng gọi lại máy chủ cho một việc thuần trình bày. */
  const doi = than('tqHMDoi');
  t('🔴 ' + b + ': đổi chế độ thì vẽ lại từ dữ liệu ĐÃ CÓ, không gọi lại máy chủ',
    /renderTQView\(TQ_R\)/.test(doi) && !/google\.script\.run/.test(doi), doi.slice(0, 200));
});

if (truot.length) {
  console.log('HỎNG: ' + truot.length);
  truot.forEach(x => console.log('  ✗ ' + x));
  console.log('ĐẠT: ' + dat);
  process.exit(1);
}
console.log('ĐẠT: ' + dat + ' phép — tổng quan gom theo đầu mục, và gom không làm lệch tổng.');
