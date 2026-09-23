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
      /* Đính kèm một tấm ảnh THẬT rồi gửi — chỉ ảnh thật mới đi qua được phép kiểm nội dung
         của máy chủ, và chỉ ảnh thật mới cho biết bong bóng có hiện ra đúng không. */
      /* Một tấm PNG THẬT, sinh bằng mã (xem `tools/xem/README` nếu cần dựng lại). Bản đầu
         em gõ tay một chuỗi base64 'trông giống PNG': nó có phần đầu hợp lệ nên máy chủ
         nhận, nhưng dữ liệu ảnh là rác nên trình duyệt không vẽ được — và ảnh chụp ra một
         ô vỡ trông y như lỗi của phần phục vụ tệp. Suýt đi sửa nhầm chỗ. */
      const PNG = Buffer.from(
        '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'
        + 'vH5RXrskr16WV67Iy1flpWty/rq8eENeuCnnbsnzt+Xsjjx3V07vybP35eSBPPNQjh/J04/l6Ik8'
        + '9VQOn8mTz+XghTzxUvZfyeOvZe+NPPZWdt/Jo+9l54M88lG2P8nDn2XrizxUIJuF8mCRbBTLAyWy'
        + 'Xir3l8laudxXIauA3FspK6DcUyXLkNx9ihCWu2pkEZE7a2UBlTvqZB6T2+tlDpfbGmSWkFsbZYaU'
        + 'W5pkmpKbm2WKlptaZJKRG1tlgpUb2mSck+vbZYyX6zpkVJBrO2VElGu6ZPg0A92nj8hVPTKoyJW9'
        + 'MqDKFX1yuSaX9cululwyIBcbctGgXGjKBUPyP0v+Oyz/seXfI/IvR/45Kv9w5e9j8jdP/joun/jy'
        + '8YR8FMiHk/JBKO9PyXuRvDst78Ty9oy8lcibs/JGKq/PyWuZvDovr+Tk5QV5aVHO5+XFJXlhWc6t'
        + 'yPOrcrYmz63L6YY8uyknZ84qW/8pm+eUjfPK+gVl7aKyeklZuawsX1GWrir5a8ridWXhhpK7qczf'
        + 'UrLbytwdJb2rzN5TkvvKzAMlfqhMP1Kix8rUEyV8qkw+U4LnysQLxX+pjL9SvNfK2BvFfauMvlOc'
        + '98rIB8X+qAx/UqzPytAXxSxQBgsVo0gZKFb0EqW/VNHKlL5yRa1QegFFqVR6TplVKd2QIlUrXbAi'
        + '1iidiCLUKh2owtcp7ZjC1SttuMI2KK2EwjQqLaRCNynNlEI1K020QrYojYxCtCoNrIK3KfWcgrUr'
        + 'dbyCdii1goJ0KjWiAncp1ZICdStVp5HoOX1TAXqVClUp71PKNKW0XynRleIBpchQCgeVAlP5N6T8'
        + 'tZQ/w8pvW/k1ovx0lB+jyndX+TamfPWUk3Hl2FeOJpTDQDmYVPZDZW9K2Y2UnWllO1a2ZpTNRNmY'
        + 'VdZTZW1OWc2UlXllOacsLSj5RWUxrywsKbllZX5FyVaVuTUlXVdmN5RkU5k5c1bd/E/dOKeun1fX'
        + 'LqirF9WVS+ryZXXpipq/qi5eUxeuq7kb6vxNNbulzt1W0zvq7F01uafO3FfjB+r0QzV6pE49VsMn'
        + '6uRTNXimTjxX/Rfq+EvVe6WOvVbdN+roW9V5p468V+0P6vBH1fqkDn1WzS/qYIFqFKoDRaperPaX'
        + 'qFqp2lemquVq7ykkQO2pVGVQ7a5SJUjtqlZFWO2sUQVE7ahVeVRtr1M5TG2rV1lcbW1QGUJtaVRp'
        + 'Um1uUilKbWpWSVptbFEJRm1oVXFWrW9TMU6ta1dRXq3tUBFBrelUYVGt7lIhSa3qVkFZrexRgdOE'
        + '9J6OoJb1qaWaWtKvFutq0YBaaKgFg+o/U/07pP6x1N/D6i9b/Tmi/nDU76PqN1f9OqaeeOrxuHrk'
        + 'q4cT6kGg7k+qe6G6O6XuROr2tLoVq5sz6kairs+qa6m6OqeuZOryvLqUU/ML6uKiupBXc0vq/LKa'
        + 'rahzq2q6ps6uq8mGOrOpxmfOahv/aevntLXz2uoFbeWitnxJW7qs5a9oi1e1hWta7ro2f0PLbmpz'
        + 't7T0tjZ7R0vuajP3tPi+Nv1Aix5qU4+08LE2+UQLnmoTzzT/uTb+QvNeamOvNPe1NvpGc95qI+80'
        + '+702/EGzPmpDnzTzszb4RTMKtIFCTS/S+os1rUTrO6VSpvWWa0qF1gNocqXWDWpSldYFaWK11glr'
        + 'Qo3WgWh8rdaOalyd1oZpbL3WimtMg9ZCaHSj1kxqVJPWRGlks9ZIa0SL1sBoeKtWz2pYm1bHaWi7'
        + 'VstrSIdWI2hwp1YtalCXViVpYLdWKWtAj1ahaOW9WtlpYPpOJ9KK+7UiXSsc0AoM7d+g9tfU/gxp'
        + 'vy3t17D209Z+jGjfHe3bqPbV1U7GtGNPOxrXDn3tYELbD7S9SW031HamtO1I25rWNmNtY0ZbT7S1'
        + 'WW011VbmtOVMW5rX8jltcUFbWNRyeW1+ScuWtbkVLV3VZte0ZF2b2dDiTW36zFl9/T997Zy+el5f'
        + 'uaAvX9SXLun5y/riFX3hqp67ps9f17Mb+txNPb2lz97Wkzv6zF09vqdP39ejB/rUQz18pE8+1oMn'
        + '+sRT3X+mjz/XvRf62EvdfaWPvtadN/rIW91+pw+/160P+tBH3fykD37WjS/6QIGuF+r9pxiK9b4S'
        + 'XS3Ve8t0pVzvqdBlQO+u1CVQ76rSRUjvrNYFWO+o0XlEb6/VOVRvq9NZTG+t1xlcb2nQaUJvbtQp'
        + 'Um9q0klKb2zWCVpvaNFxRq9v1TFWr2vTUU6vbdcRXq/p0GFBr+7UIVGv6tJBSa/s1gFZr+jRyxW9'
        + 'rFcvVfWSPr34ND/9pwPqBQP6P0P/O6j/MfXfQ/ovS/85rP+w9e8j+jdH/zqqn7j68Zh+5OmH4/qB'
        + 'r+9P6HuBvjup74T69pS+Femb0/pGrK/P6GuJvjqrr6T68py+lOn5eX0xpy8s6LlFfT6vZ0v63LKe'
        + 'ruizq3qyps+s6/GGPr2pR2fOGmv/GavnjJXzxvIFY+mikb9kLF42Fq4YuavG/DUju27M3TDSm8bs'
        + 'LSO5bczcMeK7xvQ9I7pvTD0wwofG5CMjeGxMPDH8p8b4M8N7boy9MNyXxugrw3ltjLwx7LfG8DvD'
        + 'em8MfTDMj8bgJ8P4bAx8MfQCo7/Q0IqMvmJDLTF6Sw2lzOgpN+QKoxswpEqjCzTEKqMTMoRqowM2'
        + '+BqjHTG4WqMNNdg6oxUzmHqjBTfoBqOZMKhGo4k0yCajkTKIZqOBNvAWo54xsFajjjXQNqOWM5B2'
        + 'o4Y34A6jWjCgTqNKNMAuo1IygG6jQjbKe4wyxSjtNUpUo7jPKNKMwn6jQDf+DRh/DePPoPHbNH4N'
        + 'GT8t48ew8d02vo0YXx3jZNQ4do2jMePQMw7GjX3f2JswdgNjZ9LYDo2tKWMzMjamjfXYWJsxVhNj'
        + 'ZdZYTo2lOSOfGYvzxkLOyC0Y84tGljfmlox02ZhdMZJVY2bNiNeN6Q0j2jSmzpw1V/8zV86Zy+fN'
        + 'pQtm/qK5eMlcuGzmrpjzV83smjl33UxvmLM3zeSWOXPbjO+Y03fN6J45dd8MH5iTD83gkTnx2PSf'
        + 'mONPTe+ZOfbcdF+Yoy9N55U58tq035jDb03rnTn03jQ/mIMfTeOTOfDZ1L+Y/QWmVmj2FZlqsdlb'
        + 'YiqlZk+ZKZeb3RWmBJhdlaYImp1VpgCZHdUmD5vtNSaHmG21JouarXUmg5kt9SaNm80NJkWYTY0m'
        + 'SZqNTSZBmQ3NJk6b9S0mxph1rSbKmrVtJsKZNe0mzJvVHSYkmFWdJiialV0mIJkV3Wa5bJb1mKWK'
        + 'WdJrFqtmUZ9ZqJkF/eY/3fw7YP4xzN+D5i/T/Dlk/rDM78PmN9v8OmKeOObxqHnkmodj5oFn7o+b'
        + 'e765O2HuBOb2pLkVmptT5kZkrk+ba7G5OmOuJObyrLmUmvk5czEzF+bNXM6cXzCzRXMub6ZL5uyy'
        + 'mayYM6tmvGZOr5vRhjm1aYZnzlor/1nL56yl81b+grV40Vq4ZOUuW/NXrOyqNXfNSq9bszes5KY1'
        + 'c8uKb1vTd6zorjV1zwrvW5MPrOChNfHI8h9b408s76k19sxyn1ujLyznpTXyyrJfW8NvLOutNfTO'
        + 'Mt9bgx8s46M18MnSP1v9XyytwOortNQiq7fYUkqsnlJLLrO6yy2pwuoCLLHS6gQtocrqgCy+2mqH'
        + 'La7GakMsttZqRS2mzmrBLLreasYtqsFqIiyy0WokLaLJaqAsvNmqpy2sxapjLLTVqmUtpM2q4Sy4'
        + '3armLajDqhIssNOqFC2gy6qQrPJuq0y2SnusEsUq7rWKVKuwzyrQrH/91l/d+jNg/TasX4PWT9P6'
        + 'MWR9t6xvw9ZX2zoZsY4d62jUOnStgzFr37P2xq1d39qZsLYDa2vS2gytjSlrPbLWpq3V2FqZsZYT'
        + 'a2nWyqfW4py1kFm5eWs+Z2UL1tyileat2SUrWbZmVqx41Zpes6J1a2rDCjetyTNn7eX/7KVzdv68'
        + 'vXjBXrho5y7Z85ft7Io9d9VOr9mz1+3khj1z045v2dO37eiOPXXXDu/Zk/ft4IE98dD2H9njj23v'
        + 'iT321Haf2aPPbeeFPfLStl/Zw69t64099NY239mD723jgz3w0dY/2f2fbe2L3Vdgq4V2b5GtFNs9'
        + 'JbZcaneX2VK53VVhi4DdWWkLoN1RZfOQ3V5tc7DdVmOziN1aazOo3VJn05jdXG9TuN3UYJOE3dho'
        + 'E6Td0GTjlF3fbGO0Xddio4xd22ojrF3TZsOcXd1uQ7xd1WGDgl3ZaQOiXdFll0t2WbddKtslPXax'
        + 'Yhf12oWqXdBn/9Psv/32H93+PWD/Muyfg/YP0/4+ZH+z7K/D9oltH4/YR459OGofuPb+mL3n2bvj'
        + '9o5vb0/YW4G9OWlvhPb6lL0W2avT9kpsL8/YS4mdn7UXU3thzs5l9vy8neXsuQU7XbRn83ayZM8s'
        + '2/GKPb1qR2v21LodbtiTm3Zw5qyz9J+TP+csnncWLji5i878JSe77MxdcdKrzuw1J7nuzNxw4pvO'
        + '9C0nuu1M3XHCu87kPSe470w8cPyHzvgjx3vsjD1x3KfO6DPHee6MvHDsl87wK8d67Qy9ccy3zuA7'
        + 'x3jvDHxw9I9O/ydH++z0fXHUAqe30FGKnJ5iRy5xuksdqczpKnfECqcTcIRKpwN0+CqnHXK4aqcN'
        + 'dtgapxVxmFqnBXXoOqcZc6h6pwl3yAankXCIRqeBdPAmp55ysGanjnbQFqeWcZBWp4Z14DanmnOg'
        + 'dqeKd8AOp1JwgE6nQnTKu5wyySntdkpkp7jHKVKcwl6nQHX+9Tl/NedPv/Nbd34NOD8N58eg8910'
        + 'vg05Xy3nZNg5tp2jEefQcQ5GnX3X2Rtzdj1nZ9zZ9p2tCWczcDYmnfXQWZtyViNnZdpZjp2lGSef'
        + 'OIuzzkLq5Oac+czJ5p25nJMuOLOLTpJ3ZpaceNmZXnGiVWdqzQnXnckNJ9h0Js6cdfP/uYvn3IXz'
        + 'bu6CO3/RzS65c5fd9Io7e9VNrrkz1934hjt9041uuVO33fCOO3nXDe65E/dd/4E7/tD1Hrljj133'
        + 'iTv61HWeuSPPXfuFO/zStV65Q69d8407+NY13rkD7139g9v/0dU+uX2fXfWL21vgKoVuT5ErF7vd'
        + 'Ja5U6naVuWK521nhCoDbUenyoNte5XKQ21btsrDbWuMyiNtS69Ko21znUpjbVO+SuNvY4BKE29Do'
        + '4qRb3+RilFvX7KK0W9viIoxb0+rCrFvd5kKcW9Xugrxb2eECglvR6ZaLblmXWyq5Jd1usewW9biF'
        + 'ilvQ6/5T3b997h/N/d3v/tLdnwPuD8P9Puh+M92vQ+6J5R4Pu0e2ezjiHjju/qi757q7Y+6O526P'
        + 'u1u+uznhbgTu+qS7FrqrU+5K5C5Pu0uxm59xFxN3YdbNpe78nJtl7ty8m+bc2QU3WXRn8m685E4v'
        + 'u9GKO7Xqhmvu5LobbLgTm65/5qy3+J+3cM7LnffmL3jZRW/ukpde9maveMlVb+aaF1/3pm940U1v'
        + '6pYX3vYm73jBXW/inuff98YfeN5Db+yR5z72Rp94zlNv5JlnP/eGX3jWS2/olWe+9gbfeMZbb+Cd'
        + 'p7/3+j942kev75OnfvZ6v3hKgddT6MlFXnexJ5V4XaWeWOZ1lntChdcBeHyl1w56XJXXBnlstdcK'
        + 'e0yN14J4dK3XjHpUndeEeWS914h7RIPXQHh4o1dPeliTV0d5aLNXS3tIi1fDeHCrV816UJtXxXlg'
        + 'u1fJe0CHVyF45Z1emeiVdnklklfc7RXJXmGPV6B4/3q9v6r3p8/7rXm/+r2fuvdjwPtueN8Gva+m'
        + 'dzLkHVve0bB3aHsHI96+4+2NeruutzPmbXve1ri36XsbE9564K1NequhtzLlLUfe0rSXj73FGW8h'
        + '8XKz3nzqZXPeXOal895szksWvJlFL85700tetOxNrXjhqje55gXr3sSG529642fO+gv/+blz/vx5'
        + 'P7vgz13000v+7GU/ueLPXPXja/70dT+64U/d9MNb/uRtP7jjT9z1/Xv++H3fe+CPPfTdR/7oY995'
        + '4o889e1n/vBz33rhD730zVf+4GvfeOMPvPX1d37/e1/74Pd99NVPfu9nX/ni9xT4cqHfXeRLxX5X'
        + 'iS+W+p1lvlDud1T4POC3V/oc6LdV+Szkt1b7DOy31Pg04jfX+hTqN9X5JOY31vsE7jc0+Djh1zf6'
        + 'GOnXNfko5dc2+wjt17T4MONXt/oQ61e1+SDnV7b7AO9XdPjlgl/W6ZeKfkmXXyz5Rd1+oewX9Pj/'
        + 'FP9vr/9H9X/3+b80/2e//0P3vw/43wz/66B/YvrHQ/6R5R8O+we2vz/i7zn+7qi/4/rbY/7WKc9x'
        + 'f8P31yf8tcBfnfRXQn95yl+K/Py0vxj7CzN+LvHnZ/0s9efm/DTzZ+f9JOfPLPjxoj+d96Mlf2rZ'
        + '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'
        + 'Jt2fEulz0vUlEQuSzsJEKEo6ihO+JGkvTbiypK08YSuSViBhKpMWMKGrkmYooaqTJjgha5JGJCFq'
        + 'kwY0weuSeizB6pM6PEEbkloiQRqTGjKBm5JqKoGakyo6AVuSSiYBWpMKNilvS8q4pLQ9KeGT4o6k'
        + 'SEgKO5MCMfnXlfyVkj/dyW85+dWT/FSSH73JdzX51pd81ZKT/uRYT44GkkMjORhM9s1kbyjZtZKd'
        + '4WTbTrZGkk0n2RhN1t1kbSxZ9ZKV8WTZT5YmknyQLE4mC2GSm0rmoySbTuZO1cwks0lyumZOXc0l'
        + '01kSzSdTuSRcSCYXkyCfTCwl/nIyvpJ4q8nYWuKuJ6MbibOZjJw5m87+lybn0pnzaXwhnb6YRpfS'
        + 'qctpeCWdvJoG19KJ66l/Ix2/mXq30rHbqXsnHb2bOvfSkfup/SAdfphaj9Khx6n5JB18mhrP0oHn'
        + 'qf4i7X+Zaq/Svtep+ibtfZsq79Ke96n8Ie3+mEqf0q7Pqfgl7SxIhcK0oyjli9P2kpQrTdvKUrY8'
        + 'ba1IGSBtqUxpMG2uSikobapOSThtrEkJJG2oTXE0ra9LMSytq09RPK1tSBEirWlMYTKtbkohKq1q'
        + 'TkE6rWxJASataE3L2bSsLS3l0pL2tJhPizrSQiEt6Ez/ienfrvSPlP7uTn/J6c+e9IeSfu9Nv6np'
        + '1770REuP+9MjPT0cSA+MdH8w3TPT3aF0x0q3h9MtO90cSTecdH00XXPT1bF0xUuXx9MlP81PpItB'
        + 'ujCZ5sJ0firNonRuOk3jdPZUzqmp2dNtOj2XRlk6NZ+GuXRyIQ0W04l86i+l48upt5KOrabuWjq6'
        + 'njob6chmap85myX/ZTPnsvh8Nn0hiy5mU5ey8HI2eSULrmYT1zL/ejZ+I/NuZmO3Mvd2Nnonc+5m'
        + 'I/cy+342/CCzHmZDjzLzcTb4JDOeZgPPMv151v8i015mfa8y9XXW+yZT3mY97zL5fdb9IZM+Zl2f'
        + 'MvFz1vklEwqyjsKML8raizOuJGsrzdiyrLU8YyqyFiCjK7NmMKOqsiYoI6uzRjgjarIGJMNrs3o0'
        + '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'
        + 'TGJjNrE+l1ibT6wuJFYWE8tLiaXlxOJKYmE1Mb+WmFtPzG4kZjYT01uJ4HYisJPw7yZ8ewlvKOHZ'
        + 'T7iJhOsg4SQTjsOEnUrYjhJWOmE5TpiZhOkkYTxzlp05x06fZ4MX2MBF1n+J9V1mvVdYz1XWfY11'
        + 'XWedN1jHTdZ+i7XdZq13WMtd1nyPNd1njQ9Yw0NW/4jVPWa1T1jNU1b9jFU9Z5UvWMVLVv6Klb1m'
        + 'pW9YyVtW/I4VvWeFH9ipj+zkJ3biMzv+hR37yo42sSPN7HALK2hlh9pYvJ0d7GCxTnagi0UBtr+b'
        + 'RUC2r4eFIba3l4VgtqePBRG2u58FULZrgO3E2I5Bth1n24bYVgHbMsw2j7BNo2xqjP03ziYn2L+T'
        + 'LD/F/hGynIg9FbOshP0tZRMy9pecjSvYn0o2pmJ/qNmohv2uZSM69pueDRvYEyPLmNhjM0tb2CMr'
        + 'S9nYQztLOtgDJ0u42H03G/Kwe15218fu+NntALsVZDen2Y0Zdn2WXZtjV+fZlQV2eZFdWmIXl9mF'
        + 'FXZ+lZ1bY2fX2ZkNdnqTDW6xgW3Wv8P6dlnvHusJse591kWwzgPWQbL2Q9ZGsdYj1kKz5mPWxLDG'
        + 'E9Zw5iw3fY4LnucCFzj/Rc53ifNe5jxXOPdVznWNc17nHDc4+03Odouz3uYsdzjzXc50jzPe5wwP'
        + 'OP1DTveI0z7mNE849VNO9YxTPucULzj5S072ipO+5iRvOPFbTvSOE77npj5wkx+5iU/c+Gdu7As3'
        + '+pUbaeKGmzlBCzfUyuFt3GA7h3VwA50c2sX1AxzSzfWBHNzD9UIc1Mv1wBzYx3UjHNDPdaFc5wDX'
        + 'gXHtg1wbzrUOcS0CrnmYaxrhUqPcvzEuOc79neD4Se7PFMcJuVMRx4q53xIuIeV+ybi4nPup4GJK'
        + '7oeKi6q57xououW+6biwnjsxcIyROzZxtJk7snCUlTu0caSdO3BwhJPbd3EhN7fn4Xa93I6P2/Zz'
        + 'WwFuM8htTHPrM9zaLLc6x63Mc8sL3NIit7jELSxz8yvc3Co3u8bNrHPTG1xwkwtscf5tzrfDeXc5'
        + 'zx7nDnGufc5JcI4Dzk5ytkPOSnGWI85Mc6ZjzshwhhNOf+YsHzzHB87z/gu87yLvvcR7LvPuK7zr'
        + 'Ku+8xjuu8/YbvO0mb73FW27z5ju86S5vvMcb7vP6B7zuIa99xGse8+onvOopr3zGK57z8he87CUv'
        + 'fcVLXvPiN7zoLS98x0+95yc/8BMf+fFP/NhnfvQLP/KVH27iBc38UAuPt/KDbTzWzg908Ggn39/F'
        + 'IwDf183DIN/bw0MQ39PLgzDf3ccDCN/Vz3eifMcA347xbYN8K863DPHNAr5pmE+N8P9G+eQY/3ec'
        + '5yf4P5M8N8WfCnlWxP8W8wkJ/0vKx2X8TzkfU/A/lHxUxX9X8xEN/03Lh3X8iZ5nDPyxkadN/JGZ'
        + 'pyz8oZUnbfyBnScc/L6TD7n4PTe/6+F3vPy2j9/y85sBfiPIr0/zazP86iy/Mscvz/NLC/ziIr+w'
        + 'xM8v83Mr/OwqP7PGT6/zwQ0+sMn7t3jfNu/d4T27vHuPd4V45z7vIHj7AW8jeeshb6F48xFvonnj'
        + 'MW9geP0JrztzNhk4l/SfT/ouJL0Xk55LSfflpOtK0nk16biWtF9P2m4krTeTlltJ8+2k6U7SeDdp'
        + 'uJfU30/qHiS1D5OaR0n146TqSVL5NKl4lpQ/T8peJKUvk5JXSfHrpOhNUvg2OfUuOfk+OfEhOf4x'
        + 'OfYpOfo5OfIlOfw1KWhKDjUn8ZbkYGsSa0sOtCfRjmR/ZxLpSvYBSbg72QsmoZ5kD5QEe5PdcBLo'
        + 'S3Yhyc7+ZAeabB9ItmHJ1sFkC55sHko2CZKp4eS/kWRyNPl3LMmPJ/9MJLnJ5OlUkhUmf4uSCXHy'
        + 'lyQZlyZ/ypIxefKHIhlVJr+rkhF18psmGdYmT3RJRp88NiRpY/LIlKTMyUNLkrQmD2xJwp7cdyRD'
        + 'zuSeK7nrTu54ktve5JYvuelPbgSS68Hk2nRydSa5MptcnksuzScXF5ILi8n5peTccnJ2JTmzmpxe'
        + 'SwbXk4GNpH8z6dtKereTnp2kezfp2ks6Q0nHftJOJG0HSSuZtBwmzVTSdJQ00knDcVLPJHUnSe2Z'
        + 'syn/uZTvfMp7IeW5mHJfSrkup5xXUo6rKfu1lO16ynojZbmZMt9KmW6njHdShrsp/b2U7n5K+yCl'
        + 'eZhSP0qpHqeUT1KKpyn5s5TseUr6IiV5mRK/Solep4RvUlNvU5PvUhPvU+MfUmMfU6OfUiOfU8Nf'
        + 'UoKvqaGmFN6cGmxJYa2pgbYU2p7q70ghnam+rhQMpHq7UxCY6ulJgVCquzcFwKmuvlQnkuroT7Wj'
        + 'qbaBVCuWahlMNeOppqFUSpD6N5xKjqT+jqb4sdSf8RQ3kTqdTLFTqd/CVEKU+iVOxSWpn9JUTJb6'
        + 'IU9FFanvylRElfqmToU1qRNtitGljvUp2pA6MqYoU+rQnCItqQNrirCl9u2pkCO150ztulI77tS2'
        + 'J7XlTW36Uhv+1HogtRZMrU6nVmZSy7OppbnU4nxqYSE1v5iaW0rNLqdmVlLTq6ngWiqwnvJvpHyb'
        + 'Ke9WyrOdcu+kXLsp517KEUrZ91M2ImU9SFnIlPkwZaJSxqOUgU7pj1M6JqU9SWnOnE37zqW959Oe'
        + 'C2n3xbTrUtp5Oe24krZfTduupa3X05YbafPNtOlW2ng7bbiT1t9N6+6ltffTmgdp9cO06lFa+Tit'
        + 'eJKWP03LnqWlz9OSF2nxy7ToVVr4Oj31Jj35Nj3xLj3+Pj32IT36MT3yKT38OS34kh76msab0oPN'
        + 'aawlPdCaRtvS/e1ppCPd15mGu9K9QBrqTveAabAn3Q2lgd50F5zu7Et3IOn2/nQbmm4dSLdg6ebB'
        + 'dBOeTg2l/wnSyeH035E0P5r+M5bmxtOnE2l2Mv17Kp0Qpn+J0nFx+qckHZOmf8jSUXn6uyIdUaa/'
        + 'qdJhdfpEk2a06WNdmtanjwxpypg+NKVJc/rAkias6X1bOmRP7znSu870jiu97U5vedKb3vSGL73u'
        + 'T68F0qvB9Mp0enkmvTSbXpxLL8yn5xfSc4vp2aX0zHJ6eiUdXE0H1tL+9bRvI+3dTHu20u7ttGsn'
        + '7dxNO/bS9lDatp+2EmnLQdpMpk2HaSOVNhyl9XRad5zWMmnNSVp95mzGey7jOZ9xX8i4LmaclzKO'
        + 'yxn7lYztasZ6LWO5njHfyJhuZoy3MobbGf2djO5uRnsvo7mfUT/IqB5mlI8yiscZ+ZOM7GlG+iwj'
        + 'eZ4Rv8iIXmaErzJTrzOTbzITbzPj7zJj7zOjHzIjHzPDnzKCz5mhLxn8a2awKYM1ZwZaMmhrpr8t'
        + 'g7Rn+joycGemtysDAZme7gwIZrp7MgCU6erNdMKZjr5MO5Jp68+0opmWgUwzlmkazKTwzL+hTFKQ'
        + '+Tuc4Ucyf0Yz3FjmdDzDTmR+T2YSU5lfwkxclPkpzsQkmR/STFSW+S7PRBSZb8pMWJU5UWcYTeZY'
        + 'm6F1mSN9hjJkDo0Z0pQ5MGcIS2bfmgnZMnv2zK4js+PMbLsyW+7Mpiez4c2s+zJr/sxqILMSzCxP'
        + 'Z5ZmMouzmYW5zPx8Zm4hM7uYmVnKTC9ngiuZwGrGv5bxrWe8GxnPZsa9lXFtZ5w7Gcduxr6XsYUy'
        + '1v2MhciYDzImMmM8zBiojP4oo6Mz2uOMhsmoTzKqM2eznnNZ9/ms60LWeTHruJS1X87armStV7OW'
        + 'a1nz9azpRtZ4M2u4ldXfzuruZLV3s5p7WfX9rOpBVvkwq3iUlT/Oyp5kpU+zkmdZ8fOs6EVW+DI7'
        + '9So7+To78SY7/jY79i47+j478iE7/DEr+JQd+pzFv2QHv2axpuxAcxZtyfa3ZpG2bF97Fu7I9nZm'
        + 'oa5sD5AFu7PdYBboyXZB2c7ebAecbe/LtiHZ1v5sC5ptHsg2YdnUYPYfnk0OZf8Ksvxw9s9IlhvN'
        + 'no5l2fHs74lsYjL7ayobF2Z/irIxcfaHJBuVZr/LshF59psiG1ZmT1RZRp091mRpbfZIl6X02UND'
        + 'ljRmD0xZwpzdt2RD1uyeLbtrz+44stvO7JYru+nObniy697smi+76s+uBLLLwezSdHZxJrswm52f'
        + 'y87NZ2cXsjOL2emlbHA5G1jJ+lezvrWsdz3r2ci6N7OuraxzO+vYydp3s7a9rDWUtexnzUTWdJA1'
        + 'klnDYVZPZXVHWS2d1Rxn1UxWdZJVnjmbc5/Luc7nnBdyjos5+6Wc7XLOeiVnuZozX8uZrueMN3KG'
        + 'mzn9rZzudk57J6e5m1Pfy6nu55QPcoqHOfmjnOxxTvokJ3maEz/LiZ7nhC9yUy9zk69yE69z429y'
        + 'Y29zo+9yI+9zwx9ygo+5oU85/HNu8EsO+5obaMqhzbn+lhzSmutry8Htud6OHNSZ6+nKgUCuuzsH'
        + 'gLmunlwnlOvozbXDuba+XCuSa+nPNaO5poFcCsv9G8wl8dzfoRwvyP0ZznEjudPRHDuW+z2eS0zk'
        + 'fk3m4lO5n8JcTJT7Ic5FJbnv0lxElvsmz4UVuRNljlHljtU5WpM70uYoXe5QnyMNuQNjjjDl9s25'
        + 'kCW3Z83t2nI79ty2I7flzG26chvu3Lont+bNrfpyK/7cciC3FMwtTucWZnLzs7m5udzsfG5mITe9'
        + 'mAsu5QLLOf9Kzrea867lPOs590bOtZlzbuUc2zn7Ts62m7Pu5SyhnHk/ZyJyxoOcgczpD3M6Kqc9'
        + 'ymnonPo4p2JyypOc4szZvOtc3nk+77iQt1/M2y7lrZfzlit589W86VreeD1vuJHX38zrbuW1t/Oa'
        + 'O3n13bzqXl55P694kJc/zMse5aWP85InefHTvOhZXvg8P/UiP/kyP/EqP/46P/YmP/o2P/IuP/w+'
        + 'L/iQH/qYxz/lBz/nsS/5ga95tCnf35xHWvJ9rXm4Ld/bnoc68j2debAr3w3kge58F5jv7Ml3QPn2'
        + '3nwbnG/ty7cg+eb+fBOaTw3k/2H55GD+L57nh/J/BHluOH86kmdH87/H8onx/K+JfHwy/3MqHxPm'
        + 'f4jyUXH+uyQfkea/yfJhef5EkWeU+WNVnlbnjzR5Sps/1OVJff7AkCeM+X1TPmTO71nyu9b8ji2/'
        + 'bc9vOfKbzvyGK7/uzq958qve/Iovv+zPLwXyi8H8wnR+fiY/N5ufncvPzOenF/LBxXxgKe9fzvtW'
        + '8t7VvGct717Puzbyzs28Yytv387bdvLW3bxlL28O5U37eSORNxzk9WRed5jXUnnNUV5N51XHeSWT'
        + 'V5zk5WfOFpznCo7zBfuFgu1iwXqpYLlcMF8pmK4WjNcKhusF/Y2C7mZBe6uguV1Q3ymo7haU9wqK'
        + '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'
        + 'gteVoTcV/G1l8F0Fe18Z+FBBP1b6P1WQz5W+LxX4a6W3qQI1V3paKmBrpbutArRXujoqnZ2Vjq5K'
        + 'O1Bp6660gpWWnkozVGnqraTgyr++ShKp/O2v8Gjlz0CFwyqngxUWr/weqiQElV/DlfhI5edoJTZW'
        + '+TFeiU5Uvk9WIlOVb8JKWFQ5EVcYSeVYWqFllSN5hVJUDpUVUlU5UFcITWVfWwnpKnv6yq6hsmOs'
        + 'bJsqW+bKpqWyYa2s2ypr9sqqo7LirCy7KkvuyqKnsuCtzPsqc/7KbKAyE6xMT1eCM5XAbMU/V/HN'
        + 'V7wLFc9ixb1UcS1XnCsVx2rFvlaxrVesGxXLZsW8VTFtV4w7FcNuRb9X0YUq2v2KhqioDyoqsqI8'
        + 'rCioivyoIqMr0uOKhKmITyqiM2erlnNV8/mq6ULVeLFquFTVX67qrlS1V6uaa1X19arqRlV5s6q4'
        + 'VZXfrsruVKV3q5J7VfH9quhBVfiwOvWoOvm4OvGkOv60OvasOvq8OvKiOvyyKnhVHXpdxd9UB99W'
        + 'sXfVgfdV9EO1/2MV+VTt+1yFv1R7v1ahpmpPcxVsqXa3VoG2ald7tbOj2tFZbe+qtgHV1u5qC1ht'
        + '7qk2QdVUb/UfXE32Vf8iVb6/+getcgPVU6zKDlZ/49XEUPWXoBofrv4cqcZGqz/GqtHx6veJamSy'
        + '+m2qGhZWT0RVRlw9llRpafVIVqXk1UNFlVRWD1RVQl3d11RD2uqerrqrr+4YqtvG6papummubliq'
        + '69bqmq26aq+uOKrLzuqSq7rori54qvPe6pyvOuuvzgSq08FqcLoamKn6Z6u+uap3vupZqLoXq66l'
        + 'qnO56lip2lertrWqdb1q2aiaN6umrapxu2rYqep3q7q9qjZU1exX1URVdVBVklXFYVVOVWVHVSld'
        + 'lRxXxUxVdFIVnjlbM5+rmc7XjBdqhos1/aWa7nJNe6WmuVpTX6uprteUN2qKmzX5rZrsdk16pya5'
        + 'WxPfq4nu14QPalMPa5OPahOPa+NPamNPa6PPaiPPa8MvaoKXtaFXNfx1bfBNDXtbG3hXQ9/X+j/U'
        + 'kI+1vk81+HOt90sN+lrraaqBzbXulhrQWutqq3W21zo6au2dtbauWitQa+muNYO1pp5aCqr9660l'
        + '4drfvhqP1P701zi0djpQY7Ha78FaAq/9GqrFBbWfw7XYSO3HaC06Vvs+XotM1L5N1sJTtRNhjRHV'
        + 'jsU1WlI7ktYoWe1QXiMVtQNljVDV9tW1kKa2p63t6mo7+tq2obZlrG2aahvm2rqltmatrdpqK/ba'
        + 'sqO25KwtumoL7tq8pzbnrc36ajP+2nSgFgzWAtM1/0zNN1vzztU88zX3Qs21WHMu1RzLNftKzbZa'
        + 's67VLOs180bNtFkzbtUM2zX9Tk23W9Pu1TShmnq/piJqyoOagqzJD2syqiY9qknomvi4JmJqwpPa'
        + '1JmzddO5uvF83XChrr9Y112qay/XNVfq6qt11bW68npdcaMuv1mX3apLb9cld+riu3XRvbrwfn3q'
        + 'QX3yYX3iUX38cX3sSX30aX3kWX34eV3woj70so6/qg++rmNv6gNv6+i7ev/7OvKh3vexDn+q936u'
        + 'Q1/qPV/rYFO9u7kOtNS7WuudbfWO9np7R72ts97aVW8B6s3d9Sawnuqp/4Pqyd76X7jO99X/IHWu'
        + 'v36K1tmB+m+snhis/8Lr8aH6T0E9Nlz/MVKPjta/j9Uj4/VvE/XwZP1kqs4I68eiOi2uH0nqlLR+'
        + 'KKuT8vqBok4o6/uqekhd39PUd7X1HV19W1/fMtQ3jfUNU33dXF+z1Fet9RVbfdleX3LUF531BVd9'
        + '3l2f89RnvfUZX33aXw8G6oFg3T9d983UvbN1z1zdPV93LdSdi3XHUt2+XLet1K2rdcta3bxeN23U'
        + 'jZt1w1Zdv13X7dS1u3XNXl0dqqv260qirjioy8m67LAupeqSo7qYrouO60KmPnVSnzxztmE81zCc'
        + 'b+gvNHQXG9pLDc3lhvpKQ3W1obzWUFxvyG80ZDcb0lsNye2G+E5DdLchvNeYut+YfNCYeNgYf9QY'
        + 'e9wYfdIYedoYftYQPG8MvWjgLxuDrxrY68bAmwb6ttH/roG8b/R9aMAfG72fGtDnRs+XBvi10d3U'
        + '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', 'base64');
      await p.setInputFiles('#chatTep', { name: 'ca-sang.png', mimeType: 'image/png', buffer: PNG });
      await p.waitForTimeout(700);
      await p.fill('#chatO', 'Ảnh quầy lúc mở cửa');
      await p.click('#btChatGui');
      await p.waitForTimeout(2200);
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
