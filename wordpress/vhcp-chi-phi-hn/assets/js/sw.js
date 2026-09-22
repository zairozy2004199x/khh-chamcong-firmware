/**
 * THỢ NỀN CỦA APP CHI PHÍ (service worker).
 *
 * Việc của nó đúng một câu: **mở app lên là thấy giao diện ngay, kể cả lúc mạng ở cơ sở đang
 * chết**. Nó KHÔNG giữ hộ đơn nào — đơn chi phí phải do máy chủ ghi, không do điện thoại nhớ.
 *
 * ⚠️ Tệp này được PHP phục vụ ở địa chỉ `/<slug>/sw.js` (xem `VHCPHN_Pwa::ra_sw`), và `__BAN__` bị
 *    thay bằng số bản plugin lúc phục vụ. Đừng sửa `__BAN__` thành số cụ thể ở đây.
 *
 * =================================================================================================
 * 🔴 BỐN LUẬT, BỎ CÁI NÀO CŨNG HỎNG THEO KIỂU IM LẶNG
 * =================================================================================================
 * 1. KHÔNG BAO GIỜ NHỚ MỘT LƯỢT GỌI DỮ LIỆU. Mọi đường hỏi/ghi của app đều mang `vhcphn_api`,
 *    `/wp-json/` hay `admin-ajax.php`. Nhớ hộ một lượt "danh sách đơn" là kế toán mở app ra thấy
 *    bảng của hôm qua và tưởng đơn mình vừa gửi biến mất; nhớ hộ một lượt "tổng chi" là con số
 *    tiền sai mà trông vẫn bình thường. Bảng tiền nói sai còn tệ hơn bảng tiền không mở được.
 * 2. TRANG APP LẤY MẠNG TRƯỚC, CÁI ĐÃ NHỚ CHỈ LÀ ĐƯỜNG LUI. Lấy cái đã nhớ trước thì bản vá đẩy
 *    lên hôm nay phải đợi tới lần mở sau nữa mới tới tay người dùng — mà bộ này ra bản mỗi ngày.
 * 3. TỆP TĨNH CỦA CHÍNH BỘ NÀY (css · js · ảnh biểu tượng) THÌ NHỚ HẲN, vì chúng mang `?ver=` đổi
 *    theo số bản: địa chỉ đổi là lượt nhớ cũ thành vô dụng, không bao giờ đưa nhầm bản cũ.
 * 4. CHỈ ĐỤNG VÀO GET, CÙNG TÊN MIỀN. Lượt POST (chính là lượt ghi đơn) đi thẳng, không qua tay
 *    thợ nền — không có chỗ nào để lỡ.
 */

'use strict';

var BAN    = '__BAN__';
var KHO_VO = 'vhcphn-vo-' + BAN;      // vỏ app: trang /<slug>/
var KHO_TT = 'vhcphn-tt-' + BAN;      // tệp tĩnh: css, js, ảnh
var TT_TOI_DA = 120;

/* Địa chỉ trang app — thợ nền nằm ở `/<slug>/sw.js` nên thư mục chứa nó chính là phạm vi app. */
var TRANG = new URL('./', self.location.href).href;

self.addEventListener('install', function (e) {
  e.waitUntil(
    caches.open(KHO_VO).then(function (kho) {
      /* Nạp sẵn đúng TRANG APP. Trang này giống hệt nhau với mọi người — `VHCPHN_App::render()`
         không nhét tên ai vào HTML, người dùng do JavaScript nạp sau — nên nhớ nó rồi đưa lại
         cho người khác trên máy dùng chung cũng không lộ gì. */
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
        if (t.indexOf('vhcphn-') !== 0) { return null; }       // kho của bộ khác, không đụng
        if (t === KHO_VO || t === KHO_TT) { return null; }
        return caches.delete(t);
      }));
    }).then(function () {
      /* Quản luôn những tab đang mở, không đợi họ đóng rồi mở lại. */
      return self.clients.claim();
    })
  );
});

/** Cắt bớt kho tệp tĩnh cho khỏi phình. Xoá từ cái cũ nhất — `keys()` trả theo thứ tự thêm vào. */
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
    '<body style="margin:0;background:#fff;color:#171417;' +
    'font:16px/1.6 system-ui,-apple-system,Segoe UI,Roboto,sans-serif">' +
    '<div style="max-width:420px;margin:0 auto;padding:48px 20px">' +
    '<h1 style="font-size:20px;margin:0 0 12px">Chưa mở được trang chi phí</h1>' +
    '<p style="opacity:.8;margin:0 0 8px">Máy đang không có mạng, và app chưa kịp nhớ trang nào.</p>' +
    '<p style="opacity:.8;margin:0">Có mạng lại thì mở lần nữa — đơn đã gửi vẫn nằm trên máy chủ, ' +
    'không mất đi đâu cả.</p>' +
    '</div></body></html>',
    { status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8' } }
  );
}

/**
 * Lượt gọi này có phải ĐƯỜNG DỮ LIỆU không.
 *
 * 🔴 DÒ BA ĐƯỜNG, KHÔNG PHẢI MỘT. App có ba cổng gọi máy chủ (xem `VHCPHN_App::head_block`):
 *    `/wp-json/`, `admin-ajax.php`, và chính URL trang kèm `vhcphn_api` — cổng cuối sinh ra vì
 *    Cloudflare hay chặn hai cổng trước. Bỏ sót cổng nào là thợ nền nhớ hộ dữ liệu qua cổng ấy,
 *    và bảng tiền hiện số của lần mở trước.
 */
function laDuLieu(u) {
  return u.searchParams.has('vhcphn_api')
    || u.pathname.indexOf('/wp-json/') === 0
    || u.pathname.indexOf('/admin-ajax.php') >= 0;
}

/**
 * Tệp tĩnh của CHÍNH bộ này — chúng mang `?ver=` đổi theo số bản nên nhớ hẳn là an toàn.
 *
 * 🔴 SO VỚI THƯ MỤC PLUGIN DO PHP NHÉT VÀO, KHÔNG DÒ BẰNG MỘT KHUÔN CHỮ. Bản đầu dò
 *    thư mục plugin bằng một biểu thức chính quy chứa tiền tố của bộ này — và
 *    `tools/tach-ban-vung.sh` đổi MỌI chuỗi tiền tố trong tệp này thành `<tiền tố><mã vùng>`,
 *    nên ở bản vùng cái khuôn ấy hoá thành một chuỗi KHÔNG khớp tên thư mục nào cả. Hỏng im
 *    lặng: thợ nền thôi nhớ css/js, app mở ngoại tuyến ra trơ khung. Địa chỉ thật thì không
 *    đoán sai được.
 *
 * ⚠️ VÀ ĐỪNG VIẾT TÊN THẬT CỦA BẢN VÙNG RA ĐÂY, kể cả trong lời văn: `kiem-tach-ban-vung.php`
 *    quét bản gốc tìm mọi chuỗi của bản vùng và đỏ nếu thấy — phép ấy sinh ra từ sự cố
 *    08/09/2026 (một bản vùng chiếm mất đường REST, bản gốc mở ra TRỐNG TRƠN). Nhắc tên trong
 *    chú thích thì vô hại, nhưng làm phép quét kêu oan, mà một phép hay kêu oan là một phép
 *    sắp bị nới lỏng. Ai cần biết tên bản vùng thì đọc `tools/tach-ban-vung.sh`.
 */
var GOC_TINH = '__GOC_TINH__';
function laTinh(u) {
  if (!GOC_TINH || GOC_TINH.charAt(0) === '_') { return false; }   // PHP chưa thay -> thà không nhớ
  var g;
  try { g = new URL(GOC_TINH, self.location.href); } catch (x) { return false; }
  if (u.origin !== g.origin) { return false; }
  return u.pathname.indexOf(g.pathname) === 0
    && /\.(css|js|png|jpg|jpeg|svg|woff2?)$/i.test(u.pathname);
}

self.addEventListener('fetch', function (e) {
  var req = e.request;
  if (req.method !== 'GET') { return; }                       // luật 4
  var u;
  try { u = new URL(req.url); } catch (x) { return; }
  if (u.origin !== self.location.origin) { return; }          // luật 4
  if (laDuLieu(u)) { return; }                                // luật 1 — đi thẳng, không đụng

  /* Luật 3 — tệp tĩnh: lấy cái đã nhớ trước cho nhanh, không có thì tải rồi nhớ lại. */
  if (laTinh(u)) {
    e.respondWith(
      caches.match(req).then(function (co) {
        if (co) { return co; }
        return fetch(req).then(function (r) {
          if (r && r.ok) {
            var ban = r.clone();
            caches.open(KHO_TT).then(function (kho) {
              return kho.put(req, ban);
            }).then(function () { return tia(KHO_TT, TT_TOI_DA); });
          }
          return r;
        });
      })
    );
    return;
  }

  /* Luật 2 — trang app (và mọi lượt điều hướng): mạng trước, cái đã nhớ chỉ là đường lui. */
  if (req.mode === 'navigate' || u.href === TRANG) {
    e.respondWith(
      fetch(req).then(function (r) {
        if (r && r.ok) {
          var ban = r.clone();
          caches.open(KHO_VO).then(function (kho) { return kho.put(TRANG, ban); });
        }
        return r;
      }).catch(function () {
        return caches.match(TRANG).then(function (co) { return co || trangLui(); });
      })
    );
  }
});
