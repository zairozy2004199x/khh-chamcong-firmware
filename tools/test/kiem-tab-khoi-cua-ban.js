/**
 * TAB KHỐI CỦA CHÍNH BẢN NÀY PHẢI BÀY RA — CHẠY THẬT, KHÔNG SOI CHỮ
 * =============================================================================================
 *
 * 🔴 Anh Thắng 22/09/2026, LẦN THỨ HAI: *"Vẫn mất đơn khi tạo hoặc F5"* — ảnh cho thấy thanh
 *    KHỐI chỉ có "Khu vui chơi" và "Văn phòng", không có tab nào cho Hà Nội.
 *
 * Bản 1.264.0 đã vá đúng bệnh ấy — nhưng chỉ NỬA ĐƯỜNG: chèn mã vùng vào bộ khối ở MÁY CHỦ,
 * rồi quên mất rằng MÀN có một danh sách RIÊNG gõ cứng `[kvc, mtd, vp]`. Máy chủ biết khối
 * 'hn', màn thì không — nên đơn đóng dấu 'hn' vẫn không tab nào bày, y như trước khi vá.
 *
 * ⚠️ VÌ SAO BÀI KIỂM CŨ KHÔNG BẮT ĐƯỢC: nó soi MÁY CHỦ và thấy đủ. Bài kiểm chỉ soi một đầu
 *    của một đường dây thì nó canh được nửa đường dây. Đường này bốn chặng — cột trong sổ · bộ
 *    khối máy chủ · gói khởi động · danh sách bên màn — đứt chặng nào cũng ra đúng một cảnh.
 *
 * ⚠️ NÊN BÀI NÀY CHẠY THẬT mấy hàm dựng thanh khối, trên gói khởi động GIẢ của bản Hà Nội, rồi
 *    đếm tab. Soi chữ xanh suốt cả lượt hỏng ấy: hàm vẫn nằm đó, danh sách vẫn nằm đó.
 *
 * Chạy: node tools/test/kiem-tab-khoi-cua-ban.js
 */
const fs = require('fs'), path = require('path'), vm = require('vm');
const GOC = path.resolve(__dirname, '..', '..');
const BAN = ['vhcp-chi-phi', 'vhcp-chi-phi-hn', 'vhcp-chi-phi-mtd', 'vhcp-chi-phi-vp'];

let dat = 0; const truot = [];
function t(ten, dk, them) {
  if (dk) { dat++; return; }
  truot.push(ten + (them === undefined ? '' : '\n      → ' + String(them).slice(0, 200)));
}
const teq = (ten, mong, thuc) =>
  t(ten + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc);

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
  /* Mã khối của CHÍNH bản này, đọc từ sơ đồ bảng — không đoán theo tên thư mục. */
  const dbf = path.join(GOC, 'wordpress', b, 'includes/class-vhcp-db.php');
  const khoiBan = (fs.readFileSync(dbf, 'utf8').match(/const KHOI = '([^']*)'/) || [])[1] || '';
  t(b + ': đọc được khối của bản', khoiBan !== '', khoiBan);

  /* ⚠️ BỐC CẢ HAI DÒNG KHAI. Từ 1.272.0 `KHOI_DS` khai đủ ba khối ngay tại dòng của nó, còn
     `KHOI_DS_LUI` là BẢN SAO của nó — bốc mỗi dòng sau là `vm` nổ vì thiếu dòng trước. */
  /* ⚠️ TƯỚC CHÚ THÍCH TRƯỚC KHI BỐC. Chú thích ngay trên hai dòng ấy NHẮC LẠI đúng mẫu
     `var KHOI_DS=[{ma:...` để giải thích vì sao phải khai theo thứ tự đó — nên bốc trên
     nguyên tệp là regex tóm phải lời ghi chú rồi ném SyntaxError. Lần thứ năm trong phiên
     22/09 mắc đúng loại lỗi "phép soi khớp phải chú thích của chính mình". */
  const maChay = h.replace(/\/\*[\s\S]*?\*\//g, ' ');
  const lui = ((maChay.match(/var KHOI_DS=\[\{ma:[^;]*;/) || [])[0] || '')
    + '\n' + ((maChay.match(/var KHOI_DS_LUI=KHOI_DS[^;]*;/) || [])[0] || '');
  const mo = (maChay.match(/var KHOI_MO=\[[^;]*;/) || [])[0] || '';
  t(b + ': bốc được hai dòng khai khối và KHOI_MO', /KHOI_DS_LUI/.test(lui) && mo !== '');

  /* Gói khởi động GIẢ, dựng như máy chủ của chính bản ấy sẽ gửi. */
  const khoiDs = [{ ma: khoiBan, ten: khoiBan.toUpperCase() },
                  { ma: 'kvc', ten: 'Khu vui chơi' },
                  { ma: 'mtd', ten: 'Máy tự động' },
                  { ma: 'vp', ten: 'Văn phòng' }]
    .filter((x, i, a) => a.findIndex(y => y.ma === x.ma) === i);

  const ctx = { BOOT: { khoiDs: khoiDs, khoiBan: khoiBan, khoiCoDon: [khoiBan] } };
  ctx.window = ctx;
  vm.createContext(ctx);
  vm.runInContext(lui + '\n' + mo + '\n'
    + than('_napKhoiDs') + than('_khoiMo') + than('_khoiLuuTru') + than('_khoiBay') + than('_khoiDuoc'), ctx);

  /* 🔴 `boot()` PHẢI GỌI `_napKhoiDs()`. Bài này tự gọi nó để đo hành vi, nên nếu `boot()`
     quên gọi thì mọi phép dưới vẫn xanh trong khi trên màn thật danh sách chẳng bao giờ được
     nạp. Đây là chỗ duy nhất phải soi chữ — không dựng nổi cả vòng đời trang trong `vm`. */
  t('🔴 ' + b + ': `boot()` GỌI `_napKhoiDs()` ngay khi nhận gói khởi động',
    /BOOT=b\|\|BOOT; _napKhoiDs\(\);/.test(h), '');

  ctx._napKhoiDs();
  const bay = ctx._khoiBay().map(x => x.ma);
  t('🔴 ' + b + ': thanh KHỐI CÓ tab cho khối của chính bản này ("' + khoiBan + '")',
    bay.indexOf(khoiBan) >= 0, bay.join(', '));
  /* Khối của bản phải NHẬN VIỆC MỚI. Thiếu là đơn vừa lập xong đã nằm ở một tab "lưu trữ" —
     vẫn thấy, nhưng người ta không hiểu vì sao nó ở đó. */
  t('🔴 ' + b + ': và khối ấy NHẬN VIỆC MỚI, không rơi vào nhóm lưu trữ',
    ctx._khoiMo().map(x => x.ma).indexOf(khoiBan) >= 0, ctx._khoiMo().map(x => x.ma).join(', '));
  t(b + ': tên khối đọc được, không trơ mã',
    (ctx._khoiBay().filter(x => x.ma === khoiBan)[0] || {}).ten !== undefined);

  /* 🔴 CA THEN CHỐT, và là ca bài cũ bỏ sót: máy chủ báo một khối KHÔNG CÓ trong đường lui
     gõ cứng. Đó đúng là cảnh của mọi bản vùng mã mới (hn, dn, hcm…). Màn mà vẫn bám đường lui
     thì tab ấy không bao giờ hiện, và đơn của nó biến mất.
     ⚠️ Dựng ca này bằng một mã BỊA (`zz`), không dùng mã của chính bản — với bản gốc thì
        `khoiBan` là 'kvc', vốn đã nằm sẵn trong đường lui, nên đục hỏng mà phép vẫn xanh. */
  const c3 = { BOOT: { khoiDs: [{ ma: 'zz', ten: 'Vùng ZZ' }, { ma: 'kvc', ten: 'Khu vui chơi' }],
                       khoiBan: 'zz', khoiCoDon: ['zz'] } };
  c3.window = c3;
  vm.createContext(c3);
  vm.runInContext(lui + '\n' + mo + '\n'
    + than('_napKhoiDs') + than('_khoiMo') + than('_khoiLuuTru') + than('_khoiBay') + than('_khoiDuoc'), c3);
  c3._napKhoiDs();
  t('🔴 ' + b + ': khối máy chủ báo mà đường lui KHÔNG có vẫn PHẢI bày ra tab',
    c3._khoiBay().map(x => x.ma).indexOf('zz') >= 0, c3._khoiBay().map(x => x.ma).join(', '));
  t('   và nó nhận việc mới', c3._khoiMo().map(x => x.ma).indexOf('zz') >= 0,
    c3._khoiMo().map(x => x.ma).join(', '));

  /* 🔴 ĐƯỜNG LUI: gói khởi động của bản CŨ không có `khoiDs`. Trả mảng rỗng lúc ấy là thanh
     KHỐI trắng trơn — không vào được đâu cả, tệ hơn hẳn việc thiếu một tab. */
  const c2 = { BOOT: { khoiCoDon: [] } };
  c2.window = c2;
  vm.createContext(c2);
  vm.runInContext(lui + '\n' + mo + '\n'
    + than('_napKhoiDs') + than('_khoiMo') + than('_khoiLuuTru') + than('_khoiBay') + than('_khoiDuoc'), c2);
  c2._napKhoiDs();
  t('🔴 ' + b + ': gói khởi động bản CŨ (thiếu `khoiDs`) -> vẫn còn ba khối cũ, không trắng thanh',
    c2._khoiBay().length > 0, c2._khoiBay().map(x => x.ma).join(', '));
});

if (truot.length) {
  console.log('HỎNG: ' + truot.length);
  truot.forEach(x => console.log('  ✗ ' + x));
  console.log('ĐẠT: ' + dat);
  process.exit(1);
}
console.log('ĐẠT: ' + dat + ' phép — bản nào cũng bày tab cho khối của chính nó, và bản cũ không trắng thanh.');
