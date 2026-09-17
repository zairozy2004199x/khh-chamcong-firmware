/**
 * LỚP VỎ APP — chạy trên chính trang chấm công.
 *
 * Hai việc, không có việc thứ ba:
 *   1. đăng ký thợ nền `/cc/sw.js`;
 *   2. khi trang đang mở trong trình duyệt (chưa cài), bày một dải nhắc cách cài lên màn hình chính.
 *
 * 🔴 KHÔNG ĐỤNG VÀO BẤT CỨ THỨ GÌ CỦA TRANG TRẠM. Không đổi CSS, không gắn thêm sự kiện lên nút
 *    nào của họ, không đọc biến nào của họ. Trang ấy do bộ `vhcp-cham-cong` giữ và đang được sửa
 *    song song; mọi thứ mình móc vào đó đều là một chỗ sẽ gãy vào ngày họ đổi tên một class.
 *    Tệp này chỉ thêm ĐÚNG MỘT phần tử của riêng mình rồi thôi.
 */

(function () {
  'use strict';

  var the = document.querySelector('script[data-sw]');
  if (!the) { return; }
  var duongSW = the.getAttribute('data-sw');
  var phamVi  = the.getAttribute('data-pham-vi') || './';

  var O_TAT = 'ccapp_tat_nhac';      // mốc thời gian người dùng bấm "Để sau"
  var NGHI   = 30 * 24 * 3600 * 1000; // nhắc lại sau 30 ngày

  // ───────────────────────────────────────────────────────────────────────── thợ nền

  if ('serviceWorker' in navigator) {
    /* Đợi `load` rồi mới đăng ký. Đăng ký sớm thì lượt tải thợ nền giành băng thông với chính
       mấy tệp trang đang cần để hiện ra — trên 3G ở cơ sở, chênh nhau vài giây thấy rõ. */
    window.addEventListener('load', function () {
      navigator.serviceWorker.register(duongSW, { scope: phamVi }).catch(function () {
        /* Không báo gì ra màn hình. Thợ nền hỏng thì app vẫn chạy y như một trang web bình
           thường — mất phần ngoại tuyến thôi. Ném một hộp lỗi vào mặt người đang vội chấm công
           để báo một việc họ không sửa được là phiền chứ không giúp. */
      });
    });
  }

  // ─────────────────────────────────────────────────────────────── đã cài rồi thì thôi

  function daCai() {
    if (window.navigator.standalone === true) { return true; }         // iOS
    try {
      return window.matchMedia('(display-mode: standalone)').matches ||
             window.matchMedia('(display-mode: minimal-ui)').matches;
    } catch (e) { return false; }
  }

  function dangTat() {
    try {
      var t = Number(localStorage.getItem(O_TAT) || 0);
      return t > 0 && (Date.now() - t) < NGHI;
    } catch (e) { return false; }   // chế độ riêng tư chặn localStorage — cứ nhắc, không sao
  }

  function tat() {
    try { localStorage.setItem(O_TAT, String(Date.now())); } catch (e) { /* kệ */ }
  }

  // ─────────────────────────────────────────────────────────────────── dải nhắc cài app

  function laIOS() {
    var ua = navigator.userAgent || '';
    if (/iPad|iPhone|iPod/.test(ua)) { return true; }
    // iPad từ iPadOS 13 khai mình là "Macintosh" — phân biệt bằng màn hình cảm ứng.
    return /Macintosh/.test(ua) && navigator.maxTouchPoints > 1;
  }

  function dungDai(loi, nut) {
    var d = document.createElement('div');
    d.id = 'ccapp-nhac';
    /* Kiểu viết thẳng vào thuộc tính style, không thêm tệp CSS: một phần tử thì không bõ, mà
       quan trọng hơn là không đẻ ra selector nào có thể vô tình trúng phần tử của trang trạm. */
    d.style.cssText = [
      'position:fixed', 'left:12px', 'right:12px',
      /* Chừa chỗ cho vạch Home của iPhone. Thiếu dòng này thì nút "Để sau" nằm đúng chỗ vuốt
         lên, bấm mãi không trúng. */
      'bottom:calc(12px + env(safe-area-inset-bottom, 0px))',
      'z-index:2147483000', 'background:#0B1F3A', 'color:#fff',
      'border:1px solid rgba(201,168,76,.55)', 'border-radius:14px',
      'padding:12px 14px', 'box-shadow:0 8px 30px rgba(0,0,0,.35)',
      'font:14px/1.45 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif',
      'display:flex', 'gap:10px', 'align-items:center'
    ].join(';');

    var chu = document.createElement('div');
    chu.style.cssText = 'flex:1;min-width:0';
    chu.innerHTML = loi;
    d.appendChild(chu);

    if (nut) { d.appendChild(nut); }

    var deSau = document.createElement('button');
    deSau.type = 'button';
    deSau.textContent = 'Để sau';
    deSau.style.cssText = 'flex:none;background:transparent;color:#C9A84C;border:0;' +
      'font:inherit;padding:8px 4px;cursor:pointer';
    deSau.addEventListener('click', function () { tat(); d.remove(); });
    d.appendChild(deSau);

    document.body.appendChild(d);
    return d;
  }

  function nutVang(chu) {
    var b = document.createElement('button');
    b.type = 'button';
    b.textContent = chu;
    b.style.cssText = 'flex:none;background:#C9A84C;color:#0B1F3A;border:0;border-radius:9px;' +
      'font:600 14px/1 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;' +
      'padding:10px 14px;cursor:pointer';
    return b;
  }

  if (daCai() || dangTat()) { return; }

  /* Android / Chrome trên máy tính: trình duyệt tự đưa ra lời mời cài, mình chỉ việc giữ lại
     rồi bày đúng lúc. Bắt buộc `preventDefault()` — không gọi thì Chrome tự hiện thanh của nó và
     `prompt()` sau đó vô tác dụng. */
  var loiMoi = null;
  window.addEventListener('beforeinstallprompt', function (ev) {
    ev.preventDefault();
    loiMoi = ev;
    if (daCai() || dangTat() || document.getElementById('ccapp-nhac')) { return; }
    var nut = nutVang('Cài app');
    var dai = dungDai('Cài <b>Chấm Công</b> lên màn hình chính cho nhanh.', nut);
    nut.addEventListener('click', function () {
      loiMoi.prompt();
      loiMoi.userChoice.then(function () { tat(); dai.remove(); });
    });
  });

  /* iPhone thì KHÔNG có `beforeinstallprompt` và cũng không có hàm nào gọi ra hộp cài — Safari
     cố ý không cho. Cách duy nhất là chỉ đường bằng chữ. Đợi 2 giây rồi mới hiện, để người vào
     chấm công cho nhanh không bị che mất nút ngay khi trang vừa mở. */
  if (laIOS()) {
    window.setTimeout(function () {
      if (daCai() || dangTat() || document.getElementById('ccapp-nhac')) { return; }
      dungDai(
        'Cài lên màn hình chính: bấm <b>Chia sẻ</b> ' +
        '<span style="color:#C9A84C">&#x2191;</span> ở thanh dưới, chọn ' +
        '<b>Thêm vào MH chính</b>.',
        null
      );
    }, 2000);
  }
})();
