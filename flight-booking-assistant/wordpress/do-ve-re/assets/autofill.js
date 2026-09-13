// Hàm điền hộ của "Dò Vé Rẻ" — bản đọc được, để sửa bảng luật.
//
// Sửa xong thì chép nguyên hàm này đè lên hàm DVR_FILL trong index.html
// (trang tự đóng gói nó thành bookmarklet và thành script Playwright).
//
// Thứ tự làm việc:
//   1. LUẬT RIÊNG theo tên miền (D.sites) — do chế độ "Học form" ghi lại, khớp chính xác
//      bằng selector nên luôn đúng kể cả khi trang không có nhãn tiếng Việt nào.
//   2. LUẬT CHUNG — đoán theo chữ quanh ô, hai vòng:
//      vòng 1 chỉ nhìn chữ của CHÍNH ô đó (name, id, placeholder, aria-label, <label> của nó);
//      vòng 2 mới nhìn thêm chữ đứng trước ô, và chỉ lấy khối không chứa ô nhập nào khác,
//      để không bị lây nhãn của ô liền trên.
// Bảng luật chạy từ riêng tới chung. Ô thứ n của cùng một luật nhận dữ liệu của hành khách thứ n.
// Ô nào dính số thẻ / CVV / OTP / captcha thì bỏ qua — cố ý.

function DVR_FILL(D){
  var strip = function(s){
    return (s||"").normalize("NFD").replace(/[\u0300-\u036f]/g,"").toLowerCase()
      .replace(/\u0111/g,"d").replace(/[_\-.]+/g," ").replace(/\s+/g," ").trim();
  };
  var T = D.trip || {};
  var P = function(n, k){ var p = (D.pax || [])[Math.min(n, (D.pax||[]).length-1)] || {}; return p[k]; };

  // giá trị theo tên khoá — dùng chung cho luật riêng lẫn luật chung
  var VAL = {
    full:   function(n){ return P(n,"full"); },
    first:  function(n){ return P(n,"first"); },
    last:   function(n){ return P(n,"last"); },
    dob:    function(n){ return [P(n,"dob"), P(n,"dobIso")]; },
    gender: function(n){ return [P(n,"genderVi"), P(n,"genderEn"), P(n,"genderShort")]; },
    idNo:   function(n){ return P(n,"idNo"); },
    nat:    function(n){ return [P(n,"natVi"), P(n,"natEn"), P(n,"natCode")]; },
    phone:  function(){ return D.contact.phone; },
    email:  function(){ return D.contact.email; },
    ctName: function(){ return D.contact.name; },
    company:function(){ return D.invoice.company; },
    tax:    function(){ return D.invoice.tax; },
    invEmail:function(){ return D.invoice.email; },
    addr:   function(){ return D.invoice.addr; },
    buyer:  function(){ return D.invoice.buyer; },
    cardHolder: function(){ return D.pay.holder; },
    from:   function(){ return [T.from, T.fromCity]; },
    to:     function(){ return [T.to, T.toCity]; },
    depDate:function(){ return [T.dep, T.depIso]; },
    retDate:function(){ return [T.ret, T.retIso]; },
    adt:    function(){ return T.adt; },
    chd:    function(){ return T.chd; },
    inf:    function(){ return T.inf; }
  };

  var RULES = [
    // hoá đơn & liên hệ
    {k:"tax",        re:/(ma so thue|tax ?(code|id|number)|\bmst\b)/},
    {k:"invEmail",   re:/((hoa don|vat|invoice|billing).{0,16}(e ?mail)|(e ?mail).{0,16}(hoa don|vat|invoice|billing))/},
    {k:"company",    re:/(ten (cong ty|don vi|doanh nghiep)|cong ty|company|organi[sz]ation|business name)/},
    {k:"buyer",      re:/(nguoi mua|buyer)/},
    {k:"addr",       re:/(dia chi|address|street)/},
    {k:"cardHolder", re:/(ten chu the|chu the|card ?holder|name on card)/},
    {k:"phone",      re:/(dien thoai|so dt|\bsdt\b|phone|mobile|\btel\b)/},
    {k:"email",      re:/(e ?mail)/},
    // chuyến bay — ngày đứng trước điểm đi/đến vì "departure" dùng chung cho cả hai
    {k:"depDate",    re:/(ngay di|ngay khoi hanh|ngay bay|ngay xuat phat|departure ?date|depart(ure)? on|depart ?date|outbound ?date|\bdepartdate\b)/},
    {k:"retDate",    re:/(ngay ve|ngay tro ve|ngay quay ve|return ?date|inbound ?date|\breturndate\b)/},
    {k:"from",       re:/(diem di|noi di|noi khoi hanh|san bay di|bay tu|\btu\b|\bfrom\b|origin|departure ?(city|airport|station)|departing ?from|\bdeparture\b)/},
    {k:"to",         re:/(diem den|noi den|san bay den|bay den|\bden\b|destination|arrival ?(city|airport|station)|flying ?to|going ?to|(^| )to( |$)|\barrival\b)/},
    {k:"adt",        re:/(nguoi lon|so nguoi lon|\badult)/},
    {k:"chd",        re:/(tre em|so tre em|\bchild)/},
    {k:"inf",        re:/(em be|tre so sinh|\binfant|\bbaby\b)/},
    // hành khách
    {k:"dob",        re:/(ngay sinh|date ?of ?birth|\bdob\b|birth ?date|birthday|\bsinh\b)/},
    {k:"gender",     re:/(gioi tinh|gender|\bsex\b|danh xung|\btitle\b|xung ho)/},
    {k:"idNo",       re:/(so (cccd|cmnd|giay to|ho chieu|the)|\bcccd\b|\bcmnd\b|passport|id ?(no|number)|identity|giay to tuy than)/},
    {k:"nat",        re:/(quoc tich|nationality|quoc gia|country)/},
    {k:"full",       re:/(ho (va )?ten|full ?name|passenger ?name|contact ?name|ten (hanh khach|khach|day du|nguoi|lien he))/},
    {k:"first",      re:/(ho dem|ten dem|first ?name|given ?name|middle ?name|(^| )ten( |$))/},
    {k:"last",       re:/(surname|last ?name|family ?name|(^| )ho( |$))/}
  ];
  var SKIP = /(cvv|cvc|card ?number|so the|ma bao mat|expir|het han|\botp\b|captcha|coupon|promo|ma giam|mat khau|password)/;

  var ownSig = function(el){
    var s = [el.name, el.id, el.placeholder, el.getAttribute("aria-label"),
             el.getAttribute("title"), el.getAttribute("autocomplete"), el.getAttribute("data-testid")].join(" ");
    if(el.id){
      try {
        var l = document.querySelector('label[for="' + (window.CSS && CSS.escape ? CSS.escape(el.id) : el.id) + '"]');
        if(l) s += " " + l.textContent;
      } catch(e){}
    }
    var w = el.closest ? el.closest("label") : null;
    if(w) s += " " + w.textContent;
    return strip(s).slice(0,300);
  };
  var ownText = function(node){      // chữ nằm ngay trong khối, không tính chữ của ô nhập khác
    var s = "";
    for(var i=0;i<node.childNodes.length;i++){
      var c = node.childNodes[i];
      if(c.nodeType === 3) s += " " + c.nodeValue;
      else if(c.nodeType === 1 && /^(SPAN|B|STRONG|EM|I|SMALL|LABEL|P|H1|H2|H3|H4|H5|H6)$/.test(c.tagName)
              && !c.querySelector("input, select, textarea")) s += " " + c.textContent;
    }
    return s;
  };
  var nearSig = function(el){
    var s = "", p = el.parentElement;
    for(var k=0; k<3 && p; k++){
      s += " " + ownText(p);
      var prev = p.previousElementSibling;
      if(prev && !prev.querySelector("input, select, textarea")) s += " " + prev.textContent;
      p = p.parentElement;
    }
    return strip(s).slice(0,240);
  };
  var usable = function(el){
    if(!el || el.disabled || el.readOnly) return false;
    if(/^(hidden|password|submit|button|checkbox|radio|file|image|reset|range|color)$/.test(el.type||"")) return false;
    if(el.offsetParent === null && el.tagName !== "SELECT") return false;
    return true;
  };
  var setVal = function(el, vals){
    var list = [].concat(vals).filter(function(v){ return v !== undefined && v !== null && v !== ""; });
    if(!list.length) return false;
    if(el.tagName === "SELECT"){
      for(var k=0;k<list.length;k++){
        var t = strip(String(list[k]));
        for(var i=0;i<el.options.length;i++){
          var o = el.options[i];
          if(!o.value && !strip(o.textContent)) continue;
          if(strip(o.textContent) === t || strip(o.value) === t ||
             (t.length > 1 && strip(o.textContent).indexOf(t) > -1)){
            el.value = o.value;
            el.dispatchEvent(new Event("input",{bubbles:true}));
            el.dispatchEvent(new Event("change",{bubbles:true}));
            return true;
          }
        }
      }
      return false;
    }
    var v = String(list[0]);
    if(el.type === "date" && /^\d{2}\/\d{2}\/\d{4}$/.test(v)){ var q = v.split("/"); v = q[2]+"-"+q[1]+"-"+q[0]; }
    if(el.type === "number") v = String(v).replace(/[^\d]/g,"") || "0";
    var proto = el.tagName === "TEXTAREA" ? HTMLTextAreaElement.prototype : HTMLInputElement.prototype;
    var d = Object.getOwnPropertyDescriptor(proto, "value");
    try { el.focus(); } catch(e){}
    if(d && d.set) d.set.call(el, v); else el.value = v;
    el.dispatchEvent(new Event("input",{bubbles:true}));
    el.dispatchEvent(new Event("change",{bubbles:true}));
    // ô chọn sân bay thường là hộp gợi ý: đánh thức danh sách để người dùng chọn tiếp
    try { el.dispatchEvent(new KeyboardEvent("keyup",{bubbles:true, key:v.slice(-1)})); } catch(e){}
    el.dispatchEvent(new Event("blur",{bubbles:true}));
    return true;
  };

  var seen = {}, filled = 0, skipped = 0, done = [];
  var take = function(el, key){
    if(done.indexOf(el) > -1) return false;
    var n = seen[key] || 0;
    var get = VAL[key];
    if(!get) return false;
    var vals = [].concat(get(n)).filter(function(v){ return v !== undefined && v !== null && v !== ""; });
    if(!vals.length){ done.push(el); return false; }        // đúng loại nhưng hồ sơ trống
    if(!setVal(el, vals)) return false;
    seen[key] = n + 1; filled++; done.push(el);
    return true;
  };

  // ---- 1. luật riêng của trang này (do "Học form" ghi lại) ----
  var host = location.hostname.replace(/^www\./,"");
  var own = [];
  Object.keys(D.sites || {}).forEach(function(h){
    var hh = h.replace(/^www\./,"");
    if(host === hh || host.slice(-(hh.length+1)) === "." + hh) own = own.concat(D.sites[h]);
  });
  own.forEach(function(r){
    var els;
    try { els = document.querySelectorAll(r.sel); } catch(e){ return; }
    for(var i=0;i<els.length;i++){
      var el = els[i];
      if(!usable(el)) continue;
      if(el.tagName !== "SELECT" && String(el.value||"").trim() !== "") continue;
      if(take(el, r.key)) break;
    }
  });

  // ---- 2. luật chung ----
  var nodes = document.querySelectorAll("input, select, textarea");
  for(var i=0;i<nodes.length;i++){
    var el = nodes[i];
    if(!usable(el) || done.indexOf(el) > -1) continue;
    if(el.tagName !== "SELECT" && String(el.value||"").trim() !== "") continue;

    var sOwn = ownSig(el);
    var both = (sOwn + " " + nearSig(el)).trim();
    if(SKIP.test(both)){ skipped++; continue; }

    var hit = false;
    for(var pass=0; pass<2 && !hit; pass++){
      var s = pass === 0 ? sOwn : both;
      if(pass === 1 && s === sOwn) break;
      for(var j=0;j<RULES.length;j++){
        if(!RULES[j].re.test(s)) continue;
        if(take(el, RULES[j].k)){ hit = true; break; }
        if(done.indexOf(el) > -1){ hit = true; break; }     // hồ sơ trống ô này → thôi
        // select không có lựa chọn nào khớp → thử luật kế tiếp
      }
    }
  }

  var box = document.createElement("div");
  box.textContent = "Dò Vé Rẻ đã điền " + filled + " ô"
    + (own.length ? " (" + own.length + " luật riêng của " + host + ")" : "")
    + (skipped ? " · bỏ qua " + skipped + " ô thẻ/OTP (cố ý)" : "");
  box.setAttribute("style","position:fixed;z-index:2147483647;left:50%;bottom:20px;transform:translateX(-50%);background:#0B2440;color:#E9F0F8;font:14px/1.4 system-ui,sans-serif;padding:11px 16px;border-left:4px solid #E8890B;box-shadow:0 8px 30px rgba(0,0,0,.35)");
  document.body.appendChild(box);
  setTimeout(function(){ box.remove(); }, 6000);
  return filled;
}

if (typeof module !== 'undefined') module.exports = DVR_FILL;
