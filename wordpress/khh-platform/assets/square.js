/* Square — Bảng tin nội bộ (chia sẻ, thả tim, bình luận) */
(function(){
'use strict';
var A=window.APP,esc=A.esc,norm=A.norm;
var V={q:'',tag:''};
function posts(){return A.col('feed').slice().sort(function(a,b){return new Date(b.at)-new Date(a.at)})}
function tags(){var m={};posts().forEach(function(p){if(p.tag)m[p.tag]=(m[p.tag]||0)+1});return m}

function side(){
  var t=tags();
  return '<aside class="side">'+A.sideUser()+A.sideSearch('sqSearch','Tìm bài viết',V.q)+
    '<div class="side-list"><div class="grp"><div class="grp-b">'+
    '<button class="pitem'+(V.tag?'':' on')+'" type="button" data-tag="">'+A.icon('square','ic')+
      '<span class="t">Tất cả bài viết</span><span class="n">'+posts().length+'</span></button>'+
    '<button class="pitem'+(V.tag==='__mine'?' on':'')+'" type="button" data-tag="__mine">'+A.icon('hrm','ic')+
      '<span class="t">Bài của tôi</span><span class="n">'+posts().filter(function(p){return A.isMe(p.by)}).length+'</span></button>'+
    '</div></div><div class="grp"><div class="grp-h"><span>Chủ đề</span></div><div class="grp-b">'+
    Object.keys(t).map(function(k){
      return '<button class="pitem'+(V.tag===k?' on':'')+'" type="button" data-tag="'+esc(k)+'">'+
        '<span class="t">#'+esc(k)+'</span><span class="n">'+t[k]+'</span></button>'}).join('')+
    '</div></div></div></aside>'}

function view(){
  var q=norm(V.q);
  var list=posts().filter(function(p){
    if(V.tag==='__mine')return A.isMe(p.by);
    if(V.tag&&p.tag!==V.tag)return false;
    return true}).filter(function(p){return !q||norm(p.tx+' '+p.by).indexOf(q)>=0});
  return side()+'<section class="stage"><header class="topbar">'+A.navBtn()+
    '<h1>Bảng tin nội bộ</h1><span class="spacer"></span>'+
    '<span class="by">'+posts().length+' bài · '+A.peers().length+' người đang mở trang</span></header><nav class="tabs"></nav>'+
    '<div class="content" id="content"><div class="pad" style="max-width:720px;margin:0 auto">'+
    '<div class="card-box" style="margin-bottom:14px">'+
    '<div style="display:flex;gap:10px">'+A.av(A.me()||'?','l')+
    '<textarea id="sqIn" rows="2" placeholder="Chia sẻ với cả công ty — ảnh công trường, mẹo hay, lời cảm ơn…" '+
      'style="flex:1;border:1px solid var(--line-2);border-radius:3px;padding:8px 10px;background:var(--panel-2);resize:vertical"></textarea></div>'+
    '<div style="display:flex;gap:8px;margin-top:9px;align-items:center">'+
    '<input id="sqTag" placeholder="Chủ đề (vd: công trường)" style="max-width:220px;border:1px solid var(--line-2);border-radius:3px;padding:6px 9px;background:var(--panel)">'+
    '<span class="spacer"></span><button class="btn" type="button" data-act="post">Đăng bài</button></div></div>'+
    (list.length?list.map(card).join(''):'<p class="empty">Chưa có bài viết nào.</p>')+
    '</div></div></section>'}

function card(p){
  var liked=(p.likes||[]).some(function(n){return A.isMe(n)});
  return '<div class="card-box" style="margin-bottom:12px">'+
    '<div style="display:flex;gap:10px;align-items:center;margin-bottom:8px">'+A.av(p.by,'l')+
    '<div style="flex:1"><div style="font-weight:500">'+esc(p.by)+'</div>'+
    '<div class="by">'+esc(A.ago(p.at))+(p.tag?' · #'+esc(p.tag):'')+'</div></div>'+
    (A.isMe(p.by)?'<button class="linkbtn" type="button" data-del="'+p.id+'">Xoá</button>':'')+'</div>'+
    '<p style="white-space:pre-wrap;line-height:1.6">'+esc(p.tx)+'</p>'+
    '<div style="display:flex;gap:14px;align-items:center;margin-top:10px;padding-top:9px;border-top:1px solid var(--line)">'+
    '<button class="linkbtn" type="button" data-like="'+p.id+'" style="color:'+(liked?'var(--red)':'var(--muted)')+'">'+
      (liked?'♥':'♡')+' '+((p.likes||[]).length||0)+' thích</button>'+
    '<span class="by">'+((p.cmts||[]).length)+' bình luận</span>'+
    (p.likes&&p.likes.length?'<span class="spacer"></span>'+A.stack(p.likes,5):'')+'</div>'+
    ((p.cmts||[]).map(function(c){
      return '<div class="cmt">'+A.av(c.by,'s')+'<div class="b"><div class="hd"><span class="nm">'+esc(c.by)+'</span>'+
        '<span class="by">'+esc(A.ago(c.at))+'</span></div><div>'+esc(c.tx)+'</div></div></div>'}).join(''))+
    '<div style="display:flex;gap:8px;margin-top:9px">'+
    '<input class="sqc" data-cin="'+p.id+'" placeholder="Viết bình luận" style="flex:1;border:1px solid var(--line-2);border-radius:3px;padding:6px 9px;background:var(--panel)">'+
    '<button class="btn sm ghost" type="button" data-cmt="'+p.id+'">Gửi</button></div>'+
    '</div>'}

function after(){
  var root=A.$('#appRoot');
  root.addEventListener('click',function(e){
    var el=e.target.closest('[data-act],[data-like],[data-cmt],[data-del],[data-tag]');
    if(!el)return;var g;
    if(el.hasAttribute('data-tag')){V.tag=el.getAttribute('data-tag')||'';A.render();return}
    if((g=el.getAttribute('data-del'))){if(confirm('Xoá bài viết này?'))A.remove('feed',g);return}
    if((g=el.getAttribute('data-like'))){
      var p=A.find('feed',g);if(!p)return;
      if(!A.me()){A.toast('Đặt tên của bạn trước đã.',true);return}
      var o=Object.assign({},p),l=(o.likes||[]).slice();
      var i=l.map(norm).indexOf(norm(A.me()));
      if(i>=0)l.splice(i,1);else l.push(A.me());
      o.likes=l;A.save('feed',o);return}
    if((g=el.getAttribute('data-cmt'))){
      var p2=A.find('feed',g),inp=A.$('[data-cin="'+g+'"]');
      if(!p2||!inp||!inp.value.trim())return;
      var o2=Object.assign({},p2);
      o2.cmts=(o2.cmts||[]).concat([{by:A.me()||'ẩn danh',tx:inp.value.trim(),at:new Date().toISOString()}]).slice(-40);
      A.save('feed',o2);return}
    if((g=el.getAttribute('data-act'))&&g==='post'){
      var t=A.$('#sqIn'),tag=A.$('#sqTag');
      if(!t||!t.value.trim())return;
      if(!A.me()){A.toast('Đặt tên của bạn trước khi đăng.',true);return}
      A.save('feed',{id:A.uid(),by:A.me(),tx:t.value.trim(),tag:(tag&&tag.value.trim())||'',
        at:new Date().toISOString(),likes:[],cmts:[]});
      return}});
  root.addEventListener('keydown',function(e){
    if(e.key==='Enter'&&e.target.classList.contains('sqc')){
      e.preventDefault();
      var id=e.target.getAttribute('data-cin');
      var b=A.$('[data-cmt="'+id+'"]');if(b)b.click()}});
  var q=A.$('#sqSearch');if(q)q.addEventListener('input',function(){V.q=this.value;A.render()})}

A.register({id:'square',name:'Bảng tin',desc:'Chia sẻ nội bộ',cat:'info',color:'#2D6BE4',icon:'square',
  side:true,info:false,view:view,after:after});
})();
