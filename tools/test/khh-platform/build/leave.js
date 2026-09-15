/* Timeoff — Nghỉ phép */
(function(){
'use strict';
var A=window.APP,esc=A.esc,norm=A.norm;
var V={view:'all',q:''};
var TP={annual:'Nghỉ phép năm',unpaid:'Nghỉ không lương',sick:'Nghỉ ốm',other:'Nghỉ việc riêng'};
var ST={pending:{l:'Chờ duyệt',c:'amb'},approved:{l:'Đã duyệt',c:'done'},rejected:{l:'Từ chối',c:'late'}};

function offs(){return A.col('timeoffs').slice().sort(function(a,b){return new Date(b.at)-new Date(a.at)})}
function one(id){return A.find('timeoffs',id)}
function list(){
  return offs().filter(function(t){
    if(V.view==='mine')return A.isMe(t.staff);
    if(V.view==='pending')return t.status==='pending';
    if(V.view==='approve')return t.status==='pending'&&A.isMe(t.approver);
    return true})}

function side(){
  var nav=[['all','Tất cả đơn','timeoff'],['pending','Chờ duyệt','request'],
           ['approve','Chờ tôi duyệt','checkin'],['mine','Đơn của tôi','hrm'],['balance','Số ngày phép','timesheet']];
  return '<aside class="side">'+A.sideUser()+
    '<div style="padding:0 10px 10px"><button class="btn" type="button" data-act="new" style="width:100%">+ Tạo đơn nghỉ</button></div>'+
    '<div class="side-list"><div class="grp"><div class="grp-b">'+
    nav.map(function(n){
      var c=n[0]==='pending'?offs().filter(function(t){return t.status==='pending'}).length:
            n[0]==='approve'?offs().filter(function(t){return t.status==='pending'&&A.isMe(t.approver)}).length:
            n[0]==='mine'?offs().filter(function(t){return A.isMe(t.staff)}).length:
            n[0]==='all'?offs().length:0;
      return '<button class="pitem'+(V.view===n[0]?' on':'')+'" type="button" data-v="'+n[0]+'">'+
        A.icon(n[2],'ic')+'<span class="t">'+esc(n[1])+'</span>'+(c?'<span class="n">'+c+'</span>':'')+'</button>'}).join('')+
    '</div></div></div></aside>'}

function view(){
  var body=V.view==='balance'?balanceHtml():listHtml();
  return side()+'<section class="stage"><header class="topbar">'+A.navBtn()+
    '<h1>'+esc(V.view==='balance'?'Số ngày phép':'Đơn xin nghỉ')+'</h1><span class="spacer"></span>'+
    '<span class="qsearch"><input id="toQ" type="search" placeholder="Tìm theo tên" value="'+esc(V.q)+'" aria-label="Tìm đơn nghỉ"></span>'+
    '<button class="btn" type="button" data-act="new">+ Tạo đơn nghỉ</button></header><nav class="tabs"></nav>'+
    '<div class="content" id="content">'+body+'</div></section>'}

function listHtml(){
  var q=norm(V.q),ls=list().filter(function(t){return !q||norm(t.staff).indexOf(q)>=0});
  var h='<div class="pad grid g4" style="padding-bottom:0">'+
    A.tile('Tổng đơn',offs().length,'<span>trong hệ thống</span>')+
    A.tile('Chờ duyệt',offs().filter(function(t){return t.status==='pending'}).length,'<span>cần xử lý</span>','var(--amber)')+
    A.tile('Đã duyệt',offs().filter(function(t){return t.status==='approved'}).length,'<span>tính vào bảng công</span>','var(--green)')+
    A.tile('Tổng ngày nghỉ',offs().filter(function(t){return t.status==='approved'})
      .reduce(function(n,t){return n+(+t.days||0)},0),'<span>đã duyệt</span>')+
    '</div><div class="pad"><div class="tbl-wrap"><table class="t"><thead><tr>'+
    '<th>Nhân sự</th><th>Loại nghỉ</th><th>Thời gian</th><th class="n">Số ngày</th><th>Lý do</th>'+
    '<th>Người duyệt</th><th>Trạng thái</th><th></th></tr></thead><tbody>';
  ls.forEach(function(t){
    var st=ST[t.status]||ST.pending;
    h+='<tr><td><span style="display:flex;gap:8px;align-items:center">'+A.av(t.staff,'s')+
      '<b style="font-weight:500">'+esc(t.staff)+'</b></span></td>'+
      '<td><span class="chip info">'+esc(TP[t.type]||t.type)+'</span></td>'+
      '<td class="sub num">'+esc(A.fmtD(t.from))+' – '+esc(A.fmtD(t.to))+'</td>'+
      '<td class="n">'+esc(t.days)+'</td>'+
      '<td class="sub">'+esc(t.reason||'')+'</td>'+
      '<td class="sub">'+esc(t.approver||'—')+'</td>'+
      '<td><span class="chip '+st.c+'">'+st.l+'</span></td>'+
      '<td class="n" style="white-space:nowrap">'+
        (t.status==='pending'?'<button class="btn sm green" type="button" data-ok="'+t.id+'">Duyệt</button> '+
          '<button class="btn sm ghost" type="button" data-no="'+t.id+'">Từ chối</button> ':'')+
        '<button class="linkbtn" type="button" data-edit="'+t.id+'">Sửa</button></td></tr>'});
  if(!ls.length)h+='<tr><td colspan="8" style="padding:20px;color:var(--muted)">Không có đơn nghỉ nào.</td></tr>';
  return h+'</tbody></table></div></div>'}

function balanceHtml(){
  var ss=A.col('staff').filter(function(s){return s.status!=='left'});
  var h='<div class="pad"><div class="tbl-wrap"><table class="t"><thead><tr><th>Nhân sự</th><th>Bộ phận</th>'+
    '<th class="n">Phép năm</th><th class="n">Đã nghỉ</th><th class="n">Còn lại</th><th style="width:120px"></th></tr></thead><tbody>';
  ss.forEach(function(s){
    var used=offs().filter(function(t){return norm(t.staff)===norm(s.name)&&t.status==='approved'&&t.type==='annual'})
      .reduce(function(n,t){return n+(+t.days||0)},0);
    var total=s.leaveYear==null?12:s.leaveYear;
    h+='<tr><td><span style="display:flex;gap:8px;align-items:center">'+A.av(s.name,'s',s.color)+esc(s.name)+'</span></td>'+
      '<td class="sub">'+esc(s.dept||'—')+'</td><td class="n">'+total+'</td><td class="n">'+used+'</td>'+
      '<td class="n"><b style="font-weight:500;color:'+(total-used<=0?'var(--red)':'var(--green)')+'">'+(total-used)+'</b></td>'+
      '<td>'+A.wbar([{v:used,c:'var(--amber)'},{v:Math.max(0,total-used),c:'var(--green)'}],total||1)+'</td></tr>'});
  if(!ss.length)h+='<tr><td colspan="6" style="padding:20px;color:var(--muted)">Chưa có nhân sự nào.</td></tr>';
  return h+'</tbody></table></div></div>'}

function openOff(id){
  var t=id?one(id):null,ss=A.col('staff');
  A.form({title:t?'Sửa đơn nghỉ':'Tạo đơn xin nghỉ',
    fields:[
      {k:'staff',label:'Người nghỉ',type:'people',required:true,value:t?t.staff:(A.me()||''),half:true},
      {k:'type',label:'Loại nghỉ',type:'select',value:t?t.type:'annual',half:true,
       options:Object.keys(TP).map(function(k){return {v:k,l:TP[k]}})},
      {k:'from',label:'Từ ngày',type:'date',required:true,value:t?t.from:'',half:true},
      {k:'to',label:'Đến ngày',type:'date',required:true,value:t?t.to:'',half:true},
      {k:'approver',label:'Người duyệt',type:'people',value:t?(t.approver||''):'',half:true},
      {k:'status',label:'Trạng thái',type:'select',value:t?t.status:'pending',half:true,
       options:Object.keys(ST).map(function(k){return {v:k,l:ST[k].l}})},
      {k:'reason',label:'Lý do',type:'textarea',value:t?(t.reason||''):''}],
    onDelete:t?function(){A.remove('timeoffs',t.id)}:null,confirmDelete:'Xoá đơn nghỉ này?',
    onSave:function(v){
      var d=Math.max(1,A.daysBetween(v.from,v.to)+1);
      var o=t?Object.assign({},t):{id:A.uid(),at:new Date().toISOString(),by:A.me()||'ẩn danh'};
      o.staff=v.staff;o.type=v.type;o.from=v.from;o.to=v.to;o.approver=v.approver;
      o.status=v.status;o.reason=v.reason;o.days=d;
      A.save('timeoffs',o).then(function(){if(o.status==='approved')markDays(o)})}})}

/* ghi ngày nghỉ vào bảng công khi đơn được duyệt */
function markDays(t){
  var s=A.staffByName(t.staff);if(!s||!t.from)return;
  var cur=new Date(t.from),end=new Date(t.to||t.from);
  while(cur<=end){
    var cyc=cur.getFullYear()+'-'+A.pad(cur.getMonth()+1),d=A.pad(cur.getDate());
    var id=s.id+'_'+cyc.replace('-',''),a=A.find('attendance',id);
    var o=a?Object.assign({},a):{id:id,staffId:s.id,cycle:cyc,days:{}};
    o.days=Object.assign({},o.days);
    o.days[d]=Object.assign({},o.days[d],{leave:t.type==='annual'?'annual':'unpaid',late:0,note:'Nghỉ theo đơn'});
    A.save('attendance',o);
    cur.setDate(cur.getDate()+1)}}

function after(){
  A.$('#appRoot').addEventListener('click',function(e){
    var el=e.target.closest('[data-v],[data-act],[data-ok],[data-no],[data-edit]');
    if(!el)return;var g;
    if((g=el.getAttribute('data-v'))){V.view=g;A.render();return}
    if((g=el.getAttribute('data-act'))){if(g==='new')openOff(null);return}
    if((g=el.getAttribute('data-edit'))){openOff(g);return}
    if((g=el.getAttribute('data-ok'))){
      var t=one(g);
      if(!A.needRoleFor('timeoff.approve',t&&t.staff,'Chỉ Quản lý trở lên mới duyệt được đơn nghỉ.'))return;
      if(t){var o=Object.assign({},t);
      o.status='approved';o.approver=o.approver||A.me()||'';
      A.save('timeoffs',o).then(function(){markDays(o);A.toast('Đã duyệt đơn nghỉ và ghi vào bảng công.')})}return}
    if((g=el.getAttribute('data-no'))){
      var t2=one(g);
      if(!A.needRoleFor('timeoff.approve',t2&&t2.staff,'Chỉ Quản lý trở lên mới từ chối được đơn nghỉ.'))return;
      if(t2){var o2=Object.assign({},t2);
      o2.status='rejected';A.save('timeoffs',o2)}return}});
  var q=A.$('#toQ');if(q)q.addEventListener('input',function(){V.q=this.value;A.render()})}

A.register({id:'timeoff',name:'Nghỉ phép',desc:'Đơn nghỉ & ngày phép',cat:'hrm',color:'#E0912B',icon:'timeoff',
  side:true,info:false,view:view,after:after,
  badge:function(){return A.col('timeoffs').filter(function(t){return t.status==='pending'&&A.isMe(t.approver)}).length}});
})();
