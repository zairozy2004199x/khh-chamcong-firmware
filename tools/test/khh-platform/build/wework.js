/* Wework — Quản lý công việc & dự án */
(function(){
'use strict';
var A=window.APP,esc=A.esc,norm=A.norm;
var V={view:'project',pid:A.ls('kh.wk.pid')||null,tab:'work',mode:A.ls('kh.wk.mode')||'list',
       q:'',pq:'',sort:'manual',filter:'all',closedG:{},closedL:{},mineTab:'all'};
var ST={todo:{l:'Phải làm',c:'todo'},doing:{l:'Đang làm',c:'doing'},done:{l:'Hoàn thành',c:'done'}};
var PR={high:'Gấp',normal:'Bình thường',low:'Thong thả'};
var PS={ontrack:{l:'Đúng tiến độ',c:'ok'},risk:{l:'Có rủi ro cao',c:'hi'},late:{l:'Chậm tiến độ',c:'late'}};
var SORTS={manual:'Thủ công',due:'Hạn chót',name:'Tên việc',who:'Người phụ trách'};
var FILTERS={all:'Tất cả',todo:'Phải làm',doing:'Đang làm',done:'Hoàn thành',late:'Quá hạn',mine:'Việc của tôi'};

/* ---------- dữ liệu ---------- */
function projs(){return A.col('projects').slice().sort(function(a,b){return (a.order||0)-(b.order||0)})}
function proj(id){return A.find('projects',id)}
function cur(){return proj(V.pid)}
function task(id){return A.find('tasks',id)}
function pTasks(pid){return A.col('tasks').filter(function(t){return t.pid===pid})}
function lists(p){var l=(p&&p.lists)||[];return l.length?l:[{id:'default',name:'Chưa phân loại'}]}
function isLate(t){return t.status!=='done'&&!!t.due&&new Date(t.due).getTime()<Date.now()}
function dueTone(t){if(t.status==='done'||!t.due)return '';
  var dt=new Date(t.due).getTime(),n=Date.now();
  if(dt<n)return 'crit';if(dt-n<1728e5)return 'warn';return ''}
function stats(pid){
  var t=pTasks(pid),done=0,doing=0,late=0,lateDone=0;
  t.forEach(function(x){
    if(x.status==='done'){done++;if(x.due&&x.doneAt&&new Date(x.doneAt)>new Date(x.due))lateDone++}
    else if(x.status==='doing')doing++;
    if(isLate(x))late++});
  return {n:t.length,done:done,doing:doing,todo:t.length-done-doing,late:late,lateDone:lateDone,pct:A.pct(done,t.length)}}
function members(p){
  var m=(p.members||[]).slice();
  pTasks(p.id).forEach(function(t){if(t.assignee&&m.indexOf(t.assignee)<0)m.push(t.assignee)});
  return m}
function nextOrder(pid,lid){var m=0;
  A.col('tasks').forEach(function(t){if(t.pid===pid&&(t.list||'default')===lid)m=Math.max(m,t.order||0)});
  return m+10}
function log(o,tx){o.log=(o.log||[]).slice(-40);o.log.push({by:A.me()||'ẩn danh',tx:tx,at:new Date().toISOString()})}

/* ---------- thanh bên ---------- */
function side(){
  var q=norm(V.pq),groups={},order=[];
  projs().forEach(function(p){
    if(q&&norm(p.name+' '+(p.group||'')).indexOf(q)<0)return;
    var g=p.group||'Dự án';
    if(!groups[g]){groups[g]=[];order.push(g)}
    groups[g].push(p)});
  var nav=[['mine','Công việc','work'],['projects','Dự án & phòng ban','grid'],
           ['members','Thành viên','hrm'],['report','Báo cáo','flow']];
  var h='<aside class="side">'+A.sideUser()+A.sideSearch('wkSearch','Tìm dự án & phòng ban',V.pq)+'<div class="side-list">';
  h+='<div class="grp"><div class="grp-b">'+nav.map(function(n){
    return '<button class="pitem'+(V.view===n[0]?' on':'')+'" type="button" data-v="'+n[0]+'">'+
      A.icon(n[2],'ic')+'<span class="t">'+esc(n[1])+'</span></button>'}).join('')+'</div></div>';
  var star=A.S.star,pin=projs().filter(function(p){return star[p.id]});
  if(pin.length)h+=grp('Ghim','__pin',pin);
  order.forEach(function(g){h+=grp(g,g,groups[g])});
  if(!order.length&&!pin.length)
    h+='<p style="padding:12px;color:var(--rail-muted);font-size:12px">'+(A.S.ready?'Chưa có dự án nào.':'Đang tải…')+'</p>';
  return h+'</div><button class="side-add" type="button" data-act="newproj">+ Dự án mới</button></aside>'}
function grp(label,key,arr){
  var h='<div class="grp'+(V.closedG[key]?' closed':'')+'"><button class="grp-h" type="button" data-grp="'+esc(key)+'">'+
    '<span>'+esc(label)+'</span><span class="cv">▾</span></button><div class="grp-b">';
  arr.forEach(function(p){
    var st=stats(p.id);
    h+='<button class="pitem'+(p.id===V.pid&&V.view==='project'?' on':'')+'" type="button" data-proj="'+p.id+'">'+
      A.av(p.name,'s',p.color)+'<span class="t">'+esc(p.name)+'</span>'+
      (p.demo?'<span class="tag-demo">ví dụ</span>':'')+
      (st.late?'<span class="n" style="color:var(--red)">'+st.late+'</span>':'<span class="n">'+st.done+'/'+st.n+'</span>')+
      '</button>'});
  return h+'</div></div>'}

/* ---------- khung chính ---------- */
function view(){
  if(V.view==='project'&&!cur()){var f=projs()[0];V.pid=f?f.id:null}
  if(V.pid)A.ls('kh.wk.pid',V.pid);
  var body,head;
  if(V.view==='mine'){head=headSimple('Công việc của tôi');body=mineHtml()}
  else if(V.view==='projects'){head=headSimple('Dự án & phòng ban');body=projectsHtml()}
  else if(V.view==='members'){head=headSimple('Thành viên');body=membersAllHtml()}
  else if(V.view==='report'){head=headSimple('Báo cáo hệ thống');body=sysReportHtml()}
  else {head=headProject();body=projectBody()}
  return side()+'<section class="stage">'+head+'<div class="content" id="content">'+body+'</div></section>'+infoPanel()}

function headSimple(title){
  return '<header class="topbar">'+A.navBtn()+'<h1>'+esc(title)+'</h1><span class="spacer"></span>'+
    '<span class="qsearch"><input id="wkQ" type="search" placeholder="Tìm nhanh công việc" value="'+esc(V.q)+'" aria-label="Tìm công việc"></span>'+
    '</header><div class="tabs"></div>'}

function headProject(){
  var p=cur();
  if(!p)return '<header class="topbar">'+A.navBtn()+'<h1>Chưa có dự án</h1></header><div class="tabs"></div>';
  var tabs=[['work','Công việc'],['discuss','Thảo luận'],['docs','Tài liệu'],['acts','Hoạt động'],['report','Báo cáo'],['members','Thành viên']];
  var h='<header class="topbar">'+A.navBtn()+
    '<span class="av sq" style="background:'+(p.color||A.hue(p.name))+'">'+esc(A.initials(p.name))+'</span>'+
    '<h1>'+esc(p.name)+'</h1>'+
    '<button class="star'+(A.S.star[p.id]?'':' off')+'" type="button" data-act="star" title="Ghim dự án">★</button>'+
    '<span class="spacer"></span>'+
    '<span class="qsearch"><input id="wkQ" type="search" placeholder="Tìm nhanh công việc" value="'+esc(V.q)+'" aria-label="Tìm công việc"></span>'+
    '<button class="icon-btn" type="button" data-act="editproj" title="Sửa dự án" aria-label="Sửa dự án">'+
      '<svg width="16" height="16" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="4" cy="10" r="1.4"/><circle cx="10" cy="10" r="1.4"/><circle cx="16" cy="10" r="1.4"/></svg></button>'+
    '</header><nav class="tabs">'+tabs.map(function(t){
      return '<button class="tab'+(V.tab===t[0]?' on':'')+'" type="button" data-tab="'+t[0]+'">'+esc(t[1])+'</button>'}).join('')+'</nav>';
  h+=miniSum(p);
  if(V.tab==='work')h+=actionBar();
  return h}

function miniSum(p){
  var st=stats(p.id),ps=PS[p.status||'ontrack'];
  return '<div class="mini-sum"><span style="font-weight:500">'+st.done+'/'+st.n+' hoàn thành</span>'+
    '<span class="bar" style="flex:1;min-width:110px;margin:0"><i class="d" style="width:'+(st.n?st.done/st.n*100:0)+'%"></i>'+
    '<i class="g" style="width:'+(st.n?st.doing/st.n*100:0)+'%"></i></span>'+
    '<span class="num" style="color:var(--muted)">'+st.pct+'%</span>'+
    (st.late?'<span class="chip late">'+st.late+' quá hạn</span>':'')+
    '<span class="chip '+ps.c+'">'+ps.l+'</span></div>'}

function actionBar(){
  return '<div class="actionbar">'+
    '<button class="linkbtn" type="button" data-act="newtask">+ Tạo công việc</button>'+
    '<span style="color:var(--muted)">hoặc</span>'+
    '<button class="linkbtn" type="button" data-act="newlist">Tạo nhóm công việc mới</button>'+
    '<span class="spacer"></span>'+
    '<button class="drop" type="button" data-menu="sort">Sắp xếp: <b>'+esc(SORTS[V.sort])+'</b> <span class="cv">▾</span></button>'+
    '<button class="drop" type="button" data-menu="filter">Lọc: <b>'+esc(FILTERS[V.filter])+'</b> <span class="cv">▾</span></button>'+
    '<span class="seg"><button type="button" data-mode="list"'+(V.mode==='list'?' class="on"':'')+'>Danh sách</button>'+
    '<button type="button" data-mode="board"'+(V.mode==='board'?' class="on"':'')+'>Dạng bảng</button></span></div>'}

/* ---------- lọc & sắp xếp ---------- */
function sorter(a,b){
  if(V.sort==='due'){var x=a.due?new Date(a.due).getTime():8.64e15,y=b.due?new Date(b.due).getTime():8.64e15;if(x!==y)return x-y}
  else if(V.sort==='name')return String(a.title).localeCompare(String(b.title),'vi');
  else if(V.sort==='who')return String(a.assignee||'zzz').localeCompare(String(b.assignee||'zzz'),'vi');
  return (a.order||0)-(b.order||0)}
function visible(){
  var q=norm(V.q);
  return pTasks(V.pid).filter(function(t){
    if(V.filter==='late')return isLate(t);
    if(V.filter==='mine')return A.isMe(t.assignee);
    if(V.filter!=='all'&&t.status!==V.filter)return false;
    return true}).filter(function(t){
    return !q||norm(t.title+' '+(t.note||'')+' '+(t.assignee||'')).indexOf(q)>=0}).sort(sorter)}

/* ---------- dòng công việc ---------- */
function rowHtml(t,showProj){
  var late=isLate(t),st=ST[t.status]||ST.todo,p=showProj?proj(t.pid):null;
  var subs=(t.subs||[]),sd=subs.filter(function(s){return s.done}).length;
  return '<div class="row'+(late?' late':'')+(t.status==='done'?' done':'')+'" data-task="'+t.id+'">'+
    '<button class="ck'+(t.status==='done'?' on':'')+'" type="button" data-check="'+t.id+'" aria-label="Đánh dấu hoàn thành">'+
      '<svg viewBox="0 0 10 10" fill="none" stroke="#fff" stroke-width="2"><path d="M1.6 5.2 4 7.5 8.4 2.6"/></svg></button>'+
    '<div class="rmain"><button class="rt" type="button" data-open="'+t.id+'">'+esc(t.title)+'</button>'+
    '<div class="chips"><span class="chip '+st.c+'">'+st.l+'</span>'+
      (late?'<span class="chip late">Quá hạn</span>':'')+
      (t.prio==='high'?'<span class="chip hi">Gấp</span>':'')+
      (subs.length?'<span class="chip soft">Việc con '+sd+'/'+subs.length+'</span>':'')+
      ((t.cmts||[]).length?'<span class="chip soft">'+t.cmts.length+' bình luận</span>':'')+
      (p?'<span class="chip soft">'+esc(p.name)+'</span>':'')+
      (t.by?'<span class="by">Tạo bởi @'+esc(t.by)+'</span>':'')+
    '</div></div>'+
    '<div class="rside">'+
      (t.due?'<span class="due '+dueTone(t)+'">'+A.fmtDT(t.due)+'</span>':'<span class="due">Không thời hạn</span>')+
      '<span class="who">'+(t.assignee?A.av(t.assignee,'s')+'<span>'+esc(t.assignee)+'</span>':'<span style="color:var(--muted)">Chưa giao</span>')+'</span>'+
    '</div></div>'}

/* ---------- thân dự án ---------- */
function projectBody(){
  var p=cur();
  if(!p)return '<div class="empty"><h3>'+(A.S.ready?'Chưa có dự án nào':'Đang tải dữ liệu…')+'</h3>'+
    '<p>Tạo dự án đầu tiên rồi chia nhỏ thành các nhóm công việc.</p>'+
    (A.S.ready?'<p style="margin-top:14px"><button class="btn" type="button" data-act="newproj">+ Dự án mới</button></p>':'')+'</div>';
  if(V.tab==='report')return reportHtml(p);
  if(V.tab==='members')return membersHtml(p);
  if(V.tab==='discuss')return discussHtml(p);
  if(V.tab==='docs')return docsHtml(p);
  if(V.tab==='acts')return actsHtml(p);
  return V.mode==='board'?boardHtml(p):listHtml(p)}

function listHtml(p){
  var vis=visible(),h='';
  lists(p).forEach(function(L){
    var items=vis.filter(function(t){return (t.list||'default')===L.id});
    var all=pTasks(p.id).filter(function(t){return (t.list||'default')===L.id});
    var done=all.filter(function(t){return t.status==='done'}).length;
    h+='<section class="tl'+(V.closedL[p.id+':'+L.id]?' closed':'')+'"><div class="tl-h">'+
      '<button class="cv" type="button" data-ltog="'+esc(L.id)+'" aria-label="Thu gọn">▾</button>'+
      '<button class="tl-name" type="button" data-ledit="'+esc(L.id)+'">'+esc(L.name)+'</button>'+
      '<span class="tl-count">'+done+'/'+all.length+'</span>'+
      '<button class="linkbtn" type="button" data-addin="'+esc(L.id)+'">+ Thêm công việc</button></div><div class="tl-b">'+
      (items.length?items.map(function(t){return rowHtml(t)}).join('')
        :'<p style="padding:11px 14px;color:var(--muted);border-top:1px solid var(--line)">Chưa có công việc nào ở đây.</p>')+
      '</div></section>'});
  return h+'<div style="padding:12px 14px"><button class="linkbtn" type="button" data-act="newlist">+ Tạo nhóm công việc mới</button></div>'}

function cardHtml(t){
  var late=isLate(t),subs=(t.subs||[]),sd=subs.filter(function(s){return s.done}).length;
  return '<div class="card'+(late?' late':'')+(t.status==='done'?' done':'')+'" draggable="true" data-task="'+t.id+'">'+
    '<div class="ct">'+esc(t.title)+'</div>'+
    '<div class="card-f">'+
      (t.status==='done'?'<span style="color:var(--green)">✔</span>':'')+
      (t.prio==='high'?'<span class="chip hi">Gấp</span>':'')+
      (subs.length?'<span class="by">'+sd+'/'+subs.length+'</span>':'')+
      (t.due?'<span class="due '+dueTone(t)+'">'+A.fmtD(t.due)+'</span>':'')+
      '<span class="spacer"></span>'+(t.assignee?A.av(t.assignee,'s'):'')+
    '</div></div>'}

function boardHtml(p){
  var vis=visible(),h='<div class="board" id="board">';
  lists(p).forEach(function(L){
    var items=vis.filter(function(t){return (t.list||'default')===L.id});
    var open=items.filter(function(t){return t.status!=='done'}),fin=items.filter(function(t){return t.status==='done'});
    var all=pTasks(p.id).filter(function(t){return (t.list||'default')===L.id});
    h+='<div class="col" data-l="'+esc(L.id)+'"><div class="col-h"><span class="gr">≡</span>'+
      '<button class="nm" type="button" data-ledit="'+esc(L.id)+'">'+esc(L.name)+'</button>'+
      '<span class="ct">'+all.filter(function(t){return t.status==='done'}).length+'/'+all.length+'</span></div>'+
      '<button class="col-add" type="button" data-addin="'+esc(L.id)+'">+ Thêm công việc</button>'+
      '<div class="col-b" data-l="'+esc(L.id)+'">'+open.map(cardHtml).join('')+
      (fin.length?'<div class="donehead">Công việc đã hoàn thành</div>'+fin.map(cardHtml).join(''):'')+'</div></div>'});
  return h+'<button class="col" style="width:196px;padding:12px;color:var(--muted);align-items:center;justify-content:center;min-height:44px" type="button" data-act="newlist">+ Thêm nhóm công việc</button></div>'}

/* ---------- báo cáo dự án ---------- */
function reportHtml(p){
  var all=pTasks(p.id),st=stats(p.id),mem=members(p);
  var lateDone=all.filter(function(t){return t.status==='done'&&t.due&&t.doneAt&&new Date(t.doneAt)>new Date(t.due)}).length;
  var withDue=all.filter(function(t){return !!t.due}).length;
  var h='<div class="pad grid g4" style="padding-bottom:0">'+
    A.tile('Công việc',st.n,'<span><span class="dot" style="background:var(--blue)"></span>'+st.doing+' đang thực hiện</span>'+
      '<span><span class="dot" style="background:var(--green)"></span>'+st.done+' hoàn thành</span>')+
    A.tile('Nhóm công việc',lists(p).length,'<span>'+esc((p.group||'Dự án'))+'</span>')+
    A.tile('Thành viên',mem.length,'<span>'+mem.slice(0,3).map(esc).join(', ')+(mem.length>3?'…':'')+'</span>')+
    A.tile('Quá hạn',st.late,'<span>'+lateDone+' hoàn thành muộn</span>',st.late?'var(--red)':'')+
    A.tile('Thời lượng',(p.start&&p.end?A.daysBetween(p.start,p.end):0)+' ngày',
      '<span>'+(A.fmtD(p.start)||'…')+' – '+(A.fmtD(p.end)||'…')+'</span>')+
    '</div>';
  h+='<div class="pad grid g2">';
  h+='<div class="card-box"><h3 style="margin-bottom:12px">Báo cáo trạng thái công việc</h3>'+
    A.donut([{v:st.done-lateDone,c:'var(--green)',l:'Hoàn thành'},{v:lateDone,c:'var(--amber)',l:'Hoàn thành muộn'},
             {v:st.doing,c:'var(--blue)',l:'Đang thực hiện'},{v:st.late,c:'var(--red)',l:'Quá hạn'},
             {v:Math.max(0,st.todo-st.late),c:'var(--panel-3)',l:'Chưa bắt đầu'}],st.n,'công việc')+'</div>';
  var per={};
  all.forEach(function(t){var k=t.assignee||'Chưa giao';
    per[k]=per[k]||{n:0,d:0,g:0,l:0};per[k].n++;
    if(t.status==='done')per[k].d++;if(t.status==='doing')per[k].g++;if(isLate(t))per[k].l++});
  var ranked=Object.keys(per).sort(function(a,b){return A.pct(per[b].d,per[b].n)-A.pct(per[a].d,per[a].n)});
  h+='<div class="card-box"><h3 style="margin-bottom:12px">Thành viên xuất sắc</h3>'+
    (ranked.length?ranked.slice(0,6).map(function(k){var w=per[k];
      return '<div class="mini">'+A.av(k,'')+'<span class="t">'+esc(k)+
        '<span class="by" style="display:block">'+w.d+'/'+w.n+' công việc đã hoàn thành</span></span>'+
        '<span class="chip ok">'+A.pct(w.d,w.n)+'%</span></div>'}).join('')
      :'<p style="color:var(--muted)">Chưa có dữ liệu.</p>')+'</div>';
  h+='<div class="card-box"><h3 style="margin-bottom:12px">Công việc không đúng hạn</h3>'+
    '<div style="display:grid;gap:14px">'+
    A.gauge(A.pct(st.late,withDue||1),'var(--red)',st.late,'công việc quá hạn trên '+withDue+' công việc có thời hạn')+
    A.gauge(A.pct(lateDone,st.done||1),'var(--amber)',lateDone,'công việc hoàn thành muộn trên '+st.done+' đã hoàn thành')+
    '</div></div>';
  var now=Date.now(),q={a:0,b:0,c:0,d:0};
  all.filter(function(t){return t.status!=='done'}).forEach(function(t){
    var imp=t.prio==='high';
    var urg=!!t.due&&(new Date(t.due).getTime()-now)<2592e5;
    if(imp&&urg)q.a++;else if(imp)q.b++;else if(urg)q.c++;else q.d++});
  h+='<div class="card-box"><h3 style="margin-bottom:4px">Ma trận Eisenhower</h3>'+
    '<p class="by" style="margin-bottom:12px">Công việc chưa xong, chia theo mức gấp và hạn trong 3 ngày</p>'+
    '<div style="display:grid;grid-template-columns:1fr 1fr;gap:1px;background:var(--line);border:1px solid var(--line)">'+
    quad('Quan trọng · Khẩn cấp',q.a,'var(--red-weak)','var(--red)')+
    quad('Quan trọng · Chưa gấp',q.b,'var(--amber-weak)','var(--amber)')+
    quad('Không quan trọng · Khẩn cấp',q.c,'var(--blue-weak)','var(--blue)')+
    quad('Không quan trọng · Chưa gấp',q.d,'var(--panel-2)','var(--ink-2)')+
    '</div></div>';
  h+='<div class="card-box"><h3 style="margin-bottom:12px">Tiến độ theo nhóm công việc</h3><div class="tbl-wrap"><table class="t">'+
    '<thead><tr><th>Nhóm</th><th class="n">Tổng</th><th class="n">Xong</th><th class="n">Quá hạn</th><th style="width:100px">Tiến độ</th></tr></thead><tbody>';
  lists(p).forEach(function(L){
    var a=all.filter(function(t){return (t.list||'default')===L.id});
    var d=a.filter(function(t){return t.status==='done'}).length;
    var g=a.filter(function(t){return t.status==='doing'}).length;
    var lt=a.filter(isLate).length;
    h+='<tr><td>'+esc(L.name)+'</td><td class="n">'+a.length+'</td><td class="n">'+d+'</td>'+
      '<td class="n" style="'+(lt?'color:var(--red)':'')+'">'+lt+'</td><td>'+
      A.wbar([{v:d,c:'var(--green)'},{v:g,c:'var(--blue)'},{v:Math.max(0,a.length-d-g),c:'var(--panel-3)'}],a.length||1)+'</td></tr>'});
  h+='</tbody></table></div></div></div>';
  return h}
function quad(l,v,bg,col){
  return '<div style="background:'+bg+';padding:14px;min-height:74px">'+
    '<div style="font-size:22px;font-weight:500;color:'+col+'" class="num">'+v+'</div>'+
    '<div style="font-size:10.5px;color:var(--muted)">'+esc(l)+'</div></div>'}

/* ---------- thành viên / thảo luận / tài liệu / hoạt động ---------- */
function membersHtml(p){
  var mem=members(p),all=pTasks(p.id);
  var h='<div class="pad"><div class="actionbar" style="border:0;padding:0 0 12px">'+
    '<button class="linkbtn" type="button" data-act="editproj">+ Thêm thành viên vào dự án</button></div><div class="grid g3">';
  if(!mem.length)h+='<p class="empty">Chưa có thành viên nào.</p>';
  mem.forEach(function(k){
    var a=all.filter(function(t){return norm(t.assignee)===norm(k)});
    var d=a.filter(function(t){return t.status==='done'}).length,g=a.filter(function(t){return t.status==='doing'}).length;
    var lt=a.filter(isLate).length,s=A.staffByName(k);
    h+='<div class="card-box"><div style="display:flex;align-items:center;gap:10px">'+A.av(k,'l')+
      '<div><div style="font-weight:500">'+esc(k)+'</div><div class="by">'+esc(s?s.title:(A.isMe(k)?'Bạn':'Thành viên dự án'))+'</div></div></div>'+
      '<div class="kpis" style="margin-top:11px"><div class="kpi t"><b>'+(a.length-d-g)+'</b><span>Phải làm</span></div>'+
      '<div class="kpi g"><b>'+g+'</b><span>Đang làm</span></div><div class="kpi d"><b>'+d+'</b><span>Hoàn thành</span></div></div>'+
      (lt?'<p style="margin-top:9px;color:var(--red);font-size:12px">'+lt+' việc đang quá hạn</p>':'')+'</div>'});
  return h+'</div></div>'}

function membersAllHtml(){
  var names=A.people(),tasks=A.col('tasks');
  var h='<div class="pad"><div class="tbl-wrap"><table class="t"><thead><tr>'+
    '<th>Thành viên</th><th>Chức danh</th><th class="n">Được giao</th><th class="n">Đang làm</th>'+
    '<th class="n">Hoàn thành</th><th class="n">Quá hạn</th><th style="width:110px">Tiến độ</th></tr></thead><tbody>';
  names.forEach(function(k){
    var a=tasks.filter(function(t){return norm(t.assignee)===norm(k)});
    var d=a.filter(function(t){return t.status==='done'}).length,g=a.filter(function(t){return t.status==='doing'}).length;
    var lt=a.filter(isLate).length,s=A.staffByName(k);
    h+='<tr><td><span style="display:flex;align-items:center;gap:8px">'+A.av(k,'')+esc(k)+'</span></td>'+
      '<td class="sub">'+esc(s?s.title:'—')+'</td><td class="n">'+a.length+'</td><td class="n">'+g+'</td>'+
      '<td class="n">'+d+'</td><td class="n" style="'+(lt?'color:var(--red)':'')+'">'+lt+'</td>'+
      '<td>'+A.wbar([{v:d,c:'var(--green)'},{v:g,c:'var(--blue)'},{v:Math.max(0,a.length-d-g),c:'var(--panel-3)'}],a.length||1)+'</td></tr>'});
  if(!names.length)h+='<tr><td colspan="7" style="color:var(--muted)">Chưa có thành viên nào.</td></tr>';
  return h+'</tbody></table></div></div>'}

function discussHtml(p){
  var c=(p.cmts||[]).slice().reverse();
  return '<div class="pad" style="max-width:760px">'+
    '<div class="card-box"><div class="sec-h"><h3>Thảo luận dự án</h3><span class="ds">'+(p.cmts||[]).length+' bình luận</span></div>'+
    '<div style="display:flex;gap:8px"><input id="pCmt" placeholder="Viết bình luận rồi bấm Gửi" style="flex:1;border:1px solid var(--line-2);border-radius:3px;padding:7px 9px;background:var(--panel)">'+
    '<button class="btn" type="button" data-act="pcmt">Gửi</button></div>'+
    (c.length?c.map(function(m){
      return '<div class="cmt">'+A.av(m.by,'')+'<div class="b"><div class="hd"><span class="nm">'+esc(m.by)+'</span>'+
        '<span class="by">'+esc(A.ago(m.at))+'</span></div><div>'+esc(m.tx)+'</div></div></div>'}).join('')
      :'<p style="margin-top:14px;color:var(--muted)">Chưa có bình luận nào.</p>')+'</div></div>'}

function docsHtml(p){
  var d=p.docs||[];
  return '<div class="pad" style="max-width:820px"><div class="card-box">'+
    '<div class="sec-h"><h3>Tài liệu dự án</h3><span class="ds">'+d.length+' tài liệu</span><span class="spacer"></span>'+
    '<button class="btn sm" type="button" data-act="newdoc">+ Thêm tài liệu</button></div>'+
    (d.length?'<div class="tbl-wrap"><table class="t"><thead><tr><th>Tên tài liệu</th><th>Ghi chú</th><th>Người thêm</th><th></th></tr></thead><tbody>'+
      d.map(function(x,i){
        return '<tr><td><b style="font-weight:500">'+esc(x.name)+'</b></td><td class="sub">'+esc(x.note||'')+'</td>'+
          '<td class="sub">'+esc(x.by)+' · '+esc(A.fmtD(x.at))+'</td>'+
          '<td class="n"><button class="linkbtn" type="button" data-deldoc="'+i+'">Xoá</button></td></tr>'}).join('')+
      '</tbody></table></div>'
      :'<p style="color:var(--muted)">Chưa có tài liệu nào. Ghi lại biên bản, bản vẽ, hợp đồng… của dự án ở đây.</p>')+'</div></div>'}

function actsHtml(p){
  var evs=[];
  pTasks(p.id).forEach(function(t){
    (t.log||[]).forEach(function(l){evs.push({at:l.at,by:l.by,tx:l.tx,t:t.title})});
    if(t.at)evs.push({at:t.at,by:t.by,tx:'tạo công việc',t:t.title})});
  (p.cmts||[]).forEach(function(m){evs.push({at:m.at,by:m.by,tx:'bình luận: '+m.tx,t:''})});
  evs.sort(function(a,b){return new Date(b.at)-new Date(a.at)});
  return '<div class="pad" style="max-width:760px"><div class="card-box"><h3 style="margin-bottom:10px">Hoạt động gần đây</h3>'+
    (evs.length?evs.slice(0,60).map(function(e){
      return '<div class="cmt">'+A.av(e.by,'s')+'<div class="b"><div><b style="font-weight:500">'+esc(e.by)+'</b> '+esc(e.tx)+
        (e.t?' — <span style="color:var(--app)">'+esc(e.t)+'</span>':'')+'</div>'+
        '<div class="by">'+esc(A.ago(e.at))+'</div></div></div>'}).join('')
      :'<p style="color:var(--muted)">Chưa có hoạt động nào.</p>')+'</div></div>'}

/* ---------- công việc của tôi ---------- */
function mineHtml(){
  var tabs={all:'Tất cả',todo:'Phải làm',doing:'Đang làm',late:'Quá hạn',done:'Hoàn thành'};
  var q=norm(V.q);
  var mine=A.col('tasks').filter(function(t){return A.isMe(t.assignee)});
  var f=mine.filter(function(t){
    if(V.mineTab==='late')return isLate(t);
    if(V.mineTab!=='all'&&t.status!==V.mineTab)return false;
    return true}).filter(function(t){return !q||norm(t.title).indexOf(q)>=0})
    .sort(function(a,b){
      var x=a.due?new Date(a.due).getTime():8.64e15,y=b.due?new Date(b.due).getTime():8.64e15;return x-y});
  var h='<div class="actionbar">'+Object.keys(tabs).map(function(k){
    var n=mine.filter(function(t){return k==='all'?1:k==='late'?isLate(t):t.status===k}).length;
    return '<button class="drop'+(V.mineTab===k?' on':'')+'" type="button" data-mtab="'+k+'" style="'+
      (V.mineTab===k?'color:var(--app);font-weight:500':'')+'">'+esc(tabs[k])+' <b>'+n+'</b></button>'}).join('')+'</div>';
  if(!A.me())return h+'<div class="empty"><h3>Chưa biết bạn là ai</h3><p>Bấm vào ô tên ở góc trên bên trái để nhận tên của bạn, rồi quay lại đây.</p></div>';
  h+=f.length?f.map(function(t){return rowHtml(t,true)}).join('')
    :'<div class="empty"><h3>Không có công việc nào</h3><p>Việc được giao cho '+esc(A.me())+' sẽ hiện ở đây.</p></div>';
  return h}

/* ---------- danh sách dự án & phòng ban ---------- */
function projectsHtml(){
  var q=norm(V.q);
  var list=projs().filter(function(p){return !q||norm(p.name+' '+(p.group||'')).indexOf(q)>=0});
  var h='<div class="actionbar"><button class="linkbtn" type="button" data-act="newproj">+ Thêm dự án mới</button>'+
    '<span class="spacer"></span><span class="by">'+list.length+' dự án · '+A.col('tasks').length+' công việc</span></div>'+
    '<div class="tbl-wrap"><table class="t"><thead><tr><th style="padding-left:14px">Dự án & phòng ban</th><th>Bộ phận</th>'+
    '<th>Thành viên</th><th style="width:180px">Thống kê</th><th>Trạng thái</th><th></th></tr></thead><tbody>';
  list.forEach(function(p){
    var st=stats(p.id),mem=members(p),ps=PS[p.status||'ontrack'];
    h+='<tr><td style="padding-left:14px"><button type="button" data-proj="'+p.id+'" style="display:flex;gap:9px;align-items:center;text-align:left">'+
      A.av(p.name,'',p.color)+'<span><b style="font-weight:500">'+esc(p.name)+'</b>'+
      (p.demo?' <span class="chip soft">ví dụ</span>':'')+
      '<span class="sub" style="display:block">'+esc(p.desc||'Chưa có mô tả')+'</span></span></button></td>'+
      '<td><span class="chip info">'+esc(p.group||'Chưa có')+'</span></td>'+
      '<td>'+A.stack(mem,5)+'</td>'+
      '<td><span class="by">'+st.done+'/'+st.n+' hoàn thành · '+st.late+' quá hạn</span>'+
      A.wbar([{v:st.done,c:'var(--green)'},{v:st.doing,c:'var(--blue)'},{v:Math.max(0,st.todo),c:'var(--panel-3)'}],st.n||1)+'</td>'+
      '<td><span class="chip done">Active</span> <span class="chip '+ps.c+'">'+ps.l+'</span></td>'+
      '<td class="n"><button class="linkbtn" type="button" data-editproj="'+p.id+'">Option</button></td></tr>'});
  if(!list.length)h+='<tr><td colspan="6" style="color:var(--muted);padding:20px 14px">Chưa có dự án nào.</td></tr>';
  return h+'</tbody></table></div>'}

/* ---------- báo cáo hệ thống ---------- */
function sysReportHtml(){
  var all=A.col('tasks'),ps=projs();
  var done=all.filter(function(t){return t.status==='done'}).length;
  var doing=all.filter(function(t){return t.status==='doing'}).length;
  var late=all.filter(isLate).length;
  var h='<div class="pad grid g4" style="padding-bottom:0">'+
    A.tile('Dự án',ps.length,'<span>'+ps.filter(function(p){return p.status==='risk'||p.status==='late'}).length+' cần chú ý</span>')+
    A.tile('Công việc',all.length,'<span>'+doing+' đang làm · '+done+' xong</span>')+
    A.tile('Quá hạn',late,'<span>trên toàn hệ thống</span>',late?'var(--red)':'')+
    A.tile('Thành viên',A.people().length,'<span>đang được giao việc</span>')+'</div>'+
    '<div class="pad grid g2">'+
    '<div class="card-box"><h3 style="margin-bottom:12px">Trạng thái công việc toàn hệ thống</h3>'+
    A.donut([{v:done,c:'var(--green)',l:'Hoàn thành'},{v:doing,c:'var(--blue)',l:'Đang làm'},
             {v:late,c:'var(--red)',l:'Quá hạn'},{v:Math.max(0,all.length-done-doing-late),c:'var(--panel-3)',l:'Chưa bắt đầu'}],
            all.length,'công việc')+'</div>'+
    '<div class="card-box"><h3 style="margin-bottom:12px">Tiến độ từng dự án</h3><div class="tbl-wrap"><table class="t">'+
    '<thead><tr><th>Dự án</th><th class="n">Xong</th><th class="n">Quá hạn</th><th style="width:110px">Tiến độ</th></tr></thead><tbody>'+
    ps.map(function(p){var st=stats(p.id);
      return '<tr><td><button type="button" data-proj="'+p.id+'" style="text-align:left">'+esc(p.name)+'</button></td>'+
        '<td class="n">'+st.done+'/'+st.n+'</td><td class="n" style="'+(st.late?'color:var(--red)':'')+'">'+st.late+'</td>'+
        '<td>'+A.wbar([{v:st.done,c:'var(--green)'},{v:st.doing,c:'var(--blue)'},{v:Math.max(0,st.todo),c:'var(--panel-3)'}],st.n||1)+'</td></tr>'}).join('')+
    '</tbody></table></div></div></div>'}

/* ---------- panel thông tin ---------- */
function infoPanel(){
  if(V.view!=='project'||!cur()){
    var all=A.col('tasks'),late=all.filter(isLate).length,mine=all.filter(function(t){return A.isMe(t.assignee)});
    return '<aside class="info"><div class="card-box"><p class="sect-t">Tổng quan</p>'+
      '<div class="metarow"><span class="k">Dự án</span><b class="v num">'+projs().length+'</b></div>'+
      '<div class="metarow"><span class="k">Công việc</span><b class="v num">'+all.length+'</b></div>'+
      '<div class="metarow"><span class="k">Quá hạn</span><b class="v num" style="color:var(--red)">'+late+'</b></div>'+
      '</div><div class="card-box"><p class="sect-t">Công việc của tôi</p>'+
      (A.me()?'<div class="kpis"><div class="kpi t"><b>'+mine.filter(function(t){return t.status==='todo'}).length+'</b><span>Phải làm</span></div>'+
      '<div class="kpi g"><b>'+mine.filter(function(t){return t.status==='doing'}).length+'</b><span>Đang làm</span></div>'+
      '<div class="kpi l"><b>'+mine.filter(isLate).length+'</b><span>Quá hạn</span></div></div>'
      :'<p style="color:var(--muted)">Đặt tên của bạn để lọc việc của mình.</p>')+'</div></aside>'}
  var p=cur(),st=stats(p.id),ps=PS[p.status||'ontrack'],mem=members(p);
  var my=pTasks(p.id).filter(function(t){return A.isMe(t.assignee)});
  var late=pTasks(p.id).filter(isLate).sort(function(a,b){return new Date(a.due)-new Date(b.due)}).slice(0,5);
  var h='<aside class="info">';
  if(A.demoCount())h+='<div class="demo-note"><span>Đang có '+A.demoCount()+' bản ghi ví dụ trên toàn hệ thống.</span>'+
    '<span class="spacer"></span><button type="button" data-act="wipe">Xoá hết</button></div>';
  h+='<div class="card-box"><h2>'+esc(p.name)+'</h2>'+
    '<p style="margin-bottom:10px;color:'+(p.desc?'var(--ink-2)':'var(--muted)')+'">'+esc(p.desc||'Chưa có mô tả')+'</p>'+
    '<div class="metarow"><span class="k">'+st.done+'/'+st.n+' hoàn thành</span><b class="v num">'+st.pct+'%</b></div>'+
    '<div class="bar"><i class="d" style="width:'+(st.n?st.done/st.n*100:0)+'%"></i><i class="g" style="width:'+(st.n?st.doing/st.n*100:0)+'%"></i></div>'+
    '<div class="metarow"><span class="k">Thời gian</span><span class="v">'+((p.start||p.end)?esc((A.fmtD(p.start)||'…')+' – '+(A.fmtD(p.end)||'…')):'Chưa đặt')+'</span></div>'+
    '<div class="metarow"><span class="k">Bộ phận</span><span class="v">'+esc(p.group||'Chưa có')+'</span></div>'+
    '<div class="metarow"><span class="k">Trạng thái</span><span class="v"><span class="chip '+ps.c+'">'+ps.l+'</span></span></div>'+
    '<div class="metarow"><span class="k">'+mem.length+' thành viên</span><span class="v">'+A.stack(mem,7)+'</span></div></div>';
  h+='<div class="card-box"><p class="sect-t">Công việc của tôi</p>'+
    (A.me()?'<div class="kpis"><div class="kpi t"><b>'+my.filter(function(t){return t.status==='todo'}).length+'</b><span>Phải làm</span></div>'+
    '<div class="kpi g"><b>'+my.filter(function(t){return t.status==='doing'}).length+'</b><span>Đang làm</span></div>'+
    '<div class="kpi d"><b>'+my.filter(function(t){return t.status==='done'}).length+'</b><span>Hoàn thành</span></div></div>'
    :'<p style="color:var(--muted)">Đặt tên của bạn ở góc trên bên trái để lọc việc của mình.</p>')+'</div>';
  h+='<div class="card-box"><p class="sect-t" style="color:var(--red)">Cần lưu ý</p>'+
    (late.length?late.map(function(t){
      return '<div class="mini"><span class="av s" style="background:var(--red)">!</span>'+
        '<button class="t" type="button" data-open="'+t.id+'">'+esc(t.title)+'</button>'+
        '<span class="due crit">'+A.fmtD(t.due)+'</span></div>'}).join('')
      :'<p style="color:var(--muted)">Không có việc nào quá hạn.</p>')+'</div>';
  return h+'</aside>'}

/* ---------- hộp thoại công việc ---------- */
var dlgT=null,dlgId=null;

/* Ai vẫn làm nhóm công việc này — đếm theo số việc đã giao, nhiều nhất lên trước.
   Nhóm cố định thường chỉ một người, chọn nhóm là điền sẵn được luôn. */
function nguoiCuaNhom(pid,lid){
  var dem={},ten={};
  A.col('tasks').forEach(function(t){
    if(t.pid!==pid||(t.list||'default')!==(lid||'default'))return;
    var n=String(t.assignee||'').trim();
    if(!n)return;
    var k=n.toLowerCase();
    if(!ten[k])ten[k]=n;
    dem[k]=(dem[k]||0)+1});
  return Object.keys(dem).sort(function(a,b){
    return dem[b]-dem[a]||ten[a].localeCompare(ten[b],'vi')}).map(function(k){return ten[k]})}

function openTask(id,listId){
  var p=cur();if(!p)return;
  if(!id){
    var t={id:A.uid(),pid:p.id,list:listId||lists(p)[0].id,title:'',assignee:A.me()||'',due:'',start:'',
           status:'todo',prio:'normal',note:'',result:'',subs:[],cmts:[],followers:A.me()?[A.me()]:[],log:[],
           by:A.me()||'',at:new Date().toISOString(),order:nextOrder(p.id,listId||lists(p)[0].id)};
    var hopMoi=A.form({title:'Công việc mới',wide:false,
      /* Chọn nhóm xong thì người phụ trách hiện sẵn: nhóm có đúng một người quen
         làm thì điền luôn, nhiều người thì bày ra cho bấm chọn. */
      onChange:function(k,v,api){
        if(k&&k!=='list')return;
        var ds=nguoiCuaNhom(p.id,v.list);
        if(!ds.length){api.hint('assignee','');return}
        if(ds.length===1){
          api.set('assignee',ds[0]);
          api.hint('assignee','Nhóm này vẫn do '+esc(ds[0])+' phụ trách.');return}
        api.hint('assignee','Nhóm này thường là '+ds.slice(0,4).map(function(n){
          return '<button type="button" class="linkbtn" data-goiy="'+esc(n)+'">'+esc(n)+'</button>'}).join(' · '))},
      fields:[{k:'title',label:'Tên công việc',required:true,ph:'Việc cần làm là gì?'},
              {k:'list',label:'Nhóm công việc',type:'select',value:t.list,options:lists(p).map(function(L){return {v:L.id,l:L.name}}),half:true},
              {k:'assignee',label:'Người phụ trách',type:'people',value:t.assignee,half:true},
              {k:'due',label:'Hạn chót',type:'datetime-local',half:true},
              {k:'prio',label:'Mức ưu tiên',type:'chips',value:'normal',options:[{v:'high',l:'Gấp'},{v:'normal',l:'Bình thường'},{v:'low',l:'Thong thả'}],half:true},
              {k:'note',label:'Mô tả',type:'textarea'}],
      onSave:function(v){
        t.title=v.title;t.list=v.list;t.assignee=v.assignee;t.prio=v.prio;t.note=v.note;
        t.due=v.due?new Date(v.due).toISOString():'';
        t.order=nextOrder(p.id,t.list);
        log(t,'tạo công việc');
        A.save('tasks',t)}});
    hopMoi.el.addEventListener('click',function(e){
      var b=e.target.closest('[data-goiy]');
      if(!b)return;
      hopMoi.api.set('assignee',b.getAttribute('data-goiy'))});
    return}
  dlgId=id;
  if(!dlgT){
    dlgT=document.createElement('dialog');dlgT.className='wide';document.body.appendChild(dlgT);
    dlgT.addEventListener('click',onDlgClick);
    dlgT.addEventListener('change',onDlgChange);
    dlgT.addEventListener('keydown',function(e){
      if(e.key==='Enter'&&e.target.id==='tSub'){e.preventDefault();addSub()}
      if(e.key==='Enter'&&e.target.id==='tCmt'){e.preventDefault();addCmt()}});
    dlgT.addEventListener('close',function(){dlgId=null});
  }
  fillTask();dlgT.showModal()}

function fillTask(){
  var t=task(dlgId);if(!t||!dlgT)return;
  var p=proj(t.pid)||cur(),late=isLate(t);
  var subs=t.subs||[],cmts=(t.cmts||[]).slice().reverse(),fol=t.followers||[],lg=(t.log||[]).slice().reverse();
  dlgT.innerHTML='<form method="dialog"><div class="dlg-h">'+
    '<span class="chip '+(ST[t.status]||ST.todo).c+'">'+(ST[t.status]||ST.todo).l+'</span>'+
    (late?'<span class="chip late">Quá hạn</span>':'')+
    '<h3>Chi tiết công việc</h3>'+
    '<button type="button" class="icon-btn" data-x="close" aria-label="Đóng">✕</button></div>'+
    '<div class="dlg-b" style="grid-template-columns:minmax(0,1.55fr) minmax(0,1fr);display:grid;gap:18px;max-height:min(70dvh,600px)">'+
    '<div>'+
      '<div class="fld"><label for="tTitle">Tên công việc</label><input id="tTitle" value="'+esc(t.title)+'"></div>'+
      '<div class="sec"><div class="sec-h"><h3>Mô tả</h3></div>'+
        '<textarea id="tNote" style="width:100%;min-height:70px;border:1px solid var(--line-2);border-radius:3px;padding:8px;background:var(--panel)" placeholder="Việc cần làm gì, bàn giao ra sao…">'+esc(t.note||'')+'</textarea></div>'+
      '<div class="sec"><div class="sec-h"><h3>Kết quả công việc</h3><span class="ds">Cập nhật khi xong</span></div>'+
        '<textarea id="tResult" style="width:100%;min-height:56px;border:1px solid var(--line-2);border-radius:3px;padding:8px;background:var(--panel)" placeholder="Kết quả, link tài liệu, biên bản…">'+esc(t.result||'')+'</textarea></div>'+
      filesHtml(t)+
      '<div class="sec"><div class="sec-h"><h3>Công việc con</h3><span class="ds">'+subs.filter(function(s){return s.done}).length+'/'+subs.length+'</span></div>'+
        subs.map(function(s,i){
          return '<div class="subt'+(s.done?' done':'')+'">'+
            '<button class="ck'+(s.done?' on':'')+'" type="button" data-sub="'+i+'" aria-label="Xong">'+
            '<svg viewBox="0 0 10 10" fill="none" stroke="#fff" stroke-width="2"><path d="M1.6 5.2 4 7.5 8.4 2.6"/></svg></button>'+
            '<span class="t">'+esc(s.t)+'</span>'+
            (s.who?A.av(s.who,'s'):'')+
            '<button class="linkbtn" type="button" data-subdel="'+i+'">Xoá</button></div>'}).join('')+
        '<div style="display:flex;gap:8px;margin-top:8px"><input id="tSub" placeholder="Thêm việc con rồi Enter" style="flex:1;border:1px solid var(--line-2);border-radius:3px;padding:6px 9px;background:var(--panel)">'+
        '<button class="btn sm" type="button" data-x="addsub">Thêm</button></div></div>'+
      '<div class="sec"><div class="sec-h"><h3>Bình luận</h3><span class="ds">'+(t.cmts||[]).length+'</span></div>'+
        '<div style="display:flex;gap:8px"><input id="tCmt" placeholder="Viết bình luận rồi Enter" style="flex:1;border:1px solid var(--line-2);border-radius:3px;padding:6px 9px;background:var(--panel)">'+
        '<button class="btn sm" type="button" data-x="addcmt">Gửi</button></div>'+
        cmts.map(function(m){
          return '<div class="cmt">'+A.av(m.by,'')+'<div class="b"><div class="hd"><span class="nm">'+esc(m.by)+'</span>'+
            '<span class="by">'+esc(A.ago(m.at))+'</span></div><div>'+esc(m.tx)+'</div></div></div>'}).join('')+
      '</div>'+
    '</div>'+
    '<div>'+
      '<div class="fld"><label for="tStatus">Trạng thái</label><select id="tStatus">'+
        Object.keys(ST).map(function(k){return '<option value="'+k+'"'+(t.status===k?' selected':'')+'>'+ST[k].l+'</option>'}).join('')+'</select></div>'+
      '<div class="fld" style="margin-top:11px"><label for="tAssignee">Người phụ trách</label>'+
        '<input id="tAssignee" list="dl_people" value="'+esc(t.assignee||'')+'"></div>'+
      '<div class="fld" style="margin-top:11px"><label for="tList">Nhóm công việc</label><select id="tList">'+
        lists(p).map(function(L){return '<option value="'+esc(L.id)+'"'+((t.list||'default')===L.id?' selected':'')+'>'+esc(L.name)+'</option>'}).join('')+'</select></div>'+
      '<div class="fld" style="margin-top:11px"><label for="tStart">Bắt đầu</label><input id="tStart" type="datetime-local" value="'+A.toLocal(t.start)+'"></div>'+
      '<div class="fld" style="margin-top:11px"><label for="tDue">Deadline</label><input id="tDue" type="datetime-local" value="'+A.toLocal(t.due)+'"></div>'+
      '<div class="fld" style="margin-top:11px"><label for="tPrio">Mức ưu tiên</label><select id="tPrio">'+
        Object.keys(PR).map(function(k){return '<option value="'+k+'"'+(t.prio===k?' selected':'')+'>'+PR[k]+'</option>'}).join('')+'</select></div>'+
      '<div class="sec"><div class="sec-h"><h3 style="font-size:12.5px">Người theo dõi</h3></div>'+
        (fol.length?fol.map(function(n,i){
          return '<div class="mini">'+A.av(n,'s')+'<span class="t">'+esc(n)+'</span>'+
            '<button class="linkbtn" type="button" data-foldel="'+i+'">Bỏ</button></div>'}).join('')
          :'<p class="by">Chưa có ai theo dõi.</p>')+
        '<div style="display:flex;gap:8px;margin-top:8px"><input id="tFol" list="dl_people" placeholder="Thêm người theo dõi" style="flex:1;border:1px solid var(--line-2);border-radius:3px;padding:6px 9px;background:var(--panel)">'+
        '<button class="btn sm ghost" type="button" data-x="addfol">Thêm</button></div></div>'+
      '<div class="sec"><div class="sec-h"><h3 style="font-size:12.5px">Lịch sử hoạt động</h3></div>'+
        (lg.length?lg.slice(0,12).map(function(l){
          return '<div class="tline"><span class="tm">'+esc(A.ago(l.at))+'</span><span><b style="font-weight:500">'+esc(l.by)+'</b> '+esc(l.tx)+'</span></div>'}).join('')
          :'<p class="by">Chưa có hoạt động.</p>')+
        '<p class="by" style="margin-top:8px">Tạo bởi @'+esc(t.by||'ẩn danh')+(t.at?' · '+A.fmtDT(t.at):'')+'</p></div>'+
    '</div></div>'+
    '<div class="dlg-f"><button type="button" class="btn danger" data-x="del">Xoá công việc</button>'+
    '<span class="spacer"></span><button type="button" class="btn ghost" data-x="close">Đóng</button>'+
    '<button type="button" class="btn" data-x="save">Lưu thay đổi</button></div></form>'}

/* ---------- tệp đính kèm: ảnh và PDF ---------- */
/* Giới hạn dung lượng lấy theo chính máy chủ (php.ini của hosting thường
   chỉ cho 2–8MB), không tự đặt 20MB rồi để người dùng chọn xong mới báo lỗi. */
var MAX_SO=20;
function maxTep(){
  var n=window.KH_API&&Number(window.KH_API.maxUpload);
  return n&&n>0?n:20*1024*1024}
/* Máy chủ nói trước tài khoản này có được tải tệp hay không, để chặn ngay ở nút
   thay vì để người dùng chọn tệp xong mới nhận cái lỗi 403 khó hiểu. */
function duocTaiTep(){
  var a=window.KH_API;
  return !a||typeof a.canUpload==='undefined'||Number(a.canUpload)?true:false}

function filesHtml(t){
  var fs=t.files||[];
  return '<div class="sec"><div class="sec-h"><h3>Tệp đính kèm</h3>'+
    '<span class="ds">'+(fs.length?fs.length+' tệp':'ảnh hoặc PDF')+'</span></div>'+
    (fs.length?'<div class="atts">'+fs.map(function(f,i){
      return '<div class="att">'+
        (A.isImage(f.type)
          ? '<a class="th" href="'+esc(f.url)+'" target="_blank" rel="noopener" title="Mở ảnh">'+
            '<img src="'+esc(f.url)+'" alt="'+esc(f.name)+'" loading="lazy"></a>'
          : '<a class="th pdf" href="'+esc(f.url)+'" target="_blank" rel="noopener" title="Mở tệp">PDF</a>')+
        '<div class="b"><a href="'+esc(f.url)+'" target="_blank" rel="noopener" class="nm">'+esc(f.name)+'</a>'+
        '<span class="by">'+esc(kichThuoc(f.size))+(f.by?' · '+esc(f.by):'')+'</span></div>'+
        '<button class="linkbtn" type="button" data-fdel="'+i+'">Xoá</button></div>'}).join('')+'</div>'
      :'<p class="by">Chưa có tệp nào. Đính kèm bản thiết kế, hợp đồng, ảnh hiện trường…</p>')+
    '<div style="display:flex;gap:8px;align-items:center;margin-top:8px">'+
      '<input id="tFile" type="file" accept="image/*,application/pdf,.pdf" multiple hidden>'+
      '<button class="btn sm ghost" type="button" data-x="addfile"'+
        (duocTaiTep()?'':' disabled')+'>+ Thêm tệp</button>'+
      '<span class="by" id="tFileHint">'+(duocTaiTep()
        ?('Ảnh hoặc PDF, mỗi tệp tối đa '+kichThuoc(maxTep())+'.')
        :'Tài khoản của bạn chưa có quyền tải tệp — nhờ quản trị cấp quyền.')+'</span>'+
    '</div></div>'}

function kichThuoc(n){
  n=Number(n)||0;
  if(!n)return '';
  if(n<1024)return n+' B';
  if(n<1024*1024)return Math.round(n/1024)+' KB';
  return (n/1024/1024).toFixed(1).replace('.0','')+' MB'}

function moChonTep(){
  if(!duocTaiTep()){
    A.toast('Tài khoản của bạn chưa có quyền tải tệp lên — nhờ quản trị cấp quyền.',true);return}
  var el=A.$('#tFile',dlgT);
  if(!el)return;
  el.value='';          /* chọn lại đúng tệp vừa gỡ vẫn nổ sự kiện change */
  el.click()}

/* Gắn theo kiểu uỷ quyền trên cả hộp thoại: hộp được vẽ lại sau mỗi lần lưu,
   gắn thẳng vào ô input thì lần vẽ sau là mất bộ nghe. */
function onDlgChange(e){
  if(!e.target||'tFile'!==e.target.id)return;
  nhanTep(e.target)}

function nhanTep(el){
  (function(){
    var ds=Array.prototype.slice.call(el.files||[]);
    if(!ds.length)return;
    var t=task(dlgId);if(!t)return;
    var dang=(t.files||[]).length;
    if(dang+ds.length>MAX_SO){A.toast('Mỗi công việc giữ tối đa '+MAX_SO+' tệp.',true);return}
    var gh=maxTep(),qua=ds.filter(function(f){return f.size>gh});
    if(qua.length){A.toast('“'+qua[0].name+'” nặng '+kichThuoc(qua[0].size)+
      ', quá mức máy chủ cho phép ('+kichThuoc(gh)+').',true);return}
    var xau=ds.filter(function(f){return !A.isImage(f.type)&&f.type!=='application/pdf'});
    if(xau.length){A.toast('Chỉ nhận ảnh hoặc PDF — “'+xau[0].name+'” thì không.',true);return}

    var hint=A.$('#tFileHint',dlgT);
    if(hint)hint.textContent='Đang tải '+ds.length+' tệp lên…';
    Promise.all(ds.map(function(f){return A.upload(f,f.name)})).then(function(kq){
      var duoc=kq.filter(Boolean);
      if(!duoc.length){
        A.toast('Không tải được tệp lên — '+(A.uploadErr||'máy chủ không nói lý do')+'.',true);
        if(hint)hint.textContent='Ảnh hoặc PDF, mỗi tệp tối đa '+kichThuoc(maxTep())+'.';return}
      var t2=task(dlgId);if(!t2)return;
      var o=Object.assign({},t2);
      o.files=(o.files||[]).concat(duoc.map(function(x){
        return {url:x.url,id:x.id,name:x.name,type:x.type,size:x.size,
                by:A.me()||'ẩn danh',at:new Date().toISOString()}}));
      log(o,duoc.length>1?('đính kèm '+duoc.length+' tệp'):('đính kèm “'+duoc[0].name+'”'));
      A.save('tasks',o).then(function(){
        fillTask();
        A.toast(duoc.length<ds.length
          ? ('Đã đính kèm '+duoc.length+'/'+ds.length+' tệp — số còn lại không tải được.')
          : ('Đã đính kèm '+duoc.length+' tệp.'))})})})()}

function addSub(){
  var t=task(dlgId),el=A.$('#tSub',dlgT);if(!t||!el||!el.value.trim())return;
  var o=Object.assign({},t);o.subs=(o.subs||[]).concat([{t:el.value.trim(),done:false,who:''}]);
  log(o,'thêm việc con “'+el.value.trim()+'”');
  A.save('tasks',o).then(fillTask)}
function addCmt(){
  var t=task(dlgId),el=A.$('#tCmt',dlgT);if(!t||!el||!el.value.trim())return;
  var o=Object.assign({},t);
  o.cmts=(o.cmts||[]).concat([{by:A.me()||'ẩn danh',tx:el.value.trim(),at:new Date().toISOString()}]).slice(-60);
  A.save('tasks',o).then(fillTask)}
function onDlgClick(e){
  var b=e.target.closest('[data-x],[data-sub],[data-subdel],[data-foldel],[data-fdel]');if(!b)return;
  var t=task(dlgId);if(!t)return;
  var o=Object.assign({},t),x=b.getAttribute('data-x');
  if(b.hasAttribute('data-sub')){var i=+b.getAttribute('data-sub');
    o.subs=(o.subs||[]).slice();o.subs[i]=Object.assign({},o.subs[i],{done:!o.subs[i].done});
    A.save('tasks',o).then(fillTask);return}
  if(b.hasAttribute('data-subdel')){var j=+b.getAttribute('data-subdel');
    o.subs=(o.subs||[]).slice();o.subs.splice(j,1);A.save('tasks',o).then(fillTask);return}
  if(b.hasAttribute('data-foldel')){var k=+b.getAttribute('data-foldel');
    o.followers=(o.followers||[]).slice();o.followers.splice(k,1);A.save('tasks',o).then(fillTask);return}
  if(b.hasAttribute('data-fdel')){var n=+b.getAttribute('data-fdel');
    var ten=((o.files||[])[n]||{}).name||'tệp';
    if(!confirm('Gỡ “'+ten+'” khỏi công việc này?'))return;
    o.files=(o.files||[]).slice();o.files.splice(n,1);
    log(o,'gỡ tệp “'+ten+'”');
    A.save('tasks',o).then(fillTask);return}
  if(x==='addfile'){moChonTep();return}
  if(x==='addsub'){addSub();return}
  if(x==='addcmt'){addCmt();return}
  if(x==='addfol'){var el=A.$('#tFol',dlgT);if(el&&el.value.trim()){
    o.followers=(o.followers||[]).concat([el.value.trim()]);A.save('tasks',o).then(fillTask)}return}
  if(x==='close'){dlgT.close();return}
  if(x==='del'){if(confirm('Xoá công việc này?')){A.remove('tasks',dlgId);dlgT.close()}return}
  if(x==='save'){
    var tt=A.$('#tTitle',dlgT).value.trim();
    if(!tt){A.toast('Công việc cần có tên.',true);return}
    var was=o.status;
    o.title=tt;o.note=A.$('#tNote',dlgT).value;o.result=A.$('#tResult',dlgT).value;
    o.status=A.$('#tStatus',dlgT).value;o.assignee=A.$('#tAssignee',dlgT).value.trim();
    o.prio=A.$('#tPrio',dlgT).value;
    var nl=A.$('#tList',dlgT).value;
    if(nl!==(o.list||'default')){o.list=nl;o.order=nextOrder(o.pid,nl)}
    var sv=A.$('#tStart',dlgT).value,dv=A.$('#tDue',dlgT).value;
    o.start=sv?new Date(sv).toISOString():'';o.due=dv?new Date(dv).toISOString():'';
    if(was!==o.status){log(o,'đổi trạng thái sang “'+(ST[o.status]||ST.todo).l+'”');
      if(o.status==='done')o.doneAt=new Date().toISOString()}
    else log(o,'cập nhật công việc');
    A.save('tasks',o);dlgT.close();return}}

/* ---------- hộp thoại dự án / nhóm việc ---------- */
/* Bộ phận đã gõ ở các dự án khác — gộp chung với sổ bộ phận của Nhân sự để lần
   sau chỉ việc chọn lại. */
function pGroups(){return A.col('projects').map(function(x){return x.group})}

function openProject(id){
  if(!A.needRole('project.manage','Chỉ Quản lý trở lên mới tạo hoặc sửa được dự án.'))return;
  var p=id?proj(id):null;
  A.form({title:p?'Sửa dự án':'Dự án mới',
    fields:[{k:'name',label:'Tên dự án',required:true,value:p?p.name:''},
      {k:'group',label:'Bộ phận',type:'datalist',options:A.deptAll(pGroups()),
       value:p?(p.group||''):'',ph:'VD: Khu vui chơi',half:true},
      {k:'status',label:'Trạng thái',type:'select',value:p?(p.status||'ontrack'):'ontrack',half:true,
       options:[{v:'ontrack',l:'Đúng tiến độ'},{v:'risk',l:'Có rủi ro cao'},{v:'late',l:'Chậm tiến độ'}]},
      {k:'start',label:'Ngày bắt đầu',type:'date',value:p&&p.start?p.start.slice(0,10):'',half:true},
      {k:'end',label:'Ngày kết thúc',type:'date',value:p&&p.end?p.end.slice(0,10):'',half:true},
      {k:'color',label:'Màu nhận dạng',type:'color',value:p?(p.color||A.hue(p.name)):A.AVC[A.col('projects').length%A.AVC.length]},
      {k:'members',label:'Thành viên (cách nhau bằng dấu phẩy)',value:p?((p.members||[]).join(', ')):(A.me()||'')},
      {k:'desc',label:'Mô tả',type:'textarea',value:p?(p.desc||''):''}],
    onDelete:p?function(){
      pTasks(p.id).forEach(function(t){A.remove('tasks',t.id)});
      A.remove('projects',p.id);V.pid=null}:null,
    deleteLabel:'Xoá dự án',confirmDelete:'Xoá dự án này cùng toàn bộ công việc? Không khôi phục được.',
    onSave:function(v){
      var o=p?Object.assign({},p):{id:A.uid(),order:Date.now(),lists:[{id:'default',name:'Chưa phân loại'}],
        at:new Date().toISOString(),cmts:[],docs:[]};
      o.name=v.name;o.group=A.deptCanon(v.group,pGroups());o.status=v.status;o.start=v.start;o.end=v.end;o.color=v.color;o.desc=v.desc;
      o.members=v.members.split(',').map(function(s){return s.trim()}).filter(Boolean);
      if(!p){V.pid=o.id;V.view='project'}
      A.save('projects',o)}})}

function openList(lid){
  var p=cur();if(!p)return;
  var L=lid?lists(p).filter(function(x){return x.id===lid})[0]:null;
  A.form({title:L?'Sửa nhóm công việc':'Nhóm công việc mới',
    fields:[{k:'name',label:'Tên nhóm',required:true,value:L?L.name:'',ph:'VD: Khảo sát mặt bằng'}],
    onDelete:L?function(){
      var used=pTasks(p.id).filter(function(t){return (t.list||'default')===lid});
      var rest=lists(p).filter(function(x){return x.id!==lid});
      if(!rest.length){A.toast('Dự án cần ít nhất một nhóm công việc.',true);return}
      used.forEach(function(t){var o=Object.assign({},t);o.list=rest[0].id;A.save('tasks',o)});
      var o=Object.assign({},p);o.lists=rest;A.save('projects',o)}:null,
    deleteLabel:'Xoá nhóm',confirmDelete:'Xoá nhóm này? Công việc bên trong sẽ chuyển sang nhóm đầu tiên.',
    onSave:function(v){
      var o=Object.assign({},p);o.lists=lists(p).slice();
      if(L)o.lists=o.lists.map(function(x){return x.id===lid?{id:x.id,name:v.name}:x});
      else o.lists.push({id:A.uid(),name:v.name});
      A.save('projects',o)}})}

/* ---------- kéo thả ---------- */
var dragId=null;
function wireDrag(){
  var board=A.$('#board');if(!board)return;
  A.$$('.card',board).forEach(function(c){
    c.addEventListener('dragstart',function(e){dragId=c.getAttribute('data-task');c.classList.add('dragging');
      try{e.dataTransfer.setData('text/plain',dragId);e.dataTransfer.effectAllowed='move'}catch(err){}});
    c.addEventListener('dragend',function(){dragId=null;c.classList.remove('dragging');
      A.$$('.col',board).forEach(function(x){x.classList.remove('over')})})});
  A.$$('.col-b',board).forEach(function(box){
    box.addEventListener('dragover',function(e){if(!dragId)return;e.preventDefault();
      try{e.dataTransfer.dropEffect='move'}catch(err){}
      box.parentNode.classList.add('over')});
    box.addEventListener('dragleave',function(e){if(!box.contains(e.relatedTarget))box.parentNode.classList.remove('over')});
    box.addEventListener('drop',function(e){e.preventDefault();box.parentNode.classList.remove('over');
      if(dragId)drop(box,e.clientY)})})}
function drop(box,y){
  var t=task(dragId);if(!t)return;
  var lid=box.getAttribute('data-l');
  var cards=A.$$('.card',box).filter(function(c){return c.getAttribute('data-task')!==dragId});
  var ref=null,min=Infinity;
  cards.forEach(function(c){var r=c.getBoundingClientRect(),off=y-(r.top+r.height/2);
    if(off<0&&Math.abs(off)<min){min=Math.abs(off);ref=c}});
  var ids=cards.map(function(c){return c.getAttribute('data-task')});
  var idx=ref?ids.indexOf(ref.getAttribute('data-task')):ids.length;
  var prev=idx>0?task(ids[idx-1]):null,next=ref?task(ref.getAttribute('data-task')):null,o;
  if(!prev&&!next)o=1000;else if(!prev)o=(next.order||0)-10;
  else if(!next)o=(prev.order||0)+10;else o=((prev.order||0)+(next.order||0))/2;
  var n=Object.assign({},t);
  if((n.list||'default')!==lid)log(n,'chuyển sang nhóm khác');
  n.list=lid;n.order=o;V.sort='manual';
  A.save('tasks',n)}

/* ---------- sự kiện ---------- */
function after(){
  var root=A.$('#appRoot');
  root.addEventListener('click',onClick);
  var s=A.$('#wkSearch');if(s)s.addEventListener('input',function(){V.pq=this.value;A.render()});
  var q=A.$('#wkQ');if(q)q.addEventListener('input',function(){V.q=this.value;A.render()});
  if(V.view==='project'&&V.tab==='work'&&V.mode==='board')wireDrag();
  if(dlgId&&dlgT&&dlgT.open)fillTask()}

function onClick(e){
  var el=e.target.closest('[data-v],[data-grp],[data-proj],[data-tab],[data-mode],[data-menu],[data-act],[data-check],[data-open],[data-ltog],[data-ledit],[data-addin],[data-mtab],[data-editproj],[data-deldoc],[data-task]');
  if(!el)return;
  var g;
  if((g=el.getAttribute('data-v'))){V.view=g;V.q='';A.render();return}
  if((g=el.getAttribute('data-grp'))!==null&&el.hasAttribute('data-grp')){V.closedG[g]=!V.closedG[g];A.render();return}
  if((g=el.getAttribute('data-proj'))){V.pid=g;V.view='project';V.tab='work';V.q='';A.render();return}
  if((g=el.getAttribute('data-tab'))){V.tab=g;A.render();return}
  if((g=el.getAttribute('data-mode'))){V.mode=g;A.ls('kh.wk.mode',g);A.render();return}
  if((g=el.getAttribute('data-mtab'))){V.mineTab=g;A.render();return}
  if((g=el.getAttribute('data-menu'))){
    if(g==='sort')A.menu(el,SORTS,V.sort,function(k){V.sort=k;A.render()});
    else A.menu(el,FILTERS,V.filter,function(k){V.filter=k;A.render()});
    return}
  if((g=el.getAttribute('data-check'))){
    var t=task(g);if(t){var o=Object.assign({},t);
      o.status=o.status==='done'?'todo':'done';
      if(o.status==='done')o.doneAt=new Date().toISOString();
      log(o,o.status==='done'?'đánh dấu hoàn thành':'mở lại công việc');
      A.save('tasks',o)}
    return}
  if((g=el.getAttribute('data-open'))){openTask(g);return}
  if((g=el.getAttribute('data-ltog'))){V.closedL[V.pid+':'+g]=!V.closedL[V.pid+':'+g];A.render();return}
  if((g=el.getAttribute('data-ledit'))){openList(g);return}
  if((g=el.getAttribute('data-addin'))){openTask(null,g);return}
  if((g=el.getAttribute('data-editproj'))){openProject(g);return}
  if((g=el.getAttribute('data-deldoc'))){
    var p=cur();if(!p)return;var o=Object.assign({},p);o.docs=(o.docs||[]).slice();o.docs.splice(+g,1);A.save('projects',o);return}
  if((g=el.getAttribute('data-act'))){
    var p2=cur();
    if(g==='newproj')openProject(null);
    else if(g==='editproj')openProject(V.pid);
    else if(g==='newtask')openTask(null);
    else if(g==='newlist')openList(null);
    else if(g==='star'&&V.pid){A.S.star[V.pid]=!A.S.star[V.pid];
      if(!A.S.star[V.pid])delete A.S.star[V.pid];A.lsj('kh.star',A.S.star);A.render()}
    else if(g==='pcmt'&&p2){
      var i=A.$('#pCmt');if(i&&i.value.trim()){var o2=Object.assign({},p2);
        o2.cmts=(o2.cmts||[]).concat([{by:A.me()||'ẩn danh',tx:i.value.trim(),at:new Date().toISOString()}]).slice(-80);
        A.save('projects',o2)}}
    else if(g==='newdoc'&&p2){
      A.form({title:'Thêm tài liệu',
        fields:[{k:'name',label:'Tên tài liệu',required:true,ph:'VD: Biên bản nghiệm thu cơ sở Tân Bình'},
                {k:'note',label:'Ghi chú',type:'textarea',ph:'Để ở đâu, ai giữ bản gốc…'}],
        onSave:function(v){var o3=Object.assign({},p2);
          o3.docs=(o3.docs||[]).concat([{name:v.name,note:v.note,by:A.me()||'ẩn danh',at:new Date().toISOString()}]);
          A.save('projects',o3)}})}
    else if(g==='wipe'){
      if(!confirm('Xoá toàn bộ dữ liệu ví dụ của cả 12 ứng dụng (dự án, nhân sự, chấm công, đề xuất, quy trình, thông báo…)? Không khôi phục được.'))return;
      A.wipeDemo()}
    return}
  if((g=el.getAttribute('data-task'))){openTask(g);return}}

A.register({id:'wework',name:'Công việc & Dự án',desc:'Tasks Management',cat:'work',color:'#1177D8',icon:'work',
  side:true,info:true,view:view,after:after,
  onEnter:function(arg){if(arg&&proj(arg)){V.pid=arg;V.view='project';V.tab='work'}},
  badge:function(){return A.col('tasks').filter(function(t){return A.isMe(t.assignee)&&isLate(t)}).length}});
})();
