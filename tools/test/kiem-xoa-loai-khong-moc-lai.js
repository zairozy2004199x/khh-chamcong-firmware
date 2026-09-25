/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * XOÁ MỘT LOẠI CHI PHÍ, LƯU, MỞ LẠI — NÓ PHẢI MẤT HẲN.
 *
 * Anh Thắng 21/09/2026: *"Bấm xóa xong lưu, xong mở lại nó vẫn còn"*, kèm ảnh bảng **Văn phòng**
 * mọc ra "Chi Phí Khác MTĐ", "Chi phí marketing", "Chi phí setup", "Chi phí nuôi thú"… — toàn
 * loại của khối khác.
 *
 * =============================================================================================
 * 🔴 KHÔNG PHẢI LỖI Ở ĐƯỜNG XOÁ, MÀ Ở ĐƯỜNG VẼ LẠI
 * =============================================================================================
 * `delCfgRow()` gỡ đúng hàng, `saveCfgTkNoMx()` gửi lên đúng danh sách còn lại. Hỏng nằm ở
 * `renderTkNoMatrix()`: nó DỰNG LẠI một "dòng ma" cho mỗi tên còn trong bảng mã mà khối đang
 * đứng chưa có — dò bằng `seen[KHOI_DANG|tên]`.
 *
 * Một loại khai ở MTĐ có khoá `mtd|…`, nên đứng ở VP là câu trả lời "chưa có", và bảng VP mọc
 * ra một bản thứ hai mang đúng tên ấy. Xoá dòng ma rồi Lưu cũng vô ích: danh mục vẫn còn bản
 * thật ở MTĐ, bảng mã vẫn còn mã của nó, nên lượt vẽ sau nó mọc lại. Người dùng thấy đúng một
 * điều: xoá không ăn.
 *
 * ⚠️ HAI CÂU HỎI KHÁC NHAU, ĐỪNG DÙNG CHUNG MỘT BẢNG:
 *      `seen`  — "ô {khối, tên} này đã vẽ chưa" (hai khối ĐƯỢC PHÉP cùng tên);
 *      `coTen` — "tên này còn sống ở đâu đó không" (quyết định có dựng dòng ma hay không).
 *
 * Chạy: node tools/test/kiem-xoa-loai-khong-moc-lai.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const HTML = fs.readFileSync('wordpress/vhcp-chi-phi/templates/app.html', 'utf8');

let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them !== undefined ? (' → ' + JSON.stringify(them)) : '')); } }
function teq(n, mong, thuc) { t(n + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }

/* Bốc CHÍNH đoạn dựng hàng trong `renderTkNoMatrix()` ra chạy — không chép tay lại luật, vì
   chép tay là bài kiểm canh bản chép chứ không canh mã thật. */
const i0 = HTML.indexOf('    var rows=[], seen={}, coTen={};');
t('⚠️ bốc được đoạn dựng hàng', i0 > 0);
const i1 = HTML.indexOf('\n    });', HTML.indexOf('(CFG.tkNoMatrix||[]).forEach', i0)) + 8;
const DOAN = i0 > 0 ? HTML.slice(i0, i1) : '';
t('⚠️ đoạn bốc ra khép kín', /\}\);\s*$/.test(DOAN), DOAN.slice(-80));

function dungBang(CFG, KHOI_DANG) {
  return new Function('CFG', 'KHOI_DANG', '_khoiCuaLoai',
    DOAN + '\nreturn rows;')(CFG, KHOI_DANG,
    (x) => String((x && x.khoi) || '').toLowerCase() || 'kvc');
}

/* ═══ 1. DỰNG LẠI ĐÚNG CẢNH TRONG ẢNH ═══════════════════════════════════════════ */
const CFG = {
  loaiChiPhi: [
    { ten: 'Chi Phí Khác MTĐ', khoi: 'mtd' },
    { ten: 'Chi phí marketing', khoi: 'kvc' },
    { ten: 'Chi Phí Chung VP', khoi: 'vp' }
  ],
  tkNoMatrix: [
    { nhom: 'Chi Phí Khác MTĐ', pll: 'TUTU MN', tkNo: '64106' },
    { nhom: 'Chi phí marketing', pll: 'TUTU MN', tkNo: '64106' },
    { nhom: 'Chi Phí Chung VP', pll: 'TUTU MN', tkNo: '64206' }
  ]
};
const vp = dungBang(CFG, 'vp');
teq('🔴 đứng ở VP: KHÔNG mọc thêm dòng nào — đúng ba loại của danh mục', 3, vp.length);
teq('   và mỗi loại vẫn ở đúng khối của nó',
  [['Chi Phí Khác MTĐ', 'mtd'], ['Chi phí marketing', 'kvc'], ['Chi Phí Chung VP', 'vp']],
  vp.map(function (x) { return [x.ten, String(x.khoi || '').toLowerCase()]; }));
/* 🔴 Đây là triệu chứng anh Thắng thấy: bảng VP mọc ra loại của MTĐ. */
teq('🔴 bảng VP không còn dòng "Chi Phí Khác MTĐ" mang khối vp', 0,
  vp.filter(function (x) { return x.ten === 'Chi Phí Khác MTĐ' && String(x.khoi).toLowerCase() === 'vp'; }).length);
['kvc', 'mtd'].forEach(function (k) {
  teq('   đứng ở ' + k + ' cũng đúng ba dòng', 3, dungBang(CFG, k).length);
});

/* ═══ 2. XOÁ → LƯU → VẼ LẠI: PHẢI MẤT HẲN ══════════════════════════════════════
 * Đây là phép canh thẳng vào câu của anh Thắng. Mô phỏng trọn vòng: người dùng xoá dòng ở bảng
 * trên (danh mục mất dòng ấy), lượt Lưu bỏ luôn mã của nó (đường lưu đã đúng từ trước), rồi vẽ
 * lại. Trước bản sửa, vòng này trả lại đúng cái vừa xoá. */
function xoaRoiVe(ten, khoiDang) {
  const sau = {
    loaiChiPhi: CFG.loaiChiPhi.filter(function (x) { return x.ten !== ten; }),
    /* `saveCfgTkNoMx()` bỏ mã của loại đã xoá — xem `if(!daTen[...]) return;`. */
    tkNoMatrix: CFG.tkNoMatrix.filter(function (x) { return x.nhom !== ten; })
  };
  return dungBang(sau, khoiDang).map(function (x) { return x.ten; });
}
t('🔴 xoá "Chi Phí Khác MTĐ" rồi mở lại → MẤT HẲN (đứng ở VP)',
  xoaRoiVe('Chi Phí Khác MTĐ', 'vp').indexOf('Chi Phí Khác MTĐ') < 0, xoaRoiVe('Chi Phí Khác MTĐ', 'vp'));
t('   …và đứng ở MTĐ cũng mất',
  xoaRoiVe('Chi Phí Khác MTĐ', 'mtd').indexOf('Chi Phí Khác MTĐ') < 0);
t('   …và đứng ở KVC cũng mất',
  xoaRoiVe('Chi Phí Khác MTĐ', 'kvc').indexOf('Chi Phí Khác MTĐ') < 0);
teq('   hai loại còn lại vẫn nguyên', ['Chi phí marketing', 'Chi Phí Chung VP'], xoaRoiVe('Chi Phí Khác MTĐ', 'vp'));

/* ⚠️ CA MỒ CÔI THẬT VẪN PHẢI DỰNG LẠI. Mã còn trong bảng mã mà danh mục KHÔNG còn dòng nào mang
   tên ấy — nếu không dựng thì mã ấy nằm trong sổ mà không ai sửa được nữa, và nhìn màn thì
   tưởng đã sạch. Đây là lý do cả nhánh này tồn tại; bỏ nó đi là chữa quá tay. */
const moCoi = dungBang({
  loaiChiPhi: [{ ten: 'Chi Phí Chung VP', khoi: 'vp' }],
  tkNoMatrix: [{ nhom: 'Loại cũ mồ côi', pll: 'TUTU MN', tkNo: '64199' }]
}, 'vp');
teq('⚠️ tên MỒ CÔI (danh mục không còn) thì VẪN dựng lại để sửa được', 2, moCoi.length);
t('   và cho về khối đang đứng', moCoi.some(function (x) { return x.ten === 'Loại cũ mồ côi' && x.khoi === 'vp'; }), moCoi);

/* ⚠️ HAI KHỐI CÙNG TÊN LÀ HỢP LỆ (ý *"tránh dùng chung"*) — cả hai dòng phải sống. */
const hai = dungBang({
  loaiChiPhi: [{ ten: 'Chi phí khác', khoi: 'kvc' }, { ten: 'Chi phí khác', khoi: 'mtd' }],
  tkNoMatrix: [{ nhom: 'Chi phí khác', pll: 'TUTU MN', tkNo: '64106' }]
}, 'vp');
teq('⚠️ hai khối cùng tên → giữ ĐỦ hai dòng, không dựng thêm dòng thứ ba', 2, hai.length);
teq('   và đúng hai khối ấy', ['kvc', 'mtd'], hai.map(function (x) { return x.khoi; }));

/* ═══ 3. NÚT XOÁ VẪN GỠ ĐÚNG HÀNG ══════════════════════════════════════════════ */
t('⚠️ nút ✕ gỡ hàng khỏi bảng', /function delCfgRow\(btn\)\{[^}]*removeChild\(tr\)/.test(HTML.replace(/\s+/g, ' ')));
/* Và đường lưu vẫn bỏ mã của loại đã xoá — chốt này có từ trước, giữ cho khỏi ai gỡ nhầm. */
t('🔴 lượt Lưu bỏ luôn mã của loại đã xoá', /if\(!daTen\[ten\.toLowerCase\(\)\]\) return;/.test(HTML));
/* 24/09/2026: thêm vế `&& !doiSang[…]` — tên cũ vừa được ĐỔI ở khối nào đó mà dòng không theo được
   thì giữ chứ không xoá (xem chú thích tại chỗ). Loại XOÁ hẳn (không ai đổi tên nó) vẫn bị bỏ. */
t('   và không lôi mã cũ về cho loại đã xoá', /if\(!loaiCon\[ten\.toLowerCase\(\)\] && !doiSang\[ten\.toLowerCase\(\)\]\) return;/.test(HTML));

/* ═════════════════════════════════════════════════════════════════════════════════════════ */
if (TRUOT.length) {
  console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):');
  TRUOT.forEach(function (x) { console.log('  · ' + x); });
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + DAT + ' phép: xoá loại là mất hẳn, không mọc lại dưới khối khác.');
