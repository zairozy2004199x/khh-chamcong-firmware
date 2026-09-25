/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * BÁO CÁO TỔNG — CỘT TỔNG ĐỨNG SAU SỐ GHẾ, VÀ MỌI HÀNG PHẢI ĐỦ Ô
 *
 * Anh Thắng 18/09/2026: *"cột tổng doanh thu đẩy ra trước, cạnh cột số ghế"* (2.109.0). Khoảng
 * xem thường là nửa tháng trở lên nên để Tổng ở cuối là phải cuộn ngang hết bảng mới thấy.
 *
 * 🔴 VÌ SAO ĐÁNG MỘT BÀI KIỂM. Bảng này dựng bằng NỐI CHUỖI ở ba chỗ rời nhau — tiêu đề, thân
 *    bảng, hàng TỔNG ở chân — cộng một dòng chú thích VietQR dùng `colspan` tính tay theo số cột
 *    ngày. Dời một cột là phải sửa đủ bốn chỗ; sót một chỗ thì bảng lệch ô, mà lệch ô ở bảng ba
 *    mươi cột số tiền thì mắt không bắt được: nó vẫn ra một bảng trông bình thường, chỉ là tiền
 *    của ngày này nằm dưới tiêu đề ngày khác.
 *
 * ⚠️ SOI CHÍNH ĐOẠN JS trong tệp PHP rồi gọi thẳng `bctBang()` — không cần trình duyệt.
 *
 * Chạy: node tools/test/kiem-bct-cot-tong.js [vhcp-ghe/includes/class-vhg-trang.php]
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const duong = process.argv[2] || 'vhcp-ghe/includes/class-vhg-trang.php';
if (!fs.existsSync(duong)) {
  console.error('✗ Không thấy tệp nguồn: ' + duong + '\n  Chạy từ gốc kho, hoặc truyền đường dẫn làm tham số.');
  process.exit(2);
}
const src = fs.readFileSync(duong, 'utf8');

/* Gom MỌI khối JS heredoc — hàm cần dùng có thể nằm ở khối nào cũng được. */
let js = '', p = 0;
while (true) {
  const a = src.indexOf("<<<'JS'", p); if (a < 0) break;
  const b = src.indexOf('\nJS;', a); if (b < 0) break;
  js += src.slice(src.indexOf('\n', a) + 1, b) + '\n'; p = b + 3;
}
function lay(ten) {
  const i = js.indexOf('function ' + ten + '(');
  if (i < 0) throw new Error('không thấy hàm ' + ten);
  let d = 0;
  for (let k = js.indexOf('{', i); k < js.length; k++) {
    if (js[k] === '{') d++;
    else if (js[k] === '}' && --d === 0) return js.slice(i, k + 1);
  }
  throw new Error('hàm ' + ten + ' không đóng ngoặc');
}

/* Bệ đỡ tối thiểu: đủ để bctBang() chạy, không dựng DOM thật. */
global.esc   = s => String(s == null ? '' : s).replace(/[&<>"]/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;' }[c]));
global.L     = vi => vi;
global.ktVnd = n => String(n || 0);
global.ktEl  = (t, c, x) => ({ tag:t, cls:c, txt:x, kids:[], innerHTML:'', style:{}, appendChild(e){ this.kids.push(e); } });
eval(lay('bctThu'));
eval(lay('bctVqDung'));   // 2.142.0: bctBang hỏi hàm này để biết có lớp VietQR (dòng "cập nhật lúc" + ↻)
eval(lay('bctBang'));

/* Dữ liệu giả có BẬT lớp VietQR (cot='qr' + vqCo) — đó là ca sinh thêm dòng chú thích colspan,
   tức ca dễ lệch nhất. */
function dungBang(cotGhe) {
  const ngay = ['2026-09-01', '2026-09-02', '2026-09-03'];
  const r = {
    tu:'2026-09-01', den:'2026-09-03', ngay, tong:100, soGhe:9,
    muc: cotGhe ? 'ghe' : 'coso', cot:'qr',
    vqCo:1, vqTong:90, vqTongCot:[10,0,20], tongCot:[10,0,90], vqKhongKhop:5,
    hang: [{ coso:'VHM', maKH:'KH1', tenGhe:'VHM-1', maGhe:'80143', soGhe:3,
             so:[10,0,90], tong:100, vq:[10,0,20], vqTong:90 }]
  };
  const html = bctBang(r).kids[1].kids[0].innerHTML;
  const hang = html.split('</tr>').filter(x => x.includes('<t'));
  return {
    oMoiHang: hang.map(h => { let n = 0; h.replace(/<t[hd][^>]*>/g, m => { const c = /colspan="(\d+)"/.exec(m); n += c ? +c[1] : 1; return m; }); return n; }),
    tieuDe: hang[0]
  };
}

let hong = 0;
function phep(ok, ten, them) {
  console.log((ok ? '  ✓ ' : '  ✗ ') + ten + (ok || !them ? '' : ' → ' + them));
  if (!ok) hong++;
}
[[false, 'gộp theo CƠ SỞ'], [true, 'gộp theo TỪNG GHẾ']].forEach(function (x) {
  const { oMoiHang, tieuDe } = dungBang(x[0]);
  phep(oMoiHang.every(n => n === oMoiHang[0]), x[1] + ': mọi hàng đủ ô như nhau', '[' + oMoiHang.join(', ') + ']');
  const iGhe = tieuDe.indexOf('Số ghế'), iTong = tieuDe.indexOf('Tổng'), iNgay = tieuDe.indexOf('01/09');
  phep(iGhe > 0 && iTong > iGhe && iNgay > iTong, x[1] + ': tiêu đề xếp Số ghế → Tổng → ngày');
});
/* CSV phải xếp y hệt bảng — tệp tải về khác màn hình là kế toán dò nhầm cột. */
const csv = lay('bctXuat');
phep(/\[g\.soGhe, g\.tong\]/.test(csv), 'CSV: cột Tổng đi liền sau Số ghế ở hàng dữ liệu');
phep(/L\('Số ghế','Chairs'\), L\('Tổng','Total'\)/.test(csv), 'CSV: tiêu đề cũng xếp Số ghế → Tổng');

console.log(hong ? '\n🔴 TRƯỢT: ' + hong : '\n✓ SẠCH');
process.exit(hong ? 1 : 0);
