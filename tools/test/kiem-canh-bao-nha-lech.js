/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * NHÀ MỘT ĐẰNG, TẦM NHÌN MỘT NẺO — MÀN CẤU HÌNH PHẢI BÁO NGAY TẠI HÀNG.
 *
 * Cắn thật 09/09/2026, mất gần một buổi. Tài khoản anh Trần Ngọc Quyền có cột "Đơn vị" để trống
 * (tức K&H) còn cột "Xem đơn vị" là POSH. Hai cột đứng cạnh nhau, tên gần giống, mà ĐỂ TRỐNG thì
 * nhìn y như chưa điền gì. Hậu quả rơi xuống một màn khác, vào một ngày khác: nhân viên bấm Tạo
 * đơn, đơn ghi xong rồi biến mất ngay trước mắt.
 *
 * 🔴 CHỈ BÁO, KHÔNG CHẶN. Với kế toán/quản lý thì lệch là chuyện bình thường và cố ý — anh Thắng
 *    26/08: *"Kế toán Posh chỉ thấy chi phí Posh"*, nhà họ vẫn có thể là K&H. Chỉ ai LẬP ĐƠN mới
 *    chết vì lệch. Máy chủ chặn đúng lượt tạo đơn (kiem-loi-mo-don-noi-ro.php); đây là lớp báo
 *    sớm cho người khai.
 *
 * ⚠️ CHẠY THẬT `_dvLech()` / `_dvCanhBao()` bốc từ app.html.
 *
 * Chạy: node tools/test/kiem-canh-bao-nha-lech.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs'), path = require('path');
let DAT = 0; const TRUOT = [];
function t(ten, ok, them) { if (ok) { DAT++; return; }
  TRUOT.push(ten + (them !== undefined ? '\n      → ' + JSON.stringify(them) : '')); }
function teq(ten, mong, thuc) { t(ten + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }

const GOC = path.resolve(__dirname, '..', '..');
const HTML = fs.readFileSync(path.join(GOC, 'wordpress/vhcp-chi-phi/templates/app.html'), 'utf8');

/* ── Bệ đỡ: bốc CHÍNH ba hàm ra chạy ────────────────────────────────────────────────────── */
function boc(ten) {
  const i = HTML.indexOf('function ' + ten + '(');
  t('bốc được ' + ten + '()', i >= 0);
  if (i < 0) { console.log('\n✗ Không bốc được — dừng.'); process.exit(1); }
  const j = HTML.indexOf('\n  }', i);
  return HTML.slice(i, j + 4);
}
const BOOT = { donVi: ['K&H', 'POSH'] };
const esc = s => String(s == null ? '' : s).replace(/[&<>"]/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;' }[c]));
const src = boc('_dvChuan') + boc('_dvLech') + boc('_dvCanhBao')
  + '; return { _dvLech: _dvLech, _dvCanhBao: _dvCanhBao, _dvChuan: _dvChuan };';
const F = new Function('BOOT', 'esc', src)(BOOT, esc);

/* ── 1. 🔴 CA ĐÃ CẮN ─────────────────────────────────────────────────────────────────────── */
const quyen = { ten: 'Trần Ngọc Quyền', donVi: '', xemDonVi: 'POSH' };
t('🔴 nhà trống (=K&H) + xem POSH → LỆCH', F._dvLech(quyen) === true, quyen);
const bao = F._dvCanhBao(quyen);
/* Đọc câu như NGƯỜI DÙNG thấy: bỏ thẻ, trả dấu & về nguyên dạng. Dò thẳng trên chuỗi HTML là
   ghim vào cách escape — mai đổi bộ escape thì bài kiểm đỏ oan, mà ý định thì không đổi. */
const doc = h => String(h).replace(/<[^>]*>/g, '').replace(/&amp;/g, '&').replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&quot;/g, '"');
const van = doc(bao);
t('   có câu cảnh báo',            bao !== '', bao);
t('   nói rõ nhà đang là K&H',     van.indexOf('K&H') >= 0, van);
t('   nói rõ chỉ xem được POSH',   van.indexOf('POSH') >= 0, van);
t('   nói rõ HẬU QUẢ (không mở lại được)', van.indexOf('KHÔNG mở lại được') >= 0, van);

/* 🔴 TÊN ĐƠN VỊ LÀ CHỮ NGƯỜI DÙNG GÕ, PHẢI ESCAPE. Ô "Đơn vị" là ô gõ tay tự do (đơn vị thứ ba
   phải khai được ngay tại đó), nên một cái tên mang thẻ HTML sẽ chạy thẳng trong màn Cấu hình —
   đúng màn có bảng PIN của cả công ty. */
const doc_xau = F._dvCanhBao({ donVi: '<img src=x onerror=alert(1)>', xemDonVi: 'POSH' });
t('🔴 tên đơn vị có thẻ HTML → bị escape, không thành thẻ thật',
  doc_xau.indexOf('<img') < 0 && doc_xau.indexOf('&lt;img') >= 0, doc_xau);
t('   ô "Xem đơn vị" cũng vậy',
  F._dvCanhBao({ donVi: 'POSH', xemDonVi: '<b>x</b>' }).indexOf('<b>x</b>') < 0);

/* ── 2. KHÔNG BÁO BỪA ────────────────────────────────────────────────────────────────────── */
t('nhà POSH + xem POSH → không lệch',  F._dvLech({ donVi: 'POSH', xemDonVi: 'POSH' }) === false);
t('nhà K&H  + xem K&H  → không lệch',  F._dvLech({ donVi: 'K&H',  xemDonVi: 'K&H'  }) === false);
t('nhà trống + xem K&H → không lệch (trống = K&H)', F._dvLech({ donVi: '', xemDonVi: 'K&H' }) === false);
/* 🔴 Ô "Xem đơn vị" TRỐNG = theo mặc định của vai, KHÔNG phải "không xem gì". Báo lệch ở đây là
   240 tài khoản đang chạy đều nổi cảnh báo cùng lúc — cảnh báo nào cũng kêu thì không ai đọc. */
t('🔴 xem đơn vị TRỐNG → KHÔNG báo lệch', F._dvLech({ donVi: 'POSH', xemDonVi: '' }) === false);
teq('   và không sinh câu nào', '', F._dvCanhBao({ donVi: 'POSH', xemDonVi: '' }));

/* Xem NHIỀU đơn vị, có chứa nhà mình → hợp lệ (kế toán nhìn chung cả hai). */
t('xem "K&H, POSH" mà nhà K&H → không lệch', F._dvLech({ donVi: 'K&H', xemDonVi: 'K&H, POSH' }) === false);
t('xem "K&H, POSH" mà nhà là đơn vị thứ ba → lệch', F._dvLech({ donVi: 'HN', xemDonVi: 'K&H, POSH' }) === true);

/* 🔴 MẨU RỖNG DO DẤU PHẨY THỪA KHÔNG ĐƯỢC HOÁ THÀNH NHÀ MẶC ĐỊNH. Ô "Xem đơn vị" trước là ô gõ
   tay, nên dòng cũ dễ mang "POSH," hay "POSH, ". Không rửa mẩu rỗng ấy thì nó qua _dvChuan()
   thành "K&H" — người này bỗng được coi là xem được cả K&H, và cảnh báo tắt đúng lúc cần bật. */
t('🔴 "POSH, " (phẩy thừa) mà nhà K&H → VẪN lệch',
  F._dvLech({ donVi: 'K&H', xemDonVi: 'POSH,  ' }) === true);
t('   "POSH," mà nhà POSH → vẫn không lệch',
  F._dvLech({ donVi: 'POSH', xemDonVi: 'POSH,' }) === false);

/* Hoa/thường và khoảng trắng thừa không được tính là lệch — người khai gõ tay ô "Đơn vị". */
t('🔴 "posh" vs "POSH" → KHÔNG lệch (bỏ qua hoa thường)', F._dvLech({ donVi: 'posh', xemDonVi: 'POSH' }) === false);
t('   " POSH " thừa dấu cách → KHÔNG lệch',               F._dvLech({ donVi: ' POSH ', xemDonVi: 'POSH' }) === false);

/* ── 3. GẮN ĐÚNG CHỖ TRONG BẢNG ──────────────────────────────────────────────────────────── */
t('🔴 hàng lệch được tô nền cho thấy được', HTML.indexOf("_dvLech(u)?' style=\"background:#fff7ed\"'") >= 0);
t('   câu báo nằm NGAY DƯỚI ô Đơn vị',      HTML.indexOf('_dvInp(u.donVi)+_dvCanhBao(u)') >= 0);

/* ═════════════════════════════════════════════════════════════════════════════════════════ */
if (TRUOT.length) {
  console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):');
  TRUOT.forEach(x => console.log('  · ' + x));
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + DAT + ' phép: khai lệch nhà/tầm nhìn là thấy ngay tại hàng, không chờ nhân viên gặp lỗi.');
