/**
 * Ô "LOẠI CHI PHÍ" Ở ĐƠN — chạy thật _loaiCpList / _loaiCpVi lấy từ app.html ra.
 *
 * Hai chuyện anh Thắng gặp trong một ngày, cùng một gốc: ô này lọc 4 lớp mà ẩn IM LẶNG.
 *   (a) "mở đơn lần đầu không hiện loại chi phí, bấm qua lại nút mới hiện"
 *       -> chưa chọn cơ sở thì không biết MẢNG, mà mã tài khoản khai theo mảng.
 *   (b) "thêm rồi mà qua tk nhân viên khác thì không có"
 *       -> loại chỉ khai cho 1/7 mảng; nhân viên kia ở cơ sở thuộc mảng khác.
 * Không cái nào là app hỏng, nhưng nhìn màn hình thì y như hỏng.
 *
 *   node tools/test/test-o-loai-chi-phi.js
 */
const fs = require('fs');
const path = require('path');

const GOC  = path.join(__dirname, '..', '..');
const HTML = fs.readFileSync(path.join(GOC, 'wordpress/vhcp-chi-phi/templates/app.html'), 'utf8');

let dat = 0; const hong = [];
function t(ten, dieu, nhan) { if (dieu) { dat++; return; } hong.push(ten + (nhan === undefined ? '' : ' → nhận được: ' + JSON.stringify(nhan))); }
function teq(ten, mong, nhan) { t(ten + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(nhan), nhan); }

function layHam(ten) {
  const m = HTML.match(new RegExp('\\n  function ' + ten + '\\([\\s\\S]*?\\n  \\}'));
  if (!m) { console.error('HỎNG: không tìm thấy hàm ' + ten + ' trong app.html'); process.exit(1); }
  return m[0];
}

// ---------------------------------------------------------------- bối cảnh thật
// 2 mảng, mỗi mảng 1 cơ sở. "Chi phí cơ sở" chỉ khai cho TUTU MN (đúng cảnh anh Thắng
// tích 1/7 mảng). "Chi phí nuôi thú" chỉ khai cho FARM MN.
const BOOT = {
  cosoPll: { 'tàu estella': 'TUTU MN', 'farm phan thiết': 'FARM MN' },
  tkNoMx: {
    'chi phí cơ sở':  { 'tutu mn': ['64106'] },
    'chi phí nuôi thú': { 'farm mn': ['64168'] },
    'chi phí điện nước': { 'tutu mn': ['64127'], 'farm mn': ['64127'] },
  },
  loaiChiPhi: [
    { ten: 'Chi phí cơ sở',      tkNo: '', boPhan: 'Cơ sở' },
    { ten: 'Chi phí nuôi thú',   tkNo: '', boPhan: 'Cơ sở' },
    { ten: 'Chi phí điện nước',  tkNo: '', boPhan: 'Cơ sở' },
    { ten: 'Chi phí tháo dỡ',    tkNo: '2413', boPhan: 'Kỹ thuật' },
    { ten: 'Chi phí chưa khai mã', tkNo: '', boPhan: 'Cơ sở' },
  ],
};

const NHOM_CP_CS = '(cơ sở)';
const nguon = ['_mangCua', '_tkNoList', '_tkNoCua', '_khoaNhom', '_loaiCpList', '_loaiCpVi'].map(layHam).join('\n')
  + '\n  return { list:_loaiCpList, vi:_loaiCpVi, dat:function(n,u){ NHOM_CP=n; CURUSER=u; } };';
function moi(nhomCp, user) {
  const M = new Function('BOOT', 'NHOM_CP', 'NHOM_CP_CS', 'CURUSER', 'esc', nguon)(
    BOOT, nhomCp, NHOM_CP_CS, user, v => String(v == null ? '' : v));
  return M;
}
const NV_CS = { boPhan: 'Cơ sở' };

// ---------------------------------------------------------------- 1. (a) chưa chọn cơ sở
let M = moi(NHOM_CP_CS, NV_CS);
teq('chưa chọn cơ sở -> ô rỗng (mã khai theo mảng của cơ sở)', 0, M.list('', '', '').length);
const vi0 = M.vi('', '', '');
t('…nhưng PHẢI nói vì sao, không im lặng', /Chọn CƠ SỞ trước/.test(vi0.chu), vi0);
t('và tô màu cảnh báo chứ không xám nhạt', vi0.mau === '#b45309', vi0);

// ---------------------------------------------------------------- 2. (b) khai 1 mảng
const cs_tutu = M.list('TÀU ESTELLA', '', '').map(x => x.ten);
t('cơ sở TUTU thấy loại đã khai cho mảng TUTU', cs_tutu.indexOf('Chi phí cơ sở') >= 0, cs_tutu);
t('cơ sở TUTU KHÔNG thấy loại chỉ khai cho FARM', cs_tutu.indexOf('Chi phí nuôi thú') < 0, cs_tutu);
const cs_farm = M.list('FARM PHAN THIẾT', '', '').map(x => x.ten);
t('cơ sở FARM KHÔNG thấy loại chỉ khai cho TUTU', cs_farm.indexOf('Chi phí cơ sở') < 0, cs_farm);
t('loại khai CẢ HAI mảng thì cơ sở nào cũng thấy',
  cs_tutu.indexOf('Chi phí điện nước') >= 0 && cs_farm.indexOf('Chi phí điện nước') >= 0, [cs_tutu, cs_farm]);
// Đây là chỗ anh Thắng kêu "thêm rồi mà tk nhân viên khác không có" — phải giải thích được.
const viF = M.vi('FARM PHAN THIẾT', '', '');
t('nói rõ ẩn bao nhiêu và VÌ SAO', /ẩn:/.test(viF.chu) && /chưa khai mã cho mảng/.test(viF.chu), viF);
t('gọi đúng tên mảng đang thiếu', /FARM MN/.test(viF.chu), viF);
t('đang hiện bao nhiêu trên tổng bao nhiêu', /Đang hiện \d+\/5 loại/.test(viF.chu), viF);

// ---------------------------------------------------------------- 3. rỗng hẳn thì chỉ luôn lối ra
const M2 = moi(NHOM_CP_CS, { boPhan: 'Marketing' });   // bộ phận không khớp loại nào
teq('bộ phận khác -> không loại nào', 0, M2.list('TÀU ESTELLA', '', '').length);
const vi2 = M2.vi('TÀU ESTELLA', '', '');
t('rỗng hẳn -> chỉ luôn nút khai nhanh', /Thêm loại chi phí mới/.test(vi2.chu), vi2);
t('và đếm đủ lý do bộ phận', /thuộc bộ phận khác/.test(vi2.chu), vi2);

// ---------------------------------------------------------------- 4. đủ dùng thì đừng làm ồn
const M3 = moi('', NV_CS);
const vi3 = M3.vi('TÀU ESTELLA', '', '');
t('còn loại bị ẩn thì vẫn ghi chú nhẹ (xám)', vi3.chu === '' || vi3.mau === '#94a3b8', vi3);
t('danh mục trống thì nói thẳng',
  /Danh mục loại chi phí đang trống/.test(new Function('BOOT','NHOM_CP','NHOM_CP_CS','CURUSER','esc',nguon)(
    { cosoPll:{}, tkNoMx:{}, loaiChiPhi:[] }, '', NHOM_CP_CS, NV_CS, v=>String(v||'')).vi('X','','').chu));

// ---------------------------------------------------------------- 5. thứ tự khởi động
// renderNhomCp() chốt NHOM_CP; fillNhom() lọc theo đúng biến đó. Chạy ngược thì lần dựng
// đầu lọc bằng giá trị chưa chốt -> ô rỗng, bấm qua lại một nút mới hiện.
const mBoot = HTML.match(/el\('f_pltt'\)\.innerHTML=opts\(BOOT\.phanloai,'—'\);[\s\S]{0,400}?fillNhom\(''\);/);
t('lúc khởi động có gọi renderNhomCp()', !!mBoot && /renderNhomCp\(\)/.test(mBoot[0]), mBoot && mBoot[0]);
t('và gọi TRƯỚC fillNhom()', !!mBoot && mBoot[0].indexOf('renderNhomCp()') < mBoot[0].indexOf("fillNhom('')"), mBoot && mBoot[0]);
t('chỉ có 1 cơ sở thì chọn sẵn (khỏi phải bấm mới thấy loại chi phí)',
  /else if\(\(cosoOpts\|\|\[\]\)\.length===1\) el\('f_coso'\)\.value=cosoOpts\[0\];/.test(HTML));
t('ô Loại chi phí có chỗ hiện lời giải thích', /id="f_nhomVi"/.test(HTML));
t('fillNhom có vẽ lời giải thích đó', /_veLoaiCpVi\('f_nhomVi'/.test(HTML));

// ---------------------------------------------------------------- 6. bảng dòng chi
const CSS = fs.readFileSync(path.join(GOC, 'wordpress/vhcp-chi-phi/assets/css/vhcp.css'), 'utf8');
// Cột "Cơ sở": một đơn = một cơ sở (máy chủ chặn ở loi_khac_coso), đã chốt ở đầu form.
t('ẩn cột Cơ sở bằng LỚP CSS (đầu bảng + mọi dòng cùng lúc)',
  /#lineTable\.anCoso \.colCoso\{display:none\}/.test(CSS));
t('đầu bảng và ô của dòng dùng CHUNG một lớp', (HTML.match(/class="colCoso"/g) || []).length >= 1 && /'<td class="colCoso"'/.test(HTML));
// Bỏ ô ở dòng mà giữ ô ở đầu bảng là cả bảng trượt cột -> colspan phải giữ nguyên 13.
t('giữ nguyên 13 ô, không đổi colspan theo trạng thái', /var COLS=13, html='';/.test(HTML));
// Đơn cũ trộn cơ sở thì KHÔNG được giấu — ẩn đi là giấu mất một sai lệch có thật.
t('đơn trộn cơ sở thì cột hiện lại', /classList\[lechCs\?'remove':'add'\]\('anCoso'\)/.test(HTML));
t('và dòng lệch cơ sở bị tô đỏ', /Dòng này khác cơ sở của đơn/.test(HTML));
t('phát hiện lệch theo cả 2 cách (nhiều cơ sở, hoặc khác cơ sở của đơn)',
  /var lechCs=\(dsCs\.length>1\)\|\|\(csDon!==''&&dsCs\.length===1&&dsCs\[0\]!==csDon\);/.test(HTML));
// Ảnh: máy chủ vốn cho đính khi Nháp, chỉ giao diện không bày ra.
t('đính ảnh được ngay khi đơn còn Nháp (không đợi cấp tạm ứng)',
  /if\(!\(o\.canThucChi\|\|o\.canEditRow\)\) return xem\|\|'—';/.test(HTML));
t('hiện ảnh nhỏ để nhìn ra dòng nào đã có chứng từ', /<img src="'\+esc\(l\.anh\)\+'" style="height:28px/.test(HTML));

// ---------------------------------------------------------------- kết
if (hong.length) {
  console.error('\nĐẠT: ' + dat + ' phép thử');
  console.error('HỎNG: ' + hong.length);
  hong.forEach(h => console.error('  ✗ ' + h));
  process.exit(1);
}
console.log('ĐẠT: ' + dat + ' phép thử');
console.log('Tất cả phép thử đều đạt.');
