<?php
/** Bảng giá — shortcode [do_ve_re]. Markup giữ nguyên id để assets/bang-gia.js dùng lại. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="dvr"><div class="wrap">

<header class="hero" id="dvr-top">
  <svg class="hero-bay" viewBox="0 0 230 96" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
    <path d="M18 62h52l22-26h20l-9 26h44c8 0 14 3 14 7s-6 7-14 7H63c-6 0-11-2-14-6L18 62Z" fill="#fff"/>
    <path d="M96 62h36l-8 12h-18l-10-12Z" fill="#DCEAFF"/>
    <path d="M136 36h20l-9 26h-19l8-26Z" fill="#FF7A18"/>
    <circle cx="150" cy="66" r="3" fill="#BFE0FF"/><circle cx="163" cy="66" r="3" fill="#BFE0FF"/>
    <path d="M8 76h58" stroke="#fff" stroke-width="3" stroke-linecap="round" opacity=".75"/>
    <path d="M26 84h28" stroke="#fff" stroke-width="3" stroke-linecap="round" opacity=".5"/>
  </svg>
  <div class="hero-in">
    <?php
    $logo = dvr_cai_dat( 'logo_url', '' );
    echo '<a class="logo" href="' . esc_url( home_url( '/' ) ) . '">';
    if ( $logo ) {
        echo '<img src="' . esc_url( $logo ) . '" alt="' . esc_attr( get_bloginfo( 'name' ) ) . '">';
    } else {
        ?>
        <span class="logo-dau" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M2.4 11.2 20.9 3.1c.7-.3 1.4.4 1.1 1.1l-8.1 18.5c-.3.7-1.3.6-1.5-.1l-2.1-6.5a1 1 0 0 0-.6-.6L3.2 13.4c-.7-.2-.8-1.2-.8-1.5Z" fill="#fff"/>
          <path d="M10.3 15.5 20.8 4.3" stroke="#FFD7B0" stroke-width="1.3" stroke-linecap="round"/>
        </svg></span>
        <span class="logo-chu"><b><?php echo esc_html( dvr_cai_dat( 'hero_ten', 'Dò Vé Rẻ' ) ); ?></b><small>Vé máy bay trực tuyến</small></span>
        <?php
    }
    echo '</a>';
    ?>
    <h1><?php echo esc_html( dvr_cai_dat( 'hero_tieu_de', 'Vé máy bay trực tuyến' ) ); ?></h1>
    <p class="lede"><?php echo esc_html( dvr_cai_dat( 'hero_phu_de', 'Dò giá theo chặng và ngày, so nhiều hãng cùng lúc, chọn chuyến rẻ nhất rồi đặt ngay tại đây. Chúng tôi mua vé và gửi mã đặt chỗ vào email của quý khách.' ) ); ?></p>
    <div class="hangs">
      <span class="hang"><i style="background:#0B6B54"></i>Vietnam Airlines</span>
      <span class="hang"><i style="background:#D9432B"></i>Vietjet Air</span>
      <span class="hang"><i style="background:#1F7A3D"></i>Bamboo Airways</span>
      <span class="hang"><i style="background:#FF7A18"></i>Vietravel Airlines</span>
    </div>
  </div>

  <aside class="statecard">
    <span class="eyebrow">Nguồn giá</span>
    <span class="tag" id="srcTag">Đang dò…</span>
    <p id="srcNote">Khoá Amadeus khai trong Quản trị → Dò Vé Rẻ → Cài đặt. Chưa khai thì bảng chạy bằng giá mô phỏng.</p>
  </aside>
</header>

<form class="searchbar" id="searchForm">
  <div class="sb-row">
    <div class="field">
      <label for="from">Điểm đi</label>
      <input id="from" name="from" list="airports" value="SGN" autocomplete="off" spellcheck="false">
    </div>
    <button type="button" class="swapbtn" id="swapBtn" title="Đảo chiều" aria-label="Đảo chiều điểm đi và điểm đến">⇄</button>
    <div class="field">
      <label for="to">Điểm đến</label>
      <input id="to" name="to" list="airports" value="HAN" autocomplete="off" spellcheck="false">
    </div>
    <div class="field"><label for="dep">Ngày đi</label><input type="date" id="dep" name="dep"></div>
    <div class="field"><label for="ret">Ngày về</label><input type="date" id="ret" name="ret" disabled></div>
    <div class="field"><label for="adt">Người lớn</label><input type="number" id="adt" name="adt" min="1" max="9" value="1"></div>
    <div class="field">
      <label for="cabin">Hạng vé</label>
      <select id="cabin" name="cabin">
        <option value="ECONOMY">Phổ thông</option>
        <option value="PREMIUM_ECONOMY">Phổ thông đặc biệt</option>
        <option value="BUSINESS">Thương gia</option>
      </select>
    </div>
    <button class="btn" type="submit">Dò giá</button>
  </div>
  <datalist id="airports"></datalist>
  <div class="sb-foot">
    <div class="chips" role="group" aria-label="Loại hành trình">
      <button type="button" class="chip" id="tripOw" aria-pressed="true">Một chiều</button>
      <button type="button" class="chip" id="tripRt" aria-pressed="false">Khứ hồi</button>
      <span class="chip" style="cursor:default;background:transparent;border-style:dashed">Trẻ 2–11
        <input type="number" id="chd" min="0" max="8" value="0" style="width:44px;padding:2px 4px;margin-left:6px;display:inline-block"></span>
      <span class="chip" style="cursor:default;background:transparent;border-style:dashed">Em bé &lt;2
        <input type="number" id="inf" min="0" max="4" value="0" style="width:44px;padding:2px 4px;margin-left:6px;display:inline-block"></span>
    </div>
    <p class="hint">Gõ mã sân bay hoặc tên thành phố: <span class="num">SGN</span>, <span class="num">HAN</span>, Đà Nẵng, Phú Quốc…</p>
  </div>
</form>

<section class="sec" aria-label="Giá theo ngày">
  <div class="strip">
    <button class="arrow" type="button" id="stripPrev" aria-label="Tuần trước">‹</button>
    <div class="strip-days" id="strip"></div>
    <button class="arrow" type="button" id="stripNext" aria-label="Tuần sau">›</button>
  </div>
  <p class="hint" id="calNote">Bấm một ngày để đổi ngày đi. Cột rẻ nhất tuần được tô xanh.</p>
</section>

<main class="results">
  <aside class="rail" id="rail"></aside>

  <div>
    <div class="topbar">
      <div class="sorts" role="group" aria-label="Sắp xếp">
        <button class="sort" type="button" data-sort="price" aria-pressed="true">Rẻ nhất</button>
        <button class="sort" type="button" data-sort="dep" aria-pressed="false">Bay sớm nhất</button>
        <button class="sort" type="button" data-sort="dur" aria-pressed="false">Nhanh nhất</button>
      </div>
      <span class="count" id="boardSub">chưa dò</span>
    </div>
    <div class="res" id="rows">
      <div class="empty">Bấm <b>Dò giá</b> để xem bảng giá.</div>
    </div>
  </div>
</main>

<section class="sec">
  <span class="eyebrow">Bước 3 · đối chiếu giá thật</span>
  <h2>Mở song song nơi đang bán chặng này</h2>
  <p class="hint">Mỗi thẻ mở đúng chặng, đúng ngày, đúng số khách anh vừa nhập — không phải gõ lại. Nhóm “trang hãng” không nhận tham số qua đường dẫn nên mở về trang chủ; lúc đó dùng nút <b>Điền hộ</b> ở mục dưới.</p>
  <div class="chan-grid" id="chans"></div>
  <div class="actions">
    <button class="btn ghost sm" type="button" id="openAll">Mở cùng lúc 5 trang so giá</button>
    <span class="hint">Trình duyệt có thể chặn cửa sổ bật lên — chọn “luôn cho phép” cho trang này.</span>
  </div>
</section>

<section class="sec">
  <span class="eyebrow">Bước 4 · khai một lần, dùng mãi</span>
  <h2>Hồ sơ điền sẵn</h2>
  <p class="hint">Lưu trong máy anh (localStorage của trình duyệt này), không gửi đi đâu hết. Nút <b>Điền hộ</b> bên dưới là thứ mang hồ sơ này sang form đặt vé của hãng.</p>

  <div class="cols">
    <div class="sec">
      <div class="panel-head" style="padding-inline:0;border-bottom:1px solid var(--line)">
        <h3>Hành khách</h3>
        <button class="btn ghost sm" type="button" id="addPax">+ Thêm khách</button>
      </div>
      <div class="sec" id="paxList"></div>
    </div>

    <div class="sec">
      <div class="panel-head" style="padding-inline:0;border-bottom:1px solid var(--line)"><h3>Liên hệ &amp; hoá đơn VAT</h3></div>
      <div class="two">
        <div class="field"><label for="ctName">Người liên hệ</label><input id="ctName" name="ctName" autocomplete="name" placeholder="Nguyễn Văn A"></div>
        <div class="field"><label for="ctPhone">Điện thoại</label><input id="ctPhone" name="ctPhone" inputmode="tel" autocomplete="tel" placeholder="0912 345 678"></div>
      </div>
      <div class="field"><label for="ctEmail">Email nhận vé</label><input id="ctEmail" name="ctEmail" type="email" autocomplete="email" placeholder="ten@congty.com"></div>
      <div class="field"><label for="invCompany">Tên đơn vị xuất hoá đơn</label><input id="invCompany" name="invCompany" autocomplete="organization" placeholder="Công ty TNHH K&amp;H"></div>
      <div class="two">
        <div class="field"><label for="invTax">Mã số thuế</label><input id="invTax" name="invTax" class="num-in" inputmode="numeric" placeholder="0312345678"><span class="hint" id="taxStatus">gõ đủ 10 số, máy tự điền tên và địa chỉ công ty</span></div>
        <div class="field"><label for="invEmail">Email nhận hoá đơn</label><input id="invEmail" name="invEmail" type="email" placeholder="ketoan@congty.com"></div>
      </div>
      <div class="field"><label for="invAddr">Địa chỉ trên hoá đơn</label><input id="invAddr" name="invAddr" autocomplete="street-address" placeholder="Số 1, đường …, phường …, TP …"></div>
      <div class="field"><label for="invBuyer">Người mua hàng</label><input id="invBuyer" name="invBuyer" placeholder="để trống = lấy người liên hệ"></div>

      <div class="panel-head" style="padding-inline:0;border-bottom:1px solid var(--line)"><h3>Thanh toán</h3></div>
      <div class="two">
        <div class="field">
          <label for="payMethod">Hình thức</label>
          <select id="payMethod" name="payMethod">
            <option>Thẻ quốc tế (Visa/Master)</option>
            <option>Thẻ ATM nội địa</option>
            <option>QR ngân hàng</option>
            <option>Ví điện tử (Momo/ZaloPay)</option>
          </select>
        </div>
        <div class="field"><label for="payBank">Ngân hàng</label><input id="payBank" name="payBank" placeholder="Vietcombank"></div>
      </div>
      <div class="field"><label for="payHolder">Tên chủ thẻ (in trên thẻ)</label><input id="payHolder" name="payHolder" placeholder="NGUYEN VAN A"></div>
      <div class="safe"><b>Số thẻ và CVV cố ý không có chỗ nhập ở đây.</b> Hai số đó để trình duyệt hoặc trình quản lý mật khẩu của anh giữ và tự điền ở bước cuối — an toàn hơn hẳn việc một trang web lưu lại. Máy điền xong toàn bộ phần còn lại, anh chỉ chọn thẻ đã lưu rồi nhập OTP.</div>
    </div>
  </div>

  <div class="actions">
    <button class="btn sm" type="button" id="saveProfile">Lưu hồ sơ vào máy</button>
    <a class="btn ghost sm" id="fillLink" href="#" title="Kéo nút này lên thanh dấu trang của trình duyệt">Điền hộ (kéo lên bookmark)</a>
    <button class="btn ghost sm" type="button" id="copyFill">Sao chép mã “Điền hộ”</button>
    <button class="btn ghost sm" type="button" id="copyJson">Sao chép hồ sơ JSON</button>
    <button class="btn ghost sm" type="button" id="copyBot">Sao chép script tự đặt (Playwright)</button>
    <button class="btn ghost sm" type="button" id="clearProfile">Xoá hồ sơ</button>
  </div>
  <div class="sec" style="margin-top:4px">
    <div class="panel-head" style="padding:0 0 10px;border-bottom:1px solid var(--line)">
      <h3>Luật riêng theo trang</h3>
      <span class="hint" id="sitesCount">chưa học trang nào</span>
    </div>
    <p class="hint">Trang của hãng hay đổi giao diện, và nhiều ô trên đó chẳng có nhãn nào để đoán. Khi “Điền hộ” điền hụt: kéo nút <b>Học form</b> lên bookmark, bấm nó ngay trên trang đặt vé, bấm vào từng ô rồi chọn ô đó là gì, cuối cùng bấm <b>Chép luật</b> và dán khối JSON xuống đây. Từ lần sau máy khớp ô bằng selector đã học, không đoán nữa.</p>
    <div class="actions">
      <a class="btn ghost sm" id="learnLink" href="#" title="Kéo nút này lên thanh dấu trang">Học form (kéo lên bookmark)</a>
      <button class="btn ghost sm" type="button" id="copyLearn">Sao chép mã “Học form”</button>
    </div>
    <textarea id="sitesBox" spellcheck="false" placeholder='{ "vietjetair.com": [ { "sel": "input[name=&quot;txtFrom&quot;]", "key": "from" } ] }'></textarea>
    <div class="actions"><button class="btn sm" type="button" id="saveSites">Lưu luật riêng</button></div>
  </div>

  <p class="hint">Cách dùng nút <b>Điền hộ</b>: kéo nó lên thanh dấu trang → mở trang đặt vé của hãng → bấm dấu trang đó. Nó dò các ô “họ tên / ngày sinh / CMND / MST / địa chỉ…” trên trang và điền vào, kể cả trang tiếng Anh.</p>
</section>

<section class="sec">
  <span class="eyebrow">Bước 5 · ranh giới</span>
  <h2>Máy làm tới đâu, anh làm từ đâu</h2>
  <div class="steps">
    <div class="step"><div class="step-n">01</div><div><b>Dò giá &amp; xếp chuyến</b><p>Máy quét chặng, ngày, lịch giá 7 ngày quanh đó và chỉ ra chuyến rẻ nhất theo bộ lọc của anh.</p></div><span class="state auto">Tự động</span></div>
    <div class="step"><div class="step-n">02</div><div><b>Mở đúng trang, đúng chặng</b><p>Một cú bấm mở song song các trang bán vé với chặng – ngày – số khách đã điền sẵn trên đường dẫn.</p></div><span class="state auto">Tự động</span></div>
    <div class="step"><div class="step-n">03</div><div><b>Điền hành khách + hoá đơn VAT</b><p>Nút “Điền hộ” hoặc script Playwright đổ toàn bộ hồ sơ vào form. Trang nào đổi giao diện thì máy điền hụt vài ô, nhìn qua một lượt là thấy.</p></div><span class="state half">Máy điền, anh soát</span></div>
    <div class="step"><div class="step-n">04</div><div><b>Chọn thẻ &amp; nhập OTP</b><p>Ngân hàng bắt buộc người thật xác thực (3-D Secure). Không hệ thống nào hợp lệ mà vượt qua được bước này thay anh.</p></div><span class="state hand">Anh làm tay</span></div>
    <div class="step"><div class="step-n">05</div><div><b>Giữ mã vé &amp; hoá đơn</b><p>Sau khi thanh toán, lưu mã đặt chỗ và đối chiếu email hoá đơn VAT đúng mã số thuế đã khai.</p></div><span class="state half">Máy nhắc, anh lưu</span></div>
  </div>
  <p class="hint">Một điều nên biết trước: nhiều hãng và đại lý cấm robot đặt vé trong điều khoản sử dụng, và họ chặn bằng captcha. Cách làm ở đây là <b>điền hộ trên chính trình duyệt của anh</b> — anh vẫn là người bấm mua — chứ không phải một con bot chạy ngầm mua vé hàng loạt.</p>
</section>

<footer class="foot">
  <p>Dò Vé Rẻ · công cụ nội bộ, chạy hoàn toàn trong trình duyệt. Hồ sơ nằm ở máy anh; không có máy chủ nào nhận dữ liệu.</p>
  <p>Giá hiển thị là <b>giá mô phỏng</b> cho tới khi nối API bán vé thật. Giá cuối cùng luôn là giá trên trang thanh toán của hãng.</p>
</footer>

</div>
<div class="toast" id="toast" hidden></div>
</div>
