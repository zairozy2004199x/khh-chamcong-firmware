/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * SỐ TIỀN HẠNG MỤC LÚC XIN — MÀN PHẢI LÙI VỀ DỰ TOÁN Y NHƯ MÁY CHỦ.
 * Anh Thắng 23/09/2026: *"Xin tạm ứng lại nó đang để 0. Vì trả về nó lại đi từ ban đầu tiền
 * nhập chứ"* — mười dòng ghi 0 vì màn chỉ đọc `thucTe`, còn máy chủ (`tien_hm_du_kien`) lùi về
 * dự toán. Chạy: node tools/test/kiem-tien-xin-lai.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const HTML = fs.readFileSync('wordpress/vhcp-chi-phi/templates/app.html', 'utf8');
const DUAN = fs.readFileSync('wordpress/vhcp-chi-phi/includes/class-vhcp-duan.php', 'utf8');
let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them !== undefined ? (' → ' + JSON.stringify(them)) : '')); } }
function teq(n, mong, thuc) { t(n + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }
const ham = (n) => { const i = HTML.indexOf('  function ' + n + '('); return i < 0 ? '' : HTML.slice(i, HTML.indexOf('\n  }', i) + 4); };
const F = new Function(ham('_tienXinHm') + '\nreturn _tienXinHm;')();
t('⚠️ bốc được `_tienXinHm`', ham('_tienXinHm').length > 100);
teq('🔴 chỉ có DỰ TOÁN (thực tế 0) → lấy dự toán, không ra 0', 5000000, F({ duToan: 5000000, thucTe: 0 }, []));
teq('🔴 có thực tế → lấy thực tế', 4200000, F({ duToan: 5000000, thucTe: 4200000 }, []));
teq('   không dự toán, không thực tế → thành tiền', 900000, F({ duToan: 0, thucTe: 0, thanhTien: 900000 }, []));
teq('   trống cả → 0', 0, F({}, []));
teq('🔴 mục con có thực tế → cộng con, không cộng cha (đếm hai lần)', 3000000, F({ duToan: 9000000, thucTe: 9000000 }, [{ thucTe: 1000000 }, { thucTe: 2000000 }]));
teq('🔴 mục con chưa có thực tế → lùi về dự toán CHA', 9000000, F({ duToan: 9000000, thucTe: 0 }, [{ thucTe: 0 }, { thucTe: 0 }]));
/* Chỗ vẽ nút xin phải dùng đúng hàm này — không thì hàm đúng mà con số trên màn vẫn 0. */
t('🔴 `veNhom` tính `_tienP` bằng `_tienXinHm`', /var _tienP=_tienXinHm\(p, _kidsP\);/.test(HTML));
/* Hai bên cùng thứ tự ưu tiên: thực tế → dự toán → thành tiền. */
t('⚠️ máy chủ vẫn là thực tế → dự toán → thành tiền (`tien_hm_du_kien`)',
  /\$ke_hoach = \$dt > 0 \? \$dt : \$tht;/.test(DUAN) && /return \$tu_than > 0 \? \$tu_than : \$ke_hoach;/.test(DUAN));
if (TRUOT.length) { console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):'); TRUOT.forEach(function (x) { console.log('  · ' + x); }); process.exit(1); }
console.log('\n✓ SẠCH — ' + DAT + ' phép: số tiền lúc xin lùi về dự toán, màn và máy chủ cùng luật.');
