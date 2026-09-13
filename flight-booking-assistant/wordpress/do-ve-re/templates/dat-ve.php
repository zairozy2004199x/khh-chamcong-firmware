<?php
/** Trang khách đặt vé — shortcode [do_ve_re_dat_ve]. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="dvr"><div class="wrap">

  <header>
    <span class="eyebrow">Đặt vé qua Dò Vé Rẻ</span>
    <h1 id="title">Xác nhận chuyến</h1>
    <p class="hint" id="sub">Điền thông tin hành khách, chuyển khoản theo mã QR, phần mua vé để chúng tôi lo.</p>
  </header>

  <section class="panel pad dark" id="flightBox">
    <span class="lbl">Chuyến bay</span>
    <div id="flightInfo">Chưa có chuyến nào được chọn.</div>
  </section>

  <form class="panel pad" id="form">
    <h2>Hành khách</h2>
    <div id="paxList" class="row" style="display:grid;gap:14px"></div>
    <button class="btn ghost sm" type="button" id="addPax" style="width:max-content">+ Thêm khách</button>

    <h2 style="margin-top:6px">Liên hệ</h2>
    <div class="two">
      <div class="field"><label for="ctName">Người liên hệ</label><input id="ctName" autocomplete="name" required></div>
      <div class="field"><label for="ctPhone">Điện thoại</label><input id="ctPhone" inputmode="tel" placeholder="0912345678" required></div>
    </div>
    <div class="field"><label for="ctEmail">Email nhận vé</label><input id="ctEmail" type="email" required></div>

    <h2 style="margin-top:6px">Hoá đơn VAT <span class="hint">(để trống nếu không cần)</span></h2>
    <div class="two">
      <div class="field"><label for="invTax">Mã số thuế</label><input id="invTax" class="num" inputmode="numeric" placeholder="0312345678"></div>
      <div class="field"><label for="invCompany">Tên đơn vị</label><input id="invCompany"></div>
    </div>
    <div class="field"><label for="invAddr">Địa chỉ trên hoá đơn</label><input id="invAddr"></div>
    <p class="hint" id="taxStatus"></p>

    <div class="panel pad" style="background:var(--surface-2);box-shadow:none">
      <div class="split"><span>Giá vé <span class="hint" id="paxNote"></span></span><b class="money" id="sumFare">—</b></div>
      <div class="split"><span>Phí dịch vụ</span><b class="money" id="sumFee">—</b></div>
      <div class="split"><span><b>Tổng phải chuyển</b></span><b class="money total" id="sumTotal">—</b></div>
    </div>

    <button class="btn" type="submit" id="submit">Tạo đơn &amp; lấy mã chuyển khoản</button>
    <p class="hint" id="formErr" style="color:var(--warn)"></p>
    <p class="hint">Chúng tôi không nhận số thẻ. Khách chuyển khoản ngân hàng theo mã QR, nội dung là mã đơn.</p>
  </form>

  <section class="panel pad" id="payBox" hidden>
    <div class="row" style="justify-content:space-between">
      <div><span class="eyebrow">Đơn của anh/chị</span><h2 id="orderCode" class="num"></h2></div>
      <span class="tag wait" id="orderStatus">Chờ chuyển khoản</span>
    </div>
    <div class="two">
      <div style="display:grid;gap:10px;justify-items:center">
        <img id="qr" alt="Mã QR chuyển khoản" style="max-width:260px;width:100%;border:1px solid var(--line);background:#fff">
        <span class="hint" id="qrNote">Quét bằng app ngân hàng — số tiền và nội dung đã có sẵn trong mã.</span>
      </div>
      <div style="display:grid;gap:8px;align-content:start">
        <div class="split"><span>Ngân hàng</span><b id="bkName"></b></div>
        <div class="split"><span>Số tài khoản</span><b class="num" id="bkAcc"></b></div>
        <div class="split"><span>Chủ tài khoản</span><b id="bkOwner"></b></div>
        <div class="split"><span>Số tiền</span><b class="money" id="bkAmount"></b></div>
        <div class="split"><span>Nội dung</span><b class="num" id="bkNote"></b></div>
        <p class="hint" id="countdown"></p>
        <p class="hint"><b>Nội dung chuyển khoản phải giữ nguyên mã đơn</b> — hệ thống dò mã đó để khớp tiền về.</p>
        <div class="row">
          <button class="btn ghost sm" type="button" id="copyAcc">Chép số tài khoản</button>
          <button class="btn ghost sm" type="button" id="copyNote">Chép nội dung</button>
        </div>
      </div>
    </div>
    <p class="hint" id="payNote">Nhận được tiền, chúng tôi mua vé và gửi mã đặt chỗ vào email trên. Mua không được thì hoàn tiền đủ trong ngày.</p>
    <div id="pnrBox" hidden class="panel pad" style="background:var(--cheap-soft);border-color:var(--cheap);box-shadow:none">
      <span class="lbl">Mã đặt chỗ</span><b class="num total" id="pnr"></b>
    </div>
  </section>

  <footer class="hint">
    Giá giữ trong thời gian đếm ngược ở trên. Quá hạn mà chưa chuyển khoản thì phải đặt lại đơn theo giá mới.
  </footer>
</div>

</div>
