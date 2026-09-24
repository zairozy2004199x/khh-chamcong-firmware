/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * KHỐI "ĐÃ CẤP" — DƯ / THIẾU SO VỚI THỰC TẾ (`tienNay`), KHÔNG SO VỚI SỐ XIN.
 * Anh Thắng 24/09/2026: *"Số dư là số còn lại nếu có chênh lệch thực tế thì lấy cột thực tế chứ
 * không lấy tạm ứng nữa"*. Ảnh: lệnh 53.810.000đ, đưa 30tr + 40tr, bảng báo dư 16.190.000đ trong
 * khi thực tế 11 hạng mục là 54.210.000đ → phải là 15.790.000đ.
 * 🔴 CHẠY THẬT `_dotBlock`. Bệ như kiem-dot-trong-lenh.js. Phần máy chủ: kiem-so-du-theo-thuc-te.php.
 * Chạy: node tools/test/kiem-so-du-theo-thuc-te-man.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const HTML = fs.readFileSync('wordpress/vhcp-chi-phi/templates/app.html', 'utf8');
let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them !== undefined ? (' → ' + JSON.stringify(them)) : '')); } }
const ham = (n) => { const i = HTML.indexOf('  function ' + n + '('); return i < 0 ? '' : HTML.slice(i, HTML.indexOf('\n  }', i) + 4); };
t('⚠️ bốc được `_dotBlock`', ham('_dotBlock').length > 500);
const money = (n) => String(Number(n) || 0).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
const F = new Function('esc', 'money', '_dmy', 'DA_CUR',
  ham('_daCapTong') + ham('_conPhaiCap') + ham('_daTenHang') + ham('_daCapTheoLan') + ham('_dotBlock') + '\nreturn _dotBlock;')(
  (x) => String(x == null ? '' : x), money, (v) => String(v), { lines: [] });
const CAP = [{ lan: 1, ngay: '23/09/2026 10:47', soTien: 30000000, nguoi: 'Admin' }, { lan: 2, ngay: '23/09/2026 10:47', soTien: 40000000, nguoi: 'Admin' }];

/* ── 1. 🔴 Đúng cảnh trong ảnh ─────────────────────────────────────────────────────────── */
{
  const h = F({ soTien: 53810000, tienNay: 54210000, daCap: CAP, lich: [] });
  t('🔴 dư = 70tr − thực tế 54,21tr = 15.790.000đ', /dư <b>15\.790\.000đ<\/b>/.test(h), h);
  t('🔴 KHÔNG còn 16.190.000đ (so với số xin)', h.indexOf('16.190.000') < 0, h);
  t('🔴 nói rõ so với thực tế nào, lệnh xin bao nhiêu', /theo thực tế <b>54\.210\.000đ<\/b>, lệnh xin 53\.810\.000đ/.test(h), h);
  t('   vẫn "đủ" + "NV hoàn lúc quyết toán"', /<b>đủ<\/b>/.test(h) && /NV hoàn lúc quyết toán/.test(h), h);
  t('   tổng đã đưa 70tr', /tổng đã đưa <b>70\.000\.000đ<\/b>/.test(h), h);
}
/* ── 2. Chưa có thực tế (tienNay thiếu / 0) → so với số xin như cũ, không in dòng "theo" ─── */
{
  const h1 = F({ soTien: 53810000, daCap: CAP, lich: [] });
  t('🔴 không có `tienNay` → dư 16.190.000đ như cũ', /dư <b>16\.190\.000đ<\/b>/.test(h1), h1);
  t('   và KHÔNG in "(theo thực tế …)"', h1.indexOf('theo thực tế') < 0, h1);
  const h0 = F({ soTien: 53810000, tienNay: 0, daCap: CAP, lich: [] });
  t('   `tienNay` = 0 hiểu là "không biết" → cũng 16.190.000đ', /dư <b>16\.190\.000đ<\/b>/.test(h0), h0);
  const hb = F({ soTien: 53810000, tienNay: 53810000, daCap: CAP, lich: [] });
  t('   `tienNay` = số xin → không in "(theo thực tế …)" thừa', /dư <b>16\.190\.000đ<\/b>/.test(hb) && hb.indexOf('theo thực tế') < 0, hb);
}
/* ── 3. 🔴 Thực tế VƯỢT tiền đã đưa → "thiếu", không phải "dư" ─────────────────────────── */
{
  const h = F({ soTien: 53810000, tienNay: 75000000, daCap: CAP, lich: [] });
  t('🔴 thực tế 75tr > đưa 70tr → thiếu 5.000.000đ', /thiếu <b>5\.000\.000đ<\/b>/.test(h), h);
  t('   không nói "dư"', !/dư <b>/.test(h), h);
  t('   nói NV được thanh toán thêm lúc quyết toán', /thanh toán thêm lúc quyết toán/.test(h), h);
  t('   vẫn "đủ" theo lệnh (kế toán đã đưa trọn lệnh)', /<b>đủ<\/b>/.test(h), h);
}
/* ── 4. Chưa đưa đủ LỆNH → vẫn "còn phải đưa" theo số lệnh, không bàn dư/thiếu ───────── */
{
  const h = F({ soTien: 53810000, tienNay: 54210000, daCap: [CAP[0]], lich: [] });
  t('🔴 đưa 30tr < lệnh 53,81tr → còn phải đưa 23.810.000đ (theo LỆNH, không theo thực tế)', /còn phải đưa <b>23\.810\.000đ<\/b>/.test(h), h);
  t('   không in dư / thiếu / theo thực tế ở đây', !/dư <b>|thiếu <b>|theo thực tế/.test(h), h);
}
/* ── 5. Đưa vừa đúng thực tế → "đủ" gọn, không dư không thiếu ───────────────────────────── */
{
  const h = F({ soTien: 53810000, tienNay: 70000000, daCap: CAP, lich: [] });
  t('   đưa 70tr, thực tế 70tr → chỉ "đủ"', /<b>đủ<\/b><\/div>/.test(h) && !/dư <b>|thiếu <b>/.test(h), h);
}
/* ── 6. Chỗ nối: máy chủ gửi `tienNay` ở hai màn ───────────────────────────────────────── */
const PHP = fs.readFileSync('wordpress/vhcp-chi-phi/includes/class-vhcp-duan.php', 'utf8');
t('🔴 máy chủ có `tien_lenh_nay` cộng `tien_hm_du_kien` từng hàng', /function tien_lenh_nay\(/.test(PHP) && /tien_hm_du_kien\( \$ma_da, \(int\) \$rw \)/.test(PHP));
t('   trang dự án gửi `tienNay`', /\$d\['tienNay'\] = self::tien_lenh_nay\( \$ma_da, \$d \);/.test(PHP));
t('   màn Duyệt gửi `tienNay`', /'tienNay'\s*=> self::tien_lenh_nay\( \$ma_da, \$d \),/.test(PHP));

if (TRUOT.length) { console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):'); TRUOT.forEach(function (x) { console.log('  · ' + x); }); process.exit(1); }
console.log('\n✓ SẠCH — ' + DAT + ' phép: dư/thiếu so với thực tế khi có, so với số xin khi chưa; còn phải đưa vẫn theo lệnh.');
