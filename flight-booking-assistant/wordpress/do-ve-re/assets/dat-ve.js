"use strict";
const $ = s => document.querySelector(s);
const vnd = n => new Intl.NumberFormat("vi-VN").format(Math.round(n)) + "đ";
const Q = new URLSearchParams(location.search);

const FLIGHT = {
  route: Q.get("route") || "", date: Q.get("date") || "",
  airline: Q.get("airline") || "", number: Q.get("number") || "",
  dep: Q.get("dep") || "", arr: Q.get("arr") || "",
  stops: +Q.get("stops") || 0, bag: Q.get("bag") === "1", cabin: Q.get("cabin") || "ECONOMY"
};
const FARE_EACH = +Q.get("fare") || 0;
const CUR = Q.get("cur") || "VND";
const ADT = Math.max(1, +Q.get("adt") || 1), CHD = +Q.get("chd") || 0, INF = +Q.get("inf") || 0;
const PAX_N = ADT + CHD;
const FARE_TOTAL = +Q.get("total") || Math.round(FARE_EACH * (ADT + CHD * 0.9 + INF * 0.12));

/* ---- chuyến ---- */
if(FLIGHT.route){
  const [a,b] = FLIGHT.route.split("-");
  $("#title").textContent = a + " → " + b;
  const d = FLIGHT.date.split("-");
  $("#flightInfo").innerHTML =
    '<div style="font:600 22px/1.2 \'Barlow Condensed\',sans-serif;letter-spacing:.04em">'
    + FLIGHT.airline + " " + FLIGHT.number + " · " + FLIGHT.dep + " — " + FLIGHT.arr + '</div>'
    + '<div class="hint" style="margin-top:4px">' + (d[2] ? d[2] + "/" + d[1] + "/" + d[0] : FLIGHT.date)
    + " · " + (FLIGHT.stops ? FLIGHT.stops + " điểm dừng" : "bay thẳng")
    + " · " + (FLIGHT.bag ? "có ký gửi" : "chỉ xách tay")
    + " · " + PAX_N + " khách" + (INF ? " + " + INF + " em bé" : "") + "</div>";
} else {
  $("#sub").textContent = "Mở trang này từ nút “Đặt qua mình” trong Dò Vé Rẻ để có sẵn thông tin chuyến.";
}

/* ---- hành khách ---- */
function paxCard(i){
  return '<div class="panel pad" style="box-shadow:none;background:var(--surface-2)">'
    + '<span class="lbl">Khách ' + (i+1) + '</span>'
    + '<div class="field"><label for="p'+i+'full">Họ và tên (như trên giấy tờ)</label><input id="p'+i+'full" data-f="full" required placeholder="NGUYEN VAN A"></div>'
    + '<div class="two">'
    + '<div class="field"><label for="p'+i+'dob">Ngày sinh</label><input type="date" id="p'+i+'dob" data-f="dob"></div>'
    + '<div class="field"><label for="p'+i+'idNo">Số CCCD / hộ chiếu</label><input id="p'+i+'idNo" data-f="idNo" class="num"></div>'
    + '</div></div>';
}
let paxCount = PAX_N;
function drawPax(){ $("#paxList").innerHTML = Array.from({length: paxCount}, (_,i) => paxCard(i)).join(""); }
drawPax();
$("#addPax").addEventListener("click", () => { paxCount++; drawPax(); quote(); });

/* ---- tạm tính ---- */
$("#paxNote").textContent = PAX_N ? "(" + PAX_N + " khách" + (INF ? " + " + INF + " em bé" : "") + ")" : "";
function quote(){
  if(!FARE_TOTAL) return;
  // phí tính ngay tại đây cho khách thấy trước; máy chủ vẫn tính lại khi tạo đơn
  const f = DVR.fee || { pct: 0, flat: 0, min: 0 };
  const phi = Math.max(Math.round(FARE_TOTAL * f.pct / 100) + f.flat, f.min);
  $("#sumFare").textContent = vnd(FARE_TOTAL);
  $("#sumFee").textContent = phi ? vnd(phi) : "miễn phí";
  $("#sumTotal").textContent = vnd(FARE_TOTAL + phi);
}
quote();

/* ---- tra mã số thuế ---- */
let taxT;
$("#invTax").addEventListener("input", () => {
  clearTimeout(taxT);
  taxT = setTimeout(async () => {
    const mst = $("#invTax").value.replace(/[^\d-]/g, "");
    if(!/^\d{10}(-\d{3})?$/.test(mst)){ $("#taxStatus").textContent = ""; return; }
    $("#taxStatus").textContent = "đang tra " + mst + "…";
    try {
      const r = await fetch(DVR.rest + "/tax?mst=" + mst, { headers: { "X-WP-Nonce": DVR.nonce } });
      const j = await r.json();
      if(!r.ok) throw new Error(j.error);
      if(j.company && !$("#invCompany").value) $("#invCompany").value = j.company;
      if(j.address && !$("#invAddr").value) $("#invAddr").value = j.address;
      $("#taxStatus").textContent = j.company;
    } catch(e){ $("#taxStatus").textContent = "không tra được mã số thuế — điền tay giúp nhé"; }
  }, 600);
});

/* ---- tạo đơn ---- */
$("#form").addEventListener("submit", async e => {
  e.preventDefault();
  $("#formErr").textContent = "";
  $("#submit").disabled = true;
  const pax = [...document.querySelectorAll("#paxList .panel")].map(c => {
    const o = {};
    c.querySelectorAll("[data-f]").forEach(el => o[el.dataset.f] = el.value.trim());
    return o;
  });
  try {
    const r = await fetch(DVR.rest + "/orders", {
      method: "POST", headers: { "content-type": "application/json", "X-WP-Nonce": DVR.nonce },
      body: JSON.stringify({
        flight: FLIGHT, fare: { total: FARE_TOTAL, cur: CUR }, pax,
        contact: { name: $("#ctName").value.trim(), phone: $("#ctPhone").value.trim(), email: $("#ctEmail").value.trim() },
        invoice: { tax: $("#invTax").value.trim(), company: $("#invCompany").value.trim(), addr: $("#invAddr").value.trim() }
      })
    });
    const j = await r.json();
    if(!r.ok) throw new Error(j.error);
    showOrder(j);
  } catch(err){
    $("#formErr").textContent = "Chưa tạo được đơn: " + err.message;
    $("#submit").disabled = false;
  }
});

/* ---- màn chuyển khoản ---- */
let poll, tick;
function showOrder(o){
  $("#form").hidden = true;
  $("#payBox").hidden = false;
  // chưa chốt giá thì chưa hiện số tài khoản: tránh khách chuyển theo giá tham khảo
  const choBaoGia = o.status === "cho_bao_gia";
  ["#qr","#bkName","#bkAcc","#bkOwner","#bkAmount","#bkNote"].forEach(s => {
    const el = document.querySelector(s); if(el) el.closest(".split,.kv,div").style.opacity = choBaoGia ? ".35" : "1";
  });
  if(choBaoGia){
    $("#payNote").textContent = "Giá trên bảng là giá tham khảo. Chúng tôi đang kiểm chỗ thật với hãng và báo giá chính thức trong khoảng "
      + (o.baoGiaPhut || 15) + " phút — kèm số tài khoản. Chưa cần chuyển tiền lúc này.";
  }
  $("#orderCode").textContent = o.code;
  $("#qr").src = o.qr || "";
  $("#qr").hidden = !o.qr;
  $("#qr").onerror = () => {
    $("#qr").hidden = true;
    $("#qrNote").textContent = "Không tải được ảnh mã QR — chuyển khoản tay theo số tài khoản bên cạnh, nhớ giữ nguyên nội dung.";
  };
  $("#bkName").textContent = o.bank.bankLabel || (o.bank.bankId ? "mã VietQR " + o.bank.bankId : "—");
  $("#bkAcc").textContent = o.bank.account || "—";
  $("#bkOwner").textContent = o.bank.accountName || "—";
  $("#bkAmount").textContent = vnd(o.money.total);
  $("#bkNote").textContent = o.code;
  try { history.replaceState(null, "", location.pathname + "?don=" + o.code); } catch(e){}
  const until = Date.parse(o.expiresAt);
  clearInterval(tick);
  const dem = () => {
    const left = until - Date.now();
    if(left <= 0){ $("#countdown").textContent = "Đã quá hạn giữ giá — vui lòng đặt lại đơn."; clearInterval(tick); return; }
    const m = Math.floor(left/60000), s = Math.floor(left%60000/1000);
    $("#countdown").textContent = "Giá giữ thêm " + m + " phút " + String(s).padStart(2,"0") + " giây.";
  };
  dem();                                  // hiện ngay, không đợi hết giây đầu
  tick = setInterval(dem, 1000);
  clearInterval(poll);
  poll = setInterval(() => refresh(o.code), 5000);
}
async function refresh(code){
  try {
    const r = await fetch(DVR.rest + "/orders/" + code, { headers: { "X-WP-Nonce": DVR.nonce } });
    const j = await r.json();
    if(!r.ok) return;
    const tag = $("#orderStatus");
    tag.textContent = j.statusText || j.status;
    tag.className = "tag " + (j.status === "da_xuat_ve" ? "ok"
      : ["hoan_tien","huy","het_han"].includes(j.status) ? "bad" : "wait");
    if(j.status !== "cho_thanh_toan"){ clearInterval(tick); $("#countdown").textContent = ""; }
    if(j.status === "cho_thanh_toan" && j.bank){
      // vừa được chốt giá: hiện số tài khoản và số tiền chính thức
      $("#bkAcc").textContent = j.bank.account || "";
      $("#bkName").textContent = j.bank.bankLabel || j.bank.bankId || "";
      $("#bkOwner").textContent = j.bank.accountName || "";
      $("#bkAmount").textContent = vnd(j.money.total);
      $("#bkNote").textContent = j.code;
      if(j.qr){ $("#qr").src = j.qr; $("#qr").hidden = false; }
      ["#qr","#bkName","#bkAcc","#bkOwner","#bkAmount","#bkNote"].forEach(s => {
        const el = document.querySelector(s); if(el) el.closest(".split,.kv,div").style.opacity = "1";
      });
      $("#payNote").textContent = "Đã có giá chính thức. Chuyển khoản theo số bên trên, giữ nguyên nội dung là mã đơn.";
    }
    if(j.status === "huy") $("#payNote").textContent = "Đơn đã huỷ — xem email để biết lý do. Chúng tôi chưa thu đồng nào.";
    if(j.status === "da_nhan_tien") $("#payNote").textContent = "Đã nhận tiền. Chúng tôi đang mua vé, mã đặt chỗ sẽ hiện ngay tại đây và gửi vào email.";
    if(j.status === "da_xuat_ve"){
      $("#pnrBox").hidden = false; $("#pnr").textContent = j.pnr;
      $("#payNote").textContent = "Vé đã xuất. Giữ mã đặt chỗ này để làm thủ tục.";
      clearInterval(poll);
    }
    if(j.status === "hoan_tien"){ $("#payNote").textContent = "Đơn đã hoàn tiền."; clearInterval(poll); }
  } catch(e){}
}
$("#copyAcc").addEventListener("click", () => navigator.clipboard.writeText($("#bkAcc").textContent));
$("#copyNote").addEventListener("click", () => navigator.clipboard.writeText($("#bkNote").textContent));

// mở lại bằng ?don=DVR... thì xem thẳng trạng thái đơn cũ
const cu = Q.get("don");
if(cu){
  fetch(DVR.rest + "/orders/" + cu, { headers: { "X-WP-Nonce": DVR.nonce } }).then(r => r.json()).then(j => {
    if(j.code){ showOrder({ ...j, bank: j.bank || {}, qr: j.qr || "" }); refresh(j.code); }
  });
}
