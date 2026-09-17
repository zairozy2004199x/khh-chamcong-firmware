/**
 * THỢ NỀN CỦA APP CHẤM CÔNG (service worker).
 *
 * Việc của nó đúng một câu: **mở app lên là thấy giao diện ngay, kể cả lúc 3G ở cơ sở đang chết**.
 * Nó KHÔNG giữ hộ lượt chấm công nào — giờ công phải do máy chủ ghi, không do điện thoại nhớ.
 *
 * ⚠️ Tệp này được PHP phục vụ ở địa chỉ `/cc/sw.js` (xem `CCAPP_App::ra_sw`), và `__BAN__` bị thay
 *    bằng số bản plugin lúc phục vụ. Đừng sửa `__BAN__` thành số cụ thể ở đây.
 *
 * =================================================================================================
 * 🔴 BỐN LUẬT, BỎ CÁI NÀO CŨNG HỎNG THEO KIỂU IM LẶNG
 * =================================================================================================
 * 1. KHÔNG BAO GIỜ NHỚ LƯỢT GỌI `?viec=` (trừ ô bản đồ). Đó là đường đi của lệnh chấm công, danh
 *    sách công tháng, giờ máy chủ. Nhớ hộ một lượt "giờ máy chủ" là ảnh chấm công bị đóng dấu giờ
 *    của lần mở app trước — và tấm ảnh ấy là thứ DUY NHẤT dùng để đối chiếu khi có tranh cãi.
 * 2. TRANG APP LẤY MẠNG TRƯỚC, CÁI ĐÃ NHỚ CHỈ LÀ ĐƯỜNG LUI. Lấy cái đã nhớ trước thì bản vá đẩy
 *    lên hôm nay phải đợi tới lần mở sau nữa mới tới tay người dùng.
 * 3. Ô BẢN ĐỒ THÌ NHỚ HẲN. Chín ô ảnh cho mỗi lượt chấm; ảnh bản đồ đường phố không đổi. Hỏi lại
 *    máy chủ mỗi lần là đốt băng thông 3G đúng lúc người ta đang vội.
 * 4. CHỈ ĐỤNG VÀO GET, CÙNG TÊN MIỀN. Lượt POST (chính là lượt chấm công) đi thẳng, không qua tay
 *    thợ nền — không có chỗ nào để lỡ.
 */

'use strict';

var BAN = '__BAN__';
var KHO_VO  = 'ccapp-vo-' + BAN;     // vỏ app: trang /cc/ và mấy tệp tĩnh
var KHO_O   = 'ccapp-o-' + BAN;      // ô ảnh bản đồ
var O_TOI_DA = 300;                  // giữ tối đa ngần này ô bản đồ

/* Địa chỉ trang app — thợ nền nằm ở `/cc/sw.js` nên thư mục chứa nó chính là phạm vi app. */
var TRANG = new URL('./', self.location.href).href;

self.addEventListener('install', function (e) {
  e.waitUntil(
    caches.open(KHO_VO).then(function (kho) {
      /* Nạp sẵn đúng TRANG APP. Trang này giống hệt nhau với mọi người — `VHCC_Tram::render()`
         không nhét tên ai vào HTML, người dùng do JavaScript nạp sau — nên nhớ nó rồi đưa lại cho
         người khác trên máy dùng chung cũng không lộ gì. `kiem-cc-app.php` canh đúng điều đó. */
      return kho.add(new Request(TRANG, { cache: 'reload' }));
    }).catch(function () {
      /* Cài lúc đang mất mạng thì thôi, để lần sau. Không được để lỗi này chặn việc cài thợ nền:
         chặn là app mất luôn cả phần ngoại tuyến lẫn phần cập nhật. */
    }).then(function () {
      return self.skipWaiting();
    })
  );
});

self.addEventListener('activate', function (e) {
  e.waitUntil(
    caches.keys().then(function (ten) {
      return Promise.all(ten.map(function (t) {
        if (t.indexOf('ccapp-') !== 0) { return null; }      // kho của bộ khác, không đụng
        if (t === KHO_VO || t === KHO_O) { return null; }
        return caches.delete(t);
      }));
    }).then(function () {
      /* Quản luôn những tab đang mở, không đợi họ đóng rồi mở lại. Đổi lại là mã mới có thể lên
         giữa lúc trang cũ đang chạy — chấp nhận được vì trang app lấy mạng trước (luật 2), nên
         phần HTML người ta đang xem vốn đã là bản mới nhất mạng cho được. */
      return self.clients.claim();
    })
  );
});

/** Cắt bớt kho ô bản đồ cho khỏi phình. Xoá từ cái cũ nhất — `keys()` trả theo thứ tự thêm vào. */
function tia(ten, toiDa) {
  return caches.open(ten).then(function (kho) {
    return kho.keys().then(function (ds) {
      if (ds.length <= toiDa) { return null; }
      return Promise.all(ds.slice(0, ds.length - toiDa).map(function (k) { return kho.delete(k); }));
    });
  });
}

function trangLui() {
  return new Response(
    '<!doctype html><html lang="vi"><head><meta charset="utf-8">' +
    '<meta name="viewport" content="width=device-width,initial-scale=1">' +
    '<title>Mất mạng</title></head>' +
    '<body style="margin:0;background:#0B1F3A;color:#fff;' +
    'font:16px/1.6 system-ui,-apple-system,Segoe UI,Roboto,sans-serif">' +
    '<div style="max-width:420px;margin:0 auto;padding:48px 20px">' +
    '<h1 style="font-size:20px;margin:0 0 12px">Chưa có mạng</h1>' +
    '<p style="opacity:.85">Điện thoại đang không nối được vào mạng. Bật lại Wi‑Fi hoặc 3G/4G rồi ' +
    'kéo màn hình xuống để tải lại.</p>' +
    '<p style="opacity:.85"><b>Lượt chấm công chưa gửi đi được</b> — mở lại app và chấm lại khi có mạng.</p>' +
    '</div></body></html>',
    { status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8' } }
  );
}

self.addEventListener('fetch', function (e) {
  var req = e.request;

  // Luật 4 — chỉ GET, chỉ cùng tên miền.
  if (req.method !== 'GET') { return; }

  var u;
  try { u = new URL(req.url); } catch (err) { return; }
  if (u.origin !== self.location.origin) { return; }

  var viec = u.searchParams.get('viec');

  // Luật 3 — ô ảnh bản đồ: nhớ hẳn, lấy trong kho trước.
  if (viec === 'o') {
    e.respondWith(
      caches.open(KHO_O).then(function (kho) {
        return kho.match(req).then(function (co) {
          if (co) { return co; }
          return fetch(req).then(function (tl) {
            if (tl && tl.ok) { kho.put(req, tl.clone()); tia(KHO_O, O_TOI_DA); }
            return tl;
          });
        });
      })
    );
    return;
  }

  // Luật 1 — mọi lệnh khác của trạm: đi thẳng ra mạng, không nhớ gì cả.
  if (viec !== null) { return; }

  // Luật 2 — trang app: mạng trước, kho là đường lui.
  if (req.mode === 'navigate') {
    e.respondWith(
      fetch(req).then(function (tl) {
        if (tl && tl.ok) {
          var ban = tl.clone();
          caches.open(KHO_VO).then(function (kho) { kho.put(TRANG, ban); });
        }
        return tl;
      }).catch(function () {
        return caches.match(TRANG).then(function (co) { return co || trangLui(); });
      })
    );
    return;
  }

  /* Tệp tĩnh của plugin (biểu tượng, app.js, và nhất là bộ mẫu nhận diện khuôn mặt vài MB):
     đưa cái đã nhớ ra ngay rồi lặng lẽ tải bản mới về cho lần sau.

     ⚠️ Cách này khiến tệp cũ còn sống thêm ĐÚNG MỘT lượt mở app sau khi máy chủ có bản mới. Chấp
        nhận được vì mã chạy của trang trạm nằm THẲNG trong HTML (mà HTML thì lấy mạng trước), còn
        dưới `/wp-content/` chỉ là ảnh và bộ mẫu — mấy thứ gần như không đổi. Đổi thành "mạng
        trước" ở đây là mỗi lần mở app phải đợi tải lại bộ mẫu, tức là bỏ đi chính cái lợi. */
  if (u.pathname.indexOf('/wp-content/') === 0) {
    e.respondWith(
      caches.open(KHO_VO).then(function (kho) {
        return kho.match(req).then(function (co) {
          var moi = fetch(req).then(function (tl) {
            if (tl && tl.ok) { kho.put(req, tl.clone()); }
            return tl;
          }).catch(function () { return co; });
          return co || moi;
        });
      })
    );
  }
});

/* ═════════════════════════════════════════════════════════════════════════════════════════════
 * NHẮC CHẤM CÔNG — nhận tín hiệu đẩy rồi hiện thông báo.
 * ═════════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 TÍN HIỆU ĐẨY KHÔNG MANG NỘI DUNG, VÀ ĐÓ LÀ CỐ Ý (xem đầu class-ccapp-nhac.php). Máy chủ chỉ
 *    gõ cửa; chữ thì thợ nền tự đi hỏi. Nhờ vậy không một chữ nào của mình đi qua máy chủ đẩy của
 *    Apple/Google, và bộ này không phải tự viết hai trăm dòng mã hoá RFC 8291 mà không có cách
 *    nào thử.
 *
 * 🔴 PHẢI HIỆN MỘT THÔNG BÁO, KHÔNG ĐƯỢC IM. Trình duyệt cho phép gửi đẩy "âm thầm" đúng vài lần;
 *    nhận đẩy mà không hiện gì thì nó tự hiện một thông báo mặc định kiểu "This site has been
 *    updated in the background", rồi sau đó THU HỒI quyền đẩy của cả website. Nên nhánh nào của
 *    hàm này cũng phải kết thúc bằng một `showNotification`, kể cả nhánh mất mạng.
 *
 * ⚠️ `event.waitUntil` bọc TẤT CẢ. Thiếu nó là thợ nền bị tắt ngay khi hàm trả về, và lượt hỏi
 *    máy chủ chết giữa chừng — thông báo không bao giờ hiện, không báo lỗi gì.
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */

var NHAC_TAG = 'ccapp-nhac';

function chuDuPhong() {
  return {
    tieuDe: 'Chưa chấm công hôm nay',
    than: 'Mở app để chấm giúp em.',
    mo: TRANG
  };
}

function hienNhac(d) {
  return self.registration.showNotification(d.tieuDe, {
    body: d.than,
    icon: '/wp-content/plugins/vhcp-cc-app/assets/bieu-tuong-192.png',
    badge: '/wp-content/plugins/vhcp-cc-app/assets/bieu-tuong-192.png',
    /* Cùng `tag` thì thông báo mới ĐÈ lên thông báo cũ thay vì xếp chồng. Người quên chấm ba
       hôm liền không nên mở máy ra thấy ba dòng giống nhau. */
    tag: NHAC_TAG,
    renotify: true,
    requireInteraction: false,
    data: { mo: d.mo || TRANG }
  });
}

self.addEventListener('push', function (e) {
  e.waitUntil(
    self.registration.pushManager.getSubscription().then(function (dk) {
      if (!dk) { return chuDuPhong(); }
      return fetch(new URL('nhac', TRANG).href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ viec: 'hoi', endpoint: dk.endpoint })
      }).then(function (r) { return r.json(); }).then(function (j) {
        if (!j || !j.ok) { return chuDuPhong(); }
        return { tieuDe: j.tieuDe, than: j.than, mo: j.mo };
      });
    }).catch(function () {
      /* Mất mạng đúng lúc nhận đẩy. Câu chung chung vẫn nhắc đúng việc — và im lặng ở đây là
         mất quyền đẩy của cả website. */
      return chuDuPhong();
    }).then(hienNhac)
  );
});

/**
 * Bấm vào thông báo -> mở app.
 *
 * ⚠️ TÌM TAB ĐANG MỞ TRƯỚC KHI MỞ TAB MỚI. Không tìm thì mỗi lần bấm là một cửa sổ app nữa, và
 *    trên Android chúng chồng lên nhau tới lúc người ta phải vuốt đóng từng cái.
 */
self.addEventListener('notificationclick', function (e) {
  e.notification.close();
  var dich = (e.notification.data && e.notification.data.mo) || TRANG;
  e.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (ds) {
      for (var i = 0; i < ds.length; i++) {
        if (ds[i].url.indexOf(TRANG) === 0 && 'focus' in ds[i]) { return ds[i].focus(); }
      }
      if (self.clients.openWindow) { return self.clients.openWindow(dich); }
      return null;
    })
  );
});

/**
 * Hãng đổi endpoint (chuyện có thật, hiếm) -> đăng ký lại và báo cho máy chủ.
 *
 * 🔴 KHÔNG XỬ SỰ KIỆN NÀY THÌ MÁY ẤY IM VĨNH VIỄN. Endpoint cũ chết, máy chủ vẫn gửi vào đó, nhận
 *    410 rồi xoá sổ — và người dùng không bao giờ được nhắc nữa, cũng chẳng thấy gì sai để báo.
 */
self.addEventListener('pushsubscriptionchange', function (e) {
  /* Địa chỉ CŨ là thứ duy nhất chứng minh "máy này vốn đã đăng ký" — thợ nền không có thẻ phiên
     nào lúc này. Máy chủ đổi địa chỉ trên đúng hàng mang địa chỉ cũ ấy; không tìm thấy thì thôi,
     không đẻ hàng mới (xem CCAPP_Nhac::doi_dia_chi). */
  var cu = e.oldSubscription || null;
  e.waitUntil(
    Promise.resolve(cu || self.registration.pushManager.getSubscription()).then(function (c) {
      var khoa = (c && c.options && c.options.applicationServerKey) || null;
      var epCu = c && c.endpoint;
      if (!khoa || !epCu) { return null; }
      return self.registration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: khoa
      }).then(function (moi) {
        if (!moi) { return null; }
        return fetch(new URL('nhac', TRANG).href, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ viec: 'doi', cu: epCu, moi: moi.endpoint })
        });
      });
    }).catch(function () { return null; })
  );
});
