/**
 * Kiểm cách gom LOẠI CHI PHÍ vào ô "Chọn chi phí nào" (mục 1 của đơn chi phí).
 *
 * Quy tắc: cột Bộ phận khai rõ thì thắng; để trống thì suy từ TÊN loại. Đây là chỗ anh Thắng
 * đã soi từng nhóm trên màn hình, nên ghim lại để sửa app không lệch nhóm mà không ai biết.
 *
 *   node tools/test/test-nhom-chi-phi.js
 */
const fs = require('fs');
const path = require('path');

const FILE = path.join(__dirname, '..', '..', 'wordpress', 'vhcp-chi-phi', 'templates', 'app.html');
const html = fs.readFileSync(FILE, 'utf8');

// Lấy nguyên hàm _khoaNhom trong app.html ra để chạy — không chép lại luật ở đây, chép là rồi
// sẽ lệch với app mà phép thử vẫn xanh.
const m = html.match(/function _khoaNhom\(b, ten\)\{[\s\S]*?\n  \}/);
if (!m) {
  console.error('HỎNG: không tìm thấy hàm _khoaNhom trong app.html — đổi tên hàm thì sửa luôn phép thử này.');
  process.exit(1);
}
const NHOM_CP_CS = '(cơ sở)';
const _khoaNhom = new Function('NHOM_CP_CS', m[0] + '; return _khoaNhom;')(NHOM_CP_CS);

let dat = 0, hong = 0;
function teq(ten, mong, nhan) {
  if (mong === nhan) { dat++; return; }
  hong++;
  console.error(`  ✗ ${ten} (mong '${mong}') → nhận được: '${nhan}'`);
}

// Cột Bộ phận KHAI RÕ thì luôn thắng
teq('khai Marketing thì theo Marketing', 'Marketing', _khoaNhom('Marketing', 'Chi phí tháo dỡ'));
teq('khai "Cơ sở" thì về nhóm cơ sở dù tên có chữ setup', NHOM_CP_CS, _khoaNhom('Cơ sở', 'Chi phí setup'));
teq('khai Kỹ thuật thì theo Kỹ thuật', 'Kỹ thuật', _khoaNhom('Kỹ thuật', 'Chi phí khác'));

// Bộ phận TRỐNG -> suy từ tên (dữ liệu nạp từ bảng tính cũ để trống gần hết cột này)
teq('Chi phí cơ sở', NHOM_CP_CS, _khoaNhom('', 'Chi phí cơ sở'));
teq('Chi phí nuôi thú', NHOM_CP_CS, _khoaNhom('', 'Chi phí nuôi thú'));
teq('Chi phí NVL đồ ăn - Mua lẻ', NHOM_CP_CS, _khoaNhom('', 'Chi phí NVL đồ ăn - Mua lẻ'));
teq('Chi phí phát sinh', NHOM_CP_CS, _khoaNhom('', 'Chi phí phát sinh'));
teq('Chi phí khác', NHOM_CP_CS, _khoaNhom('', 'Chi phí khác'));
teq('Vận hành', NHOM_CP_CS, _khoaNhom('', 'Vận hành'));

teq('Chi phí marketing', 'Marketing', _khoaNhom('', 'Chi phí marketing'));
teq('Chi phí hoạt náo', 'Marketing', _khoaNhom('', 'Chi phí hoạt náo'));
teq('MKT - Hoạt náo', 'Marketing', _khoaNhom('', 'MKT - Hoạt náo'));

teq('Chi phí tháo dỡ', 'Kỹ thuật', _khoaNhom('', 'Chi phí tháo dỡ'));
teq('Chi phí setup', 'Kỹ thuật', _khoaNhom('', 'Chi phí setup'));
teq('Tháo dỡ', 'Kỹ thuật', _khoaNhom('', 'Tháo dỡ'));
teq('Setup lắp đặt', 'Kỹ thuật', _khoaNhom('', 'Setup lắp đặt'));
teq('Chi phí setup lắp đặt gian hàng mới', 'Kỹ thuật', _khoaNhom('', 'Chi phí setup lắp đặt gian hàng mới'));

teq('Chi phí công tác', 'Công tác', _khoaNhom('', 'Chi phí công tác'));

console.log(hong ? `\nĐẠT: ${dat} · HỎNG: ${hong}` : `\nĐẠT: ${dat} phép thử — nhóm loại chi phí đúng.`);
process.exit(hong ? 1 : 0);
