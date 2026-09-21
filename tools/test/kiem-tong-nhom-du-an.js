/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * DÒNG TỔNG CỦA BẢNG CHI PHÍ DỰ ÁN — NHẬP SỐ NÀO PHẢI THẤY SỐ ẤY.
 *
 * Anh Thắng 17/09/2026: *"Khi nhập nhạp, đã nhập số liệu thì cần cộng vào luôn để biết bao
 * nhiêu"*, kèm ảnh một dự án có mười mấy dòng dự toán (390.000 · 960.000 · 48.000.000 …) mà
 * dòng tổng nhóm "💰 NV TỰ TRẢ" đứng đúng một chữ số: **0**.
 *
 * =============================================================================================
 * 🔴 VÌ SAO NÓ RA 0. `tongNhom()` bản trước chỉ cộng `thucTe`. Đúng luật của bảng — nhưng ở
 *    giai đoạn LẬP DỰ TOÁN thì chưa ai tiêu đồng nào, nên con số duy nhất trên dòng tổng luôn
 *    là 0, trong khi ngay dưới nó là mười mấy dòng có tiền. Người nhập không có cách nào biết
 *    mình vừa gõ vào bao nhiêu, ngoài việc tự cộng nhẩm cả bảng.
 *
 * 🔴 VÀ VÌ SAO KHÔNG GỘP HAI SỐ LÀM MỘT. Cách rẻ là cho dòng tổng "lấy thực tế, chưa có thì lấy
 *    dự toán". Nhưng một nhóm nửa đã chi nửa chưa sẽ ra một con số không phải cái nào cả, và
 *    không có gì trên màn hình nói ra điều đó. Dự toán và thực chi là hai đại lượng khác nhau.
 *
 * ⚠️ CHẠY THẬT hàm bốc từ mã nguồn, không đọc bằng mắt.
 *
 * Chạy: node tools/test/kiem-tong-nhom-du-an.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs'), path = require('path');
let DAT = 0; const TRUOT = [];
function t(ten, ok, them) {
  if (ok) { DAT++; return; }
  TRUOT.push(ten + (them !== undefined ? '\n      → ' + JSON.stringify(them) : ''));
}
function teq(ten, mong, thuc) { t(ten + ' (mong ' + JSON.stringify(mong) + ')', mong === thuc, thuc); }

const GOC = path.resolve(__dirname, '..', '..');
const APP = path.join(GOC, 'wordpress/vhcp-chi-phi/templates/app.html');
const HTML = fs.readFileSync(APP, 'utf8');

/* Hai hàm này nằm LỒNG trong `renderDaLines()`, thụt vào 4 dấu cách — nên đóng thân là `\n    }`,
   không phải `\n  }` như `boc()` của `kiem-trang-du-an.js` dùng cho hàm cấp ngoài. Bốc nhầm mốc
   đóng thì cắt được nửa hàm, và `new Function` nổ một lỗi cú pháp chẳng liên quan gì tới việc
   đang kiểm. */
function boc(ten) {
  const i = HTML.indexOf('    function ' + ten + '(');
  t('bốc được ' + ten + '()', i >= 0);
  if (i < 0) { return ''; }
  const j = HTML.indexOf('\n    }', i);
  t('  và tìm được chỗ đóng thân của ' + ten + '()', j > i);
  return j < i ? '' : HTML.slice(i, j + 6);
}

const fnTong = boc('tongNhom');
const fnChu  = boc('tongNhomChu');

/* `money()` thật nằm rải trong app.html và kéo theo cả tá thứ khác; ở đây chỉ cần một bản đủ
   nhận ra "số nào đã đi vào chuỗi". Dấu chấm phân cách y như bản thật để phép so đọc được. */
function money(n) { return String(Number(n) || 0).replace(/\B(?=(\d{3})+(?!\d))/g, '.'); }

function dung(childrenBy) {
  const f = new Function('childrenBy', 'money',
    fnTong + '\n' + fnChu + '\nreturn { tongNhom: tongNhom, tongNhomChu: tongNhomChu };');
  return f(childrenBy, money);
}

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 1. ĐÚNG CẢNH TRONG ẢNH: chỉ có dự toán, chưa chi đồng nào
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
{
  const F = dung({});
  const ds = [
    { noiDung: 'Băng keo trong', duToan: 390000,   thucTe: 0 },
    { noiDung: 'Màn co',         duToan: 960000,   thucTe: 0 },
    { noiDung: 'Thợ Phụ',        duToan: 48000000, thucTe: 0 },
  ];
  const o = F.tongNhom(ds, []);
  teq('🔴 cộng đủ dự toán của cả nhóm', 49350000, o.duToan);
  teq('   chưa chi gì thì thực tế vẫn 0', 0, o.thucTe);
  const chu = F.tongNhomChu(o);
  t('🔴 dòng tổng NÓI RA số dự toán, không còn đứng một chữ số 0',
    chu.indexOf('49.350.000') >= 0, chu);
  t('   và gọi nó đúng tên là dự toán', chu.indexOf('dự toán') >= 0, chu);
  t('🔴 KHÔNG in "thực tế 0đ" — số 0 ấy lại là thứ đập vào mắt trước, đúng cái vừa phải sửa',
    chu.indexOf('thực tế') < 0, chu);
}

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 2. ĐÃ CHI MỘT PHẦN: hai con số, hai nhãn, KHÔNG trộn làm một
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
{
  const F = dung({});
  const ds = [
    { noiDung: 'Thợ Phụ', duToan: 48000000, thucTe: 47000000 },
    { noiDung: 'Màn co',  duToan: 960000,   thucTe: 0 },
  ];
  const o = F.tongNhom(ds, []);
  teq('dự toán cộng đủ cả dòng đã chi lẫn chưa chi', 48960000, o.duToan);
  teq('thực tế chỉ cộng cái đã chi', 47000000, o.thucTe);
  const chu = F.tongNhomChu(o);
  t('🔴 bày CẢ HAI số — nhóm nửa chi nửa chưa không được rút thành một con số',
    chu.indexOf('48.960.000') >= 0 && chu.indexOf('47.000.000') >= 0, chu);
}

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 3. HẠNG MỤC CÓ MỤC CON: tiền thực tế nằm ở con, dự toán nằm ở cha
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
{
  const F = dung({ 'Vật tư': [{ noiDung: 'Ốc vít', thucTe: 300000 }, { noiDung: 'Keo', thucTe: 200000 }] });
  const ds = [{ noiDung: 'Vật tư', duToan: 5000000, thucTe: 9999999 }];
  const o = F.tongNhom(ds, []);
  teq('🔴 có con thì thực tế = tổng CON, không cộng thêm của cha (đếm hai lần là chi thừa)',
    500000, o.thucTe);
  teq('   dự toán vẫn lấy ở cha — mục con không nhập dự toán', 5000000, o.duToan);
}

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 4. DÒNG PHÁT SINH vẫn cộng vào thực tế
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
{
  const F = dung({});
  const o = F.tongNhom([{ noiDung: 'A', duToan: 1000, thucTe: 0 }], [{ noiDung: 'PS', thucTe: 7000 }]);
  teq('phát sinh cộng vào thực tế', 7000, o.thucTe);
  teq('và không đẻ ra dự toán', 1000, o.duToan);
}

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 5. CHƯA CÓ GÌ THÌ NÓI LÀ CHƯA CÓ — đừng in một số 0 trần
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
{
  const F = dung({});
  const chu = F.tongNhomChu(F.tongNhom([{ noiDung: 'A', duToan: 0, thucTe: 0 }], []));
  t('nhóm trống trơn thì nói "chưa có số nào", không in "0đ"',
    chu.indexOf('chưa có số nào') >= 0, chu);
}

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 6. CHỖ GỌI PHẢI DÙNG BẢN CHỮ — không thì dòng tổng lại thành một con số trần
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
t('🔴 dòng tổng nhóm in bằng tongNhomChu(), không in thẳng tongNhom()',
  /tongNhomChu\(tongNhom\(nhomHt\[k\],psHt\[k\]\)\)/.test(HTML)
  && !/money\(tongNhom\(/.test(HTML));

/* 🔴 DỰ ÁN CHỈ CÓ MỘT HÌNH THỨC CHI CŨNG PHẢI CÓ DÒNG TỔNG. Bản trước chỉ vẽ dòng tổng khi có
   CẢ HAI hình thức (vì nó đi kèm nhãn nhóm) — nên đúng dự án đơn giản nhất lại là dự án không
   có chỗ nào nói tổng bao nhiêu. */
t('🔴 nhánh một-hình-thức cũng dựng dòng TỔNG',
  /var _tg=tongNhom\(parents, phatSinh\);/.test(HTML)
  && />TỔNG<\/td>/.test(HTML)
  && /tongNhomChu\(_tg\)/.test(HTML));

/* ═════════════════════════════════════════════════════════════════════════════════════════════ */
if (TRUOT.length) {
  console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):');
  TRUOT.forEach(x => console.log('  · ' + x));
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + DAT + ' phép: nhập số nào thì dòng tổng nói ra số ấy, ngay lúc nhập.');
