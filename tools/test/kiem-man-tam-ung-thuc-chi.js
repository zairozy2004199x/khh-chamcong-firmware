/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * MÀN ĐƠN: TRƯỚC KHI CẤP TIỀN CHỈ BÀY TẠM ỨNG — anh Thắng 06/09/2026
 *
 * *"Trước đã cấp tiền hệ thống phải ghi nhận tiền tạm ứng. Không tính tiền thực chi. (vì nhập
 * chi tiết đơn là các bạn nhập để lưu trữ lên) còn tạm ứng là để kế toán chốt số tạm ứng của
 * tuần đó. Còn khi đã cấp tiền lúc này kế toán mới quan tâm thực chi là bao nhiêu và thừa
 * thiếu bao nhiêu."*
 *
 * Máy chủ đã im lặng (xem `tools/test/kiem-tam-ung-truoc-cap-tien.php`). Bài này canh NỬA CÒN
 * LẠI: hai khối trên màn đơn — "Quyết toán" (`#qtCard`) và "Đối chiếu thừa/thiếu"
 * (`#reconCard`) — cũng phải im theo. Máy chủ trả 0 mà màn vẫn bày ra hai ô "Thực mua" và
 * "Còn lại" thì người đọc vẫn thấy một bảng quyết toán của đơn chưa ai đưa đồng nào; chỉ khác
 * là bây giờ nó toàn số 0, trông còn giống lỗi phần mềm hơn.
 *
 * ⚠️ BỐC ĐIỀU KIỆN THẬT TỪ app.html RA CHẠY, KHÔNG SOI CHUỖI. Dò `"'Đã cấp tạm ứng'" in src`
 *    là canh sự CÓ MẶT của một chuỗi — mà chuỗi ấy còn nằm trong nhánh chết, trong chú thích,
 *    trong một câu thông báo cho người dùng. Bốc ra chạy thì chỉ có ĐIỀU KIỆN DỰNG mới nói.
 *
 * 🔴 DÃY TRẠNG THÁI ĐỌC TỪ `TT_LUONG` BÊN PHP, không gõ tay bảy chuỗi ở đây. Thêm một chặng
 *    vào luồng mà bài kiểm không biết thì cái mới ấy lọt lưới — và chặng mới luôn là chặng dễ
 *    sai nhất.
 *
 * Chạy: node tools/test/kiem-man-tam-ung-thuc-chi.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const HTML = fs.readFileSync('wordpress/vhcp-chi-phi/templates/app.html', 'utf8');
const PHP  = fs.readFileSync('wordpress/vhcp-chi-phi/includes/class-vhcp-don.php', 'utf8');

let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them !== undefined ? (' → ' + JSON.stringify(them)) : '')); } }
function teq(n, mong, thuc) { t(n + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }

/* ── dãy trạng thái: đọc từ hằng PHP ────────────────────────────────────────────────────── */
const mLuong = PHP.match(/const TT_LUONG = array\(([\s\S]*?)\);/);
t('đọc được TT_LUONG từ class-vhcp-don.php', !!mLuong);
const LUONG = (mLuong ? mLuong[1].match(/'([^']+)'/g) : []).map(function (x) { return x.slice(1, -1); });
teq('bảy chặng', 7, LUONG.length);
const MOC = LUONG.indexOf('Đã cấp tạm ứng');
t('🔴 "Đã cấp tạm ứng" có trong dãy', MOC > 0, LUONG);

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 1. KHỐI QUYẾT TOÁN — bốc ĐIỀU KIỆN BÀY ra chạy
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const iSh = HTML.indexOf('    var show=(st===');
t('tìm được dòng dựng điều kiện bày khối Quyết toán', iSh > 0);
const dongShow = iSh > 0 ? HTML.slice(iSh, HTML.indexOf('\n', iSh)) : '';
/* Đối chứng cho chính phép bốc: cắt hụt thì mọi phép dưới đỏ oan, cắt thừa thì xanh oan. */
t('đối chứng: dòng bốc ra khép kín bằng dấu chấm phẩy', /;\s*$/.test(dongShow), dongShow);
t('và nó là một phép gán cho `show`', /^\s*var show=\(/.test(dongShow), dongShow.slice(0, 40));

const bay = new Function('st', dongShow + '\nreturn !!show;');

LUONG.forEach(function (st, i) {
  teq('khối Quyết toán bày khi đơn "' + st + '"', i >= MOC, bay(st));
});
/* Đơn cũ chưa có trạng thái, và trạng thái gõ tay lạ hoắc: đoán về phía KHÔNG BÀY. Bày nhầm là
   dựng cả một bảng quyết toán cho đơn chưa cấp tiền; không bày nhầm thì người ta hỏi ngay. */
teq('trạng thái rỗng: không bày', false, bay(''));
teq('🔴 trạng thái lạ: không bày', false, bay('Đã cấp tiền rồi nhé'));
teq('chuỗi gần giống cũng không ăn may', false, bay('Đã cấp tạm ứng cho NV'));

/* ── và SỐ TẠM ỨNG KHÔNG ĐƯỢC MẤT THEO ──────────────────────────────────────────────────────
   Đây là vế người ta hay quên khi ẩn một khối: anh Thắng cần thấy tạm ứng NGAY TỪ ĐẦU, "để kế
   toán chốt số tạm ứng của tuần đó". Nó ở khối riêng `#tuCard`, và khối ấy KHÔNG được nhận
   cùng cái gác của `#qtCard`. */
t('🔴 có khối tạm ứng riêng', HTML.indexOf('id="tuCard"') > 0);
const gacTU = HTML.match(/el\('tuCard'\)\.style\.display\s*=([^;]*);/g) || [];
gacTU.forEach(function (d) {
  t('🔴 khối tạm ứng KHÔNG bị gác theo "Đã cấp tạm ứng"', d.indexOf('Đã cấp tạm ứng') < 0, d);
});

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 2. KHỐI ĐỐI CHIẾU THỪA/THIẾU — bốc CẢ HÀM ra chạy trên DOM giả
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const iR = HTML.indexOf('  function renderRecon(){');
const jR = HTML.indexOf('\n  }', iR) + 4;
t('bốc được renderRecon()', iR > 0 && jR > iR);
const fnRecon = (iR > 0 && jR > iR) ? HTML.slice(iR, jR) : '';
t('đối chứng: hàm bốc ra khép kín', /\}\s*$/.test(fnRecon), fnRecon.slice(-30));

function chay(don, role) {
  const o = {
    reconCard: { style: { display: 'CHUA-DAT' } },
    reconBox:  { innerHTML: '' },
  };
  const f = new Function('el', 'CUR', 'CURUSER', '_reconTablesHtml',
    fnRecon + '\nrenderRecon();');
  f(function (id) { return o[id] || null; }, don, { role: role },
    function () { return '<table>bảng</table>'; });
  return o;
}

const DON_CHUA = { daCapTien: 0, don: { trangThai: 'Chờ cấp tạm ứng' } };
const DON_ROI  = { daCapTien: 1, don: { trangThai: 'Đã cấp tạm ứng' } };

teq('🔴 chưa cấp tiền: khối đối chiếu ẨN', 'none', chay(DON_CHUA, 'Admin').reconCard.style.display);
teq('🔴 cấp rồi: khối đối chiếu HIỆN',        '',     chay(DON_ROI,  'Admin').reconCard.style.display);
/* Đối chứng cho chính bệ đỡ: nếu hàm không đụng tới `#reconCard` thì giá trị mồi còn nguyên,
   và hai phép trên sẽ đỏ chứ không xanh oan. */
t('đối chứng: bệ đỡ có mồi sẵn giá trị khác hai đáp án', 'CHUA-DAT' !== 'none' && 'CHUA-DAT' !== '');
t('🔴 cấp rồi thì mới vẽ bảng vào trong', chay(DON_ROI, 'Admin').reconBox.innerHTML.length > 0);
teq('chưa cấp thì không vẽ gì', '', chay(DON_CHUA, 'Admin').reconBox.innerHTML);

/* Hai vai kế toán chuyên trách xem phần của mình ở mục 4, không xem khối này — luật cũ, phải
   giữ nguyên sau khi thêm gác mới. */
['Kế toán cá nhân', 'Kế toán NCC'].forEach(function (r) {
  teq('⚠️ ' + r + ': vẫn ẩn kể cả khi đã cấp tiền', 'none', chay(DON_ROI, r).reconCard.style.display);
});

/* 🔴 CỜ ĐỌC TỪ MÁY CHỦ, KHÔNG TỰ SUY LẠI TỪ TÊN TRẠNG THÁI. Suy lại là dựng bản thứ hai cho
   cùng một luật, rồi hai bản lệch nhau — đúng cảnh ảnh 31/08/2026 "2 có số tổng khác nhau". */
t('màn đọc cờ `daCapTien` do máy chủ trả', fnRecon.indexOf('daCapTien') > 0, fnRecon);
t('và máy chủ có trả cờ ấy thật', PHP.indexOf("'daCapTien'") > 0);

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * KẾT
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
if (TRUOT.length) {
  console.log('\n=== MÀN ĐƠN: TẠM ỨNG TRƯỚC / THỰC CHI SAU ===');
  TRUOT.forEach(function (x) { console.log('  ✗ ' + x); });
  console.log('ĐẠT: ' + DAT + '   TRƯỢT: ' + TRUOT.length);
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + DAT + ' phép: màn đơn im lặng về thực chi cho tới khi tiền ra khỏi két.');
