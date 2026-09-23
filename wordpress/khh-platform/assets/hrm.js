/* HRM — Hồ sơ & quản trị nhân sự */
(function(){
'use strict';
var A=window.APP,esc=A.esc,norm=A.norm;
var V={view:'list',sid:null,sub:'info',q:'',unit:''};
var STT={active:{l:'Đang làm việc',c:'done'},probation:{l:'Thử việc',c:'amb'},left:{l:'Đã nghỉ',c:'todo'}};
var SUBS=[['info','Thông tin cá nhân','Thông tin cá nhân, pháp lý và lý lịch'],
          ['job','Thông tin công việc','Công việc, sự nghiệp, tuyển dụng'],
          ['pay','Lương & phúc lợi','Bảng lương và phúc lợi'],
          ['kpi','Mục tiêu & đánh giá','KPIs, OKRs và phản hồi'],
          ['award','Thành tựu & giải thưởng','Chứng chỉ, giải thưởng, cột mốc'],
          ['time','Lịch làm việc & nghỉ phép','Bảng công, chấm công và nghỉ phép']];

function staff(){return A.col('staff').slice().sort(function(a,b){return String(a.code||'').localeCompare(String(b.code||''))})}
function one(id){return A.find('staff',id)}
function cur(){return one(V.sid)}
/* ---------- bộ phận ----------
   Tên bộ phận vẫn nằm ngay trong hồ sơ nhân sự (ô "dept") như trước, nên mọi
   thứ cũ vẫn chạy. Nhóm dữ liệu "depts" chỉ thêm phần hồ sơ của bộ phận:
   trưởng bộ phận, thứ tự hiển thị, ghi chú. Bộ phận nào có người mà chưa khai
   hồ sơ thì vẫn hiện đủ. */
var NODEPT='Chưa phân bộ phận';
function deptDocs(){return A.col('depts').filter(function(d){return d&&d.name})}
function deptDoc(name){
  var a=deptDocs(),k=norm(name);
  for(var i=0;i<a.length;i++)if(norm(a[i].name)===k)return a[i];
  return null}
function deptRows(all){
  var m={};
  deptDocs().forEach(function(d){m[norm(d.name)]={name:d.name,doc:d,n:0}});
  (all?staff():staffIn()).forEach(function(s){
    var nm=String(s.dept||'').trim(),k=norm(nm);
    if(!k){m.__none=m.__none||{name:NODEPT,none:true,doc:null,n:0};m.__none.n++;return}
    if(!m[k])m[k]={name:nm,doc:null,n:0};
    m[k].n++});
  var arr=Object.keys(m).map(function(k){return m[k]});
  arr.sort(function(a,b){
    if(!!a.none!==!!b.none)return a.none?1:-1;
    var oa=a.doc&&a.doc.order!=null?Number(a.doc.order):9999,
        ob=b.doc&&b.doc.order!=null?Number(b.doc.order):9999;
    if(oa!==ob)return oa-ob;
    return String(a.name).localeCompare(String(b.name),'vi')});
  return arr}
function deptRow(name){
  var rs=deptRows(true),k=norm(name);
  for(var i=0;i<rs.length;i++)if(norm(rs[i].name)===k)return rs[i];
  return null}
function deptNames(){return deptRows(true).filter(function(r){return !r.none}).map(function(r){return r.name})}
/* Gõ "phòng kỹ thuật" thì trả về đúng "Phòng kỹ thuật" đã có, khỏi sinh bộ phận trùng. */
function canonDept(nm){
  var k=norm(nm);if(!k)return '';
  var rs=deptRows(true);
  for(var i=0;i<rs.length;i++)if(!rs[i].none&&norm(rs[i].name)===k)return rs[i].name;
  return String(nm).trim()}
function staffOfDept(row){
  return A.col('staff').filter(function(s){
    return row.none?!String(s.dept||'').trim():norm(s.dept)===norm(row.name)})}
function depts(){var m={};deptRows().forEach(function(r){m[r.name]=r.n});return m}

/* ---------- mảng kinh doanh ----------
   Posh, HVC… Mỗi người thuộc một mảng; để trống là dùng chung cho mọi mảng.
   Chọn một mảng ở thanh bên thì cả phần nhân sự chỉ hiện người của mảng đó
   cộng những người dùng chung. */
function inUnit(s){
  if(!V.unit)return true;
  var u=String(s.unit||'').trim();
  if(V.unit===A.UNIT_SHARED)return !u;
  return !u||norm(u)===norm(V.unit)}
function staffIn(){return staff().filter(inUnit)}
function unitRows(){
  var m={};
  A.unitNames().forEach(function(n){m[norm(n)]={name:n,n:0,pay:0}});
  staff().forEach(function(s){
    var nm=String(s.unit||'').trim(),k=norm(nm),pay=(Number(s.salary)||0)+(Number(s.allowance)||0);
    if(!k){m.__none=m.__none||{name:A.UNIT_SHARED,none:true,n:0,pay:0};m.__none.n++;m.__none.pay+=pay;return}
    if(!m[k])m[k]={name:nm,n:0,pay:0};
    m[k].n++;m[k].pay+=pay});
  var arr=Object.keys(m).map(function(k){return m[k]});
  arr.sort(function(a,b){
    if(!!a.none!==!!b.none)return a.none?1:-1;
    return String(a.name).localeCompare(String(b.name),'vi')});
  return arr}
function unitRow(name){
  var rs=unitRows(),k=norm(name);
  for(var i=0;i<rs.length;i++)if(norm(rs[i].name)===k)return rs[i];
  return null}
function staffOfUnit(row){
  return A.col('staff').filter(function(s){
    return row.none?!String(s.unit||'').trim():norm(s.unit)===norm(row.name)})}
function saveUnits(list,scope){
  var cur=A.setting('units',{list:[],scope:1});
  return A.save('settings',{id:'units',
    list:list!=null?list:A.unitNames(),
    scope:scope!=null?(scope?1:0):(Number(cur.scope)?1:0)})}

function side(){
  var nav=[['list','Danh sách nhân sự','hrm'],['contract','Hợp đồng lao động','request'],
           ['units','Mảng kinh doanh','work'],['depts','Bộ phận','grid'],
           ['org','Cơ cấu tổ chức','flow'],
           ['report','Báo cáo nhân sự','timesheet'],['policy','Quy định & chính sách','wiki']];
  return '<aside class="side">'+A.sideUser()+A.sideSearch('hrSearch','Tìm nhanh nhân sự',V.q)+
    '<div class="side-list"><div class="grp"><div class="grp-b">'+
    nav.map(function(n){
      return '<button class="pitem'+(V.view===n[0]?' on':'')+'" type="button" data-v="'+n[0]+'">'+
        A.icon(n[2],'ic')+'<span class="t">'+esc(n[1])+'</span></button>'}).join('')+
    '</div></div>'+
    (unitRows().length>1?'<div class="grp"><div class="grp-h"><span>Mảng kinh doanh</span>'+
      (A.can('hr.edit')?'<button type="button" data-v="units" title="Quản lý mảng kinh doanh" style="color:inherit">Sửa</button>':'')+
      '</div><div class="grp-b">'+
      '<button class="pitem'+(V.unit?'':' on')+'" type="button" data-unit="">'+
        '<span class="t">Tất cả các mảng</span><span class="n">'+staff().length+'</span></button>'+
      unitRows().map(function(r){
        return '<button class="pitem'+(norm(V.unit)===norm(r.name)?' on':'')+'" type="button" data-unit="'+esc(r.name)+'">'+
          '<span class="t">'+esc(r.name)+'</span><span class="n">'+r.n+'</span></button>'}).join('')+
      '</div></div>':'')+
    '<div class="grp"><div class="grp-h"><span>Bộ phận</span>'+
    (A.can('hr.edit')?'<button type="button" data-v="depts" title="Quản lý bộ phận" style="color:inherit">Sửa</button>':'')+
    '</div><div class="grp-b">'+
    deptRows().map(function(r){
      return '<button class="pitem" type="button" data-dept="'+esc(r.name)+'"'+
        (r.doc&&r.doc.head?' title="Trưởng bộ phận: '+esc(r.doc.head)+'"':'')+'>'+
        '<span class="t">'+esc(r.name)+'</span><span class="n">'+r.n+'</span></button>'}).join('')+
    '</div></div></div><button class="side-add" type="button" data-act="new">+ Thêm nhân sự</button></aside>'}

function view(){
  var body,title;
  if(V.view==='profile'&&cur()){return side()+profileView()}
  if(V.view==='units'){title='Mảng kinh doanh';body=unitsHtml()}
  else if(V.view==='depts'){title='Bộ phận';body=deptsHtml()}
  else if(V.view==='org'){title='Cơ cấu tổ chức';body=orgHtml()}
  else if(V.view==='report'){title='Báo cáo nhân sự';body=reportHtml()}
  else if(V.view==='contract'){title='Hợp đồng lao động';body=contractHtml()}
  else if(V.view==='policy'){title='Quy định & chính sách';body=policyHtml()}
  else {title='Danh sách nhân sự';body=listHtml()}
  return side()+'<section class="stage"><header class="topbar">'+A.navBtn()+'<h1>'+esc(title)+'</h1>'+
    (V.unit&&V.view!=='units'?'<span class="chip info" style="margin-left:10px">'+esc(V.unit)+
      ' <button type="button" data-unit="" aria-label="Bỏ lọc mảng" style="color:inherit;font-weight:700">✕</button></span>':'')+
    '<span class="spacer"></span><span class="qsearch"><input id="hrQ" type="search" placeholder="Tìm nhân sự" value="'+esc(V.q)+'" aria-label="Tìm nhân sự"></span>'+
    '<button class="btn" type="button" data-act="new">+ Nhân sự</button></header><nav class="tabs"></nav>'+
    '<div class="content" id="content">'+body+'</div></section>'+overviewAside()}

function overviewAside(){
  var list=staffIn(),d=depts();
  return '<aside class="info"><div class="card-box"><p class="sect-t">Nhân sự</p>'+
    '<div class="metarow"><span class="k">Tổng nhân sự</span><b class="v num">'+list.length+'</b></div>'+
    '<div class="metarow"><span class="k">Đang làm việc</span><b class="v num">'+
      list.filter(function(s){return (s.status||'active')==='active'}).length+'</b></div>'+
    '<div class="metarow"><span class="k">Thử việc</span><b class="v num">'+
      list.filter(function(s){return s.status==='probation'}).length+'</b></div>'+
    '<div class="metarow"><span class="k">Bộ phận</span><b class="v num">'+Object.keys(d).length+'</b></div>'+
    '<div class="metarow"><span class="k">Quyền của bạn</span><span class="v"><span class="chip vio">'+
      esc(A.roleLabel())+'</span></span></div></div>'+
    '<div class="card-box"><p class="sect-t">Mới vào gần đây</p>'+
    (list.slice().sort(function(a,b){return new Date(b.start||0)-new Date(a.start||0)}).slice(0,5).map(function(s){
      return '<div class="mini">'+A.av(s.name,'s',s.color)+'<button class="t" type="button" data-sid="'+s.id+'">'+
        esc(s.name)+'</button><span class="by">'+esc(A.fmtD(s.start)||'')+'</span></div>'}).join('')
      ||'<p class="by">Chưa có nhân sự nào.</p>')+'</div></aside>'}

function listHtml(){
  var q=norm(V.q);
  var list=staffIn().filter(function(s){return !q||norm(s.name+' '+(s.code||'')+' '+(s.title||'')+' '+(s.dept||'')).indexOf(q)>=0});
  var h='<div class="tbl-wrap"><table class="t"><thead><tr><th style="padding-left:14px">Nhân sự</th><th>Mã</th>'+
    '<th>Chức danh</th><th>Bộ phận</th><th>Vai trò</th><th>Ngày bắt đầu</th><th>Thâm niên</th><th>Tình trạng</th></tr></thead><tbody>';
  list.forEach(function(s){
    var st=STT[s.status||'active'],yrs=s.start?A.daysBetween(s.start,new Date()):0;
    h+='<tr><td style="padding-left:14px"><button type="button" data-sid="'+s.id+'" style="display:flex;gap:9px;align-items:center;text-align:left">'+
      A.av(s.name,'',s.color)+'<span><b style="font-weight:500">'+esc(s.name)+'</b>'+
      '<span class="sub" style="display:block">'+esc(s.email||'')+'</span></span></button></td>'+
      '<td class="sub">'+esc(s.code||'—')+'</td><td>'+esc(s.title||'—')+'</td>'+
      '<td><span class="chip info">'+esc(s.dept||'—')+'</span>'+
        (s.unit?'<span class="chip soft" style="margin-left:5px">'+esc(s.unit)+'</span>':'')+'</td>'+
      '<td><span class="chip '+((s.role==='owner'||s.role==='admin')?'vio':(s.role==='manager'?'amb':'soft'))+'">'+
        esc(A.ROLES[s.role||'staff'])+'</span></td>'+
      '<td class="sub num">'+esc(A.fmtD(s.start)||'—')+'</td>'+
      '<td class="sub num">'+(yrs>0?Math.floor(yrs/365)+' năm '+(yrs%365)+' ngày':'—')+'</td>'+
      '<td><span class="chip '+st.c+'">'+st.l+'</span></td></tr>'});
  if(!list.length)h+='<tr><td colspan="8" style="padding:20px 14px;color:var(--muted)">Chưa có nhân sự nào.</td></tr>';
  h+='</tbody></table></div>';
  if(V.unit&&V.unit!==A.UNIT_SHARED){
    var sh=list.filter(function(s){return !String(s.unit||'').trim()}).length;
    h='<div class="pad" style="padding-bottom:0"><p class="by">Đang xem mảng <b>'+esc(V.unit)+'</b>'+
      (sh?' — gồm cả '+sh+' người '+esc(A.UNIT_SHARED)+' (làm cho mọi mảng)':'')+'.</p></div>'+h}
  return h}

function profileView(){
  var s=cur();
  var h='<section class="stage"><header class="topbar">'+A.navBtn()+
    '<button class="icon-btn" type="button" data-v="list" aria-label="Quay lại">‹</button>'+
    '<h1>'+esc(s.name)+'</h1><span class="spacer"></span>'+
    '<button class="btn ghost" type="button" data-act="edit">Sửa hồ sơ</button></header>'+
    '<nav class="tabs">'+SUBS.map(function(x){
      return '<button class="tab'+(V.sub===x[0]?' on':'')+'" type="button" data-sub="'+x[0]+'">'+esc(x[1])+'</button>'}).join('')+
    '</nav><div class="content" id="content"><div class="pad">'+subView(s)+'</div></section>';
  return h+profileAside(s)}

function profileAside(s){
  var yrs=s.start?A.daysBetween(s.start,new Date()):0;
  return '<aside class="info"><div class="card-box" style="text-align:center">'+
    '<div style="display:flex;justify-content:center;margin-bottom:10px">'+A.av(s.name,'xxl',s.color)+'</div>'+
    '<h2 style="margin-bottom:2px">'+esc(s.name)+'</h2>'+
    '<p class="by">'+esc(s.title||'—')+'</p>'+
    '<p style="margin-top:10px;color:var(--red)">♥ '+(yrs>0?Math.floor(yrs/365)+' năm '+(yrs%365)+' ngày làm việc':'Nhân sự mới')+'</p>'+
    '<div style="text-align:left;margin-top:12px">'+
    '<div class="metarow"><span class="k">Mã</span><span class="v">'+esc(s.code||'—')+'</span></div>'+
    '<div class="metarow"><span class="k">Bộ phận</span><span class="v">'+esc(s.dept||'—')+'</span></div>'+
    '<div class="metarow"><span class="k">Mảng kinh doanh</span><span class="v">'+esc(s.unit||A.UNIT_SHARED)+'</span></div>'+
    '<div class="metarow"><span class="k">Email</span><span class="v" style="word-break:break-all">'+esc(s.email||'—')+'</span></div>'+
    '<div class="metarow"><span class="k">Điện thoại</span><span class="v num">'+esc(s.phone||'—')+'</span></div>'+
    '<div class="metarow"><span class="k">Cơ sở</span><span class="v">'+esc(s.office||'—')+'</span></div>'+
    '</div></div>'+
    '<div class="card-box"><p class="sect-t">Công việc đang nhận</p>'+
    (function(){var ts=A.col('tasks').filter(function(t){return norm(t.assignee)===norm(s.name)});
      return '<div class="kpis"><div class="kpi t"><b>'+ts.filter(function(t){return t.status==='todo'}).length+'</b><span>Phải làm</span></div>'+
        '<div class="kpi g"><b>'+ts.filter(function(t){return t.status==='doing'}).length+'</b><span>Đang làm</span></div>'+
        '<div class="kpi d"><b>'+ts.filter(function(t){return t.status==='done'}).length+'</b><span>Hoàn thành</span></div></div>'})()+
    '</div></aside>'}

function subView(s){
  if(V.sub==='job')return jobSec(s);
  if(V.sub==='pay')return paySec(s);
  if(V.sub==='kpi')return kpiSec(s);
  if(V.sub==='award')return awardSec(s);
  if(V.sub==='time')return timeSec(s);
  return infoSec(s)}

function kv(k,v){return '<div><div class="k">'+esc(k)+'</div><div class="v">'+(v||'—')+'</div></div>'}
function secHead(t,d,act){return '<div class="sec-h"><div><h3>'+esc(t)+'</h3>'+
  (d?'<p class="ds">'+esc(d)+'</p>':'')+'</div><span class="spacer"></span>'+(act||'')+'</div>'}

function infoSec(s){
  return '<div class="sec">'+secHead('Ảnh nhận diện','Dùng để đối chiếu với ảnh chụp lúc chấm công',
      '<button class="btn sm ghost" type="button" data-act="face">'+(s.face?'Đổi ảnh':'Tải ảnh lên')+'</button>')+
    '<div style="display:flex;gap:14px;align-items:center">'+
    (s.face?'<img src="'+esc(s.face.url)+'" alt="Ảnh nhận diện '+esc(s.name)+'" '+
      'style="width:110px;height:110px;object-fit:cover;border-radius:4px;border:1px solid var(--line)">'
      :'<div style="width:110px;height:110px;border-radius:4px;border:1px dashed var(--line-2);display:grid;place-items:center;color:var(--muted);font-size:11.5px;text-align:center;padding:8px">Chưa có ảnh mẫu</div>')+
    '<p class="by" style="max-width:380px">Máy chấm công ngoài hiện trường vẫn là nơi nhận diện khuôn mặt. '+
    'Ảnh mẫu ở đây để người phụ trách đối chiếu bằng mắt với ảnh chụp lúc chấm công khi có nghi ngờ.</p>'+
    (s.face?'<button class="linkbtn" type="button" data-act="facedel">Xoá ảnh</button>':'')+
    '</div></div>'+
    '<div class="sec">'+secHead('Thông tin chính','Các thông tin cá nhân quan trọng',
      '<button class="linkbtn" type="button" data-act="edit">Sửa thông tin cơ bản</button>')+
    '<div class="kv">'+
    kv('Họ và tên','<b style="font-weight:500">'+esc(s.name)+'</b>')+kv('Mã nhân sự',esc(s.code))+
    kv('Ngày bắt đầu',esc(A.fmtD(s.start)))+kv('Ngày chính thức',esc(A.fmtD(s.official)))+
    kv('Tình trạng việc làm','<span style="color:var(--green)">'+esc((STT[s.status||'active']).l)+'</span>')+
    kv('Chức danh',esc(s.title))+kv('Vai trò trên hệ thống',esc(A.ROLES[s.role||'staff']))+
    kv('Số điện thoại',esc(s.phone))+kv('Email',esc(s.email))+
    kv('Ngày sinh',esc(A.fmtD(s.dob)))+kv('Giới tính',esc(s.gender))+kv('Tình trạng hôn nhân',esc(s.marital))+
    kv('Cơ sở',esc(s.office))+kv('Lịch làm việc',esc(s.worktime||'Full-time'))+
    kv('Khu vực / Chuyên môn',esc(s.area))+kv('Phân loại nhân sự',esc(s.type||'Nhân viên chính thức'))+
    kv('Ghi chú thêm',esc(s.note))+'</div></div>'+
    '<div class="sec">'+secHead('Thuế và bảo hiểm','Thông tin về thuế, bảo hiểm và các chính sách theo kèm')+
    '<div class="kv">'+kv('Mã số thuế',esc(s.taxCode))+
    kv('Giảm trừ gia cảnh',(s.dependents?esc(s.dependents)+' người phụ thuộc':'Không'))+
    kv('Số sổ BHXH',esc(s.bhxh))+kv('Nơi đăng ký BHXH',esc(s.bhxhPlace))+
    kv('Chính sách bảo hiểm',esc(s.insurance||'Standard (lương cơ bản)'))+'</div></div>'}

function jobSec(s){
  var ts=A.col('tasks').filter(function(t){return norm(t.assignee)===norm(s.name)});
  var ps=A.col('projects').filter(function(p){return (p.members||[]).some(function(n){return norm(n)===norm(s.name)})});
  return '<div class="sec">'+secHead('Thông tin công việc','Vị trí, hợp đồng và quá trình làm việc')+
    '<div class="kv">'+kv('Chức danh',esc(s.title))+kv('Bộ phận',esc(s.dept))+
      kv('Mảng kinh doanh',esc(s.unit||A.UNIT_SHARED))+
    kv('Quản lý trực tiếp',esc(s.manager))+kv('Hợp đồng hiện tại',esc(s.contract||'HĐ lao động 12 tháng'))+
    kv('Ngày ký hợp đồng',esc(A.fmtD(s.contractAt)))+kv('Loại hình',esc(s.type||'Full-Time Employee'))+'</div></div>'+
    '<div class="sec">'+secHead('Dự án đang tham gia',ps.length+' dự án')+
    (ps.length?ps.map(function(p){
      return '<div class="mini">'+A.av(p.name,'',p.color)+'<span class="t">'+esc(p.name)+'</span>'+
        '<button class="linkbtn" type="button" data-go="wework" data-arg="'+p.id+'">Mở</button></div>'}).join('')
      :'<p class="by">Chưa tham gia dự án nào.</p>')+'</div>'+
    '<div class="sec">'+secHead('Khối lượng công việc',ts.length+' công việc được giao')+
    A.wbar([{v:ts.filter(function(t){return t.status==='done'}).length,c:'var(--green)'},
            {v:ts.filter(function(t){return t.status==='doing'}).length,c:'var(--blue)'},
            {v:ts.filter(function(t){return t.status==='todo'}).length,c:'var(--panel-3)'}],ts.length||1)+'</div>'}

function paySec(s){
  var cyc=A.col('payrolls').slice().sort(function(a,b){return String(b.cycle).localeCompare(String(a.cycle))});
  return '<div class="sec">'+secHead('Lương & phúc lợi','Bảng lương và phúc lợi',
      '<button class="linkbtn" type="button" data-act="edit">Sửa</button>')+
    '<div class="kv">'+kv('Lương cơ bản','<b style="font-weight:500">'+esc(A.money(s.salary))+'</b>')+
    kv('Phụ cấp',esc(A.money(s.allowance)))+
    kv('Hình thức trả','Chuyển khoản hàng tháng')+
    kv('Ngân hàng',esc(s.bank))+'</div></div>'+
    '<div class="sec">'+secHead('Phiếu lương gần đây')+
    (cyc.length?'<div class="tbl-wrap"><table class="t"><thead><tr><th>Kỳ lương</th><th class="n">Số công</th>'+
      '<th class="n">Lương cơ bản</th><th class="n">Phụ cấp</th><th class="n">Khấu trừ</th><th class="n">Thực nhận</th></tr></thead><tbody>'+
      cyc.slice(0,6).map(function(c){
        var r=(c.rows||[]).filter(function(x){return norm(x.staff)===norm(s.name)})[0];
        if(!r)return '';
        return '<tr><td>'+esc(c.cycle)+'</td><td class="n">'+(r.days||0)+'</td>'+
          '<td class="n">'+esc(A.shortMoney(r.base))+'</td><td class="n">'+esc(A.shortMoney(r.allowance))+'</td>'+
          '<td class="n">'+esc(A.shortMoney(r.deduction))+'</td>'+
          '<td class="n"><b style="font-weight:500">'+esc(A.shortMoney(r.net))+'</b></td></tr>'}).join('')+
      '</tbody></table></div>':'<p class="by">Chưa có kỳ lương nào.</p>')+'</div>'}

function kpiSec(s){
  var k=s.kpis||[];
  return '<div class="sec">'+secHead('Mục tiêu (KPIs & OKRs)','Danh sách các mục tiêu cá nhân được giao',
      '<button class="btn sm" type="button" data-act="addkpi">+ Thêm mục tiêu</button>')+
    (k.length?'<div class="tbl-wrap"><table class="t"><thead><tr><th>Mục tiêu</th><th>Khoảng thời gian</th>'+
      '<th style="width:120px">Hoàn thành</th><th></th></tr></thead><tbody>'+
      k.map(function(x,i){
        return '<tr><td><b style="font-weight:500">'+esc(x.name)+'</b>'+
          (x.note?'<span class="sub" style="display:block">'+esc(x.note)+'</span>':'')+'</td>'+
          '<td class="sub num">'+esc(A.fmtD(x.from))+' – '+esc(A.fmtD(x.to))+'</td>'+
          '<td>'+A.wbar([{v:x.pct||0,c:'var(--app)'}],100)+'<span class="by num">'+(x.pct||0)+'%</span></td>'+
          '<td class="n"><button class="linkbtn" type="button" data-delkpi="'+i+'">Xoá</button></td></tr>'}).join('')+
      '</tbody></table></div>':'<p class="by">Chưa có mục tiêu nào được giao.</p>')+'</div>'+
    '<div class="sec">'+secHead('Đánh giá & phản hồi')+
    '<p class="by">'+esc(s.review||'Chưa có đánh giá nào trong kỳ này.')+'</p></div>'}

function awardSec(s){
  var aw=s.awards||[],ce=s.certs||[],mi=s.milestones||[];
  function block(t,arr,key,ic){
    return '<div class="sec">'+secHead(t,arr.length+' mục',
      '<button class="btn sm ghost" type="button" data-addlist="'+key+'">+ Thêm</button>')+
      (arr.length?'<div class="grid g3">'+arr.map(function(x,i){
        return '<div class="card-box" style="border-left:3px solid var(--app)">'+
          '<div style="font-weight:500">'+esc(x.name)+'</div>'+
          '<div class="by">'+esc(x.note||'')+(x.at?' · '+A.fmtD(x.at):'')+'</div>'+
          '<button class="linkbtn" type="button" data-dellist="'+key+':'+i+'" style="margin-top:6px">Xoá</button></div>'}).join('')+'</div>'
        :'<p class="by">Chưa có mục nào.</p>')+'</div>'}
  return block('Giải thưởng',aw,'awards')+block('Chứng chỉ',ce,'certs')+block('Cột mốc',mi,'milestones')}

function timeSec(s){
  var cyc=A.ymd(new Date()).slice(0,7);
  var att=A.find('attendance',s.id+'_'+cyc.replace('-',''));
  var days=att?att.days||{}:{};
  var ks=Object.keys(days);
  var worked=ks.filter(function(d){return days[d].in&&!days[d].off}).length;
  var late=ks.reduce(function(n,d){return n+(days[d].late||0)},0);
  var offs=A.col('timeoffs').filter(function(t){return norm(t.staff)===norm(s.name)});
  return '<div class="sec">'+secHead('Bảng công kỳ '+cyc,'Dữ liệu tổng hợp từ máy chấm công',
      '<button class="linkbtn" type="button" data-go="timesheet">Mở bảng công</button>')+
    '<div class="grid g4">'+
      A.tile('Ngày công',worked,'<span>trong kỳ '+esc(cyc)+'</span>')+
      A.tile('Đi muộn',late+' phút','<span>cộng dồn cả kỳ</span>',late?'var(--amber)':'')+
      A.tile('Đơn nghỉ',offs.length,'<span>'+offs.filter(function(t){return t.status==='approved'}).length+' đã duyệt</span>')+
      A.tile('Phép còn lại',(s.leaveLeft==null?12:s.leaveLeft)+' ngày','<span>trong năm nay</span>')+
    '</div></div>'+
    '<div class="sec">'+secHead('Đơn nghỉ phép gần đây')+
    (offs.length?offs.slice(0,6).map(function(t){
      return '<div class="mini"><span class="chip '+(t.status==='approved'?'done':t.status==='rejected'?'late':'amb')+'">'+
        (t.status==='approved'?'Đã duyệt':t.status==='rejected'?'Từ chối':'Chờ duyệt')+'</span>'+
        '<span class="t">'+esc(A.fmtD(t.from))+' – '+esc(A.fmtD(t.to))+' · '+esc(t.reason||'')+'</span>'+
        '<span class="by">'+t.days+' ngày</span></div>'}).join('')
      :'<p class="by">Chưa có đơn nghỉ nào.</p>')+'</div>'}

/* ---------- trang mảng kinh doanh ---------- */
function unitsHtml(){
  var rows=unitRows(),canEdit=A.can('hr.edit'),real=rows.filter(function(r){return !r.none});
  var h='<div class="pad"><div class="actionbar" style="border:0;padding:0 0 12px">'+
    '<span class="by">'+real.length+' mảng · '+staff().length+' nhân sự</span><span class="spacer"></span>'+
    (canEdit?'<button class="btn ghost" type="button" data-uact="bulk">Gán mảng hàng loạt</button>'+
      '<button class="btn" type="button" data-uact="new">+ Thêm mảng</button>':'')+'</div>';
  h+='<div class="tbl-wrap"><table class="t"><thead><tr><th style="padding-left:14px">Mảng kinh doanh</th>'+
    '<th class="n">Nhân sự</th><th class="n">Quỹ lương tháng</th>'+(canEdit?'<th></th>':'')+'</tr></thead><tbody>';
  rows.forEach(function(r){
    h+='<tr><td style="padding-left:14px"><span style="display:flex;gap:9px;align-items:center">'+
      A.av(r.name,'',r.none?'#94A3B8':'')+
      '<button type="button" data-unit="'+esc(r.name)+'" style="font-weight:500">'+esc(r.name)+'</button>'+
      (r.none?'<span class="chip soft">làm cho mọi mảng</span>':'')+'</span></td>'+
      '<td class="n">'+r.n+'</td><td class="n sub">'+esc(A.shortMoney(r.pay))+'</td>';
    if(canEdit)h+='<td class="n" style="white-space:nowrap">'+
      (r.none
        ? (r.n?'<button class="linkbtn" type="button" data-umerge="'+esc(r.name)+'">Gán vào một mảng</button>':'<span class="sub">—</span>')
        : '<button class="linkbtn" type="button" data-uedit="'+esc(r.name)+'">Sửa</button> · '+
          '<button class="linkbtn" type="button" data-umerge="'+esc(r.name)+'">Gộp</button> · '+
          '<button class="linkbtn" type="button" data-udel="'+esc(r.name)+'">Xoá</button>')+'</td>';
    h+='</tr>'});
  if(!rows.length)h+='<tr><td colspan="4" style="padding:20px 14px;color:var(--muted)">'+
    'Chưa khai mảng nào. Bấm <b>+ Thêm mảng</b> để tạo Posh, HVC…</td></tr>';
  h+='</tbody></table></div>';
  h+=unitScopeCard();
  h+='<p class="by" style="margin-top:12px;max-width:820px">Bộ phận thì dùng chung giữa các mảng — '+
    'Phòng kế toán vẫn là một bộ phận duy nhất. Mảng ghi trên hồ sơ từng người, nên ai làm cho cả hai mảng '+
    'thì để <b>'+esc(A.UNIT_SHARED)+'</b>: mảng nào cũng thấy và quản lý được người đó.</p></div>';
  return h}

function unitScopeCard(){
  var on=A.unitScopeOn(),canS=A.can('settings.edit');
  return '<div class="card-box" style="margin-top:18px;max-width:820px">'+
    '<div class="sec-h"><div><h3>Tách quyền theo mảng</h3>'+
    '<p class="ds">'+(on
      ? 'Đang tách: Quản lý chỉ thao tác với người cùng mảng với mình, cộng những người dùng chung.'
      : 'Đang mở: Quản lý thao tác được với người của mọi mảng.')+'</p></div>'+
    (canS?'<button class="btn'+(on?' ghost':'')+'" type="button" data-uact="scope">'+
      (on?'Tắt tách quyền':'Bật tách quyền')+'</button>':'')+'</div>'+
    '<p class="by">Chủ sở hữu và Quản trị luôn thấy toàn công ty. Quản lý chưa khai mảng thì vẫn làm việc với mọi mảng. '+
    'Tách quyền theo mảng chạy độc lập với giới hạn theo bộ phận — bật cả hai thì phải thoả cả hai.</p></div>'}

var UNIT_MSG='Chỉ Quản trị hoặc Chủ sở hữu mới sửa được mảng kinh doanh.';

function moveUnit(list,to,done){
  if(!list.length){done(0);return}
  A.saveMany('staff',list.map(function(s){var o=Object.assign({},s);o.unit=to;return o})).then(done)}

function openUnit(name){
  if(!A.needRole('hr.edit',UNIT_MSG))return;
  var row=name?unitRow(name):null;
  if(row&&row.none)return;
  A.form({title:row?('Mảng — '+row.name):'Thêm mảng kinh doanh',
    note:row&&row.n?(row.n+' nhân sự đang thuộc mảng này. Đổi tên ở đây sẽ cập nhật cho cả '+row.n+' hồ sơ.'):'',
    fields:[{k:'name',label:'Tên mảng',required:true,value:row?row.name:'',ph:'Posh'}],
    onSave:function(v){
      var nw=String(v.name||'').trim();
      if(!nw)return false;
      var other=unitRows().filter(function(x){
        return !x.none&&norm(x.name)===norm(nw)&&(!row||norm(x.name)!==norm(row.name))})[0];
      if(other){
        if(!row){A.toast('Đã có mảng "'+other.name+'" rồi.',true);return false}
        if(!confirm('Đã có mảng "'+other.name+'". Gộp "'+row.name+'" ('+row.n+' nhân sự) vào đó?'))return false;
        mergeUnitInto(row,other.name);return}
      var names=A.unitNames(),out=[],found=false;
      names.forEach(function(x){
        if(row&&norm(x)===norm(row.name)){out.push(nw);found=true}else out.push(x)});
      if(!found)out.push(nw);
      saveUnits(out);
      if(row&&norm(row.name)!==norm(nw)){
        moveUnit(staffOfUnit(row),nw,function(n){
          if(norm(V.unit)===norm(row.name))V.unit=nw;
          A.toast(n?('Đã đổi tên mảng và cập nhật '+n+' hồ sơ nhân sự.'):'Đã đổi tên mảng.')})}
      else A.toast(row?'Đã lưu mảng.':'Đã thêm mảng '+nw+'.')}})}

function mergeUnitInto(row,to){
  moveUnit(staffOfUnit(row),to,function(n){
    if(!row.none)saveUnits(A.unitNames().filter(function(x){return norm(x)!==norm(row.name)}));
    A.toast(row.none
      ? ('Đã gán '+n+' nhân sự vào mảng '+to+'.')
      : ('Đã gộp '+row.name+' vào '+to+(n?(', chuyển '+n+' nhân sự.'):'.')));
    if(norm(V.unit)===norm(row.name))V.unit='';
    A.render()})}

function mergeUnit(name){
  if(!A.needRole('hr.edit',UNIT_MSG))return;
  var row=unitRow(name);if(!row)return;
  var others=unitRows().filter(function(x){return !x.none&&norm(x.name)!==norm(row.name)});
  if(!others.length){A.toast('Chưa có mảng nào khác để chuyển sang.',true);return}
  A.form({title:row.none?'Gán vào một mảng':'Gộp mảng',
    note:row.none
      ? (row.n+' nhân sự đang để dùng chung. Chọn mảng để gán cho tất cả — sau đó họ chỉ còn thuộc mảng đó.')
      : (row.name+' đang có '+row.n+' nhân sự. Họ sẽ chuyển sang mảng bạn chọn, còn '+row.name+' bị xoá khỏi danh sách.'),
    fields:[{k:'to',label:row.none?'Gán vào mảng':'Gộp vào mảng',type:'select',
      options:others.map(function(x){return {v:x.name,l:x.name+' ('+x.n+' người)'}})}],
    saveLabel:row.none?'Gán':'Gộp',
    onSave:function(v){if(!v.to)return false;mergeUnitInto(row,v.to)}})}

function delUnit(name){
  if(!A.needRole('hr.edit',UNIT_MSG))return;
  var row=unitRow(name);if(!row||row.none)return;
  var others=unitRows().filter(function(x){return !x.none&&norm(x.name)!==norm(row.name)});
  A.form({title:'Xoá mảng '+row.name,
    note:row.n
      ? (row.n+' nhân sự đang thuộc mảng này. Chọn nơi chuyển họ sang trước khi xoá — không ai bị mất hồ sơ.')
      : 'Mảng này chưa có nhân sự nào.',
    fields:row.n?[{k:'to',label:'Chuyển nhân sự sang',type:'select',
      options:[{v:'',l:'— '+A.UNIT_SHARED+' —'}].concat(
        others.map(function(x){return {v:x.name,l:x.name}}))}]:[],
    saveLabel:'Xoá mảng',
    onSave:function(v){
      moveUnit(staffOfUnit(row),(v&&v.to)||'',function(n){
        saveUnits(A.unitNames().filter(function(x){return norm(x)!==norm(row.name)}));
        A.toast('Đã xoá mảng '+row.name+(n?(', chuyển '+n+' nhân sự.'):'.'));
        if(norm(V.unit)===norm(row.name))V.unit='';
        A.render()})}})}

/* Gán mảng cho cả một bộ phận một lượt — khỏi sửa tay từng hồ sơ. */
function bulkUnit(){
  if(!A.needRole('hr.edit',UNIT_MSG))return;
  var us=A.unitNames();
  if(!us.length){A.toast('Thêm ít nhất một mảng trước đã.',true);return}
  var ds=deptRows(true);
  A.form({title:'Gán mảng hàng loạt',
    note:'Chọn một bộ phận rồi gán cả bộ phận đó vào một mảng. Người đã có mảng khác cũng bị ghi đè.',
    fields:[
      {k:'dept',label:'Bộ phận',type:'select',
       options:[{v:'*',l:'— Tất cả nhân sự ('+staff().length+' người) —'}].concat(
         ds.map(function(r){return {v:r.name,l:r.name+' ('+r.n+' người)'}}))},
      {k:'unit',label:'Gán vào mảng',type:'select',
       options:[{v:'',l:A.UNIT_SHARED}].concat(us.map(function(x){return {v:x,l:x}}))}],
    saveLabel:'Gán',
    onSave:function(v){
      var list;
      if(v.dept==='*')list=A.col('staff').slice();
      else{var row=deptRow(v.dept);if(!row)return false;list=staffOfDept(row)}
      if(!list.length){A.toast('Bộ phận này chưa có ai.',true);return false}
      moveUnit(list,v.unit||'',function(n){
        A.toast('Đã gán '+n+' nhân sự vào '+(v.unit||A.UNIT_SHARED)+'.');A.render()})}})}

function toggleUnitScope(){
  if(!A.needRole('settings.edit','Chỉ Quản trị hoặc Chủ sở hữu mới đổi được cấu hình này.'))return;
  var on=A.unitScopeOn();
  saveUnits(null,on?0:1).then(function(){
    A.toast(on?'Đã tắt tách quyền — Quản lý làm việc được với mọi mảng.'
              :'Đã bật tách quyền — Quản lý chỉ làm việc trong mảng của mình.')})}

/* ---------- trang quản lý bộ phận ---------- */
function deptsHtml(){
  var rows=deptRows(true),canEdit=A.can('hr.edit'),n=rows.filter(function(r){return !r.none}).length;
  var h='<div class="pad"><div class="actionbar" style="border:0;padding:0 0 12px">'+
    '<span class="by">'+n+' bộ phận · '+staff().length+' nhân sự</span><span class="spacer"></span>'+
    (canEdit?'<button class="btn" type="button" data-dact="new">+ Thêm bộ phận</button>':'')+'</div>';
  h+='<div class="tbl-wrap"><table class="t"><thead><tr><th style="padding-left:14px">Bộ phận</th>'+
    '<th class="n">Nhân sự</th><th>Trưởng bộ phận</th><th>Ghi chú</th>'+
    (canEdit?'<th class="n">Thứ tự</th><th></th>':'')+'</tr></thead><tbody>';
  rows.forEach(function(r,i){
    var real=rows.filter(function(x){return !x.none});
    var pos=real.indexOf(r);
    h+='<tr><td style="padding-left:14px"><span style="display:flex;gap:9px;align-items:center">'+
      A.av(r.name,'',r.none?'#94A3B8':'')+
      '<button type="button" data-dept="'+esc(r.name)+'" style="font-weight:500">'+esc(r.name)+'</button>'+
      (r.none?'<span class="chip soft">chưa khai bộ phận</span>':(r.doc?'':'<span class="chip soft">chưa khai hồ sơ</span>'))+'</span></td>'+
      '<td class="n">'+r.n+'</td>'+
      '<td>'+(r.doc&&r.doc.head
        ? '<span style="display:flex;gap:7px;align-items:center">'+A.av(r.doc.head,'s')+esc(r.doc.head)+'</span>'
        : '<span class="sub">—</span>')+'</td>'+
      '<td class="sub">'+esc(r.doc&&r.doc.note||'')+'</td>';
    if(canEdit){
      h+='<td class="n">'+(r.none?'<span class="sub">—</span>':
          '<button class="linkbtn" type="button" data-dmove="up:'+esc(r.name)+'"'+(pos<=0?' disabled':'')+'>▲</button> '+
          '<button class="linkbtn" type="button" data-dmove="down:'+esc(r.name)+'"'+(pos<0||pos>=real.length-1?' disabled':'')+'>▼</button>')+'</td>'+
        '<td class="n" style="white-space:nowrap">'+
        (r.none
          ? (r.n?'<button class="linkbtn" type="button" data-dmerge="'+esc(r.name)+'">Gán bộ phận</button>':'<span class="sub">—</span>')
          : '<button class="linkbtn" type="button" data-dedit="'+esc(r.name)+'">Sửa</button> · '+
            '<button class="linkbtn" type="button" data-dmerge="'+esc(r.name)+'">Gộp</button> · '+
            '<button class="linkbtn" type="button" data-ddel="'+esc(r.name)+'">Xoá</button>')+'</td>'}
    h+='</tr>'});
  if(!rows.length)h+='<tr><td colspan="6" style="padding:20px 14px;color:var(--muted)">Chưa có bộ phận nào.</td></tr>';
  h+='</tbody></table></div>';
  h+=scopeCard(canEdit);
  h+='<p class="by" style="margin-top:12px;max-width:820px">Đổi tên một bộ phận ở đây sẽ cập nhật luôn ô "Bộ phận" '+
    'trong hồ sơ của tất cả nhân sự thuộc bộ phận đó, không phải sửa từng người.</p></div>';
  return h}

function scopeCard(canEdit){
  var on=A.scopeOn(),canS=A.can('settings.edit');
  return '<div class="card-box" style="margin-top:18px;max-width:820px">'+
    '<div class="sec-h"><div><h3>Phạm vi quyền của Quản lý</h3>'+
    '<p class="ds">'+(on
      ? 'Đang giới hạn: Quản lý chỉ duyệt nghỉ phép, sửa chấm công và xem lương của người trong bộ phận mình phụ trách.'
      : 'Đang mở: Quản lý thao tác được trên toàn công ty, không phân biệt bộ phận.')+'</p></div>'+
    (canS?'<button class="btn'+(on?' ghost':'')+'" type="button" data-dact="scope">'+
      (on?'Tắt giới hạn':'Bật giới hạn')+'</button>':'')+'</div>'+
    '<p class="by">Chủ sở hữu và Quản trị luôn quản lý toàn công ty. Một Quản lý phụ trách bộ phận của chính họ, '+
    'cộng thêm những bộ phận mà họ được đặt làm trưởng bộ phận ở bảng trên.</p></div>'}

/* ---------- các trang khác ---------- */
function orgHtml(){
  var rows=deptRows();
  return '<div class="pad"><div class="grid g3">'+rows.map(function(r){
    var mem=staffOfDept(r).filter(inUnit);
    return '<div class="card-box"><div style="display:flex;align-items:center;gap:9px;margin-bottom:10px">'+
      A.av(r.name,'l',r.none?'#94A3B8':'')+'<div><div style="font-weight:500">'+esc(r.name)+'</div>'+
      '<div class="by">'+mem.length+' nhân sự'+
        (r.doc&&r.doc.head?' · Trưởng bộ phận: '+esc(r.doc.head):'')+'</div></div></div>'+
      mem.map(function(s){
        var head=r.doc&&r.doc.head&&norm(r.doc.head)===norm(s.name);
        return '<div class="mini">'+A.av(s.name,'s',s.color)+
          '<button class="t" type="button" data-sid="'+s.id+'">'+esc(s.name)+'</button>'+
          (head?'<span class="chip vio">Trưởng bộ phận</span>':'')+
          '<span class="by">'+esc(s.title||'')+'</span></div>'}).join('')+'</div>'}).join('')+
    (rows.length?'':'<p class="empty">Chưa có bộ phận nào.</p>')+'</div></div>'}

function contractHtml(){
  var list=staffIn();
  return '<div class="tbl-wrap"><table class="t"><thead><tr><th style="padding-left:14px">Nhân sự</th><th>Hợp đồng</th>'+
    '<th>Ngày ký</th><th>Loại hình</th><th>Tình trạng</th></tr></thead><tbody>'+
    list.map(function(s){
      return '<tr><td style="padding-left:14px"><button type="button" data-sid="'+s.id+'" style="display:flex;gap:9px;align-items:center">'+
        A.av(s.name,'',s.color)+esc(s.name)+'</button></td>'+
        '<td>'+esc(s.contract||'HĐ lao động 12 tháng')+'</td>'+
        '<td class="sub num">'+esc(A.fmtD(s.contractAt)||'—')+'</td>'+
        '<td>'+esc(s.type||'Full-Time Employee')+'</td>'+
        '<td><span class="chip '+(STT[s.status||'active']).c+'">'+(STT[s.status||'active']).l+'</span></td></tr>'}).join('')+
    (list.length?'':'<tr><td colspan="5" style="padding:20px 14px;color:var(--muted)">Chưa có nhân sự nào.</td></tr>')+
    '</tbody></table></div>'}

function policyHtml(){
  var ps=A.col('posts').filter(function(p){return p.kind==='policy'});
  return '<div class="pad"><div class="actionbar" style="border:0;padding:0 0 12px">'+
    '<button class="btn" type="button" data-act="newpolicy">+ Thêm quy định</button></div>'+
    '<div class="grid g2">'+(ps.length?ps.map(function(p){
      return '<div class="card-box"><h3 style="margin-bottom:6px">'+esc(p.title)+'</h3>'+
        '<p class="by" style="margin-bottom:8px">'+esc(p.author)+' · '+esc(A.fmtD(p.at))+'</p>'+
        '<p style="white-space:pre-wrap">'+esc(p.body||'')+'</p></div>'}).join('')
      :'<p class="empty">Chưa có quy định nào. Ghi lại nội quy làm việc, chính sách nhân sự ở đây.</p>')+'</div></div>'}

function reportHtml(){
  var list=staffIn(),act=list.filter(function(s){return (s.status||'active')==='active'}).length;
  var prob=list.filter(function(s){return s.status==='probation'}).length;
  var totalSalary=list.reduce(function(n,s){return n+(Number(s.salary)||0)+(Number(s.allowance)||0)},0);
  var yrs=list.filter(function(s){return s.start}).map(function(s){return A.daysBetween(s.start,new Date())/365});
  var avgY=yrs.length?(yrs.reduce(function(a,b){return a+b},0)/yrs.length):0;
  var d=depts();
  var cyc=A.col('payrolls').slice().sort(function(a,b){return String(a.cycle).localeCompare(String(b.cycle))});
  return '<div class="pad grid g4" style="padding-bottom:0">'+
    A.tile('Tổng nhân sự',list.length,'<span><span class="dot" style="background:var(--green)"></span>'+act+' chính thức</span>'+
      '<span><span class="dot" style="background:var(--amber)"></span>'+prob+' thử việc</span>')+
    A.tile('Quỹ lương tháng',A.shortMoney(totalSalary),'<span>gồm cả phụ cấp</span>')+
    A.tile('Thâm niên trung bình',avgY.toFixed(2)+' năm','<span>trên '+yrs.length+' nhân sự</span>')+
    A.tile('Bộ phận',Object.keys(d).length,'<span>'+esc(Object.keys(d).slice(0,2).join(', '))+'…</span>')+
    '</div><div class="pad grid g2">'+
    (A.unitNames().length?'<div class="card-box"><h3 style="margin-bottom:12px">Nhân sự &amp; quỹ lương theo mảng</h3>'+
      A.donut(unitRows().map(function(r,i){return {v:r.n,c:A.AVC[i%A.AVC.length],l:r.name}}),staff().length,'nhân sự')+
      '<div style="margin-top:10px">'+unitRows().map(function(r){
        return '<div class="metarow"><span class="k">'+esc(r.name)+'</span>'+
          '<span class="v num">'+r.n+' người · '+esc(A.shortMoney(r.pay))+'</span></div>'}).join('')+'</div>'+
      '<p class="by" style="margin-top:8px">Quỹ lương là lương cơ bản cộng phụ cấp trong hồ sơ, chưa trừ bảo hiểm và thuế.</p>'+
      '</div>':'')+
    '<div class="card-box"><h3 style="margin-bottom:12px">Nhân sự theo bộ phận</h3>'+
    A.donut(Object.keys(d).map(function(k,i){return {v:d[k],c:A.AVC[i%A.AVC.length],l:k}}),list.length,'nhân sự')+'</div>'+
    '<div class="card-box"><h3 style="margin-bottom:12px">Quỹ lương qua các kỳ</h3>'+
    (cyc.length?A.line([{name:'Tổng thực nhận',color:'var(--green)',
        pts:cyc.map(function(c){return Math.round((c.rows||[]).reduce(function(n,r){return n+(r.net||0)},0)/1e6)})}],
        cyc.map(function(c){return c.cycle.slice(5)}))+'<p class="by">Đơn vị: triệu đồng</p>'
      :'<p class="by">Chưa có kỳ lương nào.</p>')+'</div>'+
    '<div class="card-box"><h3 style="margin-bottom:12px">Thâm niên từng người</h3><div class="tbl-wrap"><table class="t">'+
    '<thead><tr><th>Nhân sự</th><th>Bộ phận</th><th class="n">Ngày vào</th><th class="n">Thâm niên</th></tr></thead><tbody>'+
    list.map(function(s){var y=s.start?A.daysBetween(s.start,new Date()):0;
      return '<tr><td><span style="display:flex;gap:8px;align-items:center">'+A.av(s.name,'s',s.color)+esc(s.name)+'</span></td>'+
        '<td class="sub">'+esc(s.dept||'—')+'</td><td class="n sub">'+esc(A.fmtD(s.start)||'—')+'</td>'+
        '<td class="n">'+(y>0?(y/365).toFixed(1)+' năm':'—')+'</td></tr>'}).join('')+
    '</tbody></table></div></div></div>'}

/* ---------- hộp thoại bộ phận ---------- */
var EDIT_MSG='Chỉ Quản trị hoặc Chủ sở hữu mới sửa được danh sách bộ phận.';

function moveStaff(list,to,done){
  if(!list.length){done(0);return}
  A.saveMany('staff',list.map(function(s){var o=Object.assign({},s);o.dept=to;return o})).then(done)}

function openDept(name){
  if(!A.needRole('hr.edit',EDIT_MSG))return;
  var row=name?deptRow(name):null;
  if(row&&row.none)return;
  A.form({title:row?('Bộ phận — '+row.name):'Thêm bộ phận',
    note:row&&row.n?(row.n+' nhân sự đang thuộc bộ phận này. Đổi tên ở đây sẽ cập nhật cho cả '+row.n+' hồ sơ.'):'',
    fields:[
      {k:'name',label:'Tên bộ phận',required:true,value:row?row.name:'',ph:'Phòng kỹ thuật'},
      {k:'head',label:'Trưởng bộ phận',type:'people',value:row&&row.doc?(row.doc.head||''):'',half:true,
       hint:'Người này quản lý được nhân sự của bộ phận khi bật giới hạn phạm vi'},
      {k:'order',label:'Thứ tự hiển thị',type:'number',half:true,
       value:row&&row.doc&&row.doc.order!=null?row.doc.order:deptNames().length},
      {k:'note',label:'Ghi chú',value:row&&row.doc?(row.doc.note||''):'',ph:'VD: khối vận hành cửa hàng'}],
    onSave:function(v){
      var nw=String(v.name||'').trim();
      if(!nw)return false;
      var other=deptRows(true).filter(function(x){
        return !x.none&&norm(x.name)===norm(nw)&&(!row||norm(x.name)!==norm(row.name))})[0];
      if(other){
        if(!row){A.toast('Đã có bộ phận "'+other.name+'" rồi.',true);return false}
        if(!confirm('Đã có bộ phận "'+other.name+'". Gộp "'+row.name+'" ('+row.n+' nhân sự) vào đó?'))return false;
        mergeInto(row,other.name);return}
      var d=row&&row.doc?Object.assign({},row.doc):{id:A.uid(),at:new Date().toISOString()};
      var old=row?row.name:'';
      d.name=nw;d.head=v.head||'';d.note=v.note||'';d.order=v.order===''?null:Number(v.order);
      A.save('depts',d);
      if(row&&norm(old)!==norm(nw)){
        moveStaff(staffOfDept(row),nw,function(n){
          A.toast(n?('Đã đổi tên bộ phận và cập nhật '+n+' hồ sơ nhân sự.'):'Đã đổi tên bộ phận.')})}
      else A.toast(row?'Đã lưu bộ phận.':'Đã thêm bộ phận '+nw+'.')}})}

function mergeInto(row,to){
  moveStaff(staffOfDept(row),to,function(n){
    if(row.doc)A.remove('depts',row.doc.id);
    A.toast(row.none
      ? ('Đã gán '+n+' nhân sự vào '+to+'.')
      : ('Đã gộp '+row.name+' vào '+to+(n?(', chuyển '+n+' nhân sự.'):'.')));
    if(norm(V.q)===norm(row.name))V.q='';
    A.render()})}

function mergeDept(name){
  if(!A.needRole('hr.edit',EDIT_MSG))return;
  var row=deptRow(name);if(!row)return;
  var others=deptRows(true).filter(function(x){return !x.none&&norm(x.name)!==norm(row.name)});
  if(!others.length){A.toast('Chưa có bộ phận nào khác để chuyển sang.',true);return}
  A.form({title:row.none?'Gán bộ phận':'Gộp bộ phận',
    note:row.none
      ? (row.n+' nhân sự chưa khai bộ phận. Chọn bộ phận để gán cho tất cả.')
      : (row.name+' đang có '+row.n+' nhân sự. Họ sẽ chuyển sang bộ phận bạn chọn, còn '+row.name+' bị xoá khỏi danh sách.'),
    fields:[{k:'to',label:row.none?'Gán vào bộ phận':'Gộp vào bộ phận',type:'select',
      options:others.map(function(x){return {v:x.name,l:x.name+' ('+x.n+' người)'}})}],
    saveLabel:row.none?'Gán':'Gộp',
    onSave:function(v){if(!v.to)return false;mergeInto(row,v.to)}})}

function delDept(name){
  if(!A.needRole('hr.edit',EDIT_MSG))return;
  var row=deptRow(name);if(!row||row.none)return;
  var others=deptRows(true).filter(function(x){return !x.none&&norm(x.name)!==norm(row.name)});
  A.form({title:'Xoá bộ phận '+row.name,
    note:row.n
      ? (row.n+' nhân sự đang thuộc bộ phận này. Chọn nơi chuyển họ sang trước khi xoá — không ai bị mất hồ sơ.')
      : 'Bộ phận này chưa có nhân sự nào.',
    fields:row.n?[{k:'to',label:'Chuyển nhân sự sang',type:'select',
      options:[{v:'',l:'— Để trống (chưa phân bộ phận) —'}].concat(
        others.map(function(x){return {v:x.name,l:x.name}}))}]:[],
    saveLabel:'Xoá bộ phận',
    onSave:function(v){
      moveStaff(staffOfDept(row),(v&&v.to)||'',function(n){
        if(row.doc)A.remove('depts',row.doc.id);
        A.toast('Đã xoá bộ phận '+row.name+(n?(', chuyển '+n+' nhân sự.'):'.'));
        if(norm(V.q)===norm(row.name))V.q='';
        A.render()})}})}

function moveDept(name,dir){
  if(!A.needRole('hr.edit',EDIT_MSG))return;
  var rows=deptRows(true).filter(function(x){return !x.none}),i=-1;
  rows.forEach(function(r,k){if(norm(r.name)===norm(name))i=k});
  var j=i+(dir==='up'?-1:1);
  if(i<0||j<0||j>=rows.length)return;
  var t=rows[i];rows[i]=rows[j];rows[j]=t;
  A.saveMany('depts',rows.map(function(r,k){
    var d=r.doc?Object.assign({},r.doc):{id:A.uid(),name:r.name,at:new Date().toISOString()};
    d.order=k;return d}))}

function toggleScope(){
  if(!A.needRole('settings.edit','Chỉ Quản trị hoặc Chủ sở hữu mới đổi được cấu hình này.'))return;
  var on=A.scopeOn();
  A.save('settings',{id:'scope',dept:on?0:1}).then(function(){
    A.toast(on?'Đã tắt giới hạn — Quản lý thao tác được trên toàn công ty.'
              :'Đã bật giới hạn — Quản lý chỉ thao tác trong bộ phận mình phụ trách.')})}

/* ---------- hộp thoại ---------- */
function openStaff(id){
  if(!A.needRole('hr.edit','Chỉ Quản trị hoặc Chủ sở hữu mới sửa được hồ sơ nhân sự.'))return;
  var s=id?one(id):null;
  A.form({title:s?'Sửa hồ sơ nhân sự':'Thêm nhân sự',wide:true,
    fields:[
      {k:'name',label:'Họ và tên',required:true,value:s?s.name:''},
      {k:'code',label:'Mã nhân sự',value:s?(s.code||''):'',half:true,ph:'MNV-01'},
      {k:'title',label:'Chức danh',value:s?(s.title||''):'',half:true},
      {k:'dept',label:'Bộ phận',type:'datalist',options:deptNames(),value:s?(s.dept||''):'',half:true,
       ph:'Phòng kỹ thuật',hint:'Chọn trong danh sách có sẵn để khỏi sinh bộ phận trùng tên'},
      {k:'unit',label:'Mảng kinh doanh',type:'select',value:s?(s.unit||''):'',half:true,
       options:[{v:'',l:A.UNIT_SHARED}].concat(A.unitNames().map(function(x){return {v:x,l:x}})),
       hint:'Để '+A.UNIT_SHARED+' nếu người này làm cho mọi mảng'},
      {k:'status',label:'Tình trạng',type:'select',value:s?(s.status||'active'):'active',half:true,
       options:[{v:'active',l:'Đang làm việc'},{v:'probation',l:'Thử việc'},{v:'left',l:'Đã nghỉ'}]},
      {k:'role',label:'Vai trò trên hệ thống',type:'select',value:s?(s.role||'staff'):'staff',half:true,
       options:Object.keys(A.ROLES).map(function(k){return {v:k,l:A.ROLES[k]}})},
      {k:'email',label:'Email',type:'email',value:s?(s.email||''):'',half:true},
      {k:'phone',label:'Số điện thoại',value:s?(s.phone||''):'',half:true},
      {k:'start',label:'Ngày bắt đầu',type:'date',value:s&&s.start?s.start.slice(0,10):'',half:true},
      {k:'official',label:'Ngày chính thức',type:'date',value:s&&s.official?s.official.slice(0,10):'',half:true},
      {k:'dob',label:'Ngày sinh',type:'date',value:s&&s.dob?s.dob.slice(0,10):'',half:true},
      {k:'gender',label:'Giới tính',type:'select',value:s?(s.gender||'Nam'):'Nam',half:true,options:['Nam','Nữ','Khác']},
      {k:'marital',label:'Hôn nhân',type:'select',value:s?(s.marital||'Chưa kết hôn'):'Chưa kết hôn',half:true,
       options:['Chưa kết hôn','Đã kết hôn']},
      {k:'office',label:'Cơ sở',type:'datalist',options:A.officeAll(),
       value:s?(s.office||''):'',half:true,ph:'VD: Vinpearl Phú Quốc'},
      {k:'area',label:'Khu vực / Chuyên môn',value:s?(s.area||''):'',half:true},
      {k:'worktime',label:'Lịch làm việc',type:'select',value:s?(s.worktime||'Full-time'):'Full-time',half:true,
       options:['Full-time','Part-time','Theo ca']},
      {k:'type',label:'Phân loại',value:s?(s.type||'Full-Time Employee'):'Full-Time Employee',half:true},
      {k:'manager',label:'Quản lý trực tiếp',type:'people',value:s?(s.manager||''):'',half:true},
      {k:'contract',label:'Hợp đồng hiện tại',value:s?(s.contract||''):'HĐ lao động 12 tháng',half:true},
      {k:'contractAt',label:'Ngày ký hợp đồng',type:'date',value:s&&s.contractAt?s.contractAt.slice(0,10):'',half:true},
      {k:'salary',label:'Lương cơ bản (₫)',type:'number',value:s?(s.salary||''):'',half:true},
      {k:'allowance',label:'Phụ cấp (₫)',type:'number',value:s?(s.allowance||''):'',half:true},
      {k:'leaveLeft',label:'Phép còn lại (ngày)',type:'number',value:s?(s.leaveLeft==null?12:s.leaveLeft):12,half:true},
      {k:'taxCode',label:'Mã số thuế',value:s?(s.taxCode||''):'',half:true},
      {k:'dependents',label:'Người phụ thuộc',type:'number',value:s?(s.dependents||''):'',half:true},
      {k:'bhxh',label:'Số sổ BHXH',value:s?(s.bhxh||''):'',half:true},
      {k:'bhxhPlace',label:'Nơi đăng ký BHXH',value:s?(s.bhxhPlace||''):'',half:true},
      {k:'bank',label:'Ngân hàng',value:s?(s.bank||''):'',half:true},
      {k:'color',label:'Màu nhận dạng',type:'color',value:s?(s.color||A.hue(s.name)):A.AVC[A.col('staff').length%A.AVC.length]},
      {k:'note',label:'Ghi chú thêm',type:'textarea',value:s?(s.note||''):''}],
    onDelete:s?function(){A.remove('staff',s.id);V.view='list'}:null,
    deleteLabel:'Xoá hồ sơ',confirmDelete:'Xoá hồ sơ nhân sự này?',
    onSave:function(v){
      var o=s?Object.assign({},s):{id:A.uid(),at:new Date().toISOString(),awards:[],certs:[],milestones:[],kpis:[]};
      Object.keys(v).forEach(function(k){o[k]=v[k]});
      o.dept=canonDept(o.dept);
      o.unit=A.canonUnit(o.unit);
      o.office=A.officeCanon(o.office);
      if(!s){V.sid=o.id;V.view='profile'}
      A.save('staff',o)}})}

function after(){
  var root=A.$('#appRoot');
  root.addEventListener('click',function(e){
    var el=e.target.closest('[data-v],[data-sid],[data-sub],[data-act],[data-dept],[data-dact],[data-dedit],[data-dmerge],[data-ddel],[data-dmove],[data-unit],[data-uact],[data-uedit],[data-umerge],[data-udel],[data-delkpi],[data-addlist],[data-dellist]');
    if(!el)return;var g,s=cur();
    if((g=el.getAttribute('data-v'))){V.view=g;V.sid=null;A.render();return}
    if((g=el.getAttribute('data-sid'))){V.sid=g;V.view='profile';V.sub='info';A.render();return}
    if((g=el.getAttribute('data-sub'))){V.sub=g;A.render();return}
    if((g=el.getAttribute('data-dept'))){V.view='list';V.q=(g===NODEPT?'':g);A.render();return}
    if(el.hasAttribute('data-unit')){
      g=el.getAttribute('data-unit');
      V.unit=g;
      if(V.view==='profile'||V.view==='units')V.view='list';
      V.sid=null;A.render();return}
    if((g=el.getAttribute('data-uedit'))){openUnit(g);return}
    if((g=el.getAttribute('data-umerge'))){mergeUnit(g);return}
    if((g=el.getAttribute('data-udel'))){delUnit(g);return}
    if((g=el.getAttribute('data-uact'))){
      if(g==='new')openUnit(null);
      else if(g==='bulk')bulkUnit();
      else if(g==='scope')toggleUnitScope();
      return}
    if((g=el.getAttribute('data-dedit'))){openDept(g);return}
    if((g=el.getAttribute('data-dmerge'))){mergeDept(g);return}
    if((g=el.getAttribute('data-ddel'))){delDept(g);return}
    if((g=el.getAttribute('data-dmove'))){var mp=g.split(':');moveDept(mp.slice(1).join(':'),mp[0]);return}
    if((g=el.getAttribute('data-dact'))){
      if(g==='new')openDept(null);
      else if(g==='scope')toggleScope();
      return}
    if((g=el.getAttribute('data-delkpi'))&&s){
      var o=Object.assign({},s);o.kpis=(o.kpis||[]).slice();o.kpis.splice(+g,1);A.save('staff',o);return}
    if((g=el.getAttribute('data-addlist'))&&s){
      A.form({title:'Thêm mục',
        fields:[{k:'name',label:'Tên',required:true},{k:'note',label:'Ghi chú'},{k:'at',label:'Ngày',type:'date'}],
        onSave:function(v){var o2=Object.assign({},s);o2[g]=(o2[g]||[]).concat([v]);A.save('staff',o2)}});return}
    if((g=el.getAttribute('data-dellist'))&&s){
      var p=g.split(':'),o3=Object.assign({},s);o3[p[0]]=(o3[p[0]]||[]).slice();o3[p[0]].splice(+p[1],1);A.save('staff',o3);return}
    if((g=el.getAttribute('data-act'))){
      if(g==='new')openStaff(null);
      else if(g==='edit')openStaff(V.sid);
      else if(g==='face'&&s){
        if(!A.needRole('hr.edit','Chỉ Quản trị mới đổi được ảnh nhận diện.'))return;
        var inp=document.createElement('input');inp.type='file';inp.accept='image/*';
        inp.addEventListener('change',function(){
          var f=this.files&&this.files[0];if(!f)return;
          if(f.size>20*1024*1024){A.toast('Ảnh quá 20MB.',true);return}
          A.toast('Đang tải ảnh lên…');
          A.upload(f,f.name).then(function(info){
            if(!info){A.toast('Không tải được ảnh lên bản này.',true);return}
            var o=Object.assign({},s);o.face=info;A.save('staff',o);A.toast('Đã lưu ảnh nhận diện.')})});
        inp.click()}
      else if(g==='facedel'&&s){
        if(!A.needRole('hr.edit'))return;
        var o2=Object.assign({},s);delete o2.face;A.save('staff',o2)}
      else if(g==='addkpi'&&s){
        A.form({title:'Thêm mục tiêu',
          fields:[{k:'name',label:'Mục tiêu',required:true,ph:'VD: Hoàn thành lắp đặt 12 cơ sở'},
            {k:'from',label:'Từ ngày',type:'date',half:true},{k:'to',label:'Đến ngày',type:'date',half:true},
            {k:'pct',label:'Đã hoàn thành (%)',type:'number',value:0,half:true},
            {k:'note',label:'Ghi chú',half:true}],
          onSave:function(v){var o4=Object.assign({},s);o4.kpis=(o4.kpis||[]).concat([v]);A.save('staff',o4)}})}
      else if(g==='newpolicy'){
        A.form({title:'Thêm quy định',
          fields:[{k:'title',label:'Tiêu đề',required:true},{k:'body',label:'Nội dung',type:'textarea',rows:6}],
          onSave:function(v){A.save('posts',{id:A.uid(),kind:'policy',title:v.title,body:v.body,
            author:A.me()||'ẩn danh',at:new Date().toISOString()})}})}
      return}});
  var q=A.$('#hrQ');if(q)q.addEventListener('input',function(){V.q=this.value;A.render()});
  var s2=A.$('#hrSearch');if(s2)s2.addEventListener('input',function(){V.q=this.value;V.view='list';A.render()})}

A.register({id:'hrm',name:'Hồ sơ nhân sự',desc:'Quản trị nhân sự',cat:'hrm',color:'#1A8CD8',icon:'hrm',
  side:true,info:true,view:view,after:after,
  onEnter:function(arg){if(arg&&one(arg)){V.sid=arg;V.view='profile'}}});
})();
