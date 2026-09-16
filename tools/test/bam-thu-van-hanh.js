/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * BẤM THẬT VÀO TRANG /van-hanh — CHẠY TRONG CHROMIUM
 *
 * 🔴 VÌ SAO CẦN, TRONG KHI ĐÃ CÓ 79 PHÉP THỬ PHÍA MÁY CHỦ.
 *
 *    Bài `kiem-van-hanh.php` canh MÁY CHỦ: quyền, tiền, khoá duy nhất. Nó không biết gì về việc
 *    cái nút có gọi được hàm hay không. Ngày 23/08/2026 ở trang /ghe đã có đúng một lỗi kiểu ấy:
 *    hàm khai lộn phạm vi, mã CÓ trong nguồn nên mọi phép thử canh chuỗi đều xanh, mà bấm thì
 *    không có gì xảy ra.
 *
 *    Bài này nạp trang thật vào Chromium, gõ PIN, bấm VÀO, mở màn Doanh thu, gõ số vé, bấm LƯU —
 *    và đòi trang phải gửi lên đúng việc, đúng số lượng, KHÔNG kèm tổng tiền.
 *
 * ⚠️ Máy chủ ở đây là GIẢ (chặn `fetch` trong trang). Bài này không kiểm máy chủ — phần ấy đã có
 *    bài PHP. Nó kiểm đúng một thứ: trang có nói chuyện đúng cách không.
 *
 * Chạy: node tools/test/bam-thu-van-hanh.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

let DAT = 0; const TRUOT = [];
function t(ten, dung, them) {
  if (dung) { DAT++; return; }
  TRUOT.push(ten + (them === undefined ? '' : ' — ' + JSON.stringify(them)));
}

/* ---- 1. Dựng HTML thật từ template PHP ------------------------------------------------------
   Không chép tay HTML sang đây: chép tay là bài kiểm canh một bản sao, sửa template thật mà
   quên sửa bản sao thì bài vẫn xanh trong khi trang đã hỏng. */
const goc = path.join(__dirname, '..', '..');
const tmpl = path.join(goc, 'wordpress/vhcp-van-hanh/templates/trang.php');
const be = `<?php
define('ABSPATH', __DIR__);
function esc_url_raw($u){ return $u; }
function rest_url($p){ return 'http://may-chu-gia.test/wp-json/' . $p; }
function wp_json_encode($x){ return json_encode($x, JSON_UNESCAPED_UNICODE); }
class VHVH_API { const NS = 'vhvh/v1'; }
class VHVH_Tien { const NHOM_NHAN = array('le'=>'Vé lẻ','combo'=>'Combo','le_tet'=>'Ngày lễ',
  'online'=>'Vé online','free'=>'Vé miễn phí','them'=>'Bán thêm'); }
require ${JSON.stringify(tmpl)};
`;
const tmpDir = fs.mkdtempSync('/tmp/vh-bam-');
const bePhp = path.join(tmpDir, 'be.php');
fs.writeFileSync(bePhp, be);
let html;
try {
  html = execFileSync('php', [bePhp], { encoding: 'utf8' });
} catch (e) {
  console.error('🔴 Không dựng được HTML từ template:', e.stderr || e.message);
  process.exit(1);
}
t('template dựng ra HTML có thân trang', html.indexOf('id="ung-dung"') >= 0);

/* ---- 2. Máy chủ giả, nhét vào trang trước khi mã của trang chạy --------------------------- */
const gia = `
<script>
window.__GOI = [];
window.fetch = function(url, opt){
  var d = JSON.parse(opt.body);
  window.__GOI.push({ than: d, the: opt.headers['X-VHVH-The'] || '' });
  var j;
  switch (d.viec) {
    case 'dang_nhap':
      j = d.pin === '246810'
        ? { ok:true, the:'THE-GIA-123', toi:{ ten:'CHT C', vai:'cua_hang_truong',
            coso:'GO BÀ RỊA', ds_coso:['GO BÀ RỊA'],
            man:['tong_quan','su_co','tien','checklist','kho'],
            url_cham_cong:'http://vi-du.test/cham-cong',
            muc:[
              { nhom:'TỔNG QUAN', muc:[{ma:'tong_quan',ten:'Tổng quan',icon:'🏠',xong:1}] },
              { nhom:'SỰ CỐ & CẢNH BÁO', muc:[{ma:'su_co',ten:'Sự cố',icon:'⚠️',xong:1}] },
              { nhom:'VẬN HÀNH CƠ SỞ', muc:[
                  {ma:'cham_cong',ten:'Chấm công',icon:'🕒',xong:0,noi:'cham_cong'},
                  {ma:'checklist',ten:'Checklist',icon:'📋',xong:1},
                  {ma:'kho',ten:'Kiểm tra kho',icon:'📦',xong:1},
                  {ma:'danh_gia',ten:'Đánh giá nhân viên',icon:'⭐',xong:0}] },
              { nhom:'KINH DOANH', muc:[{ma:'tien',ten:'Doanh thu & Chi phí',icon:'💰',xong:1}] }
            ] } }
        : { ok:false, error:'PIN không đúng hoặc chưa được cấp' };
      break;
    case 'tong_quan':
      j = { ok:true, hnay:'2026-09-16', su_co:[], so:{
            so_coso:1, so_nhan_su:6, checklist_tb:null, su_co_mo:0,
            thu:5000000, chi:300000, khach:48, cho_duyet:2,
            hang:[{ coso:'GHOST HOUSE - GO BÀ RỊA', thu:5000000, chi_tieu:null, phan_tram:null,
                    checklist:null, su_co_mo:0, bc_hnay:0, diem:60, trang_thai:'co_van_de' }] } };
      break;
    case 'tien_doc':
      j = { ok:true, ban:null, ds_ve:[
        { khoa:'ve_1luot', nhan:'Vé 1 lượt', gia:100000, nhom:'le', vao_thu:1, vao_khach:1 },
        { khoa:'bua_le', nhan:'Bùa chú (lẻ)', gia:30000, nhom:'them', vao_thu:1, vao_khach:0 },
        { khoa:'online_1luot', nhan:'Vé 1 lượt online', gia:100000, nhom:'online',
          vao_thu:0, vao_khach:1 } ] };
      break;
    case 'tien_luu':
      j = { ok:true, ban:{ tt:'duyet', tong_thu:1060000, tong_chi:0, khach:10,
            nguoi_duyet:'CHT C', ve:d.ve, tra_tien:{}, khach_gio:[], chi:[], thu_khac:[], ghi:'' } };
      break;
    case 'cl_doc':
      j = { ok:true, ban:null, dm:[
        { khoa:'thu_ngan', ten:'Quầy thu ngân', muc:['Lau mặt bàn','Vệ sinh mặt tiền','Bật tivi'] },
        { khoa:'ki_thuat', ten:'Phòng kĩ thuật', muc:['Kiểm tra bộ đàm','Kiểm tra camera'] } ] };
      break;
    case 'cl_luu':
      j = { ok:true, ban:{ xong:Object.keys(d.muc||{}).length, tong:5, nguoi:'CHT C', muc:d.muc } };
      break;
    case 'kho_doc':
      j = { ok:true, tuan_tu:'2026-09-14', ban:null, so_sanh:{ choi:{lech:-4,hao:null} }, dm:[
        { khoa:'van_hanh', ten:'Đồ vận hành', muc:[
            {khoa:'choi',ten:'Chổi',kieu:'dem'},
            {khoa:'tu_tho',ten:'Tủ thờ',kieu:'dem_tinh'}] } ] };
      break;
    case 'kho_luu': j = { ok:true, ban:{ muc:d.muc, nguoi:'CHT C' } }; break;
    case 'su_co_ds': j = { ok:true, ds:[] }; break;
    case 'su_co_them':
      j = (d.tieu_de && d.giao_ten && d.han) ? { ok:true, id:1 }
        : { ok:false, error:'Thiếu ô bắt buộc.' };
      break;
    default: j = { ok:false, error:'Việc không hợp lệ.' };
  }
  return Promise.resolve({ status:200, text: function(){ return Promise.resolve(JSON.stringify(j)); } });
};
</script>
`;
const trang = path.join(tmpDir, 'trang.html');
fs.writeFileSync(trang, html.replace('<div id="ung-dung"></div>', gia + '<div id="ung-dung"></div>'));

/* ---- 3. Bấm ------------------------------------------------------------------------------- */
(async function () {
  let chromium;
  try { ({ chromium } = require('playwright')); }
  catch (e) {
    console.log('⚠️  Bỏ qua: máy này chưa có playwright (' + e.message + ')');
    process.exit(0);
  }
  /* Chromium cài sẵn trong máy dựng nằm ở /opt/pw-browsers và có thể LỆCH BẢN với gói
     playwright vừa cài. Playwright chỉ tìm đúng thư mục mang số bản của nó, không thấy là nó
     đòi tải về — mà máy này chặn mạng ra ngoài. Nên chỉ thẳng đường dẫn khi có. */
  const fsx = require('fs');
  const ung = ['/opt/pw-browsers/chromium/chrome-linux/chrome',
               '/opt/pw-browsers/chromium-1194/chrome-linux/chrome']
              .filter(function(p){ try { return fsx.existsSync(p); } catch (e) { return false; } });
  const tuyChon = ung.length ? { executablePath: ung[0] } : {};
  let trinh;
  try { trinh = await chromium.launch(tuyChon); }
  catch (e) {
    console.log('⚠️  Bỏ qua: không mở được Chromium (' + e.message.split('\n')[0] + ')');
    process.exit(0);
  }
  const trang2 = await trinh.newPage();
  const loiJs = [];
  trang2.on('pageerror', function (e) { loiJs.push(String(e)); });
  await trang2.goto('file://' + trang);

  /* --- màn đăng nhập --- */
  t('mở trang ra màn ĐĂNG NHẬP, không phải màn quản trị',
    await trang2.locator('#oPin').isVisible());
  t('🔴 chưa đăng nhập thì KHÔNG có thanh điều hướng',
    0 === await trang2.locator('[data-man]').count());

  /* PIN sai: phải hiện đúng câu của máy chủ, và KHÔNG vào được. */
  await trang2.fill('#oPin', '111111');
  await trang2.click('#btVao');
  await trang2.waitForTimeout(150);
  t('PIN sai thì hiện nguyên văn câu chối của máy chủ',
    (await trang2.locator('.bao-loi').innerText()).indexOf('PIN không đúng') >= 0);
  t('🔴 PIN sai thì vẫn đứng ngoài', await trang2.locator('#oPin').isVisible());

  /* PIN đúng. */
  await trang2.fill('#oPin', '246810');
  await trang2.click('#btVao');
  await trang2.waitForTimeout(200);
  t('PIN đúng thì vào được', 5 === await trang2.locator('[data-man]').count(),
    await trang2.locator('[data-man]').count());
  t('hiện tên và vai người đăng nhập',
    (await trang2.locator('.toi').innerText()).indexOf('CHT C') >= 0);

  /* Thẻ phải đi kèm mọi lượt gọi SAU khi đăng nhập. */
  const goi1 = await trang2.evaluate(() => window.__GOI);
  const sauDangNhap = goi1.filter(g => g.than.viec !== 'dang_nhap');
  t('🔴 mọi lượt gọi sau đăng nhập đều mang thẻ',
    sauDangNhap.length > 0 && sauDangNhap.every(g => g.the === 'THE-GIA-123'),
    sauDangNhap.map(g => g.than.viec + ':' + g.the));

  /* --- màn tổng quan --- */
  const oSo = await trang2.locator('.o-so').first().innerText();
  t('tổng quan hiện bốn ô số', oSo.indexOf('CƠ SỞ') >= 0 && oSo.indexOf('NHÂN SỰ') >= 0, oSo);
  t('chưa có checklist thì hiện gạch, KHÔNG hiện 0%', oSo.indexOf('—') >= 0, oSo);
  t('hiện thẻ cơ sở với tên thật',
    (await trang2.locator('.coso-the .ten').innerText()).indexOf('GO BÀ RỊA') >= 0);
  /* 🔴 Chưa đặt chỉ tiêu thì phải nói "chưa đặt", không được hiện 0% — 0% đọc ra là "làm tệ",
     trong khi thật ra là "chưa ai khai chỉ tiêu". */
  t('🔴 chưa đặt chỉ tiêu thì nói CHƯA ĐẶT, không hiện 0%',
    (await trang2.locator('.coso-the').innerText()).indexOf('Chưa đặt chỉ tiêu') >= 0);

  /* 🔴 Mục chưa làm vẫn hiện trên thanh bên, kèm chữ "đang làm" — giấu đi thì người dùng đi mở
     app cũ làm phần còn lại, và số liệu nằm hai nơi. */
  t('🔴 mục chưa làm vẫn hiện trên thanh bên',
    (await trang2.locator('.ben').innerText()).indexOf('Checklist') >= 0);
  t('🔴 và nói thẳng là đang làm', 1 <= await trang2.locator('.ben .sap').count());
  t('mục chưa làm thì KHÔNG bấm được',
    await trang2.locator('.ben button.chua').first().isDisabled());
  /* Chấm công nối SANG plugin chấm công, không dựng sổ thứ hai. */
  t('🔴 mục Chấm công là liên kết sang plugin chấm công',
    'http://vi-du.test/cham-cong' === await trang2.locator('.ben a.ben-noi').first().getAttribute('href'));
  t('thanh bên chia nhóm', 4 <= await trang2.locator('.ben .nhom').count());

  /* --- màn doanh thu --- */
  await trang2.click('[data-man="tien"]');
  await trang2.waitForTimeout(200);
  t('mở được màn Doanh thu', await trang2.locator('[data-ve="ve_1luot"]').isVisible());
  t('vé xếp theo nhóm có tiêu đề nhóm',
    (await trang2.locator('.ve-nhom .ten').first().innerText()).length > 0);

  /* Gõ số vé → tổng tạm phải đổi NGAY. Không đổi nghĩa là ô không nối được vào hàm cộng. */
  await trang2.fill('[data-ve="ve_1luot"]', '10');
  await trang2.fill('[data-ve="bua_le"]', '2');
  await trang2.fill('[data-ve="online_1luot"]', '5');
  await trang2.dispatchEvent('[data-ve="online_1luot"]', 'input');
  await trang2.waitForTimeout(100);
  t('gõ số vé thì tổng tạm đổi ngay',
    '1.060.000₫' === (await trang2.locator('#tgThu').innerText()), await trang2.locator('#tgThu').innerText());
  /* 🔴 Vé online không vào doanh thu cơ sở, nhưng VẪN là lượt khách — cùng luật với máy chủ. */
  t('🔴 vé online không cộng vào doanh thu trên màn hình',
    '1.060.000₫' === (await trang2.locator('#tgThu').innerText()));
  t('🔴 nhưng vẫn tính vào lượt khách', '15' === (await trang2.locator('#tgKhach').innerText()),
    await trang2.locator('#tgKhach').innerText());

  /* Ba ô thu tiền lệch doanh thu thì phải NÓI RA — chứ không chặn. */
  await trang2.fill('#pMat', '1000000');
  await trang2.dispatchEvent('#pMat', 'input');
  await trang2.waitForTimeout(80);
  t('lệch tiền thì báo ra màn hình',
    (await trang2.locator('#soLech').innerText()).indexOf('Lệch') >= 0,
    await trang2.locator('#soLech').innerText());
  await trang2.fill('#pMat', '1060000');
  await trang2.dispatchEvent('#pMat', 'input');
  await trang2.waitForTimeout(80);
  t('khớp thì báo khớp', (await trang2.locator('#soLech').innerText()).indexOf('Khớp') >= 0);

  /* --- lưu --- */
  await trang2.click('#btLuu');
  await trang2.waitForTimeout(250);
  const goi2 = await trang2.evaluate(() => window.__GOI);
  const luu = goi2.filter(g => g.than.viec === 'tien_luu').pop();
  t('bấm LƯU thì có gửi lên máy chủ', !!luu);
  t('gửi đúng số lượng từng loại vé',
    luu && luu.than.ve.ve_1luot === 10 && luu.than.ve.bua_le === 2 && luu.than.ve.online_1luot === 5,
    luu && luu.than.ve);
  /* 🔴 ĐÂY LÀ PHÉP THỬ QUAN TRỌNG NHẤT CỦA BÀI. Bản Firebase cũ tính tiền ở máy khách rồi ghi
     thẳng con số ấy. Trang này chỉ được gửi SỐ LƯỢNG; tổng là việc của máy chủ. */
  t('🔴 KHÔNG gửi kèm tổng tiền — tổng là việc của máy chủ',
    luu && luu.than.tong_thu === undefined && luu.than.tong_chi === undefined
      && luu.than.khach === undefined, luu && Object.keys(luu.than));
  t('gửi kèm ba ô thu tiền', luu && luu.than.tra_tien.mat === 1060000);
  t('lưu xong hiện băng báo thành công',
    (await trang2.locator('.bao-ok').innerText()).indexOf('Đã lưu') >= 0);
  /* Máy chủ trả về trạng thái đã duyệt → màn hình phải KHOÁ lại, không cho gõ tiếp. */
  t('🔴 báo cáo đã duyệt thì ô nhập bị khoá',
    await trang2.locator('[data-ve="ve_1luot"]').isDisabled());

  /* --- màn checklist --- */
  await trang2.click('[data-man="checklist"]');
  await trang2.waitForTimeout(200);
  t('mở được màn Checklist', 5 === await trang2.locator('[data-cl]').count());
  t('checklist chia theo khu', 2 <= await trang2.locator('.the h2').count());
  await trang2.check('[data-cl="thu_ngan_0"]');
  await trang2.check('[data-cl="ki_thuat_1"]');
  await trang2.waitForTimeout(80);
  /* Đếm tại chỗ khi tích — không phải bấm Lưu mới biết còn thiếu mấy mục. */
  t('tích thì số đếm đổi ngay',
    (await trang2.locator('#clTien').innerText()).indexOf('2/5') >= 0,
    await trang2.locator('#clTien').innerText());
  await trang2.click('#btClLuu');
  await trang2.waitForTimeout(250);
  const goiCl = (await trang2.evaluate(() => window.__GOI)).filter(x => x.than.viec === 'cl_luu').pop();
  t('bấm LƯU CHECKLIST thì có gửi lên', !!goiCl);
  t('gửi đúng khoá mục đã tích',
    goiCl && goiCl.than.muc.thu_ngan_0 === 1 && goiCl.than.muc.ki_thuat_1 === 1,
    goiCl && goiCl.than.muc);
  /* 🔴 Mục KHÔNG tích thì không gửi — gửi 0 cho mọi mục thì máy chủ vẫn đếm đúng, nhưng gói tin
     phình vô ích và khoá nào bỏ sót cũng khó thấy. */
  t('🔴 mục chưa tích thì KHÔNG gửi', goiCl && goiCl.than.muc.thu_ngan_1 === undefined);
  t('🔴 KHÔNG gửi kèm số "xong" — máy chủ tự đếm',
    goiCl && goiCl.than.xong === undefined && goiCl.than.tong === undefined,
    goiCl && Object.keys(goiCl.than));
  t('lưu xong hiện băng báo', (await trang2.locator('.bao-ok').innerText()).indexOf('2/5') >= 0);

  /* --- màn kiểm kho --- */
  await trang2.click('[data-man="kho"]');
  await trang2.waitForTimeout(200);
  t('mở được màn Kiểm kho', await trang2.locator('[data-kho="choi"][data-o="so"]').isVisible());
  t('nói rõ tuần bắt đầu từ thứ Hai nào',
    (await trang2.locator('.the').first().innerText()).indexOf('14/09/2026') >= 0);
  /* Món đếm thường KHÔNG có ô % tình trạng; món hao mòn thì có. */
  t('🔴 món đếm thường không có ô tình trạng',
    0 === await trang2.locator('[data-kho="choi"][data-o="tinh"]').count());
  t('món hao mòn có ô tình trạng',
    1 === await trang2.locator('[data-kho="tu_tho"][data-o="tinh"]').count());
  t('hiện bảng lệch so với tuần trước',
    (await trang2.locator('.than').innerText()).indexOf('Lệch so với tuần trước') >= 0);
  t('và hiện tên món chứ không phải khoá',
    (await trang2.locator('.than').innerText()).indexOf('Chổi') >= 0);

  await trang2.fill('[data-kho="choi"][data-o="so"]', '4');
  await trang2.fill('[data-kho="tu_tho"][data-o="tinh"]', '70');
  await trang2.click('#btKhLuu');
  await trang2.waitForTimeout(250);
  const goiKho = (await trang2.evaluate(() => window.__GOI)).filter(x => x.than.viec === 'kho_luu').pop();
  t('bấm LƯU SỔ KHO thì có gửi lên', !!goiKho);
  t('gửi đúng số lượng đã đếm', goiKho && goiKho.than.muc.choi.so === 4, goiKho && goiKho.than.muc);
  /* 🔴 Ô số lượng để TRỐNG thì không gửi món ấy. Gửi 0 nghĩa là "đếm rồi, còn 0 cái" — khác hẳn
     "chưa đếm tới". Nhập hai chuyện ấy làm một thì sổ kho báo mất sạch đồ. */
  t('🔴 món chưa đếm thì KHÔNG gửi lên', goiKho && goiKho.than.muc.tu_tho === undefined,
    goiKho && goiKho.than.muc);

  /* --- màn sự cố --- */
  await trang2.click('[data-man="su_co"]');
  await trang2.waitForTimeout(200);
  t('mở được màn Sự cố', await trang2.locator('#sTieu').isVisible());
  await trang2.fill('#sTieu', 'Đèn hành lang chập chờn');
  await trang2.click('#btSuCo');
  await trang2.waitForTimeout(200);
  const goi3 = await trang2.evaluate(() => window.__GOI);
  const sc = goi3.filter(g => g.than.viec === 'su_co_them').pop();
  t('bấm GHI SỰ CỐ thì có gửi lên', !!sc);
  t('thiếu người phụ trách thì máy chủ chối và trang hiện câu chối',
    (await trang2.locator('.bao-loi').innerText()).indexOf('Thiếu ô bắt buộc') >= 0);

  /* --- thoát --- */
  await trang2.click('#btRa');
  await trang2.waitForTimeout(120);
  t('thoát thì quay về màn đăng nhập', await trang2.locator('#oPin').isVisible());
  t('🔴 thoát thì XOÁ thẻ trong máy',
    '' === await trang2.evaluate(() => localStorage.getItem('vhvh_the') || ''));

  t('không có lỗi JS nào trong cả lượt bấm', 0 === loiJs.length, loiJs);

  await trinh.close();
  fs.rmSync(tmpDir, { recursive: true, force: true });

  if (TRUOT.length) {
    console.log('\nTRƯỢT ' + TRUOT.length + ':');
    TRUOT.forEach(x => console.log('  ✗ ' + x));
    console.log('ĐẠT: ' + DAT);
    process.exit(1);
  }
  console.log('\nĐẠT: ' + DAT + ' phép bấm thật — trang gọi đúng việc, và không tự tính tiền.');
})();
