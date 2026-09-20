/* CHỤP GIAO DIỆN TRẠM — và đây cũng chính là giao diện app Android.
 *
 * 🔴 APP ANDROID KHÔNG CÓ GIAO DIỆN RIÊNG. Nó mở đúng trang này trong một WebView (xem
 *    `android_cham_cong/README.md`). Nên chụp trang này LÀ chụp app — trừ hai màn phụ do Kotlin
 *    vẽ (nhập địa chỉ máy chủ, báo mất mạng), thứ chỉ máy Android thật mới dựng được. Không có
 *    ảnh nào ở đây là ảnh dựng tay: tất cả đều do mã thật in ra.
 *
 * ⚠️ KHUNG MÁY ĐIỆN THOẠI, KHÔNG PHẢI KHUNG MÀN HÌNH MÁY TÍNH. Trạm là trang chỉ mở trên điện
 *    thoại; chụp ở 1500px là chụp một bố cục không ai từng thấy — và lỗi tràn ở 390px thì không
 *    hiện ra.
 *
 * Chạy: bash tools/xem/xem-tram.sh
 */
const path = require('path');
const fs = require('fs');
let chromium;
try { chromium = require('/opt/node22/lib/node_modules/playwright').chromium; }
catch (e) { try { chromium = require('playwright').chromium; }
  catch (e2) { console.log('✗ chưa có playwright'); process.exit(2); } }

const GOC = process.argv[2] || 'http://127.0.0.1:8899/';
const RA = process.argv[3] || '/tmp/xem-tram';
fs.mkdirSync(RA, { recursive: true });

const so = (n) => String(n).padStart(2, '0');
let dem = 0;
async function chup(p, ten) {
  dem++;
  const tep = path.join(RA, so(dem) + '-' + ten + '.png');
  await p.screenshot({ path: tep });
  console.log('  ✓ ' + path.basename(tep));
}

(async () => {
  const b = await chromium.launch({
    /* Camera giả: màn Chụp ảnh bật `getUserMedia` ngay khi mở. Không có cờ này thì Chromium
       chối, trang hiện "máy ảnh chưa sẵn sàng", và ảnh chụp được là một câu lỗi chứ không phải
       giao diện. */
    args: ['--use-fake-ui-for-media-stream', '--use-fake-device-for-media-stream'],
  });
  const ct = await b.newContext({
    viewport: { width: 390, height: 844 },
    deviceScaleFactor: 2,
    isMobile: true,
    hasTouch: true,
    locale: 'vi-VN',
    permissions: ['geolocation', 'camera'],
    geolocation: { latitude: 10.7755, longitude: 106.7021, accuracy: 18 },
  });
  const p = await ct.newPage();
  p.on('pageerror', (e) => console.log('  ⚠ lỗi JS: ' + e.message));

  /* ── 1. Màn gõ PIN: thứ nhân viên thấy đầu tiên mỗi sáng ── */
  await p.goto(GOC, { waitUntil: 'networkidle' });
  await p.waitForTimeout(900);
  await chup(p, 'dang-nhap');

  /* ── 2. Đăng nhập bằng đúng đường của trình duyệt thật ── */
  await p.fill('#oPin', '112233');
  await p.click('#btVao');
  await p.waitForTimeout(2500);
  await chup(p, 'tab-cham-cong');

  /* ── 3. Màn chụp ảnh ── */
  const nutChup = await p.$('#btCham');
  if (nutChup) {
    await nutChup.click();
    await p.waitForTimeout(2200);
    await chup(p, 'man-chup-anh');
    const huy = await p.$('#btHuyChup');
    if (huy) { await huy.click(); await p.waitForTimeout(600); }
  } else {
    console.log('  ⚠ không tìm thấy nút mở màn chụp — bỏ qua ảnh này');
  }

  /* ── 4. Bốn tab còn lại ── */
  for (const [tab, ten] of [
    ['tCong', 'tab-cong-cua-toi'],
    ['tCuaHang', 'tab-cua-hang'],
    ['tUng', 'tab-ung-dung'],
    ['tToi', 'tab-toi'],
  ]) {
    const n = await p.$('.tab-nut[data-tab="' + tab + '"]');
    if (!n) { console.log('  ⚠ không có tab ' + tab); continue; }
    /* ⚠️ BỎ QUA TAB ĐANG ẨN, ĐỪNG CHẾT Ở ĐÓ. Tab "Cửa hàng" chỉ hiện cho cửa hàng trưởng, và
       nó hiện SAU khi `?viec=toi` trả lời. Bản đầu của tệp này gọi thẳng `click()` rồi chết
       sau 30 giây chờ — mất luôn mấy ảnh phía sau, vì một cái tab mà đúng ra chỉ cần ghi một
       dòng "không có". */
    if (!(await n.isVisible())) { console.log('  ⚠ tab ' + tab + ' đang ẩn với vai này'); continue; }
    await n.click();
    await p.waitForTimeout(1600);
    await chup(p, ten);
  }

  /* ── 5. Nhắn tin: vào lưới Ứng dụng rồi bấm ô Nhắn tin ── */
  const nUng = await p.$('.tab-nut[data-tab="tUng"]');
  if (nUng) { await nUng.click(); await p.waitForTimeout(1200); }
  const oChat = await p.$('.o-man[data-man="mChat"]');
  if (oChat && await oChat.isVisible()) {
    await oChat.click();
    await p.waitForTimeout(1600);
    await chup(p, 'chat-danh-sach');
    /* Vào phòng cả cửa hàng, gõ một câu, gửi — chụp cả khung tin có bong bóng thật. */
    const vao = await p.$('.chat-vao');
    if (vao) {
      await vao.click();
      await p.waitForTimeout(1400);
      await p.fill('#chatO', 'Ca chiều nay đổi người nhé cả nhà.');
      await p.click('#btChatGui');
      await p.waitForTimeout(1800);
      await chup(p, 'chat-phong');
    }
    const ve = await p.$('#btChatVe');
    if (ve) { await ve.click(); await p.waitForTimeout(900); }
    const nNguoi = await p.$('#btChatNguoi');
    if (nNguoi) {
      await nNguoi.click();
      await p.waitForTimeout(1400);
      await chup(p, 'chat-chon-nguoi');
    }
    const dong = await p.$('#btDongChat');
    if (dong) { await dong.click(); await p.waitForTimeout(600); }
  } else {
    console.log('  ⚠ không thấy ô Nhắn tin trong lưới Ứng dụng');
  }

  /* ── 6. Chuông ── */
  /* ⚠️ Chuông chỉ hiện khi site có cài trang Nội bộ (`VHNB_Bao`). Bệ đỡ xem trước không có nó,
     nên nút ẩn — ghi một dòng rồi đi tiếp, đừng chết ở đây và mất mấy dòng in ra phía dưới. */
  const ch = await p.$('#btChuong');
  if (ch && await ch.isVisible()) {
    await ch.click(); await p.waitForTimeout(1200); await chup(p, 'chuong');
  } else {
    console.log('  ⚠ chuông ẩn (bệ đỡ xem trước không có trang Nội bộ)');
  }

  /* IN RA MÀN HÌNH ĐANG NÓI GÌ. Ảnh cho thấy bố cục; mấy dòng này cho thấy nó nói gì về ai —
     đọc chữ nhanh hơn soi ảnh, và đó là chỗ lỗi hay nấp. */
  console.log('\n── Lưới Ứng dụng đang có những ô nào ──');
  const oUng = await p.$$eval('#tUng .o-ung, #tUng .ung-o, #tUng a, #tUng button',
    e => e.map(x => (x.textContent || '').replace(/\s+/g, ' ').trim()).filter(s => s.length > 1));
  for (const x of [...new Set(oUng)]) console.log('  · ' + x);

  await b.close();
  console.log('\nẢnh nằm ở: ' + RA);
})();
