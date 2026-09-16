/* INFO+ — Thông báo nội bộ, Tri thức, Họp & đặt phòng */
(function(){
'use strict';
var A=window.APP,esc=A.esc,norm=A.norm;

/* =============== Inside — thông báo nội bộ =============== */
var I={sel:null,q:'',cat:''};
function anns(){return A.col('posts').filter(function(p){return p.kind==='announce'})
  .sort(function(a,b){return (b.pinned?1:0)-(a.pinned?1:0)||new Date(b.at)-new Date(a.at)})}
function cats(){var m={};anns().forEach(function(p){if(p.cat)m[p.cat]=(m[p.cat]||0)+1});return m}

function iSide(){
  var c=cats();
  return '<aside class="side">'+A.sideUser()+
    '<div style="padding:0 10px 10px"><button class="btn" type="button" data-iact="new" style="width:100%">+ Viết thông báo</button></div>'+
    '<div class="side-list"><div class="grp"><div class="grp-b">'+
    '<button class="pitem'+(I.cat?'':' on')+'" type="button" data-icat="">'+A.icon('inside','ic')+
      '<span class="t">Tất cả thông báo</span><span class="n">'+anns().length+'</span></button>'+
    '</div></div><div class="grp"><div class="grp-h"><span>Nhóm thông báo</span></div><div class="grp-b">'+
    Object.keys(c).map(function(k){
      return '<button class="pitem'+(I.cat===k?' on':'')+'" type="button" data-icat="'+esc(k)+'">'+
        '<span class="t">'+esc(k)+'</span><span class="n">'+c[k]+'</span></button>'}).join('')+
    '</div></div></div></aside>'}

function iView(){
  var q=norm(I.q);
  var ls=anns().filter(function(p){return (!I.cat||p.cat===I.cat)&&(!q||norm(p.title+' '+(p.body||'')).indexOf(q)>=0)});
  if(!I.sel||!A.find('posts',I.sel))I.sel=ls.length?ls[0].id:null;
  var sel=A.find('posts',I.sel);
  return iSide()+'<section class="stage"><header class="topbar">'+A.navBtn()+
    '<h1>Thông báo &amp; Công việc nội bộ</h1><span class="spacer"></span>'+
    '<span class="qsearch"><input id="inQ" type="search" placeholder="Tìm thông báo" value="'+esc(I.q)+'" aria-label="Tìm thông báo"></span>'+
    '<button class="btn" type="button" data-iact="new">+ Thông báo</button></header><nav class="tabs"></nav>'+
    '<div class="content" id="content"><div class="split"><div class="lst">'+
    (ls.length?ls.map(function(p){
      return '<button class="lrow'+(p.id===I.sel?' on':'')+'" type="button" data-isel="'+p.id+'">'+
        '<span class="t">'+(p.pinned?'<span style="color:var(--amber)">★</span> ':'')+esc(p.title)+'</span>'+
        '<span class="m">'+esc(String(p.body||'').slice(0,110))+'</span>'+
        '<span style="display:flex;gap:6px;align-items:center;margin-top:6px">'+A.av(p.author,'s')+
        '<span class="by">'+esc(p.author)+'</span><span class="spacer"></span>'+
        (p.cat?'<span class="chip soft">'+esc(p.cat)+'</span>':'')+
        '<span class="by">'+esc(A.fmtDM(p.at))+'</span></span></button>'}).join('')
      :'<p class="empty">Chưa có thông báo nào.</p>')+
    '</div><div class="det">'+iDetail(sel)+'</div></div></div></section>'}

function iDetail(p){
  if(!p)return '<p class="empty">Chọn một thông báo để đọc.</p>';
  var reads=p.reads||[];
  return '<div class="pad" style="max-width:780px">'+
    '<div style="display:flex;gap:10px;align-items:center;margin-bottom:8px">'+
    (p.pinned?'<span class="chip warn">Ghim</span>':'')+(p.cat?'<span class="chip info">'+esc(p.cat)+'</span>':'')+
    '<span class="spacer"></span><button class="linkbtn" type="button" data-iedit="'+p.id+'">Sửa</button></div>'+
    '<h2 style="font-size:20px;margin-bottom:6px">'+esc(p.title)+'</h2>'+
    '<p class="by" style="margin-bottom:14px">'+esc(p.author)+' · '+esc(A.fmtDT(p.at))+'</p>'+
    '<p style="white-space:pre-wrap;line-height:1.65;font-size:13.5px">'+esc(p.body||'')+'</p>'+
    '<div class="sec" style="margin-top:20px"><div class="sec-h"><h3>Đã đọc</h3>'+
    '<span class="ds">'+reads.length+' người</span><span class="spacer"></span>'+
    '<button class="btn sm '+(reads.some(function(n){return A.isMe(n)})?'ghost':'')+'" type="button" data-iread="'+p.id+'">'+
      (reads.some(function(n){return A.isMe(n)})?'Đã xác nhận đọc':'Xác nhận đã đọc')+'</button></div>'+
    (reads.length?A.stack(reads,12):'<p class="by">Chưa ai xác nhận.</p>')+'</div>'+
    '<div class="sec"><div class="sec-h"><h3>Bình luận</h3><span class="ds">'+((p.cmts||[]).length)+'</span></div>'+
    '<div style="display:flex;gap:8px"><input id="inCmt" placeholder="Viết bình luận" style="flex:1;border:1px solid var(--line-2);border-radius:3px;padding:7px 9px;background:var(--panel)">'+
    '<button class="btn" type="button" data-icmt="'+p.id+'">Gửi</button></div>'+
    ((p.cmts||[]).slice().reverse().map(function(m){
      return '<div class="cmt">'+A.av(m.by,'')+'<div class="b"><div class="hd"><span class="nm">'+esc(m.by)+'</span>'+
        '<span class="by">'+esc(A.ago(m.at))+'</span></div><div>'+esc(m.tx)+'</div></div></div>'}).join('')||
      '<p class="by" style="margin-top:12px">Chưa có bình luận nào.</p>')+'</div></div>'}

function iEdit(id){
  if(!A.needRole('post.publish','Chỉ Quản lý trở lên mới đăng được thông báo công ty.'))return;
  var p=id?A.find('posts',id):null;
  A.form({title:p?'Sửa thông báo':'Viết thông báo',wide:true,
    fields:[{k:'title',label:'Tiêu đề',required:true,value:p?p.title:''},
      {k:'cat',label:'Nhóm',value:p?(p.cat||''):'Chung',half:true,ph:'VD: Nhân sự, Kỹ thuật'},
      {k:'pinned',label:'Ghim',type:'checkbox',value:p?!!p.pinned:false,cbLabel:'Ghim lên đầu danh sách',half:true},
      {k:'body',label:'Nội dung',type:'textarea',rows:10,value:p?(p.body||''):''}],
    onDelete:p?function(){A.remove('posts',p.id);I.sel=null}:null,confirmDelete:'Xoá thông báo này?',
    onSave:function(v){
      var o=p?Object.assign({},p):{id:A.uid(),kind:'announce',author:A.me()||'ẩn danh',
        at:new Date().toISOString(),reads:[],cmts:[]};
      o.title=v.title;o.cat=v.cat;o.pinned=!!v.pinned;o.body=v.body;
      I.sel=o.id;A.save('posts',o)}})}

function iAfter(){
  A.$('#appRoot').addEventListener('click',function(e){
    var el=e.target.closest('[data-isel],[data-icat],[data-iact],[data-iedit],[data-iread],[data-icmt]');
    if(!el)return;var g;
    if((g=el.getAttribute('data-isel'))){I.sel=g;A.render();return}
    if(el.hasAttribute('data-icat')){I.cat=el.getAttribute('data-icat')||'';A.render();return}
    if((g=el.getAttribute('data-iact'))){if(g==='new')iEdit(null);return}
    if((g=el.getAttribute('data-iedit'))){iEdit(g);return}
    if((g=el.getAttribute('data-iread'))){
      var p=A.find('posts',g);if(!p)return;
      if(!A.me()){A.toast('Đặt tên của bạn trước đã.',true);return}
      var o=Object.assign({},p),r=(o.reads||[]).slice();
      var i=r.map(norm).indexOf(norm(A.me()));
      if(i>=0)r.splice(i,1);else r.push(A.me());
      o.reads=r;A.save('posts',o);return}
    if((g=el.getAttribute('data-icmt'))){
      var p2=A.find('posts',g),inp=A.$('#inCmt');
      if(!p2||!inp||!inp.value.trim())return;
      var o2=Object.assign({},p2);
      o2.cmts=(o2.cmts||[]).concat([{by:A.me()||'ẩn danh',tx:inp.value.trim(),at:new Date().toISOString()}]).slice(-60);
      A.save('posts',o2);return}});
  var q=A.$('#inQ');if(q)q.addEventListener('input',function(){I.q=this.value;A.render()})}

A.register({id:'inside',name:'Thông báo nội bộ',desc:'Thông báo & công việc chung',cat:'info',color:'#C0559B',icon:'inside',
  side:true,info:false,view:iView,after:iAfter,
  onEnter:function(arg){if(arg&&A.find('posts',arg))I.sel=arg}});

/* =============== Wiki — tri thức doanh nghiệp =============== */
var K={sel:null,q:'',cat:''};
function wikis(){return A.col('posts').filter(function(p){return p.kind==='wiki'})
  .sort(function(a,b){return String(a.title).localeCompare(String(b.title),'vi')})}
function wcats(){var m={};wikis().forEach(function(p){if(p.cat)m[p.cat]=(m[p.cat]||0)+1});return m}

function kSide(){
  var c=wcats();
  return '<aside class="side">'+A.sideUser()+A.sideSearch('wkiSearch','Tìm bài viết',K.q)+
    '<div class="side-list"><div class="grp"><div class="grp-b">'+
    '<button class="pitem'+(K.cat?'':' on')+'" type="button" data-kcat="">'+A.icon('wiki','ic')+
      '<span class="t">Tất cả bài viết</span><span class="n">'+wikis().length+'</span></button></div></div>'+
    '<div class="grp"><div class="grp-h"><span>Chủ đề</span></div><div class="grp-b">'+
    Object.keys(c).map(function(k){
      return '<button class="pitem'+(K.cat===k?' on':'')+'" type="button" data-kcat="'+esc(k)+'">'+
        '<span class="t">'+esc(k)+'</span><span class="n">'+c[k]+'</span></button>'}).join('')+
    '</div></div></div><button class="side-add" type="button" data-kact="new">+ Viết bài mới</button></aside>'}

function kView(){
  var q=norm(K.q);
  var ls=wikis().filter(function(p){return (!K.cat||p.cat===K.cat)&&(!q||norm(p.title+' '+(p.body||'')).indexOf(q)>=0)});
  if(!K.sel||!A.find('posts',K.sel))K.sel=ls.length?ls[0].id:null;
  var s=A.find('posts',K.sel);
  return kSide()+'<section class="stage"><header class="topbar">'+A.navBtn()+
    '<h1>Tri thức doanh nghiệp</h1><span class="spacer"></span>'+
    '<button class="btn" type="button" data-kact="new">+ Bài viết</button></header><nav class="tabs"></nav>'+
    '<div class="content" id="content"><div class="split"><div class="lst">'+
    (ls.length?ls.map(function(p){
      return '<button class="lrow'+(p.id===K.sel?' on':'')+'" type="button" data-ksel="'+p.id+'">'+
        '<span class="t">'+esc(p.title)+'</span>'+
        '<span class="m">'+esc(String(p.body||'').slice(0,110))+'</span>'+
        '<span style="display:flex;gap:6px;margin-top:6px">'+(p.cat?'<span class="chip soft">'+esc(p.cat)+'</span>':'')+
        '<span class="spacer"></span><span class="by">'+esc(p.author)+'</span></span></button>'}).join('')
      :'<p class="empty">Chưa có bài viết nào.</p>')+
    '</div><div class="det">'+
    (s?'<div class="pad" style="max-width:760px"><div style="display:flex;margin-bottom:8px">'+
      (s.cat?'<span class="chip info">'+esc(s.cat)+'</span>':'')+'<span class="spacer"></span>'+
      '<button class="linkbtn" type="button" data-kedit="'+s.id+'">Sửa</button></div>'+
      '<h2 style="font-size:20px;margin-bottom:6px">'+esc(s.title)+'</h2>'+
      '<p class="by" style="margin-bottom:14px">'+esc(s.author)+' · cập nhật '+esc(A.ago(s.upd||s.at))+'</p>'+
      '<p style="white-space:pre-wrap;line-height:1.7;font-size:13.5px">'+esc(s.body||'')+'</p></div>'
      :'<p class="empty">Chọn một bài viết để đọc.</p>')+
    '</div></div></div></section>'}

function kEdit(id){
  var p=id?A.find('posts',id):null;
  A.form({title:p?'Sửa bài viết':'Bài viết mới',wide:true,
    fields:[{k:'title',label:'Tiêu đề',required:true,value:p?p.title:''},
      {k:'cat',label:'Chủ đề',value:p?(p.cat||''):'Hướng dẫn',ph:'VD: Kỹ thuật, Quy trình'},
      {k:'body',label:'Nội dung',type:'textarea',rows:14,value:p?(p.body||''):''}],
    onDelete:p?function(){A.remove('posts',p.id);K.sel=null}:null,confirmDelete:'Xoá bài viết này?',
    onSave:function(v){
      var o=p?Object.assign({},p):{id:A.uid(),kind:'wiki',author:A.me()||'ẩn danh',at:new Date().toISOString()};
      o.title=v.title;o.cat=v.cat;o.body=v.body;o.upd=new Date().toISOString();
      K.sel=o.id;A.save('posts',o)}})}

function kAfter(){
  A.$('#appRoot').addEventListener('click',function(e){
    var el=e.target.closest('[data-ksel],[data-kcat],[data-kact],[data-kedit]');
    if(!el)return;var g;
    if((g=el.getAttribute('data-ksel'))){K.sel=g;A.render();return}
    if(el.hasAttribute('data-kcat')){K.cat=el.getAttribute('data-kcat')||'';A.render();return}
    if((g=el.getAttribute('data-kact'))){if(g==='new')kEdit(null);return}
    if((g=el.getAttribute('data-kedit'))){kEdit(g);return}});
  var s=A.$('#wkiSearch');if(s)s.addEventListener('input',function(){K.q=this.value;A.render()})}

A.register({id:'wiki',name:'Tri thức',desc:'Wiki & tài liệu nội bộ',cat:'info',color:'#1BA3C6',icon:'wiki',
  side:true,info:false,view:kView,after:kAfter});

/* =============== Meeting — họp & đặt phòng =============== */
var M={view:'cal',date:A.ymd(new Date())};
function meets(){return A.col('meetings').slice().sort(function(a,b){return new Date(a.start)-new Date(b.start)})}
function rooms(){return A.col('rooms')}
function sameDay(a,b){return A.ymd(a)===A.ymd(b)}

function mSide(){
  var nav=[['cal','Lịch họp','meeting'],['rooms','Phòng & tài nguyên','flow'],['mine','Cuộc họp của tôi','hrm']];
  return '<aside class="side">'+A.sideUser()+
    '<div style="padding:0 10px 10px"><button class="btn" type="button" data-mact="new" style="width:100%">+ Đặt lịch họp</button></div>'+
    '<div class="side-list"><div class="grp"><div class="grp-b">'+
    nav.map(function(n){
      return '<button class="pitem'+(M.view===n[0]?' on':'')+'" type="button" data-mv="'+n[0]+'">'+
        A.icon(n[2],'ic')+'<span class="t">'+esc(n[1])+'</span></button>'}).join('')+
    '</div></div><div class="grp"><div class="grp-h"><span>Phòng họp</span></div><div class="grp-b">'+
    (rooms().length?rooms().map(function(r){
      return '<button class="pitem" type="button" data-mv="rooms"><span class="t">'+esc(r.name)+'</span>'+
        '<span class="n">'+(r.seats||'')+' chỗ</span></button>'}).join('')
      :'<p style="padding:10px 12px;color:var(--rail-muted);font-size:12px">Chưa khai phòng nào.</p>')+
    '</div></div></div><button class="side-add" type="button" data-mact="newroom">+ Thêm phòng</button></aside>'}

function mView(){
  var body=M.view==='rooms'?roomsHtml():M.view==='mine'?mineHtml():calHtml();
  return mSide()+'<section class="stage"><header class="topbar">'+A.navBtn()+
    '<h1>'+esc(M.view==='rooms'?'Phòng & tài nguyên':M.view==='mine'?'Cuộc họp của tôi':'Lịch họp')+'</h1>'+
    '<span class="spacer"></span>'+
    (M.view==='cal'?'<input type="date" id="mDate" value="'+esc(M.date)+'" style="border:1px solid var(--line);border-radius:3px;padding:5px 8px;background:var(--panel-2)" aria-label="Chọn ngày">':'')+
    '<button class="btn" type="button" data-mact="new">+ Đặt lịch họp</button></header><nav class="tabs"></nav>'+
    '<div class="content" id="content">'+body+'</div></section>'}

function meetRow(m){
  var mine=(m.attendees||[]).some(function(n){return A.isMe(n)})||A.isMe(m.host);
  return '<div class="row" data-mopen="'+m.id+'">'+
    '<span class="av sq" style="background:'+(mine?'var(--app)':'var(--muted)')+'">'+A.pad(new Date(m.start).getHours())+'</span>'+
    '<div class="rmain"><button class="rt" type="button" data-mopen="'+m.id+'">'+esc(m.title)+'</button>'+
    '<div class="chips"><span class="chip info">'+esc(m.room||'Chưa đặt phòng')+'</span>'+
    (mine?'<span class="chip ok">Có bạn</span>':'')+
    '<span class="by">Chủ trì: '+esc(m.host||'—')+'</span>'+
    (m.note?'<span class="by">'+esc(String(m.note).slice(0,60))+'</span>':'')+'</div></div>'+
    '<div class="rside"><span class="due">'+esc(A.fmtT(m.start))+' – '+esc(A.fmtT(m.end))+'</span>'+
    (m.link?'<a class="btn sm" href="'+esc(m.link)+'" target="_blank" rel="noopener noreferrer" style="color:#fff">Tham gia</a>'
      :'<button class="btn sm ghost" type="button" data-call="'+m.id+'">Tạo phòng họp</button>')+
    A.stack(m.attendees||[],4)+'</div></div>'}

function calHtml(){
  var d=new Date(M.date),ls=meets().filter(function(m){return sameDay(m.start,d)});
  var h='<div class="pad grid g4" style="padding-bottom:0">'+
    A.tile('Cuộc họp hôm nay',ls.length,'<span>'+esc(A.fmtD(M.date))+'</span>')+
    A.tile('Phòng họp',rooms().length,'<span>đang khai báo</span>')+
    A.tile('Có bạn tham dự',ls.filter(function(m){return (m.attendees||[]).some(function(n){return A.isMe(n)})}).length,'<span>trong ngày</span>')+
    A.tile('Tuần này',meets().filter(function(m){
      var t=new Date(m.start),n=new Date();return Math.abs(A.daysBetween(n,t))<=7}).length,'<span>tổng số cuộc họp</span>')+
    '</div>';
  h+=ls.length?ls.map(meetRow).join(''):'<div class="empty"><h3>Không có cuộc họp nào</h3><p>Ngày '+esc(A.fmtD(M.date))+' đang trống.</p></div>';
  return h}

function mineHtml(){
  var ls=meets().filter(function(m){return A.isMe(m.host)||(m.attendees||[]).some(function(n){return A.isMe(n)})});
  if(!A.me())return '<div class="empty"><h3>Chưa biết bạn là ai</h3><p>Bấm ô tên ở góc trên bên trái.</p></div>';
  return ls.length?ls.map(meetRow).join(''):'<div class="empty"><h3>Bạn chưa có cuộc họp nào</h3></div>'}

function roomsHtml(){
  var d=new Date(M.date);
  return '<div class="pad"><div class="grid g3">'+(rooms().length?rooms().map(function(r){
    var use=meets().filter(function(m){return m.room===r.name&&sameDay(m.start,d)});
    return '<div class="card-box"><div style="display:flex;gap:10px;align-items:center;margin-bottom:8px">'+
      A.av(r.name,'l')+'<div><div style="font-weight:500">'+esc(r.name)+'</div>'+
      '<div class="by">'+esc(r.place||'')+' · '+(r.seats||'?')+' chỗ</div></div></div>'+
      '<p class="sect-t">Lịch ngày '+esc(A.fmtD(M.date))+'</p>'+
      (use.length?use.map(function(m){
        return '<div class="mini"><span class="num">'+esc(A.fmtT(m.start))+'–'+esc(A.fmtT(m.end))+'</span>'+
          '<button class="t" type="button" data-mopen="'+m.id+'">'+esc(m.title)+'</button></div>'}).join('')
        :'<p class="by">Trống cả ngày.</p>')+
      '<div style="margin-top:10px"><button class="btn sm ghost" type="button" data-roomedit="'+r.id+'">Sửa phòng</button></div>'+
      '</div>'}).join('')
    :'<p class="empty">Chưa khai phòng họp nào.</p>')+'</div></div>'}

function mEdit(id){
  var m=id?A.find('meetings',id):null;
  var rs=rooms().map(function(r){return {v:r.name,l:r.name}});
  A.form({title:m?'Cuộc họp':'Đặt lịch họp',wide:true,
    fields:[{k:'title',label:'Nội dung họp',required:true,value:m?m.title:''},
      {k:'room',label:'Phòng họp',type:'select',value:m?(m.room||''):'',half:true,
       options:[{v:'',l:'— Không đặt phòng —'}].concat(rs)},
      {k:'host',label:'Chủ trì',type:'people',value:m?(m.host||''):(A.me()||''),half:true},
      {k:'start',label:'Bắt đầu',type:'datetime-local',required:true,value:m?A.toLocal(m.start):A.toLocal(new Date()),half:true},
      {k:'end',label:'Kết thúc',type:'datetime-local',value:m?A.toLocal(m.end):'',half:true},
      {k:'attendees',label:'Người tham dự (cách nhau bằng dấu phẩy)',value:m?((m.attendees||[]).join(', ')):''},
      {k:'link',label:'Link họp trực tuyến',value:m?(m.link||''):'',
       ph:'Dán link Google Meet / Zoom / Teams, hoặc để trống rồi bấm “Tạo phòng họp”',
       hint:'Bấm “Tạo phòng họp” trên dòng lịch họp sẽ tự tạo phòng trên meet.jit.si — dịch vụ công cộng, không cần cài đặt.'},
      {k:'note',label:'Nội dung / biên bản',type:'textarea',rows:5,value:m?(m.note||''):''}],
    onDelete:m?function(){A.remove('meetings',m.id)}:null,confirmDelete:'Xoá cuộc họp này?',
    onSave:function(v){
      var o=m?Object.assign({},m):{id:A.uid(),at:new Date().toISOString(),by:A.me()||'ẩn danh'};
      o.title=v.title;o.room=v.room;o.host=v.host;
      o.start=v.start?new Date(v.start).toISOString():'';
      o.end=v.end?new Date(v.end).toISOString():o.start;
      o.attendees=String(v.attendees||'').split(',').map(function(s){return s.trim()}).filter(Boolean);
      o.link=v.link;o.note=v.note;
      var clash=meets().filter(function(x){
        return x.id!==o.id&&x.room&&x.room===o.room&&
          new Date(x.start)<new Date(o.end)&&new Date(x.end)>new Date(o.start)});
      if(clash.length)A.toast('Lưu ý: phòng '+o.room+' đã có cuộc họp trùng giờ.',true);
      A.save('meetings',o)}})}

function roomEdit(id){
  var r=id?A.find('rooms',id):null;
  A.form({title:r?'Sửa phòng':'Thêm phòng họp',
    fields:[{k:'name',label:'Tên phòng',required:true,value:r?r.name:'',ph:'VD: Phòng họp tầng 3'},
      {k:'place',label:'Vị trí',value:r?(r.place||''):'',half:true},
      {k:'seats',label:'Số chỗ',type:'number',value:r?(r.seats||''):'',half:true}],
    onDelete:r?function(){A.remove('rooms',r.id)}:null,confirmDelete:'Xoá phòng này?',
    onSave:function(v){
      var o=r?Object.assign({},r):{id:A.uid()};
      o.name=v.name;o.place=v.place;o.seats=v.seats;A.save('rooms',o)}})}

function mAfter(){
  A.$('#appRoot').addEventListener('click',function(e){
    var el=e.target.closest('[data-mv],[data-mact],[data-mopen],[data-roomedit],[data-call]');
    if(!el)return;var g;
    if((g=el.getAttribute('data-call'))){
      var m=A.find('meetings',g);if(!m)return;
      var url='https://meet.jit.si/KHH-'+g.replace(/[^a-zA-Z0-9]/g,'');
      var o=Object.assign({},m);o.link=url;
      A.save('meetings',o);
      window.open(url,'_blank','noopener');
      A.toast('Đã tạo phòng họp trực tuyến và gắn link vào cuộc họp.');
      return}
    if((g=el.getAttribute('data-mv'))){M.view=g;A.render();return}
    if((g=el.getAttribute('data-mopen'))){mEdit(g);return}
    if((g=el.getAttribute('data-roomedit'))){roomEdit(g);return}
    if((g=el.getAttribute('data-mact'))){if(g==='new')mEdit(null);else if(g==='newroom')roomEdit(null);return}});
  var d=A.$('#mDate');if(d)d.addEventListener('change',function(){M.date=this.value;A.render()})}

A.register({id:'meeting',name:'Họp & Đặt phòng',desc:'Lịch họp và tài nguyên',cat:'info',color:'#E0464E',icon:'meeting',
  side:true,info:false,view:mView,after:mAfter});
})();
