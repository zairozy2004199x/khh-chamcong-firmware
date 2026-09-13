// Hàm điền hộ của "Dò Vé Rẻ" — bản đọc được, để sửa bảng luật RULES.
//
// Sửa xong thì chép nguyên hàm này đè lên hàm DVR_FILL trong index.html
// (trang tự đóng gói nó thành bookmarklet và thành script Playwright).
//
// Cách khớp, theo hai vòng:
//   vòng 1 — chỉ nhìn chữ của CHÍNH ô đó (name, id, placeholder, aria-label, nhãn <label> của nó);
//   vòng 2 — nếu vòng 1 không ra, mới nhìn thêm chữ đứng trước ô, và chỉ lấy những khối
//            không chứa ô nhập nào khác, để không bị lây nhãn của ô liền trên.
// Bảng luật chạy từ riêng tới chung. Ô thứ n của cùng một luật nhận dữ liệu của hành khách thứ n.
// Ô nào dính số thẻ / CVV / OTP / captcha thì bỏ qua — cố ý.

function DVR_FILL(D){
  var strip = function(s){
    return (s||"").normalize("NFD").replace(/[\u0300-\u036f]/g,"").toLowerCase()
      .replace(/\u0111/g,"d").replace(/[_\-.]+/g," ").replace(/\s+/g," ").trim();
  };
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
  var nearSig = function(el){
    var s = "", p = el.parentElement;
    for(var k=0; k<3 && p; k++){
      var prev = p.previousElementSibling;
      if(prev && !prev.querySelector("input, select, textarea")) s += " " + prev.textContent;
      p = p.parentElement;
    }
    return strip(s).slice(0,200);
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
    var proto = el.tagName === "TEXTAREA" ? HTMLTextAreaElement.prototype : HTMLInputElement.prototype;
    var d = Object.getOwnPropertyDescriptor(proto, "value");
    if(d && d.set) d.set.call(el, v); else el.value = v;
    el.dispatchEvent(new Event("input",{bubbles:true}));
    el.dispatchEvent(new Event("change",{bubbles:true}));
    el.dispatchEvent(new Event("blur",{bubbles:true}));
    return true;
  };
  var P = function(n, k){ var p = D.pax[Math.min(n, D.pax.length-1)] || {}; return p[k]; };
  var RULES = [
    {k:"tax",    re:/(ma so thue|tax ?(code|id|number)|\bmst\b)/,                              get:function(){ return D.invoice.tax; }},
    {k:"inveml", re:/((hoa don|vat|invoice|billing).{0,16}(e ?mail)|(e ?mail).{0,16}(hoa don|vat|invoice|billing))/, get:function(){ return D.invoice.email; }},
    {k:"comp",   re:/(ten (cong ty|don vi|doanh nghiep)|cong ty|company|organi[sz]ation|business name)/, get:function(){ return D.invoice.company; }},
    {k:"buyer",  re:/(nguoi mua|buyer)/,                                                        get:function(){ return D.invoice.buyer; }},
    {k:"addr",   re:/(dia chi|address|street)/,                                                 get:function(){ return D.invoice.addr; }},
    {k:"cardnm", re:/(ten chu the|chu the|card ?holder|name on card)/,                           get:function(){ return D.pay.holder; }},
    {k:"phone",  re:/(dien thoai|so dt|\bsdt\b|phone|mobile|\btel\b)/,                           get:function(){ return D.contact.phone; }},
    {k:"email",  re:/(e ?mail)/,                                                                 get:function(){ return D.contact.email; }},
    {k:"dob",    re:/(ngay sinh|date ?of ?birth|\bdob\b|birth ?date|birthday|\bsinh\b)/,         get:function(n){ return [P(n,"dob"), P(n,"dobIso")]; }},
    {k:"gender", re:/(gioi tinh|gender|\bsex\b|danh xung|\btitle\b|xung ho)/,                    get:function(n){ return [P(n,"genderVi"), P(n,"genderEn"), P(n,"genderShort")]; }},
    {k:"idno",   re:/(so (cccd|cmnd|giay to|ho chieu|the)|\bcccd\b|\bcmnd\b|passport|id ?(no|number)|identity|giay to tuy than)/, get:function(n){ return P(n,"idNo"); }},
    {k:"nat",    re:/(quoc tich|nationality|quoc gia|country)/,                                  get:function(n){ return [P(n,"natVi"), P(n,"natEn"), P(n,"natCode")]; }},
    {k:"full",   re:/(ho (va )?ten|full ?name|passenger ?name|contact ?name|ten (hanh khach|khach|day du|nguoi|lien he))/, get:function(n){ return P(n,"full"); }},
    {k:"first",  re:/(ho dem|ten dem|first ?name|given ?name|middle ?name|(^| )ten( |$))/,       get:function(n){ return P(n,"first"); }},
    {k:"last",   re:/(surname|last ?name|family ?name|(^| )ho( |$))/,                            get:function(n){ return P(n,"last"); }}
  ];
  var SKIP = /(cvv|cvc|card ?number|so the|ma bao mat|expir|het han|\botp\b|captcha|coupon|promo|ma giam|mat khau|password)/;

  var seen = {}, filled = 0, skipped = 0;
  var nodes = document.querySelectorAll("input, select, textarea");
  for(var i=0;i<nodes.length;i++){
    var el = nodes[i];
    if(el.disabled || el.readOnly) continue;
    if(/^(hidden|password|submit|button|checkbox|radio|file|image|reset|search|range|color)$/.test(el.type||"")) continue;
    if(el.offsetParent === null && el.tagName !== "SELECT") continue;
    if(el.tagName !== "SELECT" && String(el.value||"").trim() !== "") continue;

    var own = ownSig(el);
    var both = (own + " " + nearSig(el)).trim();
    if(SKIP.test(both)){ skipped++; continue; }

    var done = false;
    for(var pass=0; pass<2 && !done; pass++){
      var s = pass === 0 ? own : both;
      if(pass === 1 && s === own) break;
      for(var j=0;j<RULES.length;j++){
        if(!RULES[j].re.test(s)) continue;
        var n = seen[RULES[j].k] || 0;
        var vals = [].concat(RULES[j].get(n)).filter(function(v){ return v !== undefined && v !== null && v !== ""; });
        if(!vals.length){ done = true; break; }            // đúng loại nhưng hồ sơ đang trống
        if(setVal(el, vals)){ seen[RULES[j].k] = n + 1; filled++; done = true; break; }
        // select không có lựa chọn nào khớp → thử luật kế tiếp
      }
    }
  }
  var box = document.createElement("div");
  box.textContent = "Dò Vé Rẻ đã điền " + filled + " ô" + (skipped ? " · bỏ qua " + skipped + " ô thẻ/OTP (cố ý)" : "");
  box.setAttribute("style","position:fixed;z-index:2147483647;left:50%;bottom:20px;transform:translateX(-50%);background:#0B2440;color:#E9F0F8;font:14px/1.4 system-ui,sans-serif;padding:11px 16px;border-left:4px solid #E8890B;box-shadow:0 8px 30px rgba(0,0,0,.35)");
  document.body.appendChild(box);
  setTimeout(function(){ box.remove(); }, 6000);
  return filled;
}

if (typeof module !== 'undefined') module.exports = DVR_FILL;
