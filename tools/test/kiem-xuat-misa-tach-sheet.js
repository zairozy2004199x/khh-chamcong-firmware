/* Tải Excel MISA: mỗi mảng kinh doanh ra một SHEET riêng — anh Thắng 25/09/2026: "Chỗ misa cũng
 * tách bảng riêng, để lỡ xuất misa nó đi theo phân loại lớn riêng".
 * Chạy: node tools/test/kiem-xuat-misa-tach-sheet.js */
const fs = require('fs');
const HTML = fs.readFileSync('wordpress/vhcp-chi-phi/templates/app.html', 'utf8');
let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them !== undefined ? (' → ' + JSON.stringify(them)) : '')); } }
function teq(n, mong, thuc) { t(n + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }
const ham = (n) => { const i = HTML.indexOf('  function ' + n + '('); return i < 0 ? '' : HTML.slice(i, HTML.indexOf('\n  }', i) + 4); };

t('⚠️ bốc được _xlsxTenSheet / _xlsxWorkbook', ham('_xlsxTenSheet').length > 20 && ham('_xlsxWorkbook').length > 40);

/* Giả lập XLSX tối thiểu: ghi lại mỗi sheet đã append (tên + số hàng dữ liệu, bỏ header). */
function xlsxGia() {
  const sheets = [];
  return {
    utils: {
      aoa_to_sheet(aoa) { return { __aoa: aoa }; },
      book_new() { return { SheetNames: [] }; },
      book_append_sheet(wb, ws, ten) { wb.SheetNames.push(ten); sheets.push({ ten, soHang: ws.__aoa.length - 1, dong: ws.__aoa.slice(1) }); },
    },
    sheets,
  };
}

function chay(XUAT) {
  const XLSX = xlsxGia();
  const wb = new Function('XUAT', 'XLSX', ham('_xlsxTenSheet') + ham('_xlsxWorkbook') + '\nreturn _xlsxWorkbook();')(XUAT, XLSX);
  return { wb, sheets: XLSX.sheets };
}

/* Ca thật (thu nhỏ): 2 dòng Chi Phí Vận Hành, 1 dòng Chi Phí Cơ Sở KVC, 1 dòng chưa khai mảng. */
const X = {
  cols: ['TK Nợ', 'Ngày', 'Diễn giải'],
  rows: [['64191', '01/09', 'A'], ['64191', '02/09', 'B'], ['64196', '01/09', 'C'], ['64199', '01/09', 'D']],
  rowMang: ['Chi Phí Vận Hành', 'Chi Phí Vận Hành', 'Chi Phí Cơ Sở KVC', '(chưa khai mảng)'],
};
{
  const { sheets } = chay(X);
  teq('🔴 3 mảng khác nhau → 3 sheet, đúng tên mảng', ['Chi Phí Vận Hành', 'Chi Phí Cơ Sở KVC', '(chưa khai mảng)'].sort(), sheets.map((s) => s.ten).sort());
  const vh = sheets.find((s) => s.ten === 'Chi Phí Vận Hành');
  t('   sheet Vận Hành có đúng 2 dòng của nó, không lẫn dòng KVC', vh.soHang === 2 && vh.dong.every((r) => r[0] === '64191'), vh);
  const kvc = sheets.find((s) => s.ten === 'Chi Phí Cơ Sở KVC');
  t('   sheet Cơ Sở KVC có đúng 1 dòng', kvc.soHang === 1 && kvc.dong[0][0] === '64196');
  t('   sheet mỗi cái đều có dòng tiêu đề cột riêng (header lặp lại)', sheets.every((s) => s.soHang >= 1));
}

/* Đã lọc còn MỘT mảng (hoặc dữ liệu vốn một mảng) → KHÔNG tách, giữ tên sheet 'MISA' như cũ. */
{
  const mot = { cols: X.cols, rows: X.rows.slice(0, 2), rowMang: ['Chi Phí Vận Hành', 'Chi Phí Vận Hành'] };
  const { sheets } = chay(mot);
  teq('🔴 chỉ một mảng → một sheet tên "MISA" (không đổi thói quen cũ)', ['MISA'], sheets.map((s) => s.ten));
  teq('   sheet ấy có đủ cả 2 dòng', 2, sheets[0].soHang);
}

/* Máy chủ cũ (lời gọi trước khi có rowMang) → không rowMang, hoặc rowMang lệch độ dài → vẫn 1 sheet, không vỡ. */
{
  const cu = { cols: X.cols, rows: X.rows };
  const { sheets } = chay(cu);
  teq('🔴 thiếu rowMang (máy chủ cũ) → lui về 1 sheet "MISA", không vỡ, đủ cả 4 dòng', ['MISA'], sheets.map((s) => s.ten));
  teq('   đủ cả 4 dòng', 4, sheets[0].soHang);
  const lech = { cols: X.cols, rows: X.rows, rowMang: ['Chi Phí Vận Hành'] };
  teq('   rowMang lệch độ dài với rows → cũng lui về 1 sheet, không đoán bừa', ['MISA'], chay(lech).sheets.map((s) => s.ten));
}

/* Tên sheet phải hợp lệ với Excel: cắt 31 ký tự, bỏ ký tự cấm, hai mảng trùng tên sau khi cắt/thay không đè nhau. */
{
  const _xlsxTenSheet = new Function(ham('_xlsxTenSheet') + '\nreturn _xlsxTenSheet;')();
  const daDung = {};
  t('🔴 bỏ ký tự Excel cấm (: \\\\ / ? * [ ])', !/[:\\/?*[\]]/.test(_xlsxTenSheet('Chi phi: Kho\\A/B?*[X]', daDung)), _xlsxTenSheet('Chi phi: Kho\\A/B?*[X]', {}));
  const dai = 'Đây là một tên mảng cực kỳ dài để kiểm tra cắt bớt còn ba mươi mốt ký tự';
  const s1 = _xlsxTenSheet(dai, {});
  t('   cắt còn tối đa 31 ký tự', s1.length <= 31, s1);
  const dung = {};
  const a1 = _xlsxTenSheet('Trùng Tên Sau Khi Cắt Bớt Ở Đây Luôn', dung);
  const a2 = _xlsxTenSheet('Trùng Tên Sau Khi Cắt Bớt Ở Đây Khác', dung);
  t('🔴 hai mảng khác nhau mà tên rút gọn trùng nhau → thêm số thứ tự, không đè', a1 !== a2 && a1.length <= 31 && a2.length <= 31, [a1, a2]);
  const rong = _xlsxTenSheet('', {});
  t('   tên rỗng → có tên mặc định, không sheet trắng', rong.length > 0, rong);
}

/* downloadCSV(): fallback CSV cũng chia khối theo mảng khi thư viện XLSX không có. */
{
  const src = ham('downloadCSV');
  t('🔴 fallback CSV có nhánh chia khối theo mảng (=== <mảng> ===)', /=== '\+ce\(m\|\|'\(chưa khai mảng\)'\)\+' ==='/.test(src), src);
  t('   downloadCSV gọi _xlsxWorkbook() thay vì tự dựng 1 sheet', /var wb=_xlsxWorkbook\(\);/.test(src), src);
}

if (TRUOT.length) { console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):'); TRUOT.forEach((x) => console.log('  · ' + x)); process.exit(1); }
console.log('\n✓ SẠCH — ' + DAT + ' phép: tải Excel MISA tách một sheet cho mỗi mảng kinh doanh.');
