/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * LỊCH SỬ SỬA SỐ CỦA TỪNG GHẾ — dựng từ bảng `bc_undo`
 *
 * Anh Thắng 01/09/2026: *"thêm phần lịch sử sửa số, ngay chỗ ô sửa lại, chèn nhỏ thôi"*.
 *
 * 🔴 THỨ DỄ SAI NHẤT Ở ĐÂY LÀ CHIỀU THỜI GIAN. `bc_undo` chỉ giữ BẢN TRƯỚC KHI SỬA, nên một
 *    bước phải ghép "bản cũ thứ k → bản cũ thứ k+1", và bước CUỐI ghép với GIÁ TRỊ HIỆN TẠI.
 *    Ghép lệch một nhịp là lịch sử vẫn ra đủ số dòng, vẫn trông hợp lý, mà mọi con số dịch đi
 *    một bước — loại sai không ai phát hiện bằng mắt.
 *
 * ⚠️ KHÔNG DÒ CHUỖI. Hàm `lich_su_sua()` được CHẠY THẬT bằng PHP trên một bảng `bc_undo` giả,
 *    rồi so từng bước một. Dò chuỗi chỉ canh được cách viết hôm nay.
 *
 * Chạy: node tools/test/kiem-lich-su-sua.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const { execFileSync } = require('child_process');
const os = require('os');
const path = require('path');

let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them != null ? (' → ' + JSON.stringify(them)) : '')); } }
function teq(n, mong, thuc) { t(n + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }

/* ---------- Bốc THÂN HÀM ra khỏi tệp nguồn, không chép lại ----------
   Chép hàm sang bài kiểm là dựng bản thứ hai: bản chép xanh mãi kể cả khi bản thật đã đổi. */
const NGUON = 'vhcp-ghe/includes/class-vhg-ketoan.php';
const src = fs.readFileSync(NGUON, 'utf8');
const iH = src.indexOf('public static function lich_su_sua(');
t('bốc được hàm lich_su_sua() từ tệp nguồn', iH > 0);
/* Cắt tới hàm public kế tiếp — thân hàm có nhiều dấu ngoặc lồng nhau, đếm ngoặc bằng tay ở đây
   là dựng thêm một bộ phân tích cú pháp nữa để mà sai. */
const iSau = src.indexOf('public static function sua(', iH);
const than = src.slice(iH, iSau > iH ? iSau : src.length);
t('thân hàm có đủ vòng ghép cặp', than.indexOf('for ( $i = 0;') > 0);

/* ---------- Bệ đỡ: wpdb giả trả về đúng những hàng mình dựng ---------- */
const BE_DO = `<?php
define( 'ARRAY_A', 'ARRAY_A' );
class VHG_DB { public static function t( $x ) { return 'vhg_' . $x; } }
class FakeWpdb {
	public $hang = array();
	public function prepare( $q ) { $a = func_get_args(); array_shift( $a ); $this->args = $a; return $q; }
	/* 🔴 TÔN TRỌNG THỨ TỰ TRONG CÂU SQL. Bản đầu trả thẳng mảng đã dựng, nên đổi ORDER BY từ ASC
	   sang DESC trong hàm thật KHÔNG làm bài kiểm đỏ — mà chiều đọc chính là thứ dễ sai nhất ở
	   đây. Phá thử bắt được đúng chỗ mù ấy. Giả lập tối thiểu: đọc chiều rồi sắp theo id. */
	public function get_results( $q, $mode = null ) {
		$h = $this->hang;
		usort( $h, function ( $a, $b ) { return (int) $a['id'] <=> (int) $b['id']; } );
		if ( false !== stripos( (string) $q, 'ORDER BY id DESC' ) ) { $h = array_reverse( $h ); }
		return $h;
	}
}
$wpdb = new FakeWpdb();
class VHG_KeToan {
	__THAN__
}
function chay( $undo, $nay ) {
	global $wpdb;
	$wpdb->hang = $undo;
	return VHG_KeToan::lich_su_sua( 'R1', 'GHE1', $nay );
}
$ca = json_decode( file_get_contents( $argv[1] ), true );
echo json_encode( chay( $ca['undo'], $ca['nay'] ), JSON_UNESCAPED_UNICODE );
`;

const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'ls-'));
const fPhp = path.join(tmp, 'be.php');
fs.writeFileSync(fPhp, BE_DO.replace('__THAN__', than));

function goi(undo, nay) {
	const fIn = path.join(tmp, 'ca.json');
	fs.writeFileSync(fIn, JSON.stringify({ undo, nay }));
	const out = execFileSync('php', [fPhp, fIn], { encoding: 'utf8' });
	return JSON.parse(out);
}
/* Một hàng `bc_undo` như wpdb trả về: chi_tiet là chuỗi JSON của MỘT MẢNG chứa một bản ghi. */
function u(id, luc, boi, gt) {
	return { id: String(id), tao_luc: luc, boi: boi, chi_tiet: JSON.stringify([gt]) };
}

/* ---------- 1. CHƯA SỬA LẦN NÀO ---------- */
teq('chưa có lượt sửa nào thì lịch sử rỗng', [], goi([], { chi_so_sau: 100, tien_mat: 500, qr: 0 }));

/* ---------- 2. MỘT LƯỢT SỬA: bản cũ ghép với GIÁ TRỊ HIỆN TẠI ---------- */
let r = goi(
	[u(1, '2026-09-01 10:00:00', 'Kế toán A', { chi_so_sau: 100, tien_mat: 500, qr: 0 })],
	{ chi_so_sau: 100, tien_mat: 800, qr: 0 });
teq('một lượt sửa ra đúng một bước', 1, r.length);
teq('bước ấy ghép bản cũ với số HIỆN TẠI', [{ o: 'tiền mặt', cu: 500, moi: 800 }], r[0].doi);
teq('kèm lúc sửa', '2026-09-01 10:00:00', r[0].luc);
teq('và ai sửa', 'Kế toán A', r[0].boi);

/* ---------- 3. HAI LƯỢT: ghép NỐI TIẾP, không lệch nhịp ----------
   🔴 Đây là ca anh Thắng chụp được: 100.000 rồi 220.000. Nếu ghép lệch một nhịp thì bước đầu sẽ
      nói "500 → 220.000" (nhảy cóc qua mốc giữa) — vẫn ra hai dòng, vẫn trông hợp lý. */
r = goi([
	u(1, '2026-09-01 10:00:00', 'Kế toán A', { chi_so_sau: 100, tien_mat: 500, qr: 0 }),
	u(2, '2026-09-01 11:00:00', 'Kế toán B', { chi_so_sau: 100, tien_mat: 100000, qr: 0 }),
], { chi_so_sau: 100, tien_mat: 220000, qr: 0 });
teq('hai lượt ra hai bước', 2, r.length);
teq('🔴 bước 1 nối vào mốc GIỮA, không nhảy cóc tới số hiện tại',
	[{ o: 'tiền mặt', cu: 500, moi: 100000 }], r[0].doi);
teq('🔴 bước 2 mới ghép với số hiện tại',
	[{ o: 'tiền mặt', cu: 100000, moi: 220000 }], r[1].doi);
teq('thứ tự là CŨ TRƯỚC MỚI SAU', ['2026-09-01 10:00:00', '2026-09-01 11:00:00'],
	[r[0].luc, r[1].luc]);

/* ---------- 4. NHIỀU Ô ĐỔI CÙNG MỘT LƯỢT ---------- */
r = goi([u(1, '2026-09-01 10:00:00', 'A', { chi_so_sau: 100, tien_mat: 500, qr: 0 })],
	{ chi_so_sau: 130, tien_mat: 500, qr: 200 });
teq('kể đủ mọi ô đã đổi trong một lượt',
	[{ o: 'chỉ số sau', cu: 100, moi: 130 }, { o: 'QR', cu: 0, moi: 200 }], r[0].doi);

/* ---------- 5. LƯỢT KHÔNG ĐỔI Ô NÀO THÌ KHÔNG KỂ ----------
   Sửa mỗi ghi chú cũng ghi một bản undo. Kể nó ra thành "đã sửa" mà không nói sửa gì thì người
   đọc mất công dò xem có gì khác. */
r = goi([u(1, '2026-09-01 10:00:00', 'A', { chi_so_sau: 100, tien_mat: 500, qr: 0, ghi_chu: 'cũ' })],
	{ chi_so_sau: 100, tien_mat: 500, qr: 0, ghi_chu: 'mới' });
teq('sửa mỗi ghi chú thì không kể thành một bước', [], r);

/* ---------- 6. SỐ VỀ TỪ JSON CÓ THỂ LÀ CHUỖI ----------
   🔴 wpdb trả cột số dưới dạng CHUỖI, và undo đi qua json_encode nữa. So bằng `!==` trần trụi là
      "500" !== 500 → mọi ô đều "đổi" ở mọi lượt, lịch sử đầy dòng rác. */
r = goi([u(1, '2026-09-01 10:00:00', 'A', { chi_so_sau: '100', tien_mat: '500', qr: '0' })],
	{ chi_so_sau: 100, tien_mat: 500, qr: 0 });
teq('🔴 số dạng chuỗi và số nguyên bằng nhau thì KHÔNG kể là đổi', [], r);

/* ---------- 7. NULL KHÁC 0 ----------
   "chưa có chỉ số" và "chỉ số 0" là hai chuyện khác hẳn — gộp lại là giấu mất một lượt sửa thật. */
r = goi([u(1, '2026-09-01 10:00:00', 'A', { chi_so_sau: null, tien_mat: 0, qr: 0 })],
	{ chi_so_sau: 0, tien_mat: 0, qr: 0 });
teq('🔴 null → 0 vẫn là một thay đổi', [{ o: 'chỉ số sau', cu: null, moi: 0 }], r[0] ? r[0].doi : null);

/* ---------- 8. LƯỢT ĐÃ HOÀN TÁC KHÔNG ĐƯỢC KỂ ----------
   Chốt này nằm trong câu SQL (`da_hoan_tac=0`), nên soi thẳng câu ấy: bệ đỡ giả không chạy SQL
   thật nên không kiểm được bằng cách chạy. */
t('🔴 câu truy vấn loại lượt đã hoàn tác',
	/da_hoan_tac\s*=\s*0/.test(than), than.slice(0, 400));
t('và chỉ lấy đúng việc "sua" của đúng ghế này',
	/viec='sua' AND ly_do=%s/.test(than) && than.indexOf("$rid . '·' . $ma") > 0);

/* ---------- 9. HAI ĐƯỜNG SỬA ĐỀU PHẢI GHI VẾT ----------
   🔴 Nếu chỉ đường kế toán ghi undo thì lịch sử kể sai: nhân viên sửa 3 lần qua màn 24h mà màn
      hình bảo chưa ai đụng vào. */
const bc = fs.readFileSync('vhcp-ghe/includes/class-vhg-baocao.php', 'utf8');
const iSD = bc.indexOf('public static function sua_dong(');
const iSDh = bc.indexOf('public static function lich_su(', iSD);
const thanSD = bc.slice(iSD, iSDh > iSD ? iSDh : bc.length);
t('🔴 màn sửa 24h của nhân viên CŨNG ghi bản cũ vào bc_undo',
	thanSD.indexOf("VHG_DB::t( 'bc_undo' )") > 0);
t('cùng dạng bản ghi với bên kế toán (viec=sua, khoá report·ghế)',
	/'viec' => 'sua'/.test(thanSD) && thanSD.indexOf("$rid . '·' . $ma") > 0);
t('và ghi vết TRƯỚC khi cập nhật dòng — ghi sau là lưu mất số cũ',
	thanSD.indexOf("VHG_DB::t( 'bc_undo' )") < thanSD.indexOf("$wpdb->update( VHG_DB::t( 'bc_dong' ), $data_up"));

/* ---------- 10. GIAO DIỆN: chèn NHỎ, ngay chỗ ô Sửa ---------- */
const tr = fs.readFileSync('vhcp-ghe/includes/class-vhg-trang.php', 'utf8');
t('có khối dựng lịch sử trên màn', tr.indexOf('function ktdLichSu(c){') > 0);
const iR = tr.indexOf('function ktdRow(o,c,m,reload,locked){');
const jR = tr.indexOf('function ktdSuaRow(', iR);
const thanRow = (iR > 0 && jR > iR) ? tr.slice(iR, jR) : '';
t('🔴 gắn vào ĐÚNG ô chứa nút Sửa/Xoá', /tdA\.appendChild\(ls\)/.test(thanRow), thanRow.slice(-400));
t('vẫn hiện khi báo cáo đã khoá — khoá là thôi sửa, không phải thôi xem',
	thanRow.indexOf('var ls=ktdLichSu(c);') > thanRow.indexOf('if(!locked){')
	&& /\}\s*\n\s*\/\*[^*]*[\s\S]*?var ls=ktdLichSu\(c\);/.test(thanRow), null);
const iLS = tr.indexOf('function ktdLichSu(c){');
const jLS = tr.indexOf('function ktdRow(', iLS);
const thanLS = (iLS > 0 && jLS > iLS) ? tr.slice(iLS, jLS) : '';
t('"chèn nhỏ thôi": chữ 11px và mờ đi', /font-size:11px/.test(thanLS) && /opacity:\./.test(thanLS));
t('🔴 mặc định chỉ hiện lượt GẦN NHẤT, phần còn lại gập lại',
	/con\.style\.display\s*=\s*'none'/.test(thanLS) && /còn '\+\(xuoi\.length-1\)\+' lượt/.test(thanLS));
t('lượt gần nhất đứng trên', /ds\.slice\(\)\.reverse\(\)/.test(thanLS));
/* 🔴 Giờ máy chủ là giờ ĐỊA PHƯƠNG; dựng lại bằng hàm Date của trình duyệt là nó hiểu thành UTC
   rồi lệch vài tiếng. Giờ sai trên nhật ký còn tệ hơn không có giờ.

   ⚠️ GỠ CHÚ THÍCH RA TRƯỚC KHI SOI. Chính lời giải thích ngay trên chỗ sửa cũng NHẮC LẠI cái tên
      đang bị cấm, nên soi trần trụi là phép thử tự đỏ vì câu chữ của mình. Đã dính đúng lần này. */
const thanLS_ma = thanLS.replace(/\/\*[\s\S]*?\*\//g, '').replace(/\/\/[^\n]*/g, '');
t('🔴 KHÔNG dựng lại giờ bằng hàm Date của trình duyệt', thanLS_ma.indexOf('new Date(') < 0,
	thanLS_ma.slice(0, 200));

/* ---------- 11. GHI CHÚ KHÔNG PHÌNH RA NHIỀU DẤU "THỰC THU GHI ĐÈ" ----------
   Anh Thắng gửi ảnh GO-TDM-1: "Thực thu ghi đè: 100.000đ · Thực thu ghi đè: 220.000đ". */
const iKS = src.indexOf('public static function sua(');
const jKS = src.indexOf('public static function duyet(', iKS);
const thanKS = (iKS > 0 && jKS > iKS) ? src.slice(iKS, jKS) : '';
/* Khớp bằng indexOf trên đúng chuỗi mã: viết cái này thành regex JS thì mỗi dấu gạch chéo ngược
   phải nhân đôi hai lần (một cho chuỗi PHP, một cho regex), và đã escape hụt một lần rồi. */
const DON_DAU = "preg_replace( '/\\s*·?\\s*Thực thu ghi đè:";
t('🔴 đường kế toán DỌN dấu ghi đè cũ trước khi gắn dấu mới',
	thanKS.indexOf(DON_DAU) > 0, thanKS.slice(0, 200));
t('và vẫn gắn lại đúng một dấu mới',
	thanKS.indexOf("'Thực thu ghi đè: ' . number_format( $cash") > 0);
/* Đối chứng: bên nhân viên vốn đã dọn đúng — chắc bài đang đọc đúng chỗ. */
t('đối chứng: đường nhân viên 24h vẫn dọn như cũ', bc.indexOf(DON_DAU) > 0);

/* ---------- KẾT ---------- */
try { fs.rmSync(tmp, { recursive: true, force: true }); } catch (e) {}
if (TRUOT.length) {
	console.log('\n✗ HỎNG ' + TRUOT.length + ' phép:');
	TRUOT.forEach(function (x) { console.log('  ✗ ' + x); });
	process.exit(1);
}
console.log('✓ SẠCH — ' + DAT + ' phép: lịch sử sửa số dựng đúng chiều, cả hai đường sửa đều để vết.');
