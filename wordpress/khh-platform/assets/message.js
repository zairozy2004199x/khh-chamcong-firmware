/* Message — Trò chuyện nội bộ (lưu trên server, hiện ngay cho người đang mở) */
(function(){
'use strict';
var A=window.APP,esc=A.esc,norm=A.norm;
var V={ch:A.ls('kh.msg.ch')||'general',q:''};
var typing={},typingT=null,lastSend=0;

function chans(){
  var c=A.col('channels').slice().sort(function(a,b){return (a.order||0)-(b.order||0)});
  if(!c.length)c=[{id:'general',name:'Chung',desc:'Kênh chung của công ty'}];
  return c.filter(function(x){
    if(!x.dm)return true;
    return (x.members||[]).some(function(n){return A.isMe(n)})})}
function isDm(c){return !!(c&&c.dm)}
function dmTitle(c){
  var other=(c.members||[]).filter(function(n){return !A.isMe(n)});
  return other.length?other.join(', '):(c.members||[]).join(', ')}
function chName(c){return isDm(c)?dmTitle(c):c.name}
function dmWith(name){
  if(!A.me()){A.toast('Đặt tên của bạn trước đã.',true);return}
  if(A.isMe(name)){A.toast('Không nhắn riêng với chính mình được.',true);return}
  var found=A.col('channels').filter(function(c){
    return c.dm&&(c.members||[]).length===2&&
      (c.members||[]).some(function(n){return A.isMe(n)})&&
      (c.members||[]).some(function(n){return norm(n)===norm(name)})})[0];
  if(found){V.ch=found.id;A.render();return}
  var o={id:A.uid(),dm:true,name:'',members:[A.me(),name],order:999,at:new Date().toISOString()};
  V.ch=o.id;A.save('channels',o)}
function chan(id){var c=chans();for(var i=0;i<c.length;i++)if(c[i].id===id)return c[i];return c[0]}
function msgs(ch){
  return A.col('messages').filter(function(m){return m.ch===ch})
    .sort(function(a,b){return new Date(a.at)-new Date(b.at)})}
function unread(ch){
  var seen=A.lsj('kh.msg.seen')||{};
  var last=seen[ch]||0;
  return msgs(ch).filter(function(m){return new Date(m.at).getTime()>last&&!A.isMe(m.by)}).length}
function markSeen(ch){
  var seen=A.lsj('kh.msg.seen')||{};seen[ch]=Date.now();A.lsj('kh.msg.seen',seen)}

function side(){
  var peers=A.peers().filter(function(p){return p&&p.name});
  return '<aside class="side">'+A.sideUser()+A.sideSearch('msSearch','Tìm trong kênh',V.q)+
    '<div class="side-list"><div class="grp"><div class="grp-h"><span>Kênh trò chuyện</span></div><div class="grp-b">'+
    chans().filter(function(c){return !c.dm}).map(function(c){
      var n=unread(c.id);
      return '<button class="pitem'+(V.ch===c.id?' on':'')+'" type="button" data-ch="'+esc(c.id)+'">'+
        '<span style="color:var(--rail-muted)">#</span><span class="t">'+esc(c.name)+'</span>'+
        (n?'<span class="n" style="color:#fff;background:var(--red);border-radius:8px;padding:0 5px">'+n+'</span>':'')+
        '</button>'}).join('')+
    '</div></div>'+
    '<div class="grp"><div class="grp-h"><span>Nhắn riêng</span></div><div class="grp-b">'+
    chans().filter(function(c){return c.dm}).map(function(c){
      var n=unread(c.id);
      return '<button class="pitem'+(V.ch===c.id?' on':'')+'" type="button" data-ch="'+esc(c.id)+'">'+
        A.av(dmTitle(c),'s')+'<span class="t">'+esc(dmTitle(c))+'</span>'+
        (n?'<span class="n" style="color:#fff;background:var(--red);border-radius:8px;padding:0 5px">'+n+'</span>':'')+
        '</button>'}).join('')+
    '<button class="pitem" type="button" data-act="newdm"><span style="color:var(--rail-muted)">+</span>'+
      '<span class="t">Nhắn riêng với…</span></button>'+
    '</div></div>'+
    '<div class="grp"><div class="grp-h"><span>Đang mở trang ('+peers.length+')</span></div><div class="grp-b">'+
    (peers.length?peers.map(function(p){
      return '<div class="pitem" style="cursor:default">'+
        '<span class="dot" style="background:var(--green);width:8px;height:8px"></span>'+
        '<span class="t">'+esc(p.name)+(p.you?' (bạn)':'')+'</span>'+
        '<span class="n">'+esc(p.app||'')+'</span></div>'}).join('')
      :'<p style="padding:8px 12px;color:var(--rail-muted);font-size:11.5px">Chưa thấy ai khác đang mở.</p>')+
    '</div></div></div><button class="side-add" type="button" data-act="newch">+ Kênh mới</button></aside>'}

function view(){
  var c=chan(V.ch);V.ch=c.id;A.ls('kh.msg.ch',V.ch);
  var q=norm(V.q);
  var list=msgs(V.ch).filter(function(m){return !q||norm(m.tx+' '+m.by).indexOf(q)>=0});
  var tps=Object.keys(typing).filter(function(n){return typing[n]>Date.now()-4000&&!A.isMe(n)});
  var h='<section class="stage"><header class="topbar">'+A.navBtn()+
    (isDm(c)?A.av(dmTitle(c),'')+'<h1>'+esc(dmTitle(c))+'</h1><span class="chip soft">nhắn riêng</span>'
      :'<h1># '+esc(c.name)+'</h1>')+
    '<span class="by">'+esc(isDm(c)?'Chỉ hai người trong cuộc trò chuyện thấy kênh này':(c.desc||''))+'</span><span class="spacer"></span>'+
    '<span class="qsearch"><input id="msQ" type="search" placeholder="Tìm tin nhắn" value="'+esc(V.q)+'" aria-label="Tìm tin nhắn"></span>'+
    '<button class="icon-btn" type="button" data-act="editch" title="Sửa kênh" aria-label="Sửa kênh">'+
      '<svg width="16" height="16" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="4" cy="10" r="1.4"/><circle cx="10" cy="10" r="1.4"/><circle cx="16" cy="10" r="1.4"/></svg></button>'+
    '</header><nav class="tabs"></nav>'+
    '<div class="content" id="content" style="display:flex;flex-direction:column-reverse">'+
    '<div class="pad" style="max-width:840px;width:100%">'+
    (list.length?groupMsgs(list):'<p class="empty">Chưa có tin nhắn nào trong kênh này. Viết dòng đầu tiên đi.</p>')+
    '</div></div>'+
    '<div style="border-top:1px solid var(--line);padding:10px 14px;background:var(--panel)">'+
    (tps.length?'<p class="by" style="margin-bottom:5px">'+esc(tps.join(', '))+' đang nhập…</p>':'')+
    '<div style="display:flex;gap:8px;align-items:flex-end">'+
    '<label class="btn ghost" for="msFile" style="cursor:pointer" title="Đính kèm ảnh hoặc tệp">📎</label>'+
    '<input id="msFile" type="file" style="display:none">'+
    '<textarea id="msIn" rows="1" placeholder="Nhắn vào '+esc(isDm(c)?dmTitle(c):'# '+c.name)+' — Enter để gửi, Shift+Enter xuống dòng" '+
      'style="flex:1;border:1px solid var(--line-2);border-radius:3px;padding:8px 10px;background:var(--panel-2);resize:none;max-height:120px"></textarea>'+
    '<button class="btn" type="button" data-act="send">Gửi</button></div></div>'+
    '</section>';
  return side()+h}

function groupMsgs(list){
  var h='',lastBy='',lastAt=0,lastDay='';
  list.forEach(function(m){
    var t=new Date(m.at).getTime(),day=A.ymd(m.at);
    if(day!==lastDay){h+='<div class="donehead" style="color:var(--muted);margin:14px 0 6px">'+esc(A.fmtD(m.at))+'</div>';
      lastDay=day;lastBy=''}
    var cont=(m.by===lastBy&&t-lastAt<300000);
    h+='<div class="cmt" style="'+(cont?'border-top:0;padding-top:2px':'')+'">'+
      (cont?'<span style="width:22px;flex:none"></span>':A.av(m.by,''))+
      '<div class="b">'+(cont?'':'<div class="hd"><span class="nm">'+esc(m.by)+
        (A.isMe(m.by)?' <span class="chip soft">bạn</span>':'')+'</span>'+
        '<span class="by">'+esc(A.fmtT(m.at))+'</span></div>')+
      (m.tx?'<div style="white-space:pre-wrap;word-break:break-word">'+esc(m.tx)+'</div>':'')+
      (m.file?(A.isImage(m.file.type)
        ? '<a href="'+esc(m.file.url)+'" target="_blank" rel="noopener noreferrer">'+
          '<img src="'+esc(m.file.url)+'" alt="'+esc(m.file.name)+'" '+
          'style="max-width:300px;max-height:220px;border-radius:3px;border:1px solid var(--line);margin-top:5px"></a>'
        : '<a class="chip soft" href="'+esc(m.file.url)+'" target="_blank" rel="noopener noreferrer" '+
          'style="display:inline-block;margin-top:5px;padding:5px 9px">📎 '+esc(m.file.name)+'</a>'):'')+
      (A.isMe(m.by)?'<button class="linkbtn" type="button" data-del="'+m.id+'" style="font-size:11px">Xoá</button>':'')+
      '</div></div>';
    lastBy=m.by;lastAt=t});
  return h}

var pendingFile=null;
function send(){
  var el=A.$('#msIn');if(!el)return;
  var tx=el.value.trim();
  if(!tx&&!pendingFile)return;
  if(!A.me()){A.toast('Đặt tên của bạn trước khi nhắn.',true);return}
  if(Date.now()-lastSend<400)return;
  lastSend=Date.now();
  el.value='';
  var f=pendingFile;pendingFile=null;
  var base={id:A.uid(),ch:V.ch,by:A.me(),tx:tx,at:new Date().toISOString()};
  if(!f){A.save('messages',base);prune(V.ch);markSeen(V.ch);return}
  A.toast('Đang tải tệp lên…');
  A.upload(f,f.name).then(function(info){
    if(info)base.file=info;
    else base.tx=(base.tx?base.tx+'\n':'')+'(không tải được tệp “'+f.name+'” lên)';
    A.save('messages',base);prune(V.ch);markSeen(V.ch)})}

function prune(ch){
  var m=msgs(ch);
  if(m.length>200)m.slice(0,m.length-200).forEach(function(x){A.remove('messages',x.id)})}

function editCh(id){
  var cc=id?A.find('channels',id):null;
  if(cc&&cc.dm){A.toast('Kênh nhắn riêng không sửa được.',true);return}
  if(!A.needRole('channel.manage','Chỉ Quản trị hoặc Quản lý mới sửa được kênh.'))return;
  var c=id?A.find('channels',id):null;
  A.form({title:c?'Sửa kênh':'Kênh mới',
    fields:[{k:'name',label:'Tên kênh',required:true,value:c?c.name:'',ph:'VD: Kỹ thuật công trường'},
      {k:'desc',label:'Mô tả',value:c?(c.desc||''):''}],
    onDelete:c?function(){
      A.col('messages').filter(function(m){return m.ch===c.id}).forEach(function(m){A.remove('messages',m.id)});
      A.remove('channels',c.id);V.ch='general'}:null,
    deleteLabel:'Xoá kênh',confirmDelete:'Xoá kênh này cùng toàn bộ tin nhắn?',
    onSave:function(v){
      var o=c?Object.assign({},c):{id:A.uid(),order:A.col('channels').length+1,at:new Date().toISOString()};
      o.name=v.name;o.desc=v.desc;
      if(!c)V.ch=o.id;
      A.save('channels',o)}})}

function after(){
  var root=A.$('#appRoot');
  root.addEventListener('click',function(e){
    var el=e.target.closest('[data-ch],[data-act],[data-del]');
    if(!el)return;var g;
    if((g=el.getAttribute('data-ch'))){V.ch=g;V.q='';markSeen(g);A.render();return}
    if((g=el.getAttribute('data-del'))){if(confirm('Xoá tin nhắn này?'))A.remove('messages',g);return}
    if((g=el.getAttribute('data-act'))){
      if(g==='send')send();
      else if(g==='newch')editCh(null);
      else if(g==='editch')editCh(V.ch);
      else if(g==='newdm'){
        var ppl=A.people().filter(function(n){return !A.isMe(n)});
        if(!ppl.length){A.toast('Chưa có ai khác trong danh bạ.',true);return}
        A.form({title:'Nhắn riêng với ai?',
          fields:[{k:'who',label:'Người nhận',type:'select',options:ppl.map(function(n){return {v:n,l:n}})}],
          saveLabel:'Mở cuộc trò chuyện',
          onSave:function(v){dmWith(v.who)}})}
      return}});
  var fi=A.$('#msFile');
  if(fi)fi.addEventListener('change',function(){
    var f=this.files&&this.files[0];
    if(!f)return;
    if(f.size>20*1024*1024){A.toast('Tệp quá 20MB, không gửi được.',true);this.value='';return}
    pendingFile=f;
    var t=A.$('#msIn');
    if(t){t.placeholder='Đã chọn “'+f.name+'” — bấm Gửi để đính kèm';t.focus()}});
  var inp=A.$('#msIn');
  if(inp){
    inp.addEventListener('keydown',function(e){
      if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();send()}});
    inp.addEventListener('input',function(){
      this.style.height='auto';this.style.height=Math.min(120,this.scrollHeight)+'px';
      if(A.me()){clearTimeout(typingT);A.emit('typing',{name:A.me(),ch:V.ch});
        typingT=setTimeout(function(){},1500)}});
    inp.focus()}
  var q=A.$('#msQ');if(q)q.addEventListener('input',function(){V.q=this.value;A.render()});
  markSeen(V.ch)}

A.onRoom('typing',function(ev){
  var d=(ev&&ev.data)||ev||{};
  if(!d.name||d.ch!==V.ch)return;
  typing[d.name]=Date.now();
  if(A.S.app==='message')A.paint()});

A.register({id:'message',name:'Trò chuyện',desc:'Chat nội bộ theo kênh',cat:'info',color:'#2D6BE4',icon:'message',
  side:true,info:false,view:view,after:after,
  badge:function(){return chans().reduce(function(n,c){return n+unread(c.id)},0)}});
})();
