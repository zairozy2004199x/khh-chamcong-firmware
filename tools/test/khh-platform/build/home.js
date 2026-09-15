/* Trang chủ — bệ phóng ứng dụng */
(function(){
'use strict';
var A=window.APP,esc=A.esc;
var cat='all',q='',clockT=null;
var CATS=[{k:'all',l:'Tất cả'},{k:'work',l:'Công việc+'},{k:'hrm',l:'Nhân sự+'},{k:'info',l:'Thông tin+'}];

function greet(){
  var h=new Date().getHours();
  var g=h<11?'Chào buổi sáng':h<14?'Chào buổi trưa':h<18?'Chào buổi chiều':'Chào buổi tối';
  return g+(A.S.me?', '+A.S.me:'')}

function clockHtml(){
  var d=new Date();
  return '<div class="clock"><span id="clkHM">'+A.pad(d.getHours())+':'+A.pad(d.getMinutes())+'</span>'+
    '<small id="clkS">:'+A.pad(d.getSeconds())+'</small></div>'+
    '<div class="clock-d">'+esc(A.DOW[d.getDay()])+', '+A.fmtD(d)+'</div>'}

function view(){
  var apps=A.apps.filter(function(a){return a.id!=='home'});
  var nq=A.norm(q);
  var list=apps.filter(function(a){
    if(cat!=='all'&&a.cat!==cat)return false;
    if(nq&&A.norm(a.name+' '+a.desc).indexOf(nq)<0)return false;
    return true});
  var ann=A.col('posts').filter(function(p){return p.kind==='announce'})
    .sort(function(a,b){return new Date(b.at)-new Date(a.at)}).slice(0,5);
  return '<div class="launch"><div class="launch-in">'+
    '<div class="launch-top">'+A.icon('grid','ic')+'<span class="co">Công ty K&amp;H — Nền tảng quản trị nội bộ</span>'+
      '<span class="spacer"></span>'+
      '<button class="app-t" type="button" id="meBtn" style="flex-direction:row;padding:6px 10px;gap:8px">'+
        A.av(A.S.me||'?','')+'<span class="nm">'+esc(A.S.me||'Đặt tên')+'</span></button>'+
      '<button class="ir" type="button" id="railNotif" style="color:#BFD4E8"'+
        (A.notifCount()?' data-n="'+A.notifCount()+'"':'')+' aria-label="Thông báo">'+A.icon('bell')+'</button>'+
      '<button class="ir" type="button" id="railTheme" style="color:#BFD4E8" aria-label="Đổi nền">'+A.icon('moon')+'</button>'+
    '</div>'+
    '<div class="lsearch"><input id="appSearch" type="search" placeholder="Tìm kiếm ứng dụng" value="'+esc(q)+'" aria-label="Tìm ứng dụng"></div>'+
    '<div class="lcats">'+CATS.map(function(c){
      return '<button class="lcat'+(c.k===cat?' on':'')+'" type="button" data-cat="'+c.k+'">'+esc(c.l)+'</button>'}).join('')+'</div>'+
    '<div class="apps">'+(list.length?list.map(function(a){
      return '<button class="app-t" type="button" data-go="'+a.id+'">'+
        '<span class="app-ic" style="background:'+a.color+'">'+A.icon(a.icon,'')+'</span>'+
        '<span class="nm">'+esc(a.name)+'</span><span class="ds">'+esc(a.desc)+'</span></button>'}).join('')
      :'<p style="color:#A8C4DD;grid-column:1/-1;text-align:center;padding:20px">Không có ứng dụng nào khớp.</p>')+'</div>'+
    '<div class="lbottom"><div>'+clockHtml()+'</div>'+
      '<div class="ann"><div class="ann-h">Thông báo công ty</div>'+
      (ann.length?ann.map(function(p){
        return '<button class="ann-r" type="button" data-go="inside" data-arg="'+p.id+'">'+
          A.av(p.author,'s')+'<span class="t">'+esc(p.title)+'</span>'+
          '<span class="m">'+esc(p.author)+', '+A.fmtDM(p.at)+'</span></button>'}).join('')
        :'<p style="color:#8FAECB;font-size:12px">Chưa có thông báo nào.</p>')+
      '</div></div>'+
    '</div></div>'}

function after(){
  if(clockT)clearInterval(clockT);
  clockT=setInterval(function(){
    var hm=document.getElementById('clkHM'),s=document.getElementById('clkS');
    if(!hm||!s){clearInterval(clockT);clockT=null;return}
    var d=new Date();
    hm.textContent=A.pad(d.getHours())+':'+A.pad(d.getMinutes());
    s.textContent=':'+A.pad(d.getSeconds())},1000);
  var si=document.getElementById('appSearch');
  if(si)si.addEventListener('input',function(){q=this.value;A.render()});
  A.$$('[data-cat]').forEach(function(b){
    b.addEventListener('click',function(){cat=b.getAttribute('data-cat');A.render()})});
}

A.register({id:'home',name:'Trang chủ',desc:'Bệ phóng ứng dụng',cat:'platform',color:'#1177D8',icon:'home',
  side:false,info:false,view:view,after:after});
})();
