/* Khối Kế toán — nhúng các ứng dụng kế toán chạy ở plugin riêng vào nền tảng.

   "Ủy nhiệm chi & Công nợ" và "Báo cáo chi phí" là hai plugin WordPress độc lập: trang
   riêng, bảng riêng, đăng nhập riêng. Nền tảng KHÔNG gọi vào ruột chúng — PHP chỉ dò
   xem plugin nào đang cài rồi đưa sang đây danh sách (KH_API.ketoan), ở đây nhúng trang
   ấy vào khung làm việc. Chưa cài plugin nào thì không khai ứng dụng nào, và nhóm
   "Kế toán+" tự ẩn khỏi màn hình chính — không để lại ô chết.

   Chỉ Chủ sở hữu và Quản trị thấy: khai ở `vai`, core.js lọc cả ở bệ phóng lẫn thanh
   biểu tượng, và chặn luôn lúc mở bằng id đã nhớ trong máy. */
(function () {
  'use strict';

  var A = window.APP;
  var esc = A.esc;
  var DS = (window.KH_API && window.KH_API.ketoan) || [];

  function khung(u) {
    return function () {
      return '<section class="ketoan">' +
        '<header class="kt-top">' +
          '<span class="kt-ten">' + esc(u.ten) + '</span>' +
          (u.mo ? '<span class="kt-mo">' + esc(u.mo) + '</span>' : '') +
          '<span class="spacer"></span>' +
          '<a class="kt-ngoai" href="' + esc(u.url) + '" target="_blank" rel="noopener">Mở tab mới ↗</a>' +
        '</header>' +
        '<iframe class="kt-khung" src="' + esc(u.url) + '" title="' + esc(u.ten) + '"></iframe>' +
        '</section>';
    };
  }

  DS.forEach(function (u) {
    if (!u || !u.id || !u.url) { return; }
    A.register({
      id: 'kt_' + u.id,
      name: u.ten,
      desc: u.mo || '',
      cat: 'ketoan',
      color: u.mau || '#1D5B8F',
      icon: u.icon || 'unc',
      vai: ['owner', 'admin'],
      side: false,
      info: false,
      view: khung(u)
    });
  });
})();
