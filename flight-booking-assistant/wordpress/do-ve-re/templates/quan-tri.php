<?php
/** Trang Đơn hàng trong khu quản trị WordPress. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="dvr"><div class="wrap">

  <header class="row" style="justify-content:space-between;align-items:end">
    <div>
      <span class="eyebrow">Bảng điều khiển</span>
      <h1>Đơn hàng</h1>
    </div>
    <button class="btn sm" type="button" id="load">Tải lại</button>
  </header>

  <div class="tiles" id="tiles"></div>

  <div class="chips" id="filters">
    <button class="chip" data-f="" aria-pressed="true">Tất cả</button>
    <button class="chip" data-f="cho_thanh_toan" aria-pressed="false">Chờ chuyển khoản</button>
    <button class="chip" data-f="da_nhan_tien" aria-pressed="false">Đã nhận tiền</button>
    <button class="chip" data-f="da_xuat_ve" aria-pressed="false">Đã xuất vé</button>
    <button class="chip" data-f="het_han" aria-pressed="false">Quá hạn</button>
    <button class="chip" data-f="hoan_tien" aria-pressed="false">Hoàn tiền</button>
  </div>

  <div class="panel scroll">
    <table>
      <thead><tr><th>Đơn</th><th>Chuyến</th><th>Khách</th><th>Tiền</th><th>Trạng thái</th><th>Việc cần làm</th></tr></thead>
      <tbody id="rows"><tr><td colspan="6" class="hint">Đang tải đơn…</td></tr></tbody>
    </table>
  </div>

  <p class="hint" id="msg"></p>
  <footer class="hint">
    Nút <b>Điền hộ</b> ở mỗi đơn mang đúng thông tin khách của đơn đó: kéo lên thanh dấu trang, mở trang hãng, bấm nó.
    Mua xong thì bấm <b>Mã đặt chỗ</b> để đóng đơn — nhập cả giá mua vào thì bảng trên tự tính lãi.
  </footer>
</div>

</div>
