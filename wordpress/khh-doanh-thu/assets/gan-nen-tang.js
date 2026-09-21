/* Gắn báo cáo doanh thu thành một ứng dụng trong Nền tảng K&H.
   Chỉ chạy khi plugin nền tảng đang bật; không có thì bỏ qua, báo cáo vẫn dùng
   được ở trang quản trị và shortcode. */
(function () {
  'use strict';
  var A = window.APP;
  if (!A || typeof A.register !== 'function' || !window.KHHDoanhThu) return;
  if (A.byId && A.byId.doanhthu) return;

  A.register({
    id: 'doanhthu',
    name: 'Doanh thu FABi',
    icon: 'payroll',
    cat: 'info',
    color: '#184f95',
    view: function () {
      return '<section class="stage">' +
        '<div class="content" id="content" style="padding:0">' +
          '<div id="khhDtApp"></div>' +
        '</div></section>';
    },
    after: function () {
      var goc = document.getElementById('khhDtApp');
      if (goc) window.KHHDoanhThu.mount(goc);
    }
  });

  /* boot() của nền tảng đã chạy trước script này, nên vẽ lại một lượt để
     biểu tượng hiện ra trong thanh ứng dụng bên trái. */
  if (typeof A.render === 'function') A.render();
})();
