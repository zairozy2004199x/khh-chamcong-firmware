/* Request — Yêu cầu & Đề xuất */
(function(){
'use strict';
var A=window.APP,esc=A.esc,norm=A.norm;
var V={tab:'all',typeId:null,sel:null,q:'',mine:{to:true,from:true,watch:true}};
var TABS={all:'Tất cả',turn:'Đến lượt duyệt',overdue:'Quá hạn duyệt',pending:'Đang chờ duyệt',
          approved:'Đã chấp thuận',rejected:'Đã từ chối',draft:'Đã lưu nháp'};
var ST={draft:{l:'Lưu nháp',c:'todo'},pending:{l:'Đang chờ duyệt',c:'info'},
        approved:{l:'Đã chấp thuận',c:'done'},rejected:{l:'Đã từ chối',c:'late'}};
var FT=[{v:'text',l:'Một dòng'},{v:'textarea',l:'Nhiều dòng'},{v:'number',l:'Con số'},{v:'date',l:'Ngày'}];

function types(){return A.col('reqtypes')}
function type(id){return A.find('reqtypes',id)}
function reqs(){return A.col('requests').slice().sort(function(a,b){return new Date(b.at)-new Date(a.at)})}
function req(id){return A.find('requests',id)}
function myTurn(r){return r.status==='pending'&&(r.approvers||[]).some(function(a){return A.isMe(a.name)&&a.state==='pending'})}
function isOverdue(r){return r.status==='pending'&&r.deadline&&new Date(r.deadline).getTime()<Date.now()}
function visible(){
  var q=norm(V.q);
  return reqs().filter(function(r){
    if(V.typeId&&r.typeId!==V.typeId)return false;
    var rel=(V.mine.to&&(r.approvers||[]).some(function(a){return A.isMe(a.name)}))||
            (V.mine.from&&A.isMe(r.by))||(V.mine.watch&&(r.watchers||[]).some(function(n){return A.isMe(n)}));
    if(A.me()&&!(V.mine.to&&V.mine.from&&V.mine.watch)&&!rel)return false;
    if(V.tab==='turn')return myTurn(r);
    if(V.tab==='overdue')return isOverdue(r);
    if(V.tab!=='all'&&r.status!==V.tab)return false;
    return true}).filter(function(r){
    return !q||norm(r.title+' '+(r.by||'')).indexOf(q)>=0})}

function side(){
  var groups={},order=[];
  types().forEach(function(t){var g=t.group||'Đề xuất';if(!groups[g]){groups[g]=[];order.push(g)}groups[g].push(t)});
  var h='<aside class="side">'+A.sideUser()+
    '<div style="padding:0 10px 10px"><button class="btn" type="button" data-act="new" style="width:100%">+ Tạo đề xuất mới</button></div>'+
    '<div style="padding:0 12px 10px;display:grid;gap:6px;font-size:12px">'+
    [['to','Gửi đến tôi'],['from','Tôi gửi đi'],['watch','Đang theo dõi']].map(function(k){
      return '<label style="display:flex;gap:8px;align-items:center;cursor:pointer">'+
        '<input type="checkbox" data-mine="'+k[0]+'"'+(V.mine[k[0]]?' checked':'')+'> '+esc(k[1])+'</label>'}).join('')+
    '</div><div class="side-list">'+
    '<div class="grp"><div class="grp-b"><button class="pitem'+(V.typeId?'':' on')+'" type="button" data-type="">'+
    A.icon('request','ic')+'<span class="t">Tất cả đề xuất</span><span class="n">'+A.col('requests').length+'</span></button></div></div>';
  order.forEach(function(g){
    h+='<div class="grp"><div class="grp-h"><span>'+esc(g)+'</span></div><div class="grp-b">'+
      groups[g].map(function(t){
        var n=A.col('requests').filter(function(r){return r.typeId===t.id}).length;
        return '<button class="pitem'+(V.typeId===t.id?' on':'')+'" type="button" data-type="'+t.id+'">'+
          '<span style="color:var(--amber)">★</span><span class="t">'+esc(t.name)+'</span><span class="n">'+n+'</span></button>'}).join('')+
      '</div></div>'});
  return h+'</div><button class="side-add" type="button" data-act="newtype">+ Mẫu đề xuất mới</button></aside>'}

function view(){
  var list=visible();
  if(!V.sel||!req(V.sel)||list.indexOf(req(V.sel))<0)V.sel=list.length?list[0].id:null;
  var h='<section class="stage"><header class="topbar">'+A.navBtn()+
    '<h1>Danh sách đề xuất</h1><span class="spacer"></span>'+
    '<span class="qsearch"><input id="rqQ" type="search" placeholder="Tìm nhanh yêu cầu · đề xuất" value="'+esc(V.q)+'" aria-label="Tìm đề xuất"></span>'+
    '</header><nav class="tabs">'+Object.keys(TABS).map(function(k){
      var n=k==='all'?null:reqs().filter(function(r){
        return k==='turn'?myTurn(r):k==='overdue'?isOverdue(r):r.status===k}).length;
      return '<button class="tab'+(V.tab===k?' on':'')+'" type="button" data-tab="'+k+'">'+esc(TABS[k])+(n?' ('+n+')':'')+'</button>'}).join('')+
    '</nav><div class="content" id="content"><div class="split">'+
    '<div class="lst">'+(list.length?list.map(rowHtml).join(''):'<p class="empty">Không có đề xuất nào ở mục này.</p>')+'</div>'+
    '<div class="det">'+detailHtml(req(V.sel))+'</div></div></div></section>';
  return side()+h}

function rowHtml(r){
  var t=type(r.typeId),st=ST[r.status]||ST.draft,ov=isOverdue(r);
  return '<button class="lrow'+(r.id===V.sel?' on':'')+'" type="button" data-sel="'+r.id+'">'+
    '<span class="t"><span class="ck'+(r.status==='approved'?' on':'')+'" style="pointer-events:none">'+
      (r.status==='approved'?'<svg viewBox="0 0 10 10" fill="none" stroke="#fff" stroke-width="2" style="opacity:1"><path d="M1.6 5.2 4 7.5 8.4 2.6"/></svg>':'')+'</span>'+
    esc(r.title)+'</span>'+
    '<span class="m">'+esc(t?t.name:'Đề xuất')+' · Người đề xuất: '+esc(r.by||'—')+
      (r.vals&&r.vals.detail?' · '+esc(String(r.vals.detail).slice(0,60)):'')+'</span>'+
    '<span style="display:flex;gap:6px;align-items:center;margin-top:6px">'+
      '<span class="chip '+st.c+'">'+st.l+'</span>'+(ov?'<span class="chip late">Quá hạn duyệt</span>':'')+
      (myTurn(r)?'<span class="chip warn">Chờ bạn</span>':'')+
      '<span class="spacer"></span><span class="by">'+esc(A.fmtDM(r.at))+'</span></span>'+
    '</button>'}

function detailHtml(r){
  if(!r)return '<p class="empty">Chọn một đề xuất ở danh sách bên trái để xem chi tiết.</p>';
  var t=type(r.typeId),st=ST[r.status]||ST.draft;
  var left=r.deadline?Math.round((new Date(r.deadline).getTime()-Date.now())/36e5):null;
  var h='<div class="pad" style="display:grid;grid-template-columns:minmax(0,1fr) 250px;gap:18px">';
  h+='<div><h2 style="font-size:17px">'+esc(r.title)+'</h2>'+
    '<p class="by" style="margin:4px 0 8px">'+(r.deadline?'Thời hạn xử lý: '+esc(A.fmtDT(r.deadline))+
      ' · '+(left>0?'còn '+left+' giờ':'đã quá hạn '+Math.abs(left)+' giờ'):'Không đặt thời hạn xử lý')+'</p>'+
    '<p style="margin-bottom:12px">Trạng thái: <span class="chip '+st.c+'">'+st.l+'</span></p>';
  if(r.status==='pending'&&myTurn(r))
    h+='<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px">'+
      '<button class="btn green lg" type="button" data-x="approve">Chấp thuận</button>'+
      '<button class="btn teal lg" type="button" data-x="forward">Chuyển tiếp</button>'+
      '<button class="btn red lg" type="button" data-x="reject">Từ chối</button></div>';
  else if(r.status==='draft')
    h+='<div style="display:flex;gap:8px;margin-bottom:16px"><button class="btn lg" type="button" data-x="submit">Gửi đề xuất</button>'+
      '<button class="btn ghost lg" type="button" data-x="edit">Sửa nháp</button></div>';
  h+='<div class="sec"><div class="sec-h"><h3 style="font-size:12px;letter-spacing:.06em;text-transform:uppercase;color:var(--muted)">Thông tin đề xuất</h3>'+
    '<span class="spacer"></span><span class="by">MÃ: '+esc(r.id.slice(-6).toUpperCase())+'</span></div><div class="kv">'+
    kv('Người tạo',esc(r.by||'—'))+kv('Nhóm đề xuất',esc(t?t.name:'—'))+
    kv('Thời gian tạo',esc(A.fmtDT(r.at)))+kv('Cập nhật gần nhất',esc(A.ago(r.upd||r.at)))+'</div></div>';
  var fs=(t&&t.fields)||[];
  h+='<div class="sec"><div class="sec-h"><h3 style="font-size:12px;letter-spacing:.06em;text-transform:uppercase;color:var(--muted)">Thông tin khác (mẫu đăng ký đề xuất)</h3></div>';
  h+=fs.length?fs.map(function(f,i){
    var v=(r.vals||{})[f.k];
    return '<div style="display:flex;gap:12px;padding:8px 0;border-top:1px solid var(--line)">'+
      '<span class="by" style="width:24px">'+A.pad(i+1)+'</span>'+
      '<span style="flex:1"><span class="by" style="display:block">'+esc(f.label)+'</span>'+
      '<b style="font-weight:500">'+esc(f.type==='number'&&v!==''&&v!=null?Number(v).toLocaleString('vi-VN'):(v||'—'))+'</b></span></div>'}).join('')
    :'<p class="by">Mẫu này chưa có trường thông tin nào.</p>';
  h+='</div>';
  h+='<div class="sec"><div class="sec-h"><h3 style="font-size:12px;letter-spacing:.06em;text-transform:uppercase;color:var(--muted)">Hoạt động chính</h3></div>'+
    ((r.log||[]).slice().reverse().map(function(l){
      return '<div class="tline"><span class="tm">'+esc(A.fmtDT(l.at))+'</span><span><b style="font-weight:500">'+esc(l.by)+'</b> '+esc(l.tx)+'</span></div>'}).join('')
      ||'<p class="by">Chưa có hoạt động.</p>')+'</div>';
  h+='</div><div>'+
    '<p class="sect-t" style="color:var(--green)">Người xét duyệt</p>'+
    ((r.approvers||[]).map(function(a){
      var col=a.state==='approved'?'var(--green)':a.state==='rejected'?'var(--red)':'var(--line-2)';
      return '<div class="mini">'+A.av(a.name,'')+'<span class="t">'+esc(a.name)+
        '<span class="by" style="display:block">'+esc(a.role||(A.staffByName(a.name)?A.staffByName(a.name).title:'Người duyệt'))+'</span></span>'+
        '<span class="dot" style="background:'+col+';width:10px;height:10px"></span></div>'}).join('')
      ||'<p class="by">Chưa có người xét duyệt.</p>')+
    '<p class="sect-t" style="color:var(--green);margin-top:16px">Người theo dõi</p>'+
    ((r.watchers||[]).map(function(n){return '<div class="mini">'+A.av(n,'s')+'<span class="t">'+esc(n)+'</span></div>'}).join('')
      ||'<p class="by">Chưa có ai theo dõi.</p>')+
    '<div style="margin-top:14px;display:grid;gap:8px">'+
    '<button class="btn ghost sm" type="button" data-x="watch">'+((r.watchers||[]).some(function(n){return A.isMe(n)})?'Bỏ theo dõi':'Theo dõi đề xuất')+'</button>'+
    '<button class="btn ghost sm" type="button" data-x="del">Xoá đề xuất</button></div>'+
    '</div></div>';
  return h}
function kv(k,v){return '<div><div class="k">'+k+'</div><div class="v">'+v+'</div></div>'}

/* ---------- tạo / sửa ---------- */
function newRequest(tid){
  var ts=types();
  if(!ts.length){A.toast('Chưa có mẫu đề xuất nào. Tạo mẫu trước đã.',true);return newType()}
  var t=tid?type(tid):ts[0];
  A.form({title:'Tạo đề xuất mới',wide:true,
    fields:[{k:'typeId',label:'Loại đề xuất',type:'select',value:t.id,options:ts.map(function(x){return {v:x.id,l:x.name}})},
            {k:'title',label:'Tên đề xuất',required:true,ph:'VD: Mua 6 bộ ESP32 cho đợt lắp tháng 10'}],
    saveLabel:'Tiếp tục',
    onSave:function(v){setTimeout(function(){fillRequest(null,v.typeId,v.title)},60)}})}

function fillRequest(existing,tid,title){
  var t=type(tid);if(!t)return;
  var r=existing||{id:A.uid(),typeId:tid,title:title,vals:{},by:A.me()||'ẩn danh',at:new Date().toISOString(),
    status:'draft',approvers:(t.approvers||[]).map(function(n){return {name:n,state:'pending'}}),watchers:[],log:[]};
  var fs=(t.fields||[]).map(function(f){
    return {k:f.k,label:f.label,type:f.type||'text',required:f.required,value:(r.vals||{})[f.k]||'',
            half:f.type!=='textarea'}});
  A.form({title:t.name,wide:true,note:'Mẫu: '+t.name,
    fields:[{k:'title',label:'Tên đề xuất',required:true,value:r.title}].concat(fs).concat(
      [{k:'deadline',label:'Thời hạn xử lý',type:'datetime-local',value:A.toLocal(r.deadline),half:true},
       {k:'approvers',label:'Người xét duyệt (cách nhau bằng dấu phẩy)',value:(r.approvers||[]).map(function(a){return a.name}).join(', '),half:true}]),
    actions:[{label:'Lưu nháp',cls:'ghost',fn:function(v){commit(r,t,v,'draft')}}],
    saveLabel:'Gửi đề xuất',
    onSave:function(v){commit(r,t,v,'pending')}})}

function commit(r,t,v,status){
  var o=Object.assign({},r);
  o.title=v.title;o.vals=o.vals||{};
  (t.fields||[]).forEach(function(f){o.vals[f.k]=v[f.k]});
  o.deadline=v.deadline?new Date(v.deadline).toISOString():'';
  var names=String(v.approvers||'').split(',').map(function(s){return s.trim()}).filter(Boolean);
  var old={};(o.approvers||[]).forEach(function(a){old[a.name]=a.state});
  o.approvers=names.map(function(n){return {name:n,state:old[n]||'pending'}});
  o.status=status;o.upd=new Date().toISOString();
  o.log=(o.log||[]).concat([{by:A.me()||'ẩn danh',tx:status==='draft'?'lưu nháp đề xuất':'gửi đề xuất',at:new Date().toISOString()}]);
  V.sel=o.id;A.save('requests',o)}

function newType(){
  A.form({title:'Mẫu đề xuất mới',
    note:'Mẫu quyết định các ô thông tin người gửi phải điền. Nhập mỗi trường một dòng, dạng: Nhãn | kiểu (text, textarea, number, date).',
    fields:[{k:'name',label:'Tên mẫu',required:true,ph:'VD: Đề xuất mua vật tư'},
            {k:'group',label:'Nhóm',value:'Quan trọng',half:true},
            {k:'approvers',label:'Người xét duyệt mặc định',value:A.me()||'',half:true},
            {k:'fields',label:'Các trường thông tin',type:'textarea',rows:6,
             value:'Chi tiết vật tư/dịch vụ | textarea\nSố lượng | number\nMục đích sử dụng | text\nChi phí dự kiến | number\nNhà cung cấp dự kiến | text'}],
    onSave:function(v){
      var fs=String(v.fields||'').split('\n').map(function(l){return l.trim()}).filter(Boolean).map(function(l,i){
        var p=l.split('|'),lab=p[0].trim(),ty=(p[1]||'text').trim();
        return {k:'f'+i,label:lab,type:ty,required:false}});
      A.save('reqtypes',{id:A.uid(),name:v.name,group:v.group||'Đề xuất',fields:fs,
        approvers:String(v.approvers||'').split(',').map(function(s){return s.trim()}).filter(Boolean)})}})}

/* ---------- hành động duyệt ---------- */
function act(kind){
  var r=req(V.sel);if(!r)return;
  var o=Object.assign({},r),now=new Date().toISOString();
  o.approvers=(o.approvers||[]).map(function(a){return Object.assign({},a)});
  if(kind==='approve'){
    o.approvers.forEach(function(a){if(A.isMe(a.name)&&a.state==='pending')a.state='approved'});
    o.log=(o.log||[]).concat([{by:A.me()||'ẩn danh',tx:'đã chấp thuận',at:now}]);
    if(o.approvers.every(function(a){return a.state==='approved'})){o.status='approved';
      o.log.push({by:'Hệ thống',tx:'đề xuất được duyệt xong',at:now})}}
  else if(kind==='reject'){
    o.approvers.forEach(function(a){if(A.isMe(a.name))a.state='rejected'});
    o.status='rejected';
    o.log=(o.log||[]).concat([{by:A.me()||'ẩn danh',tx:'đã từ chối',at:now}])}
  else if(kind==='forward'){
    A.form({title:'Chuyển tiếp cho người khác',
      fields:[{k:'who',label:'Chuyển cho',type:'people',required:true},
              {k:'note',label:'Lời nhắn',type:'textarea'}],
      onSave:function(v){
        var o2=Object.assign({},r);o2.approvers=(o2.approvers||[]).map(function(a){return Object.assign({},a)});
        o2.approvers.push({name:v.who,state:'pending'});
        o2.log=(o2.log||[]).concat([{by:A.me()||'ẩn danh',tx:'chuyển tiếp cho '+v.who+(v.note?' — '+v.note:''),at:now}]);
        o2.upd=now;A.save('requests',o2)}});
    return}
  else if(kind==='submit'){o.status='pending';
    o.log=(o.log||[]).concat([{by:A.me()||'ẩn danh',tx:'gửi đề xuất',at:now}])}
  o.upd=now;A.save('requests',o)}

function after(){
  var root=A.$('#appRoot');
  root.addEventListener('click',function(e){
    var el=e.target.closest('[data-tab],[data-type],[data-sel],[data-act],[data-x]');
    if(!el)return;var g;
    if((g=el.getAttribute('data-tab'))){V.tab=g;A.render();return}
    if(el.hasAttribute('data-type')){V.typeId=el.getAttribute('data-type')||null;A.render();return}
    if((g=el.getAttribute('data-sel'))){V.sel=g;A.render();return}
    if((g=el.getAttribute('data-act'))){
      if(g==='new')newRequest(V.typeId);
      else if(g==='newtype')newType();
      return}
    if((g=el.getAttribute('data-x'))){
      var r=req(V.sel);if(!r)return;
      if(g==='edit'){fillRequest(r,r.typeId,r.title);return}
      if(g==='del'){if(confirm('Xoá đề xuất này?')){A.remove('requests',r.id);V.sel=null}return}
      if(g==='watch'){var o=Object.assign({},r);var w=(o.watchers||[]).slice();
        var i=w.map(norm).indexOf(norm(A.me()));
        if(i>=0)w.splice(i,1);else if(A.me())w.push(A.me());else {A.toast('Đặt tên của bạn trước đã.',true);return}
        o.watchers=w;A.save('requests',o);return}
      act(g);return}});
  root.addEventListener('change',function(e){
    var m=e.target.closest('[data-mine]');
    if(m){V.mine[m.getAttribute('data-mine')]=m.checked;A.render()}});
  var q=A.$('#rqQ');if(q)q.addEventListener('input',function(){V.q=this.value;A.render()})}

A.register({id:'request',name:'Yêu cầu & Đề xuất',desc:'Đề xuất và phê duyệt',cat:'work',color:'#27A567',icon:'request',
  side:true,info:false,view:view,after:after,
  onEnter:function(arg){if(arg&&req(arg)){V.sel=arg;V.tab='all'}},
  badge:function(){return A.col('requests').filter(myTurn).length}});
})();
