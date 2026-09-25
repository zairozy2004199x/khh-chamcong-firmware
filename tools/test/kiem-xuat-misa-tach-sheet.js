/* Tải Excel MISA: mỗi ĐẦU MỤC loại chi phí ra một SHEET riêng — anh Thắng 25/09/2026: "Chỗ misa cũng
 * tách bảng riêng, để lỡ xuất misa nó đi theo phân loại lớn riêng" · "4 chi phí, 4 bảng riêng biệt".
 * Chạy: node tools/test/kiem-xuat-misa-tach-sheet.js */
const fs = require('fs');
const HTML = fs.readFileSync('wordpress/vhcp-chi-phi/templates/app.html', 'utf8');
let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them !== undefined ? (' → ' + JSON.stringify(them)) : '')); } }
function teq(n, mong, thuc) { t(n + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }
const ham = (n) => { const i = HTML.indexOf('  function ' + n + '('); return i < 0 ? '' : HTML.slice(i, HTML.indexOf('\n  }', i) + 4); };

t('⚠️ bốc được _xlsxTenSheet / _xlsxWorkbook / _xuatKhoiDauMuc', ham('_xlsxTenSheet').length > 20 && ham('_xlsxWorkbook').length > 40 && ham('_xuatKhoiDauMuc').length > 40);

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
  const wb = new Function('XUAT', 'XLSX', ham('_xuatKhoiDauMuc') + ham('_xlsxTenSheet') + ham('_xlsxWorkbook') + '\nreturn _xlsxWorkbook();')(XUAT, XLSX);
  return { wb, sheets: XLSX.sheets };
}

/* Ca thật (thu nhỏ): dòng xếp theo mảng cơ sở như tệp thật, nhưng sheet phải theo ĐẦU MỤC:
   2 dòng Cơ Sở KVC (A, C), 1 dòng Vận Hành (B), 1 dòng chưa xếp (D). */
const X = {
  cols: ['TK Nợ', 'Ngày', 'Diễn giải'],
  rows: [['64196', '01/09', 'A'], ['64191', '02/09', 'B'], ['64196', '01/09', 'C'], ['64199', '01/09', 'D']],
  rowDauMuc: ['Chi Phí Cơ Sở KVC', 'Chi Phí Vận Hành', 'Chi Phí Cơ Sở KVC', ''],
  dauMucThu: ['Chi Phí Vận Hành', 'Chi Phí Cơ Sở KVC', ''],
};
{
  const { sheets } = chay(X);
  teq('🔴 3 đầu mục → 3 sheet, đúng tên, theo thứ tự dauMucThu (Vận Hành trước dù xuất hiện sau), "Chưa xếp đầu mục" cuối',
    ['Chi Phí Vận Hành', 'Chi Phí Cơ Sở KVC', 'Chưa xếp đầu mục'], sheets.map((s) => s.ten));
  const kvc = sheets.find((s) => s.ten === 'Chi Phí Cơ Sở KVC');
  t('   sheet Cơ Sở KVC có đúng 2 dòng A, C — không lẫn B của Vận Hành', kvc.soHang === 2 && kvc.dong.map((r) => r[2]).join() === 'A,C', kvc);
  const vh = sheets.find((s) => s.ten === 'Chi Phí Vận Hành');
  t('   sheet Vận Hành có đúng 1 dòng B', vh.soHang === 1 && vh.dong[0][2] === 'B');
  t('   sheet mỗi cái đều có dòng tiêu đề cột riêng (header lặp lại)', sheets.every((s) => s.soHang >= 1));
}

/* Chỉ MỘT đầu mục → KHÔNG tách, giữ tên sheet 'MISA' như cũ. */
{
  const mot = { cols: X.cols, rows: X.rows.slice(0, 2), rowDauMuc: ['Chi Phí Vận Hành', 'Chi Phí Vận Hành'], dauMucThu: ['Chi Phí Vận Hành'] };
  const { sheets } = chay(mot);
  teq('🔴 chỉ một đầu mục → một sheet tên "MISA" (không đổi thói quen cũ)', ['MISA'], sheets.map((s) => s.ten));
  teq('   sheet ấy có đủ cả 2 dòng', 2, sheets[0].soHang);
}

/* Máy chủ cũ (không rowDauMuc), hoặc rowDauMuc lệch độ dài → vẫn 1 sheet, không vỡ. Thiếu dauMucThu → vẫn tách. */
{
  const cu = { cols: X.cols, rows: X.rows };
  const { sheets } = chay(cu);
  teq('🔴 thiếu rowDauMuc (máy chủ cũ) → lui về 1 sheet "MISA", không vỡ', ['MISA'], sheets.map((s) => s.ten));
  teq('   đủ cả 4 dòng', 4, sheets[0].soHang);
  const lech = chay({ cols: X.cols, rows: X.rows, rowDauMuc: ['Chi Phí Vận Hành', 'Chi Phí Cơ Sở KVC'] }).sheets;
  teq('🔴 rowDauMuc lệch độ dài (2 giá trị cho 4 dòng) → 1 sheet "MISA" đủ 4 dòng, không rơi dòng', [['MISA'], 4], [lech.map((s) => s.ten), lech[0].soHang]);
  const khongThu = { cols: X.cols, rows: X.rows, rowDauMuc: X.rowDauMuc };
  teq('   thiếu dauMucThu → vẫn 3 sheet, theo thứ tự xuất hiện', ['Chi Phí Cơ Sở KVC', 'Chi Phí Vận Hành', 'Chưa xếp đầu mục'], chay(khongThu).sheets.map((s) => s.ten));
}

/* Tên sheet phải hợp lệ với Excel: cắt 31 ký tự, bỏ ký tự cấm, hai đầu mục trùng tên sau khi cắt/thay không đè nhau. */
{
  const _xlsxTenSheet = new Function(ham('_xlsxTenSheet') + '\nreturn _xlsxTenSheet;')();
  const daDung = {};
  t('🔴 bỏ ký tự Excel cấm (: \\\\ / ? * [ ])', !/[:\\/?*[\]]/.test(_xlsxTenSheet('Chi phi: Kho\\A/B?*[X]', daDung)), _xlsxTenSheet('Chi phi: Kho\\A/B?*[X]', {}));
  const dai = 'Đây là một tên đầu mục cực kỳ dài để kiểm tra cắt bớt còn ba mươi mốt ký tự';
  const s1 = _xlsxTenSheet(dai, {});
  t('   cắt còn tối đa 31 ký tự', s1.length <= 31, s1);
  const dung = {};
  const a1 = _xlsxTenSheet('Trùng Tên Sau Khi Cắt Bớt Ở Đây Luôn', dung);
  const a2 = _xlsxTenSheet('Trùng Tên Sau Khi Cắt Bớt Ở Đây Khác', dung);
  t('🔴 hai đầu mục khác nhau mà tên rút gọn trùng nhau → thêm số thứ tự, không đè', a1 !== a2 && a1.length <= 31 && a2.length <= 31, [a1, a2]);
  teq('   tên rỗng → "Chưa xếp đầu mục", không sheet trắng', 'Chưa xếp đầu mục', _xlsxTenSheet('', {}));
}

/* downloadCSV(): fallback CSV cũng chia khối theo đầu mục khi thư viện XLSX không có. */
{
  const src = ham('downloadCSV');
  t('🔴 fallback CSV có nhánh chia khối theo đầu mục (=== <đầu mục> ===)', /=== '\+ce\(m\|\|'Chưa xếp đầu mục'\)\+' ==='/.test(src), src);
  t('   fallback CSV dùng chung _xuatKhoiDauMuc() với bảng trên màn', /_xuatKhoiDauMuc\(\)/.test(src), src);
  t('   downloadCSV gọi _xlsxWorkbook() thay vì tự dựng 1 sheet', /var wb=_xlsxWorkbook\(\);/.test(src), src);
}

if (TRUOT.length) { console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):'); TRUOT.forEach((x) => console.log('  · ' + x)); process.exit(1); }
console.log('\n✓ SẠCH — ' + DAT + ' phép: tải Excel MISA tách một sheet cho mỗi đầu mục loại chi phí.');
