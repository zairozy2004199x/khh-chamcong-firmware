/* Booking — Đặt phòng, xe và thiết bị dùng chung */
(function(){
'use strict';
var A=window.APP,esc=A.esc,norm=A.norm;
var V={view:'day',date:A.ymd(new Date()),kind:''};
var KIND={room:'Phòng',vehicle:'Xe',device:'Thiết bị',other:'Khác'};

function res(){return A.col('resources').slice().sort(function(a,b){return String(a.kind).localeCompare(String(b.kind))})}
function bks(){return A.col('bookings').slice().sort(function(a,b){return new Date(a.start)-new Date(b.start)})}
function sameDay(a,b){return A.ymd(a)===A.ymd(b)}
function dayBks(d){return bks().filter(function(b){return sameDay(b.start,d)})}
function clash(o){
  return bks().filter(function(x){
    return x.id!==o.id&&x.resId===o.resId&&
      new Date(x.start)<new Date(o.end)&&new Date(x.end)>new Date(o.start)})}

function side(){
  var nav=[['day','Lịch theo ngày','booking'],['res','Tài nguyên','flow'],['mine','Lượt đặt của tôi','hrm']];
  return '<aside class="side">'+A.sideUser()+
    '<div style="padding:0 10px 10px"><button class="btn" type="button" data-act="new" style="width:100%">+ Đặt tài nguyên</button></div>'+
    '<div class="side-list"><div class="grp"><div class="grp-b">'+
    nav.map(function(n){
      return '<button class="pitem'+(V.view===n[0]?' on':'')+'" type="button" data-v="'+n[0]+'">'+
        A.icon(n[2],'ic')+'<span class="t">'+esc(n[1])+'</span></button>'}).join('')+
    '</div></div><div class="grp"><div class="grp-h"><span>Nhóm tài nguyên</span></div><div class="grp-b">'+
    Object.keys(KIND).map(function(k){
      var n=res().filter(function(r){return (r.kind||'other')===k}).length;
      if(!n)return '';
      return '<button class="pitem'+(V.kind===k?' on':'')+'" type="button" data-kind="'+k+'">'+
        '<span class="t">'+esc(KIND[k])+'</span><span class="n">'+n+'</span></button>'}).join('')+
    '</div></div></div><button class="side-add" type="button" data-act="newres">+ Thêm tài nguyên</button></aside>'}

function view(){
  var body=V.view==='res'?resHtml():V.view==='mine'?mineHtml():dayHtml();
  return side()+'<section class="stage"><header class="topbar">'+A.navBtn()+
    '<h1>'+esc(V.view==='res'?'Tài nguyên dùng chung':V.view==='mine'?'Lượt đặt của tôi':'Lịch đặt theo ngày')+'</h1>'+
    '<span class="spacer"></span>'+
    (V.view==='day'?'<input type="date" id="bkDate" value="'+esc(V.date)+'" style="border:1px solid var(--line);border-radius:3px;padding:5px 8px;background:var(--panel-2)" aria-label="Chọn ngày">':'')+
    '<button class="btn" type="button" data-act="new">+ Đặt tài nguyên</button></header><nav class="tabs"></nav>'+
    '<div class="content" id="content">'+body+'</div></section>'}

function dayHtml(){
  var d=new Date(V.date),list=res().filter(function(r){return !V.kind||(r.kind||'other')===V.kind});
  var all=dayBks(d);
  var h='<div class="pad grid g4" style="padding-bottom:0">'+
    A.tile('Lượt đặt hôm nay',all.length,'<span>'+esc(A.fmtD(V.date))+'</span>')+
    A.tile('Tài nguyên',res().length,'<span>đang khai báo</span>')+
    A.tile('Của bạn',all.filter(function(b){return A.isMe(b.by)}).length,'<span>trong ngày</span>')+
    A.tile('Đang rảnh',list.filter(function(r){return !all.some(function(b){return b.resId===r.id})}).length,'<span>chưa ai đặt</span>')+
    '</div><div class="pad">';
  if(!list.length)return h+'<p class="empty">Chưa khai tài nguyên nào. Thêm phòng họp, xe công ty, máy chiếu… để đặt lịch.</p></div>';
  list.forEach(function(r){
    var use=all.filter(function(b){return b.resId===r.id});
    h+='<div class="card-box" style="margin-bottom:10px">'+
      '<div style="display:flex;align-items:center;gap:10px;margin-bottom:8px">'+A.av(r.name,'',r.color)+
      '<div style="flex:1"><b style="font-weight:500">'+esc(r.name)+'</b>'+
      '<span class="by" style="display:block">'+esc(KIND[r.kind||'other'])+(r.place?' · '+esc(r.place):'')+
      (r.seats?' · '+r.seats+' chỗ':'')+'</span></div>'+
      '<button class="btn sm ghost" type="button" data-book="'+r.id+'">Đặt</button></div>'+
      (use.length?use.map(function(b){
        return '<div class="mini"><span class="num" style="min-width:98px">'+esc(A.fmtT(b.start))+' – '+esc(A.fmtT(b.end))+'</span>'+
          '<button class="t" type="button" data-open="'+b.id+'">'+esc(b.title)+'</button>'+
          A.av(b.by,'s')+'<span class="by">'+esc(b.by)+'</span></div>'}).join('')
        :'<p class="by">Trống cả ngày.</p>')+'</div>'});
  return h+'</div>'}

function mineHtml(){
  var list=bks().filter(function(b){return A.isMe(b.by)});
  if(!A.me())return '<div class="empty"><h3>Chưa biết bạn là ai</h3><p>Bấm ô tên ở góc trên bên trái.</p></div>';
  if(!list.length)return '<div class="empty"><h3>Bạn chưa đặt tài nguyên nào</h3></div>';
  return '<div class="pad"><div class="tbl-wrap"><table class="t"><thead><tr><th>Nội dung</th><th>Tài nguyên</th>'+
    '<th>Thời gian</th><th></th></tr></thead><tbody>'+list.map(function(b){
      var r=A.find('resources',b.resId);
      return '<tr><td><b style="font-weight:500">'+esc(b.title)+'</b>'+
        (b.note?'<span class="sub" style="display:block">'+esc(b.note)+'</span>':'')+'</td>'+
        '<td>'+esc(r?r.name:'—')+'</td>'+
        '<td class="sub num">'+esc(A.fmtDT(b.start))+' → '+esc(A.fmtT(b.end))+'</td>'+
        '<td class="n"><button class="linkbtn" type="button" data-open="'+b.id+'">Sửa</button></td></tr>'}).join('')+
    '</tbody></table></div></div>'}

function resHtml(){
  var list=res().filter(function(r){return !V.kind||(r.kind||'other')===V.kind});
  return '<div class="pad grid g3">'+(list.length?list.map(function(r){
    var n=bks().filter(function(b){return b.resId===r.id}).length;
    return '<div class="card-box"><div style="display:flex;gap:10px;align-items:center;margin-bottom:8px">'+
      A.av(r.name,'l',r.color)+'<div><div style="font-weight:500">'+esc(r.name)+'</div>'+
      '<div class="by">'+esc(KIND[r.kind||'other'])+'</div></div></div>'+
      '<div class="metarow"><span class="k">Vị trí</span><span class="v">'+esc(r.place||'—')+'</span></div>'+
      (r.seats?'<div class="metarow"><span class="k">Sức chứa</span><span class="v num">'+r.seats+' chỗ</span></div>':'')+
      '<div class="metarow"><span class="k">Lượt đã đặt</span><b class="v num">'+n+'</b></div>'+
      '<div style="margin-top:10px;display:flex;gap:8px">'+
      '<button class="btn sm" type="button" data-book="'+r.id+'">Đặt lịch</button>'+
      '<button class="btn sm ghost" type="button" data-editres="'+r.id+'">Sửa</button></div></div>'}).join('')
    :'<p class="empty">Chưa có tài nguyên nào.</p>')+'</div>'}

function openBk(id,resId){
  var b=id?A.find('bookings',id):null,rs=res();
  if(!rs.length){A.toast('Khai tài nguyên trước đã.',true);return openRes(null)}
  A.form({title:b?'Lượt đặt':'Đặt tài nguyên',
    fields:[{k:'title',label:'Nội dung sử dụng',required:true,value:b?b.title:'',ph:'VD: Chở thiết bị đi cơ sở Tân Bình'},
      {k:'resId',label:'Tài nguyên',type:'select',value:b?b.resId:(resId||rs[0].id),
       options:rs.map(function(r){return {v:r.id,l:r.name+' ('+KIND[r.kind||'other']+')'}})},
      {k:'start',label:'Bắt đầu',type:'datetime-local',required:true,value:b?A.toLocal(b.start):A.toLocal(new Date()),half:true},
      {k:'end',label:'Kết thúc',type:'datetime-local',required:true,value:b?A.toLocal(b.end):'',half:true},
      {k:'by',label:'Người đặt',type:'people',value:b?b.by:(A.me()||''),half:true},
      {k:'note',label:'Ghi chú',value:b?(b.note||''):'',half:true}],
    onDelete:b?function(){A.remove('bookings',b.id)}:null,confirmDelete:'Huỷ lượt đặt này?',
    deleteLabel:'Huỷ đặt',
    onSave:function(v){
      var o=b?Object.assign({},b):{id:A.uid(),at:new Date().toISOString()};
      o.title=v.title;o.resId=v.resId;o.by=v.by;o.note=v.note;
      o.start=new Date(v.start).toISOString();
      o.end=v.end?new Date(v.end).toISOString():o.start;
      var c=clash(o);
      if(c.length){
        A.toast('Trùng lịch với “'+c[0].title+'” ('+A.fmtT(c[0].start)+'–'+A.fmtT(c[0].end)+'). Chọn giờ khác giúp mình.',true);
        return false}
      A.save('bookings',o)}})}

function openRes(id){
  if(!A.needRole('booking.manage','Chỉ Quản lý trở lên mới khai được tài nguyên.'))return;
  var r=id?A.find('resources',id):null;
  A.form({title:r?'Sửa tài nguyên':'Thêm tài nguyên',
    fields:[{k:'name',label:'Tên tài nguyên',required:true,value:r?r.name:'',ph:'VD: Xe tải 1.5 tấn 51C-123.45'},
      {k:'kind',label:'Nhóm',type:'select',value:r?(r.kind||'room'):'room',half:true,
       options:Object.keys(KIND).map(function(k){return {v:k,l:KIND[k]}})},
      {k:'place',label:'Vị trí',value:r?(r.place||''):'',half:true},
      {k:'seats',label:'Sức chứa',type:'number',value:r?(r.seats||''):'',half:true},
      {k:'color',label:'Màu',type:'color',value:r?(r.color||A.hue(r.name)):A.AVC[A.col('resources').length%A.AVC.length]}],
    onDelete:r?function(){
      A.col('bookings').filter(function(b){return b.resId===r.id}).forEach(function(b){A.remove('bookings',b.id)});
      A.remove('resources',r.id)}:null,
    deleteLabel:'Xoá tài nguyên',confirmDelete:'Xoá tài nguyên này cùng các lượt đặt?',
    onSave:function(v){
      var o=r?Object.assign({},r):{id:A.uid()};
      o.name=v.name;o.kind=v.kind;o.place=v.place;o.seats=v.seats;o.color=v.color;
      A.save('resources',o)}})}

function after(){
  A.$('#appRoot').addEventListener('click',function(e){
    var el=e.target.closest('[data-v],[data-kind],[data-act],[data-book],[data-open],[data-editres]');
    if(!el)return;var g;
    if((g=el.getAttribute('data-v'))){V.view=g;A.render();return}
    if((g=el.getAttribute('data-kind'))){V.kind=V.kind===g?'':g;A.render();return}
    if((g=el.getAttribute('data-book'))){openBk(null,g);return}
    if((g=el.getAttribute('data-open'))){openBk(g);return}
    if((g=el.getAttribute('data-editres'))){openRes(g);return}
    if((g=el.getAttribute('data-act'))){
      if(g==='new')openBk(null);else if(g==='newres')openRes(null);return}});
  var d=A.$('#bkDate');if(d)d.addEventListener('change',function(){V.date=this.value;A.render()})}

A.register({id:'booking',name:'Đặt tài nguyên',desc:'Phòng, xe và thiết bị',cat:'info',color:'#E0733D',icon:'booking',
  side:true,info:false,view:view,after:after});
})();
