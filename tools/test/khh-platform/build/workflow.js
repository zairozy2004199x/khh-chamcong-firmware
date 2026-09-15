/* Workflow — Quy trình nhiều giai đoạn */
(function(){
'use strict';
var A=window.APP,esc=A.esc,norm=A.norm;
var V={fid:A.ls('kh.fl.fid')||null,tab:'board',q:'',jtab:'all',view:'flow'};
var JS_={running:{l:'Đang xử lý',c:'info'},done:{l:'Đã hoàn thành',c:'done'},failed:{l:'Thất bại',c:'late'}};
var JTABS={all:'Tất cả',done:'Đã hoàn thành',running:'Đang xử lý',failed:'Thất bại',late:'Quá hạn'};

function flows(){return A.col('flows')}
function flow(id){return A.find('flows',id)}
function cur(){return flow(V.fid)}
function job(id){return A.find('jobs',id)}
function fJobs(fid){return A.col('jobs').filter(function(j){return j.flowId===fid})}
function stages(f){var s=(f&&f.stages)||[];return s.length?s:[{id:'s1',name:'Giai đoạn 1'}]}
function isLate(j){return j.status==='running'&&!!j.due&&new Date(j.due).getTime()<Date.now()}
function dueLbl(j){
  if(!j.due)return 'No deadline';
  var d=Math.round((new Date(j.due).getTime()-Date.now())/864e5);
  if(j.status!=='running')return A.fmtD(j.due);
  if(d<0)return 'Quá hạn '+Math.abs(d)+' ngày';
  if(d===0)return 'Đến hạn hôm nay';
  return 'Đến hạn trong '+d+' ngày'}

function side(){
  var q=norm(V.q),groups={},order=[];
  flows().forEach(function(f){
    if(q&&norm(f.name).indexOf(q)<0)return;
    var g=f.group||'Quy trình';if(!groups[g]){groups[g]=[];order.push(g)}groups[g].push(f)});
  var h='<aside class="side">'+A.sideUser()+A.sideSearch('flSearch','Tìm nhanh quy trình',V.q)+'<div class="side-list">'+
    '<div class="grp"><div class="grp-b">'+
    '<button class="pitem'+(V.view==='mine'?' on':'')+'" type="button" data-v="mine">'+A.icon('work','ic')+
      '<span class="t">Nhiệm vụ của tôi</span><span class="n">'+
      A.col('jobs').filter(function(j){return A.isMe(j.assignee)&&j.status==='running'}).length+'</span></button>'+
    '<button class="pitem'+(V.view==='all'?' on':'')+'" type="button" data-v="all">'+A.icon('flow','ic')+
      '<span class="t">Tất cả quy trình</span><span class="n">'+flows().length+'</span></button>'+
    '</div></div>';
  order.forEach(function(g){
    h+='<div class="grp"><div class="grp-h"><span>'+esc(g)+'</span></div><div class="grp-b">'+
      groups[g].map(function(f){
        var js=fJobs(f.id);
        return '<button class="pitem'+(V.fid===f.id&&V.view==='flow'?' on':'')+'" type="button" data-flow="'+f.id+'">'+
          A.av(f.name,'s',f.color)+'<span class="t">'+esc(f.name)+'</span>'+
          '<span class="n">'+js.filter(function(j){return j.status==='running'}).length+'</span></button>'}).join('')+
      '</div></div>'});
  if(!order.length)h+='<p style="padding:12px;color:var(--rail-muted);font-size:12px">Chưa có quy trình nào.</p>';
  return h+'</div><button class="side-add" type="button" data-act="newflow">+ Quy trình mới</button></aside>'}

function view(){
  if(V.view==='flow'&&!cur()){var f=flows()[0];V.fid=f?f.id:null}
  if(V.fid)A.ls('kh.fl.fid',V.fid);
  if(V.view==='mine')return side()+'<section class="stage">'+
    '<header class="topbar">'+A.navBtn()+'<h1>Danh sách nhiệm vụ</h1></header><nav class="tabs"></nav>'+
    '<div class="content" id="content">'+mineHtml()+'</div></section>';
  if(V.view==='all')return side()+'<section class="stage">'+
    '<header class="topbar">'+A.navBtn()+'<h1>Tất cả quy trình</h1></header><nav class="tabs"></nav>'+
    '<div class="content" id="content">'+allHtml()+'</div></section>';
  var f=cur();
  if(!f)return side()+'<section class="stage"><header class="topbar">'+A.navBtn()+'<h1>Quy trình</h1></header>'+
    '<nav class="tabs"></nav><div class="content" id="content"><div class="empty"><h3>Chưa có quy trình nào</h3>'+
    '<p>Quy trình là chuỗi giai đoạn cố định — ví dụ mua hàng: Thông tin đơn → Lập dự toán → Chọn NCC → Theo dõi → Nhận hàng.</p>'+
    '<p style="margin-top:14px"><button class="btn" type="button" data-act="newflow">+ Quy trình mới</button></p></div></div></section>';
  var tabs=[['board','Home'],['jobs','Nhiệm vụ'],['acts','Hoạt động'],['members','Thành viên'],['report','Báo cáo'],['edit','Chỉnh sửa']];
  var h='<section class="stage"><header class="topbar">'+A.navBtn()+
    '<span class="av sq" style="background:'+(f.color||A.hue(f.name))+'">'+esc(A.initials(f.name))+'</span>'+
    '<h1>'+esc(f.name)+'</h1><span class="spacer"></span>'+
    '<span class="qsearch"><input id="flQ" type="search" placeholder="Tìm nhanh nhiệm vụ" value="'+esc(V.q)+'" aria-label="Tìm nhiệm vụ"></span>'+
    '<button class="btn" type="button" data-act="newjob">+ Thêm nhiệm vụ</button></header>'+
    '<nav class="tabs">'+tabs.map(function(t){
      return '<button class="tab'+(V.tab===t[0]?' on':'')+'" type="button" data-tab="'+t[0]+'">'+esc(t[1])+'</button>'}).join('')+'</nav>'+
    '<div class="content" id="content">'+
    (V.tab==='jobs'?jobsHtml(f):V.tab==='report'?reportHtml(f):V.tab==='acts'?actsHtml(f):
     V.tab==='members'?membersHtml(f):V.tab==='edit'?editHtml(f):boardHtml(f))+
    '</div></section>';
  return side()+h}

function boardHtml(f){
  var q=norm(V.q);
  var js=fJobs(f.id).filter(function(j){return !q||norm(j.title+' '+(j.desc||'')).indexOf(q)>=0});
  var h='<div class="board" id="flboard">';
  stages(f).forEach(function(s,i){
    var items=js.filter(function(j){return j.stage===s.id});
    var late=items.filter(isLate).length;
    h+='<div class="col" data-s="'+esc(s.id)+'"><div class="col-h"><span class="gr">'+(i+1)+'.</span>'+
      '<button class="nm" type="button" data-sedit="'+esc(s.id)+'">'+esc(s.name)+'</button>'+
      A.stack(items.map(function(j){return j.assignee||'?'}),3)+
      '<span class="ct">'+items.length+'</span></div>'+
      '<div class="col-sub"><span>'+items.length+' nhiệm vụ</span>'+(late?'<span style="color:var(--red)">'+late+' quá hạn</span>':'')+'</div>'+
      '<div class="col-b" data-s="'+esc(s.id)+'">'+
      (items.length?items.map(jobCard).join(''):'<p class="by" style="padding:8px 2px">Trống</p>')+'</div></div>'});
  var dn=js.filter(function(j){return j.status==='done'});
  h+='<div class="col" data-s="__done"><div class="col-h"><span class="gr">✔</span><span class="nm">Done</span>'+
    '<span class="ct">'+dn.length+'</span></div><div class="col-sub"><span>đã hoàn thành</span></div>'+
    '<div class="col-b" data-s="__done">'+dn.map(jobCard).join('')+'</div></div>';
  h+='<button class="col" style="width:196px;padding:12px;color:var(--muted);align-items:center;justify-content:center;min-height:44px" type="button" data-act="newstage">+ Thêm giai đoạn</button>';
  return h+'</div>'}

function jobCard(j){
  var late=isLate(j);
  return '<div class="card'+(late?' jr':'')+(j.status==='done'?' jg':'')+'" draggable="true" data-job="'+j.id+'">'+
    '<div class="ct">'+esc(j.title)+'</div>'+
    (j.desc?'<div class="cdsc">'+esc(j.desc)+'</div>':'')+
    '<div class="card-f">'+(j.status==='failed'?'<span class="chip late">Thất bại</span>':'')+
    (j.assignee?A.av(j.assignee,'s')+'<span class="by">'+esc(j.assignee)+'</span>':'')+
    '<span class="spacer"></span><span class="due '+(late?'crit':'')+'">'+esc(dueLbl(j))+'</span></div></div>'}

function jobsHtml(f){
  var q=norm(V.q);
  var js=fJobs(f.id).filter(function(j){
    if(V.jtab==='late')return isLate(j);
    if(V.jtab!=='all'&&j.status!==V.jtab)return false;
    return true}).filter(function(j){return !q||norm(j.title).indexOf(q)>=0})
    .sort(function(a,b){return new Date(a.due||0)-new Date(b.due||0)});
  var h='<div class="actionbar">'+Object.keys(JTABS).map(function(k){
    return '<button class="drop" type="button" data-jtab="'+k+'" style="'+(V.jtab===k?'color:var(--app);font-weight:500':'')+'">'+
      esc(JTABS[k])+'</button>'}).join('')+'</div>';
  h+=js.length?js.map(function(j){return jobRow(j,f)}).join(''):'<p class="empty">Không có nhiệm vụ nào.</p>';
  return h}

function jobRow(j,f){
  var st=JS_[j.status]||JS_.running,late=isLate(j),sg=stages(f).filter(function(s){return s.id===j.stage})[0];
  return '<div class="row'+(late?' late':'')+'" data-job="'+j.id+'">'+
    '<button class="ck'+(j.status==='done'?' on':'')+'" type="button" data-jdone="'+j.id+'" aria-label="Hoàn thành">'+
    '<svg viewBox="0 0 10 10" fill="none" stroke="#fff" stroke-width="2"><path d="M1.6 5.2 4 7.5 8.4 2.6"/></svg></button>'+
    '<div class="rmain"><button class="rt" type="button" data-jopen="'+j.id+'">'+esc(j.title)+'</button>'+
    '<div class="chips"><span class="chip '+st.c+'">'+st.l+'</span>'+
    (late?'<span class="chip late">Quá hạn</span>':'')+
    (j.desc?'<span class="by">'+esc(String(j.desc).slice(0,80))+'</span>':'')+'</div></div>'+
    '<div class="rside"><span style="text-align:right"><span class="by" style="display:block">Giai đoạn</span>'+
    '<span style="font-size:11.5px">'+esc(sg?sg.name:'—')+'</span></span>'+
    '<span style="text-align:right"><span class="by" style="display:block">Deadline</span>'+
    '<span class="due '+(late?'crit':'')+'">'+(j.due?A.fmtDT(j.due):'Không thời hạn')+'</span></span>'+
    '<span class="who">'+(j.assignee?A.av(j.assignee,'s')+'<span>'+esc(j.assignee)+'</span>':'<span class="by">Chưa giao</span>')+'</span>'+
    '</div></div>'}

function mineHtml(){
  var js=A.col('jobs').filter(function(j){return A.isMe(j.assignee)})
    .sort(function(a,b){return new Date(a.due||0)-new Date(b.due||0)});
  if(!A.me())return '<div class="empty"><h3>Chưa biết bạn là ai</h3><p>Bấm ô tên ở góc trên bên trái để nhận tên.</p></div>';
  if(!js.length)return '<div class="empty"><h3>Không có nhiệm vụ nào</h3><p>Nhiệm vụ trong các quy trình giao cho bạn sẽ hiện ở đây.</p></div>';
  return js.map(function(j){var f=flow(j.flowId);return f?jobRow(j,f):''}).join('')}

function allHtml(){
  var h='<div class="tbl-wrap"><table class="t"><thead><tr><th style="padding-left:14px">Quy trình</th><th>Nhóm</th>'+
    '<th class="n">Giai đoạn</th><th class="n">Đang xử lý</th><th class="n">Hoàn thành</th><th class="n">Quá hạn</th><th style="width:110px">Tiến độ</th></tr></thead><tbody>';
  flows().forEach(function(f){
    var js=fJobs(f.id),d=js.filter(function(j){return j.status==='done'}).length;
    var r=js.filter(function(j){return j.status==='running'}).length,lt=js.filter(isLate).length;
    h+='<tr><td style="padding-left:14px"><button type="button" data-flow="'+f.id+'" style="display:flex;gap:9px;align-items:center">'+
      A.av(f.name,'',f.color)+'<b style="font-weight:500">'+esc(f.name)+'</b></button></td>'+
      '<td><span class="chip info">'+esc(f.group||'Quy trình')+'</span></td>'+
      '<td class="n">'+stages(f).length+'</td><td class="n">'+r+'</td><td class="n">'+d+'</td>'+
      '<td class="n" style="'+(lt?'color:var(--red)':'')+'">'+lt+'</td>'+
      '<td>'+A.wbar([{v:d,c:'var(--green)'},{v:r,c:'var(--blue)'}],js.length||1)+'</td></tr>'});
  if(!flows().length)h+='<tr><td colspan="7" style="padding:20px 14px;color:var(--muted)">Chưa có quy trình nào.</td></tr>';
  return h+'</tbody></table></div>'}

function reportHtml(f){
  var js=fJobs(f.id),sg=stages(f);
  var done=js.filter(function(j){return j.status==='done'}).length;
  var run=js.filter(function(j){return j.status==='running'}).length;
  var fail=js.filter(function(j){return j.status==='failed'}).length;
  var funnel=sg.map(function(s){
    return {name:s.name,v:js.filter(function(j){return stageIndex(f,j)>=stageIndex(f,{stage:s.id})}).length}});
  var h='<div class="pad grid g4" style="padding-bottom:0">'+
    A.tile('Nhiệm vụ',js.length,'<span>'+run+' đang xử lý</span>')+
    A.tile('Hoàn thành',done,'<span>'+A.pct(done,js.length)+'% tổng số</span>','var(--green)')+
    A.tile('Quá hạn',js.filter(isLate).length,'<span>cần xử lý ngay</span>','var(--red)')+
    A.tile('Giai đoạn',sg.length,'<span>'+esc(sg.map(function(s){return s.name}).join(' → ').slice(0,46))+'…</span>')+'</div>'+
    '<div class="pad grid g2">'+
    '<div class="card-box"><h3 style="margin-bottom:12px">Tỉ lệ chuyển đổi qua các giai đoạn</h3>'+A.funnel(funnel)+'</div>'+
    '<div class="card-box"><h3 style="margin-bottom:12px">Báo cáo trạng thái nhiệm vụ</h3>'+
      A.donut([{v:run,c:'var(--blue)',l:'Đang xử lý'},{v:fail,c:'var(--ink-2)',l:'Thất bại'},
               {v:done,c:'var(--green)',l:'Đã hoàn thành'}],js.length,'nhiệm vụ')+'</div>'+
    '<div class="card-box"><h3 style="margin-bottom:12px">Nhiệm vụ theo giai đoạn</h3><div class="tbl-wrap"><table class="t">'+
      '<thead><tr><th>Giai đoạn</th><th class="n">Nhiệm vụ</th><th class="n">Quá hạn</th><th style="width:110px">Tỉ trọng</th></tr></thead><tbody>'+
      sg.map(function(s){
        var a=js.filter(function(j){return j.stage===s.id});
        return '<tr><td>'+esc(s.name)+'</td><td class="n">'+a.length+'</td>'+
          '<td class="n" style="'+(a.filter(isLate).length?'color:var(--red)':'')+'">'+a.filter(isLate).length+'</td>'+
          '<td>'+A.wbar([{v:a.length,c:'var(--app)'}],js.length||1)+'</td></tr>'}).join('')+
      '</tbody></table></div></div>'+
    '<div class="card-box"><h3 style="margin-bottom:12px">Báo cáo theo người thực thi</h3><div class="tbl-wrap"><table class="t">'+
      '<thead><tr><th>Thành viên</th><th class="n">Được giao</th><th class="n">Hoàn thành</th><th class="n">Quá hạn</th></tr></thead><tbody>'+
      (function(){var m={};js.forEach(function(j){var k=j.assignee||'Chưa giao';
        m[k]=m[k]||{n:0,d:0,l:0};m[k].n++;if(j.status==='done')m[k].d++;if(isLate(j))m[k].l++});
        var ks=Object.keys(m);
        return ks.length?ks.map(function(k){
          return '<tr><td><span style="display:flex;gap:8px;align-items:center">'+A.av(k,'s')+esc(k)+'</span></td>'+
            '<td class="n">'+m[k].n+'</td><td class="n">'+m[k].d+'</td>'+
            '<td class="n" style="'+(m[k].l?'color:var(--red)':'')+'">'+m[k].l+'</td></tr>'}).join('')
          :'<tr><td colspan="4" style="color:var(--muted)">Chưa có dữ liệu.</td></tr>'})()+
      '</tbody></table></div></div></div>';
  return h}
function stageIndex(f,j){var s=stages(f);for(var i=0;i<s.length;i++)if(s[i].id===j.stage)return i;return 99}

function actsHtml(f){
  var evs=[];
  fJobs(f.id).forEach(function(j){(j.log||[]).forEach(function(l){evs.push({at:l.at,by:l.by,tx:l.tx,t:j.title})})});
  evs.sort(function(a,b){return new Date(b.at)-new Date(a.at)});
  return '<div class="pad" style="max-width:760px"><div class="card-box"><h3 style="margin-bottom:10px">Hoạt động</h3>'+
    (evs.length?evs.slice(0,60).map(function(e){
      return '<div class="cmt">'+A.av(e.by,'s')+'<div class="b"><div><b style="font-weight:500">'+esc(e.by)+'</b> '+esc(e.tx)+
      ' — <span style="color:var(--app)">'+esc(e.t)+'</span></div><div class="by">'+esc(A.ago(e.at))+'</div></div></div>'}).join('')
      :'<p style="color:var(--muted)">Chưa có hoạt động nào.</p>')+'</div></div>'}

function membersHtml(f){
  var m={};fJobs(f.id).forEach(function(j){if(j.assignee)m[j.assignee]=1});
  (f.members||[]).forEach(function(n){m[n]=1});
  var ks=Object.keys(m);
  return '<div class="pad grid g3">'+(ks.length?ks.map(function(k){
    var a=fJobs(f.id).filter(function(j){return norm(j.assignee)===norm(k)});
    return '<div class="card-box"><div style="display:flex;gap:10px;align-items:center">'+A.av(k,'l')+
      '<div><div style="font-weight:500">'+esc(k)+'</div><div class="by">'+
      esc(A.staffByName(k)?A.staffByName(k).title:'Người thực thi')+'</div></div></div>'+
      '<div class="kpis" style="margin-top:11px">'+
      '<div class="kpi g"><b>'+a.filter(function(j){return j.status==='running'}).length+'</b><span>Đang xử lý</span></div>'+
      '<div class="kpi d"><b>'+a.filter(function(j){return j.status==='done'}).length+'</b><span>Hoàn thành</span></div>'+
      '<div class="kpi l"><b>'+a.filter(isLate).length+'</b><span>Quá hạn</span></div></div></div>'}).join('')
    :'<p class="empty">Chưa có thành viên nào.</p>')+'</div>'}

function editHtml(f){
  return '<div class="pad" style="max-width:640px"><div class="card-box">'+
    '<div class="sec-h"><h3>Các giai đoạn</h3><span class="ds">Kéo thẻ trên bảng để chuyển nhiệm vụ giữa các giai đoạn</span></div>'+
    stages(f).map(function(s,i){
      return '<div class="mini"><span class="by" style="width:18px">'+(i+1)+'.</span>'+
        '<span class="t">'+esc(s.name)+'</span>'+
        '<span class="by">'+fJobs(f.id).filter(function(j){return j.stage===s.id}).length+' nhiệm vụ</span>'+
        '<button class="linkbtn" type="button" data-sedit="'+esc(s.id)+'">Sửa</button></div>'}).join('')+
    '<div style="margin-top:12px;display:flex;gap:8px"><button class="btn" type="button" data-act="newstage">+ Thêm giai đoạn</button>'+
    '<button class="btn ghost" type="button" data-act="editflow">Sửa quy trình</button></div></div></div>'}

/* ---------- hộp thoại ---------- */
function openFlow(id){
  var f=id?flow(id):null;
  A.form({title:f?'Sửa quy trình':'Quy trình mới',
    note:f?'':'Nhập các giai đoạn, mỗi dòng một giai đoạn theo đúng thứ tự chạy.',
    fields:[{k:'name',label:'Tên quy trình',required:true,value:f?f.name:'',ph:'VD: Quy trình mua vật tư'},
      {k:'group',label:'Nhóm',value:f?(f.group||''):'Quan trọng',half:true},
      {k:'color',label:'Màu',type:'color',value:f?(f.color||A.hue(f.name)):A.AVC[A.col('flows').length%A.AVC.length]},
      {k:'members',label:'Thành viên (cách nhau bằng dấu phẩy)',value:f?((f.members||[]).join(', ')):(A.me()||'')}]
      .concat(f?[]:[{k:'stages',label:'Các giai đoạn',type:'textarea',rows:5,
        value:'Thông tin đơn hàng\nLập dự toán\nChọn nhà cung cấp\nTheo dõi đơn hàng\nNhận hàng'}]),
    onDelete:f?function(){fJobs(f.id).forEach(function(j){A.remove('jobs',j.id)});A.remove('flows',f.id);V.fid=null}:null,
    deleteLabel:'Xoá quy trình',confirmDelete:'Xoá quy trình này cùng toàn bộ nhiệm vụ?',
    onSave:function(v){
      var o=f?Object.assign({},f):{id:A.uid(),at:new Date().toISOString()};
      o.name=v.name;o.group=v.group;o.color=v.color;
      o.members=String(v.members||'').split(',').map(function(s){return s.trim()}).filter(Boolean);
      if(!f)o.stages=String(v.stages||'').split('\n').map(function(s){return s.trim()}).filter(Boolean)
        .map(function(n){return {id:A.uid(),name:n}});
      if(!f){V.fid=o.id;V.view='flow'}
      A.save('flows',o)}})}

function openStage(sid){
  var f=cur();if(!f)return;
  var s=sid?stages(f).filter(function(x){return x.id===sid})[0]:null;
  A.form({title:s?'Sửa giai đoạn':'Giai đoạn mới',
    fields:[{k:'name',label:'Tên giai đoạn',required:true,value:s?s.name:''}],
    onDelete:s?function(){
      var rest=stages(f).filter(function(x){return x.id!==sid});
      if(!rest.length){A.toast('Quy trình cần ít nhất một giai đoạn.',true);return}
      fJobs(f.id).filter(function(j){return j.stage===sid}).forEach(function(j){
        var o=Object.assign({},j);o.stage=rest[0].id;A.save('jobs',o)});
      var o2=Object.assign({},f);o2.stages=rest;A.save('flows',o2)}:null,
    deleteLabel:'Xoá giai đoạn',confirmDelete:'Xoá giai đoạn này? Nhiệm vụ sẽ chuyển về giai đoạn đầu.',
    onSave:function(v){
      var o=Object.assign({},f);o.stages=stages(f).slice();
      if(s)o.stages=o.stages.map(function(x){return x.id===sid?{id:x.id,name:v.name}:x});
      else o.stages.push({id:A.uid(),name:v.name});
      A.save('flows',o)}})}

function openJob(id,stageId){
  var f=cur();if(!f)return;
  var j=id?job(id):null;
  A.form({title:j?'Nhiệm vụ':'Nhiệm vụ mới',wide:!!j,
    fields:[{k:'title',label:'Tên nhiệm vụ',required:true,value:j?j.title:''},
      {k:'stage',label:'Giai đoạn',type:'select',value:j?j.stage:(stageId||stages(f)[0].id),
       options:stages(f).map(function(s){return {v:s.id,l:s.name}}),half:true},
      {k:'status',label:'Trạng thái',type:'select',value:j?j.status:'running',half:true,
       options:[{v:'running',l:'Đang xử lý'},{v:'done',l:'Đã hoàn thành'},{v:'failed',l:'Thất bại'}]},
      {k:'assignee',label:'Người thực thi',type:'people',value:j?(j.assignee||''):(A.me()||''),half:true},
      {k:'due',label:'Deadline',type:'datetime-local',value:j?A.toLocal(j.due):'',half:true},
      {k:'desc',label:'Chi tiết',type:'textarea',value:j?(j.desc||''):''}],
    onDelete:j?function(){A.remove('jobs',j.id)}:null,confirmDelete:'Xoá nhiệm vụ này?',
    onSave:function(v){
      var o=j?Object.assign({},j):{id:A.uid(),flowId:f.id,at:new Date().toISOString(),by:A.me()||'ẩn danh',log:[]};
      var was=j?j.status:null,wasStage=j?j.stage:null;
      o.title=v.title;o.stage=v.stage;o.status=v.status;o.assignee=v.assignee;o.desc=v.desc;
      o.due=v.due?new Date(v.due).toISOString():'';
      o.log=(o.log||[]).concat([{by:A.me()||'ẩn danh',at:new Date().toISOString(),
        tx:!j?'tạo nhiệm vụ':(was!==o.status?'đổi trạng thái sang '+(JS_[o.status]||JS_.running).l:
          wasStage!==o.stage?'chuyển giai đoạn':'cập nhật nhiệm vụ')}]).slice(-30);
      A.save('jobs',o)}})}

/* ---------- kéo thả ---------- */
var dragId=null;
function wireDrag(){
  var b=A.$('#flboard');if(!b)return;
  A.$$('.card',b).forEach(function(c){
    c.addEventListener('dragstart',function(e){dragId=c.getAttribute('data-job');c.classList.add('dragging');
      try{e.dataTransfer.effectAllowed='move';e.dataTransfer.setData('text/plain',dragId)}catch(err){}});
    c.addEventListener('dragend',function(){dragId=null;c.classList.remove('dragging');
      A.$$('.col',b).forEach(function(x){x.classList.remove('over')})})});
  A.$$('.col-b',b).forEach(function(box){
    box.addEventListener('dragover',function(e){if(!dragId)return;e.preventDefault();box.parentNode.classList.add('over')});
    box.addEventListener('dragleave',function(e){if(!box.contains(e.relatedTarget))box.parentNode.classList.remove('over')});
    box.addEventListener('drop',function(e){e.preventDefault();box.parentNode.classList.remove('over');
      var j=job(dragId);if(!j)return;
      var s=box.getAttribute('data-s'),o=Object.assign({},j);
      if(s==='__done'){o.status='done';o.log=(o.log||[]).concat([{by:A.me()||'ẩn danh',tx:'đánh dấu hoàn thành',at:new Date().toISOString()}])}
      else{o.stage=s;o.status=o.status==='done'?'running':o.status;
        o.log=(o.log||[]).concat([{by:A.me()||'ẩn danh',tx:'chuyển sang giai đoạn mới',at:new Date().toISOString()}])}
      A.save('jobs',o)})})}

function after(){
  var root=A.$('#appRoot');
  root.addEventListener('click',function(e){
    var el=e.target.closest('[data-v],[data-flow],[data-tab],[data-jtab],[data-act],[data-sedit],[data-jopen],[data-jdone],[data-job]');
    if(!el)return;var g;
    if((g=el.getAttribute('data-v'))){V.view=g;A.render();return}
    if((g=el.getAttribute('data-flow'))){V.fid=g;V.view='flow';V.tab='board';A.render();return}
    if((g=el.getAttribute('data-tab'))){V.tab=g;A.render();return}
    if((g=el.getAttribute('data-jtab'))){V.jtab=g;A.render();return}
    if((g=el.getAttribute('data-sedit'))){openStage(g);return}
    if((g=el.getAttribute('data-jdone'))){var j=job(g);if(j){var o=Object.assign({},j);
      o.status=o.status==='done'?'running':'done';
      o.log=(o.log||[]).concat([{by:A.me()||'ẩn danh',tx:o.status==='done'?'đánh dấu hoàn thành':'mở lại nhiệm vụ',at:new Date().toISOString()}]);
      A.save('jobs',o)}return}
    if((g=el.getAttribute('data-jopen'))){openJob(g);return}
    if((g=el.getAttribute('data-act'))){
      if(g==='newflow')openFlow(null);
      else if(g==='editflow')openFlow(V.fid);
      else if(g==='newstage')openStage(null);
      else if(g==='newjob')openJob(null);
      return}
    if((g=el.getAttribute('data-job'))){openJob(g);return}});
  var s=A.$('#flSearch');if(s)s.addEventListener('input',function(){V.q=this.value;A.render()});
  var q=A.$('#flQ');if(q)q.addEventListener('input',function(){V.q=this.value;A.render()});
  if(V.view==='flow'&&V.tab==='board')wireDrag()}

A.register({id:'workflow',name:'Quy trình',desc:'Workflow nhiều giai đoạn',cat:'work',color:'#6C63E0',icon:'flow',
  side:true,info:false,view:view,after:after,
  badge:function(){return A.col('jobs').filter(function(j){return A.isMe(j.assignee)&&isLate(j)}).length}});
})();
