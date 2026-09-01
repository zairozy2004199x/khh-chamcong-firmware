/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * CHỈ SỐ BẤT THƯỜNG — CHỈ HIỆN CẢNH BÁO, LÝ DO LẤY TỪ CỘT "GHI CHÚ"
 *
 * Anh Thắng 01/09/2026, ảnh hàng AM-TP-2: *"Chỗ phần web ghế, bỏ giúp anh cái này"* (ô nhập
 * "VD: đổi máy đếm" trong khung đỏ) và *"Chỉ hiện cảnh báo thôi, chứ không nhập, vì phía sau có
 * rồi"*.
 *
 * Hàng nào cũng đã có sẵn cột "Ghi chú" với ô "Lý do…", và cột "Thực thu tiền mặt" đứng ngay
 * cạnh. Khung đỏ dựng thêm một ô lý do THỨ HAI là bắt người ta gõ hai lần cùng một câu, mà hai ô
 * ấy lại đi về hai chỗ khác nhau trong sổ.
 *
 * 🔴 BỎ Ô NHẬP KHÔNG ĐƯỢC PHÉP LÀM MẤT ĐƯỜNG GỬI. Máy chủ (`VHG_BaoCao::luu()`) vẫn CHẶN khi chỉ
 *    số bất thường mà không có lý do — chốt "fail closed" ấy giữ nguyên từ 28/08. Nên lý do phải
 *    có nguồn mới: cột "Ghi chú" của chính hàng đó. Bỏ ô mà quên nối nguồn thì nhân viên không
 *    bao giờ gửi được báo cáo có ghế lỗi, và không có gì trên màn nói cho họ biết vì sao.
 *
 * ⚠️ BÀI NÀY CÓ DÒ CHUỖI, và cố ý. Thứ đang canh là SỰ VẮNG MẶT của một ô nhập trên giao diện —
 *    không có biểu thức nào bốc ra chạy được. Bù lại nó canh cả hai đầu (ô biến mất Ở ĐÂU, và
 *    lý do nay lấy TỪ ĐÂU), nên xoá một nửa là đỏ.
 *
 * Chạy: node tools/test/kiem-canh-bao-bat-thuong.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const G = 'vhcp-ghe/includes/';
const jsSrc  = fs.readFileSync(G + 'class-vhg-trang.php', 'utf8');
const phpSrc = fs.readFileSync(G + 'class-vhg-baocao.php', 'utf8');

let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them != null ? (' → ' + JSON.stringify(them)) : '')); } }

/* ---------- 1. Ô LÝ DO RIÊNG PHẢI BIẾN MẤT HẲN ---------- */
/* Canh cả lớp `ly-do-bt` lẫn placeholder anh Thắng chụp được: xoá một cái mà để lại cái kia là
   ô vẫn còn đó dưới một cái tên khác. */
t('🔴 không còn ô nhập lý do riêng trong khung cảnh báo',
	jsSrc.indexOf('ly-do-bt') < 0, jsSrc.indexOf('ly-do-bt'));
t('không còn gợi ý "VD: đổi máy đếm"',
	jsSrc.indexOf('VD: đổi máy đếm') < 0);
t('không còn câu nhắc thứ hai về cột Thực thu trong khung đỏ',
	jsSrc.indexOf('Và nhập đúng số tiền thật vào cột') < 0);

/* ---------- 2. NHƯNG CẢNH BÁO THÌ VẪN PHẢI HIỆN ---------- */
/* Bỏ ô nhập mà bỏ luôn dòng chữ là mất cả thứ anh Thắng muốn GIỮ.

   ⚠️ CANH CHỖ GÁN, KHÔNG CANH CHUỖI CÓ MẶT TRONG TỆP. Bản đầu của bài này chỉ hỏi "tệp có chứa
      câu ấy không" — mà chú thích ngay bên trên chỗ sửa cũng nhắc lại câu ấy, nên tháo hẳn phép
      gán ra khỏi `w.textContent` vẫn xanh. Phá thử bắt được đúng chỗ mù này. Nay bốc riêng
      NHÁNH bất thường ra rồi soi trong đó. */
const iBT = jsSrc.indexOf('} else if(batThuong){');
const jBT = jsSrc.indexOf('} else {', iBT);
t('bốc được nhánh vẽ cảnh báo bất thường', iBT > 0 && jBT > iBT);
const nhanhBT = (iBT > 0 && jBT > iBT) ? jsSrc.slice(iBT, jBT) : '';
t('🔴 nhánh ấy GÁN thẳng câu cảnh báo vào w.textContent',
	/w\.textContent\s*=\s*\(chiSoNguoc/.test(nhanhBT), nhanhBT.slice(0, 200));
t('🔴 vẫn còn dòng cảnh báo "Chỉ số sau nhỏ hơn trước"',
	nhanhBT.indexOf('⚠ Chỉ số sau nhỏ hơn trước') > 0);
t('vẫn còn dòng cảnh báo cho ca công thức ra ÂM',
	nhanhBT.indexOf('⚠ Công thức tính ra ÂM (QR lớn hơn Actual)') > 0);
/* Và cảnh báo phải NÓI RA phải làm gì — một dòng đỏ không kèm việc cần làm thì người đọc đứng
   nhìn. Trỏ vào hai cột đã có sẵn trên bảng. */
t('cảnh báo trỏ vào cột "Ghi chú" và cột "Thực thu tiền mặt"',
	/ghi lý do ở cột "Ghi chú" và nhập số tiền thật ở cột "Thực thu tiền mặt"/.test(nhanhBT));
/* Khung vẫn phải được BẬT lên — dựng đủ chữ mà để display:none thì không ai thấy. */
t('và khung cảnh báo được bật hiện', /w\.style\.display\s*=\s*''/.test(nhanhBT));

/* ---------- 3. LÝ DO NAY LẤY TỪ CỘT GHI CHÚ ---------- */
/* Bốc đúng khối chốt-trước-khi-gửi ra soi, chứ không soi cả tệp: `.note` xuất hiện ở hàng chục
   chỗ khác (ô ghi chú lúc lưu nháp, màn kế toán…), soi trần trụi là xanh nhờ một chỗ chẳng liên
   quan. */
const iKhoi = jsSrc.indexOf('} else if(chiSoNguoc||rawCashR<0){');
const jKhoi = jsSrc.indexOf('r.abnormalReason=ly;', iKhoi);
t('bốc được khối chốt trước khi gửi', iKhoi > 0 && jKhoi > iKhoi);
const khoi = (iKhoi > 0 && jKhoi > iKhoi) ? jsSrc.slice(iKhoi, jKhoi + 40) : '';
t('🔴 lý do lấy từ ô .note của chính hàng đó',
	/querySelector\('\.note'\)/.test(khoi), khoi.slice(0, 200));
t('và vẫn gửi lên máy chủ dưới tên abnormalReason',
	khoi.indexOf('r.abnormalReason=ly;') > 0);
/* Vẫn phải đòi ĐỦ HAI THỨ: có lý do VÀ có Thực thu. Bỏ một vế là mở lại đúng chỗ 28/08 bắt chặn. */
t('vẫn đòi đủ cả lý do lẫn Thực thu mới cho gửi',
	/if\(!ly\|\|!coTTR\)\{/.test(khoi), khoi.slice(0, 300));

/* ---------- 4. MÁY CHỦ: CHỐT GIỮ NGUYÊN, CÂU CHỐI TRỎ ĐÚNG Ô ---------- */
t('🔴 máy chủ VẪN chặn khi bất thường mà không có lý do',
	phpSrc.indexOf("if ( '' === $ly_do_bt ) {") > 0);
t('câu chối trỏ vào cột Ghi chú, không còn trỏ "ô đỏ" đã bỏ',
	phpSrc.indexOf('Ghi lý do ở cột Ghi chú') > 0
	&& phpSrc.indexOf('Ghi lý do ở ô đỏ') < 0);
t('câu báo lỗi trên trang cũng trỏ đúng cột Ghi chú',
	jsSrc.indexOf('ghi lý do ở cột "Ghi chú" và nhập đúng số tiền thật') > 0
	&& jsSrc.indexOf('ghi lý do ở ô đỏ') < 0);

/* ---------- 5. KHÔNG CHÉP GHI CHÚ HAI LẦN ---------- */
/* Lý do NAY CHÍNH LÀ ghi chú, nên phép ghép cũ ('⚠ CHỈ SỐ BẤT THƯỜNG: ' . lý_do . ' · ' . ghi_chú)
   sẽ in ra "…: đổi máy · đổi máy". Bốc thẳng nhánh ghép ra chạy thử bằng JS. */
const iGhep = phpSrc.indexOf("if ( '' !== $ly_do_bt ) {");
const jGhep = phpSrc.indexOf('}', phpSrc.indexOf('trim(', iGhep) );
t('bốc được nhánh ghép ghi chú', iGhep > 0);
const ghep = iGhep > 0 ? phpSrc.slice(iGhep, iGhep + 600) : '';
t('🔴 lý do trùng ghi chú thì KHÔNG ghép hai lần',
	/\$ly_do_bt === \$ghi_chu/.test(ghep), ghep.slice(0, 300));
t('nhưng khác nhau thì vẫn ghép đủ cả hai',
	/' · ' \. \$ghi_chu/.test(ghep));
t('và tiền tố cảnh báo vẫn còn để kế toán nhận ra ngay',
	ghep.indexOf('⚠ CHỈ SỐ BẤT THƯỜNG: ') > 0);

/* ---------- KẾT ---------- */
if (TRUOT.length) {
	console.log('\n✗ HỎNG ' + TRUOT.length + ' phép:');
	TRUOT.forEach(function (x) { console.log('  ✗ ' + x); });
	process.exit(1);
}
console.log('✓ SẠCH — ' + DAT + ' phép: chỉ số bất thường chỉ CẢNH BÁO, lý do lấy từ cột Ghi chú.');
