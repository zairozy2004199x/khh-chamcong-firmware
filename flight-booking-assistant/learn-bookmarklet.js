// Chế độ "Học form" của Dò Vé Rẻ.
//
// Chạy trên chính trang đặt vé: bấm vào một ô trên trang rồi chọn xem ô đó là gì.
// Cuối cùng bấm "Chép luật" và dán lại vào ô "Luật riêng theo trang" trong Dò Vé Rẻ.
// Từ lần sau, nút "Điền hộ" khớp ô bằng selector đã học nên không phụ thuộc nhãn nữa.

function DVR_LEARN(){
  if(window.__dvrLearn) { window.__dvrLearn.stop(); return; }

  var KEYS = [
    ["full","Họ và tên (cả họ lẫn tên)"], ["last","Họ / Surname"], ["first","Tên đệm và tên / Given name"],
    ["dob","Ngày sinh"], ["gender","Giới tính"], ["idNo","Số CCCD / hộ chiếu"], ["nat","Quốc tịch"],
    ["phone","Điện thoại"], ["email","Email nhận vé"], ["ctName","Tên người liên hệ"],
    ["company","Tên công ty (hoá đơn)"], ["tax","Mã số thuế"], ["invEmail","Email nhận hoá đơn"],
    ["addr","Địa chỉ hoá đơn"], ["buyer","Người mua hàng"], ["cardHolder","Tên chủ thẻ"],
    ["from","Điểm đi"], ["to","Điểm đến"], ["depDate","Ngày đi"], ["retDate","Ngày về"],
    ["adt","Số người lớn"], ["chd","Số trẻ em"], ["inf","Số em bé"]
  ];
  var host = location.hostname.replace(/^www\./,"");
  var rules = [];

  var sel = function(el){
    if(el.name) {
      var byName = document.querySelectorAll(el.tagName.toLowerCase() + '[name="' + el.name + '"]');
      if(byName.length === 1) return el.tagName.toLowerCase() + '[name="' + el.name + '"]';
    }
    if(el.id && /^[A-Za-z][\w-]*$/.test(el.id)) return "#" + el.id;
    if(el.getAttribute("data-testid")) return '[data-testid="' + el.getAttribute("data-testid") + '"]';
    if(el.placeholder) return el.tagName.toLowerCase() + '[placeholder="' + el.placeholder.replace(/"/g,'\\"') + '"]';
    var path = [], n = el;
    while(n && n.nodeType === 1 && path.length < 5){
      var part = n.tagName.toLowerCase();
      if(n.parentElement){
        var sibs = [].slice.call(n.parentElement.children).filter(function(c){ return c.tagName === n.tagName; });
        if(sibs.length > 1) part += ":nth-of-type(" + (sibs.indexOf(n)+1) + ")";
      }
      path.unshift(part);
      n = n.parentElement;
    }
    return path.join(" > ");
  };

  var panel = document.createElement("div");
  panel.setAttribute("style","position:fixed;z-index:2147483647;right:16px;bottom:16px;width:310px;max-height:70vh;overflow:auto;background:#0B2440;color:#E9F0F8;font:13px/1.45 system-ui,sans-serif;padding:14px;box-shadow:0 10px 40px rgba(0,0,0,.45);border-left:4px solid #E8890B");
  var head = "<div style='font:600 15px/1.2 system-ui'>Học form · " + host + "</div>"
    + "<div style='margin:6px 0 10px;color:#8FA9C4'>Bấm vào một ô trên trang, rồi chọn ô đó là gì.</div>";
  var list = document.createElement("div");
  var foot = document.createElement("div");
  foot.setAttribute("style","display:flex;gap:8px;margin-top:12px");
  foot.innerHTML = "<button id='dvrCopy' style=\"flex:1;font:600 13px system-ui;background:#E8890B;color:#1B1000;border:0;padding:8px;cursor:pointer\">Chép luật</button>"
    + "<button id='dvrStop' style=\"font:600 13px system-ui;background:transparent;color:#E9F0F8;border:1px solid #1C3E63;padding:8px 10px;cursor:pointer\">Xong</button>";
  panel.innerHTML = head;
  panel.appendChild(list);
  panel.appendChild(foot);
  document.body.appendChild(panel);

  var draw = function(){
    list.innerHTML = rules.length
      ? rules.map(function(r,i){ return "<div style='display:flex;justify-content:space-between;gap:8px;padding:4px 0;border-top:1px solid #1C3E63'><span style='color:#E8890B'>" + r.key + "</span><code style='color:#8FA9C4;font-size:11px;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap' title='" + r.sel + "'>" + r.sel + "</code></div>"; }).join("")
      : "<div style='color:#8FA9C4;font-style:italic'>chưa ghi ô nào</div>";
  };
  draw();

  var menu = null;
  var closeMenu = function(){ if(menu){ menu.remove(); menu = null; } };

  var onClick = function(e){
    var el = e.target;
    if(panel.contains(el)) return;
    if(!/^(INPUT|SELECT|TEXTAREA)$/.test(el.tagName)) return;
    e.preventDefault(); e.stopPropagation();
    closeMenu();
    var r = el.getBoundingClientRect();
    menu = document.createElement("div");
    menu.setAttribute("style","position:fixed;z-index:2147483647;left:" + Math.min(r.left, innerWidth-270) + "px;top:" + Math.min(r.bottom+4, innerHeight-300) + "px;width:260px;max-height:280px;overflow:auto;background:#fff;color:#0B2440;font:13px system-ui;box-shadow:0 10px 40px rgba(0,0,0,.35);border:1px solid #CCD6E1");
    menu.innerHTML = KEYS.map(function(k){
      return "<div data-k='" + k[0] + "' style='padding:7px 10px;cursor:pointer;border-bottom:1px solid #E1E7EE'>" + k[1] + "</div>";
    }).join("") + "<div data-k='' style='padding:7px 10px;cursor:pointer;color:#8C3310'>Bỏ qua ô này</div>";
    menu.addEventListener("click", function(ev){
      var k = ev.target.getAttribute("data-k");
      if(k === null) return;
      if(k){
        var s = sel(el);
        for(var z = rules.length - 1; z >= 0; z--) if(rules[z].sel === s) rules.splice(z, 1);
        rules.push({ sel: s, key: k });
        el.style.outline = "2px solid #E8890B";
        draw();
      }
      closeMenu();
    });
    document.body.appendChild(menu);
  };

  document.addEventListener("click", onClick, true);

  foot.querySelector("#dvrCopy").addEventListener("click", function(){
    var out = {}; out[host] = rules;
    var txt = JSON.stringify(out, null, 2);
    var ta = document.createElement("textarea");
    ta.value = txt; document.body.appendChild(ta); ta.select();
    try { document.execCommand("copy"); this.textContent = "Đã chép " + rules.length + " luật"; }
    catch(e){ this.textContent = "Không chép được"; }
    ta.remove();
  });

  var stop = function(){
    document.removeEventListener("click", onClick, true);
    closeMenu(); panel.remove(); window.__dvrLearn = null;
  };
  foot.querySelector("#dvrStop").addEventListener("click", stop);
  window.__dvrLearn = { stop: stop, rules: rules };
}

if (typeof module !== 'undefined') module.exports = DVR_LEARN;
