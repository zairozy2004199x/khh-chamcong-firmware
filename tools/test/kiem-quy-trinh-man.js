/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * QUY TRÌNH BÁO CÁO CƠ SỞ HẰNG NGÀY — MÀN PHẢI NỐI ĐÚNG VÀO LÕI, VÀ CHẠY THẬT PHẦN VẼ.
 *
 * Anh Thắng 24/09/2026: *"Làm quy trình báo cáo hằng ngày tự động"* — *"Báo cáo cơ sở thôi"*.
 * `kiem-quy-trinh.php` chạy thật máy chủ. Bài này canh:
 *   · tab Nhập báo cáo: khối "việc còn treo" (GET quy-trinh, bấm là nhảy ngày), thanh bốn bước từ
 *     `r.quy_trinh`, lưu xong tải lại việc;
 *   · tab Quản trị: khối cấu hình (bật, hạn, lùi, email) POST quy-trinh; "Tổng hợp và gửi ngay" LƯU
 *     TRƯỚC rồi POST quy-trinh-chay; bảng hôm qua; nhật ký; chỉ vẽ khi có `cf` (quản trị);
 *   · chạy thật veViec / veBuoc trên DOM giả: không việc -> "Đã chốt hết"; quá hạn -> lớp xau; bước
 *     kho null -> lớp khong; kho false -> chua.
 *
 * Chạy: node tools/test/kiem-quy-trinh-man.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const src = fs.readFileSync('wordpress/khh-doanh-thu/assets/doanh-thu.js', 'utf8');
const css = fs.readFileSync('wordpress/khh-doanh-thu/assets/doanh-thu.css', 'utf8');
const js = src.replace(/\/\*[\s\S]*?\*\//g, ' ').replace(/(^|[^:'"])\/\/[^\n]*/g, '$1');
let dat = 0; const hong = [];
function t(ten, dk) { if (dk) dat++; else hong.push(ten); }
function boc(ten) {
  const i = js.indexOf('  function ' + ten + '(');
  if (i < 0) return '';
  const j = js.indexOf('\n  }\n', i);
  return js.slice(i, j + 4);
}

/* ---- tab Nhập báo cáo ---- */
const dn = boc('dungNhap');
t('khung Nhập có #bcViec trên cùng và #bcBuoc dưới ô chọn ngày', /id="bcViec" class="bc-viec"/.test(dn) && /id="bcBuoc" class="bc-buoc"/.test(dn) && dn.indexOf('id="bcViec"') < dn.indexOf('id="bcNgay"') && dn.indexOf('id="bcBuoc"') < dn.indexOf('id="bcPos"'));
t('dungNhap gọi taiViec(); bấm dòng tóm tắt là sang tab Cảnh báo (không còn thẻ ngày ở tab Nhập)', /taiViec\(\);/.test(dn) && /data-sang-cb/.test(dn) && /doiTab\('canhbao'\)/.test(dn) && !/data-viec-ngay/.test(dn));
/* Anh Thắng 24/09/2026: "cho nó sang tab cảnh báo đi, đây tab báo cáo mà". */
t('🔴 có tab Cảnh báo (#dtTabCB, khung #dtTabCanhBao) và doiTab gọi taiCanhBao', /data-tab="canhbao" id="dtTabCB"/.test(js) && /id="dtTabCanhBao"/.test(js) && /if \(t === 'canhbao'\) taiCanhBao\(\);/.test(boc('doiTab')));
t('tab Cảnh báo GET quy-trinh và vẽ veCanhBao', /api\('quy-trinh'\)/.test(boc('taiCanhBao')) && /veCanhBao\(o, r\)/.test(boc('taiCanhBao')));
const vcb = boc('veCanhBao');
t('🔴 gom theo cơ sở: bảng Cơ sở / Chưa chốt / Quá hạn / Ngày, thẻ điện thoại', /<th>Chưa chốt<\/th><th>Quá hạn<\/th>/.test(vcb) && /class="bang-the bang-cuon"/.test(vcb) && /nhom\[x\.cua_hang\]\.push\(x\)/.test(vcb));
t('bấm thẻ ngày ở tab Cảnh báo -> đặt S.nhapNgay/S.nhapCH, sang tab Nhập, napBaoCao', /S\.nhapNgay = b\.getAttribute\('data-viec-ngay'\)/.test(boc('noiCanhBao')) && /doiTab\('nhap'\)/.test(boc('noiCanhBao')) && /napBaoCao\(\)/.test(boc('noiCanhBao')));
t('nhãn tab mang số ngày chưa chốt, đỏ khi có quá hạn; gọi lúc mở trang và sau khi tải việc', /class="dem' \+ \(qh \? ' xau' : ''\)/.test(boc('nhanCanhBao')) && /demCanhBao\(\);/.test(js) && /nhanCanhBao\(r\)/.test(boc('taiViec')));
t('taiViec GET quy-trinh', /api\('quy-trinh'\)/.test(boc('taiViec')));
t('napBaoCao vẽ bốn bước từ r.quy_trinh', /veBuoc\(r\.quy_trinh\)/.test(boc('napBaoCao')));
t('🔴 lưu xong tải lại danh sách việc', /napBaoCao\(\);\s*taiViec\(\);/.test(boc('luuBaoCao')));
const vb = boc('veBuoc');
t('bốn bước đúng tên và thứ tự', /\['Số máy POS về'/.test(vb) && /\['Cơ sở khai'/.test(vb) && /\['Sổ kho'/.test(vb) && /\['Chốt ngày'/.test(vb) && vb.indexOf('Số máy POS về') < vb.indexOf('Cơ sở khai') && vb.indexOf('Sổ kho') < vb.indexOf('Chốt ngày'));

/* ---- tab Quản trị ---- */
t('taiQuanTri gọi taiQuyTrinh(o)', /taiQuyTrinh\(o\);/.test(boc('taiQuanTri')));
t('🔴 chỉ vẽ khối khi có cf (quản trị) — người thường không thấy cấu hình', /if \(r && r\.cf\) veQuyTrinh\(o, r\)/.test(boc('taiQuyTrinh')));
const vq = boc('veQuyTrinh');
t('khối #dtQuyTrinh có bật / hạn / lùi / email (ô qua o1 mang data-qt)', /data-qt="bat"/.test(vq) && /data-qt="' \+ ten \+ '"/.test(vq) && /o1\('Hạn chốt[^']*', 'han'/.test(vq) && /o1\('Nhìn lùi \(ngày\)', 'lui'/.test(vq) && /, 'email', c\.email/.test(vq));
t('nút Lưu và Tổng hợp và gửi ngay', /id="dtQtLuu"/.test(vq) && /id="dtQtChay"/.test(vq));
t('bảng hôm qua: Cơ sở / Máy POS / Trạng thái / Két lệch / Món lệch / Người, thẻ điện thoại', /<th>Máy POS<\/th><th>Trạng thái<\/th><th>Két lệch<\/th><th>Món lệch<\/th><th>Người<\/th>/.test(vq) && /class="bang-the bang-cuon"/.test(vq));
t('nhật ký 30 lượt', /Nhật ký 30 lượt gần nhất/.test(vq));
t('nói rõ hệ KHÔNG tự điền số thay cơ sở', /Hệ không tự điền số/.test(vq));
const nq = boc('noiQuyTrinh');
t('Lưu POST quy-trinh', /api\('quy-trinh', \{ method: 'POST', body: thu\(\) \}\)/.test(nq));
t('🔴 "gửi ngay" LƯU TRƯỚC rồi mới POST quy-trinh-chay', /api\('quy-trinh', \{ method: 'POST', body: thu\(\) \}\)\s*\.then\(function \(\) \{ return api\('quy-trinh-chay', \{ method: 'POST' \}\); \}\)/.test(nq));

/* ---- CSS ---- */
t('CSS có bc-viec, viec .vien.qua-han, buoc span.xong/.chua/.khong, tr.qua-han', /\.khh-dt \.bc-viec-o\.xau/.test(css) && /\.khh-dt \.viec \.vien\.qua-han/.test(css) && /\.khh-dt \.buoc span\.xong/.test(css) && /\.khh-dt \.buoc span\.chua i/.test(css) && /\.khh-dt \.buoc span\.khong/.test(css) && /\.khh-dt tr\.qua-han td/.test(css));

/* ---- chạy thật veViec / veBuoc trên DOM giả ---- */
function taoO() {
  return { innerHTML: '' };
}
const G = { querySelector(sel) { return G._o[sel] || null; }, _o: {} };
const ctx = {};
const than = boc('veViec') + boc('veCanhBao') + boc('veBuoc') + '\n  function noiCanhBao() {}' +
  '\n  var QT_NHAN = { chua_fabi: "chưa có số máy POS", chua_nop: "chưa nộp", da_luu: "đã lưu, chưa chốt", da_chot: "đã chốt" };' +
  '\n  return { veViec: veViec, veCanhBao: veCanhBao, veBuoc: veBuoc };';
const esc = (s) => String(s == null ? '' : s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const ngayVN = (s) => (s ? s.split('-').reverse().join('/') : '');
const q = (sel) => G.querySelector(sel);
let m;
try {
  m = new Function('esc', 'ngayVN', 'q', than)(esc, ngayVN, q);
} catch (e) { hong.push('không nạp được veViec/veCanhBao/veBuoc: ' + e.message); }
if (m) {
  const o = taoO();
  m.veViec(o, { viec: [], han: '10:00' });
  t('tab Nhập, không việc -> "Đã chốt hết" kèm giờ hạn', /Đã chốt hết/.test(o.innerHTML) && /10:00/.test(o.innerHTML) && /bc-viec-o xong/.test(o.innerHTML));
  const VIEC = [
    { ngay: '2026-09-22', cua_hang: 'Gò Vấp', trang_thai: 'chua_nop', qua_han: true },
    { ngay: '2026-09-23', cua_hang: 'Gò Vấp', trang_thai: 'da_luu', qua_han: false },
    { ngay: '2026-09-23', cua_hang: 'Tân Phú', trang_thai: 'chua_fabi', qua_han: false },
  ];
  m.veViec(o, { han: '10:00', viec: VIEC });
  t('🔴 tab Nhập chỉ MỘT DÒNG: 3 ngày chưa chốt · 1 quá hạn + nút sang tab Cảnh báo, KHÔNG có thẻ ngày', /<b>3 ngày chưa chốt<\/b>/.test(o.innerHTML) && /<b>1 quá hạn<\/b>/.test(o.innerHTML) && /data-sang-cb="1"/.test(o.innerHTML) && !/data-viec-ngay/.test(o.innerHTML));
  const o2 = { innerHTML: '', addEventListener() {} };
  m.veCanhBao(o2, { han: '10:00', viec: VIEC });
  const cb = o2.innerHTML;
  t('🔴 tab Cảnh báo gom theo cơ sở: Gò Vấp một dòng 2 ngày · 1 quá hạn, Tân Phú một dòng 1 ngày', /<b>Gò Vấp<\/b>/.test(cb) && /<b>Tân Phú<\/b>/.test(cb) && (cb.match(/<tr[ >]/g) || []).length === 3 && /data-nhan="Chưa chốt">2</.test(cb) && /data-nhan="Chưa chốt">1</.test(cb));
  t('quán có quá hạn xếp trước, dòng tô đỏ', cb.indexOf('Gò Vấp') < cb.indexOf('Tân Phú') && /<tr class="qua-han">/.test(cb));
  t('mỗi ngày một thẻ mang ngày + cơ sở, ngày quá hạn lớp qua-han, nhãn tiếng Việt', /class="vien qua-han" type="button" data-viec-ngay="2026-09-22" data-viec-ch="Gò Vấp"/.test(cb) && /22\/09<span>chưa nộp<\/span>/.test(cb) && /23\/09<span>chưa có số máy POS<\/span>/.test(cb));
  /* Anh Thắng 25/09/2026: "Ngày nào bấm nộp sẽ hiện xanh, chứ không phải ẩn" — `lich` có cả ngày đã chốt. */
  const LICH = VIEC.concat([{ ngay: '2026-09-21', cua_hang: 'Gò Vấp', trang_thai: 'da_chot', qua_han: false }, { ngay: '2026-09-21', cua_hang: 'Tân Phú', trang_thai: 'da_luu', qua_han: true }]);
  m.veCanhBao(o2, { han: '10:00', viec: VIEC, lich: LICH });
  const cb2 = o2.innerHTML;
  t('🔴 có lich -> bày cả ngày đã chốt thành viên XANH (lớp xong), vẫn bấm mở được ngày ấy', /class="vien xong" type="button" data-viec-ngay="2026-09-21" data-viec-ch="Gò Vấp"/.test(cb2) && /21\/09<span>đã chốt<\/span>/.test(cb2));
  t('đã lưu chưa chốt -> viên vàng (luu), quá hạn thêm qua-han', /class="vien luu qua-han" type="button" data-viec-ngay="2026-09-21" data-viec-ch="Tân Phú"/.test(cb2));
  t('cột Chưa chốt chỉ đếm ngày chưa chốt (Gò Vấp 2 dù có 3 viên; Tân Phú 2 vì đã lưu vẫn là chưa chốt); tiêu đề đếm 4 việc treo', (cb2.match(/data-nhan="Chưa chốt">2</g) || []).length === 2 && /4 ngày×cơ sở chưa chốt/.test(cb2));
  m.veCanhBao(o2, { han: '10:00', viec: [], lich: [{ ngay: '2026-09-23', cua_hang: 'Gò Vấp', trang_thai: 'da_chot', qua_han: false }] });
  t('tab Cảnh báo, hết việc treo nhưng có ngày đã chốt -> vẫn bày bảng với viên xanh và ✓ ở cột Chưa chốt', /class="vien xong"/.test(o2.innerHTML) && /data-nhan="Chưa chốt" style="color:var\(--tot\)">✓</.test(o2.innerHTML) && !/Mọi cơ sở đã chốt/.test(o2.innerHTML));
  m.veCanhBao(o2, { han: '10:00', viec: [] });
  t('máy chủ cũ không có lich, không việc -> câu trống', /Chưa có ngày nào/.test(o2.innerHTML));

  G._o['#bcBuoc'] = taoO();
  m.veBuoc({ buoc: { fabi: true, khai: true, kho: null, chot: false }, qua_han: false, han: '2026-09-24 10:00' });
  let b = G._o['#bcBuoc'].innerHTML;
  t('bước xong đánh ✓, bước chưa đánh số, kho null mờ (khong)', /class="xong"[^>]*><i>✓<\/i>Số máy POS về/.test(b) && /class="xong"[^>]*><i>✓<\/i>Cơ sở khai/.test(b) && /class="khong"[^>]*><i>3<\/i>Sổ kho/.test(b) && /class="chua"[^>]*><i>4<\/i>Chốt ngày/.test(b));
  t('chưa chốt, chưa quá hạn -> ghi "hạn 10:00 24/09/2026"', /<b class="han">hạn 10:00 24\/09\/2026<\/b>/.test(b) && !/buoc xau/.test(b));
  m.veBuoc({ buoc: { fabi: true, khai: false, kho: false, chot: false }, qua_han: true, han: '2026-09-24 10:00' });
  b = G._o['#bcBuoc'].innerHTML;
  t('🔴 quá hạn -> thanh đỏ, ghi "quá hạn 10:00", kho false = chua (không mờ)', /class="buoc xau"/.test(b) && /<b class="han">quá hạn 10:00<\/b>/.test(b) && /class="chua"[^>]*><i>3<\/i>Sổ kho/.test(b));
  m.veBuoc({ buoc: { fabi: true, khai: true, kho: true, chot: true }, qua_han: false, han: '2026-09-24 10:00' });
  b = G._o['#bcBuoc'].innerHTML;
  t('đã chốt -> bốn ✓, không còn dòng hạn', (b.match(/<i>✓<\/i>/g) || []).length === 4 && !/class="han"/.test(b));
  m.veBuoc(null);
  t('không có quy_trinh (máy chủ cũ) -> để trống, không nổ', '' === G._o['#bcBuoc'].innerHTML);
}

if (hong.length) {
  console.log('\n✗ HỎNG ' + hong.length + ' phép (đạt ' + dat + '):');
  hong.forEach((h) => console.log('   · 🔴 ' + h));
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + dat + ' phép: màn nối đúng cổng quy-trinh, việc treo và bốn bước vẽ đúng.');
