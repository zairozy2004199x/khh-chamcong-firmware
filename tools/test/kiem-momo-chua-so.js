/**
 * "CHƯA CÓ SỔ" KHÔNG PHẢI "LỆCH TIỀN".
 *
 * Ảnh anh Thắng gửi 17/09/2026, kỳ 01→16/09: bảng Đối soát MoMo báo lệch 98.160.000đ. Bốn cơ sở
 * trong đó có cột "MoMo theo sao kê" bằng 0 và cột "Ngày thiếu file" cũng bằng 0 — tức bảng
 * khẳng định nó có đủ file mà MoMo vẫn không trả tiền. Sự thật: K&H có HAI pháp nhân MoMo, sổ
 * của pháp nhân kia chưa nạp. Ghép theo mã giao dịch thì 103/103 lượt của VR Fun Aeon Tân An
 * đều có trong sổ ấy, lệch 0đ.
 *
 * 🔴 CHỖ HỎNG: `co[x.ngay]` hỏi "sổ có NGÀY này không" — một cờ CHUNG cho cả hệ, không theo cơ
 *    sở. Sổ pháp nhân A phủ đủ ngày nên mọi ngày đều tính là "có sổ", kể cả với cơ sở của pháp
 *    nhân B mà sổ ấy không hề nhắc tới.
 *
 * Cái giá: kế toán đọc "lệch 98 triệu" rồi đi tìm gần trăm triệu không hề thất lạc. Và lần sau
 * sẽ không tin bảng này nữa, kể cả lúc nó báo đúng — đó mới là mất mát thật.
 *
 * Bài này BỐC HÀM THẬT từ doanh-thu.js chứ không chép lại, y như kiem-tong-nhom-du-an.js.
 *
 * Chạy: node tools/test/kiem-momo-chua-so.js
 */
'use strict';
const fs = require('fs');
const path = require('path');

const TEP = path.join(__dirname, '..', '..', 'wordpress', 'khh-doanh-thu', 'assets', 'doanh-thu.js');
const src = fs.readFileSync(TEP, 'utf8');

/* Bốc nguyên hàm veMomo: từ "function veMomo(" tới dòng "  }" đầu tiên ở cột 2. */
const i = src.indexOf('function veMomo(');
if (i < 0) { console.error('KHÔNG tìm thấy veMomo trong doanh-thu.js — đổi tên hàm thì sửa luôn bài này.'); process.exit(1); }
const j = src.indexOf('\n  }\n', i);
if (j < 0) { console.error('KHÔNG tìm được đuôi hàm veMomo.'); process.exit(1); }
const thanHam = src.slice(i, j + 4);

/* Mấy hàm phụ veMomo gọi tới — bản tối giản, chỉ đủ để chạy. */
const S = { dsR: null };
const esc = (x) => String(x).replace(/&/g, '&amp;').replace(/</g, '&lt;');
const tien = (n) => String(Math.round(n));
const nguyen = (n) => String(Math.round(n));
const ngayVN = (n) => n;
const NUT_NAP_MOMO_SK = '<button data-mo-nap="momo_sk">nạp</button>';
const veMomo = new Function(
  'S', 'esc', 'tien', 'nguyen', 'ngayVN', 'NUT_NAP_MOMO_SK',
  thanHam + '\nreturn veMomo;'
)(S, esc, tien, nguyen, ngayVN, NUT_NAP_MOMO_SK);

let dat = 0; const hong = [];
function phep(ten, dung) { if (dung) dat++; else hong.push(ten); }

/* Dựng đúng hình dạng số liệu trong ảnh: sổ pháp nhân A phủ mọi NGÀY, nhưng chỉ phủ 2 cơ sở. */
const NGAY = ['2026-09-10', '2026-09-11'];
function dung() {
  const ds = [], sk = {};
  NGAY.forEach((n) => {
    /* Cơ sở CÓ trong sổ, khớp đúng */
    ds.push({ ngay: n, cua_hang: 'Quán Có Sổ', pos_momo: 1000000 });
    sk[n + '|Quán Có Sổ'] = 1000000;
    /* Cơ sở CÓ trong sổ nhưng lệch thật — phải VẪN kêu */
    ds.push({ ngay: n, cua_hang: 'Quán Lệch Thật', pos_momo: 500000 });
    sk[n + '|Quán Lệch Thật'] = 400000;
    /* 🔴 CA PHÂN ĐỊNH HAI LUẬT — cơ sở CÓ trong sổ nhưng tiền sổ bằng 0 (MoMo huỷ sạch trong
       kỳ). Đây là LỆCH THẬT: máy ghi có thu mà MoMo không trả đồng nào, đúng thứ bảng này sinh
       ra để bắt. Luật "sổ có nhắc tới cơ sở không" giữ nó lại đúng chỗ; luật "tiền sổ = 0" thì
       đẩy nó sang nhóm chưa có sổ và IM LUÔN — che mất một khoản lệch thật. */
    ds.push({ ngay: n, cua_hang: 'Quán Sổ Có Nhưng 0đ', pos_momo: 300000 });
    sk[n + '|Quán Sổ Có Nhưng 0đ'] = 0;
    /* Cơ sở của pháp nhân KIA — sổ không nhắc tới */
    ds.push({ ngay: n, cua_hang: 'Quán Pháp Nhân Kia', pos_momo: 2000000 });
    /* Cơ sở không bán gì qua MoMo */
    ds.push({ ngay: n, cua_hang: 'Quán Không Bán MoMo', pos_momo: 0 });
  });
  S.dsR = { co_momo: true, momo: sk, momo_ngay_co: NGAY };
  return veMomo(ds, { tu: NGAY[0], den: NGAY[1] });
}

const h = dung();

phep('có dựng ra bảng', typeof h === 'string' && h.length > 0);
phep('cơ sở của pháp nhân kia KHÔNG nằm trong bảng so',
  h.indexOf('Quán Pháp Nhân Kia') > h.indexOf('cơ sở chưa có sổ MoMo'));
phep('có khối riêng "chưa có sổ MoMo"', h.indexOf('cơ sở chưa có sổ MoMo') > -1);
phep('khối ấy nói rõ KHÔNG kể là lệch', h.indexOf('KHÔNG kể là lệch') > -1);
phep('khối ấy có nút nạp sổ', h.indexOf('data-mo-nap="momo_sk"') > -1);

/* 🔴 Con số quyết định: tổng lệch KHÔNG được nuốt 4.000.000 của quán chưa có sổ.
   Lệch thật chỉ là 2 ngày × 100.000 = 200.000. */
const mTong = h.match(/Tất cả (\d+) cơ sở/);
phep('dòng tổng chỉ đếm cơ sở SO ĐƯỢC (4, không phải 5)', mTong && mTong[1] === '4');
/* Lệch thật = 2×100.000 (Quán Lệch Thật) + 2×300.000 (Quán Sổ Có Nhưng 0đ) = 800.000.
   Cộng nhầm 4.000.000 của quán chưa có sổ là ra 4.800.000. */
phep('tổng lệch = 800.000 (không cộng 4.000.000 của quán chưa có sổ)',
  h.indexOf('>800000<') > -1 && h.indexOf('>4800000<') < 0);

/* Lệch THẬT vẫn phải kêu — đây là chốt ngược: vá xong mà im hết thì còn tệ hơn báo oan. */
phep('cơ sở có sổ mà lệch thật thì VẪN kêu', h.indexOf('Quán Lệch Thật') > -1);
phep('cơ sở CÓ trong sổ mà tiền sổ = 0 thì vẫn là LỆCH THẬT, không phải thiếu sổ',
  h.indexOf('Quán Sổ Có Nhưng 0đ') < h.indexOf('cơ sở chưa có sổ MoMo'));
phep('cơ sở không bán MoMo vẫn ở bảng chính, không bị đẩy sang nhóm chưa có sổ',
  h.indexOf('Quán Không Bán MoMo') < h.indexOf('cơ sở chưa có sổ MoMo'));

/* Ca mọi cơ sở đều chưa có sổ: đừng vẽ bảng rỗng toàn số 0 (trông như "đã soát, không lệch"). */
S.dsR = { co_momo: true, momo: {}, momo_ngay_co: NGAY };
const h2 = veMomo(NGAY.map((n) => ({ ngay: n, cua_hang: 'Quán A', pos_momo: 999000 })),
  { tu: NGAY[0], den: NGAY[1] });
phep('không cơ sở nào so được thì KHÔNG vẽ bảng tổng rỗng',
  h2.indexOf('Tất cả 0 cơ sở') < 0);
phep('…mà nói thẳng là chưa so được cơ sở nào', h2.indexOf('Chưa cơ sở nào so được') > -1);
phep('…và vẫn liệt kê cơ sở chưa có sổ', h2.indexOf('Quán A') > -1);

if (hong.length) {
  console.log('\n✗ HỎNG ' + hong.length + ' phép (đạt ' + dat + '):');
  hong.forEach((x) => console.log('   · 🔴 ' + x));
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + dat + ' phép: chưa có sổ tách riêng, lệch thật vẫn kêu.');
