/**
 * ĐĂNG KÝ THỢ NỀN CHO APP CHI PHÍ.
 *
 * ⚠️ ĐỌC ĐỊA CHỈ TỪ THUỘC TÍNH CỦA CHÍNH THẺ <script> NÀY (`data-sw`, `data-pham-vi`), không gõ
 *    cứng. Bốn bản cài chung một site, mỗi bản một đường dẫn riêng; gõ cứng là ba bản kia đăng ký
 *    thợ nền của bản gốc — mà thợ nền ấy không quản đường của họ, nên nó chạy mà chẳng đỡ gì.
 *
 * 🔴 KHÔNG CHẶN GÌ NẾU TRÌNH DUYỆT KHÔNG CÓ `serviceWorker`. Trang chi phí phải mở được trên mọi
 *    máy, kể cả máy tính của kế toán chạy trình duyệt cũ. Lớp vỏ app là thứ THÊM VÀO, không phải
 *    điều kiện để trang chạy.
 */
(function () {
  'use strict';
  var the = document.currentScript;
  if (!the) {
    var ds = document.querySelectorAll('script[data-sw]');
    the = ds.length ? ds[ds.length - 1] : null;
  }
  if (!the) { return; }
  var sw = the.getAttribute('data-sw');
  var pv = the.getAttribute('data-pham-vi') || './';
  if (!sw || !('serviceWorker' in navigator)) { return; }

  /* Thợ nền chỉ chạy trên HTTPS (hoặc localhost). Trên http thường thì `register` ném lỗi —
     bắt lại, đừng để một dòng đỏ trong bảng điều khiển làm người ta tưởng trang hỏng. */
  window.addEventListener('load', function () {
    try {
      navigator.serviceWorker.register(sw, { scope: pv }).catch(function () {});
    } catch (e) { /* không có gì để làm — app vẫn chạy như một trang web bình thường */ }
  });
}());
