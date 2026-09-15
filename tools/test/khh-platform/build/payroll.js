/* Payroll — Bảng lương: ngày công, bảo hiểm bắt buộc, giảm trừ gia cảnh, thuế TNCN luỹ tiến */
(function(){
'use strict';
var A=window.APP,esc=A.esc,norm=A.norm;
var V={view:'cycle',cyc:A.ymd(new Date()).slice(0,7),unit:''};
var DEF={personal:11000000,dependent:4400000,bhxh:8,bhyt:1.5,bhtn:1,
  capIns:46800000,capUnemp:99200000,
  brackets:[{upto:5000000,rate:5},{upto:10000000,rate:10},{upto:18000000,rate:15},
            {upto:32000000,rate:20},{upto:52000000,rate:25},{upto:80000000,rate:30},{upto:0,rate:35}]};
function cfg(){var c=A.setting('payroll',DEF);
  if(!c.brackets||!c.brackets.length)c.brackets=DEF.brackets;return c}

function pay(cyc){return A.find('payrolls','cyc_'+cyc.replace('-',''))}
function stored(){return A.col('payrolls').slice().sort(function(a,b){return String(b.cycle).localeCompare(String(a.cycle))})}
function staffList(){return A.col('staff').filter(function(s){return s.status!=='left'})}
function daysIn(cyc){var p=cyc.split('-');return new Date(+p[0],+p[1],0).getDate()}
function workDays(cyc){var n=daysIn(cyc),p=cyc.split('-'),c=0;
  for(var d=1;d<=n;d++)if(new Date(+p[0],+p[1]-1,d).getDay()!==0)c++;
  return c}
function attSummary(staffId,cyc){
  var a=A.find('attendance',staffId+'_'+cyc.replace('-','')),days=a&&a.days||{};
  var work=0,late=0,leave=0,unpaid=0;
  Object.keys(days).forEach(function(k){
    var r=days[k];
    if(r.leave){if(r.leave==='unpaid')unpaid++;else leave++;return}
    if(r.in){work++;late+=r.late||0}});
  return {work:work,late:late,leave:leave,unpaid:unpaid}}

/* thuế thu nhập cá nhân — luỹ tiến từng phần */
function taxOf(taxable,c){
  var t=0,prev=0,br=c.brackets;
  for(var i=0;i<br.length;i++){
    var top=br[i].upto&&br[i].upto>0?br[i].upto:Infinity;
    if(taxable>prev){t+=(Math.min(taxable,top)-prev)*br[i].rate/100;prev=top}
    else break}
  return Math.round(t)}
function taxSteps(taxable,c){
  var out=[],prev=0,br=c.brackets;
  for(var i=0;i<br.length;i++){
    var top=br[i].upto&&br[i].upto>0?br[i].upto:Infinity;
    if(taxable>prev){
      var part=Math.min(taxable,top)-prev;
      out.push({i:i+1,from:prev,to:top,part:part,rate:br[i].rate,amt:Math.round(part*br[i].rate/100)});
      prev=top}
    else break}
  return out}

function rowFor(s,cyc,c){
  var target=workDays(cyc),sm=attSummary(s.id,cyc);
  var paidDays=sm.work+sm.leave;
  var salary=Number(s.salary)||0,allow=Number(s.allowance)||0;
  var base=Math.round(salary*Math.min(1,target?paidDays/target:0));
  var perMin=salary/(target||1)/8/60;
  var lateDed=Math.round(sm.late*perMin);
  var gross=base+allow;
  var insBase=Math.min(salary,c.capIns),unempBase=Math.min(salary,c.capUnemp);
  var bhxh=Math.round(insBase*c.bhxh/100),bhyt=Math.round(insBase*c.bhyt/100),bhtn=Math.round(unempBase*c.bhtn/100);
  var ins=bhxh+bhyt+bhtn;
  var dep=Number(s.dependents)||0;
  var relief=c.personal+dep*c.dependent;
  var preTax=Math.max(0,gross-ins-lateDed);
  var taxable=Math.max(0,preTax-relief);
  var tax=taxOf(taxable,c);
  return {staff:s.name,staffId:s.id,days:paidDays,target:target,late:sm.late,unpaid:sm.unpaid,
    salary:salary,base:base,allowance:allow,gross:gross,
    bhxh:bhxh,bhyt:bhyt,bhtn:bhtn,ins:ins,deduction:lateDed,
    dependents:dep,relief:relief,taxable:taxable,tax:tax,
    net:gross-ins-lateDed-tax}}

function compute(cyc){
  if(!A.needRole('payroll.run','Chỉ Quản trị hoặc Chủ sở hữu mới tính được bảng lương.'))return;
  var c=cfg();
  var rows=staffList().map(function(s){return rowFor(s,cyc,c)});
  var o=Object.assign({},pay(cyc)||{},{id:'cyc_'+cyc.replace('-',''),cycle:cyc,rows:rows,
    cfg:{personal:c.personal,dependent:c.dependent,bhxh:c.bhxh,bhyt:c.bhyt,bhtn:c.bhtn},
    at:new Date().toISOString(),by:A.me()||'ẩn danh'});
  A.save('payrolls',o);
  A.toast('Đã tính lại kỳ '+cyc+': ngày công, bảo hiểm, giảm trừ gia cảnh và thuế TNCN.')}

function side(){
  var nav=[['cycle','Kỳ lương','payroll'],['report','Báo cáo lương','flow'],['cfg','Cấu hình tính lương','shield']];
  return '<aside class="side">'+A.sideUser()+'<div class="side-list"><div class="grp"><div class="grp-b">'+
    nav.map(function(n){
      return '<button class="pitem'+(V.view===n[0]?' on':'')+'" type="button" data-v="'+n[0]+'">'+
        A.icon(n[2],'ic')+'<span class="t">'+esc(n[1])+'</span></button>'}).join('')+
    '</div></div><div class="grp"><div class="grp-h"><span>Payroll cycles</span></div><div class="grp-b">'+
    (stored().length?stored().map(function(c){
      var tot=(c.rows||[]).reduce(function(n,r){return n+(r.net||0)},0);
      return '<button class="pitem'+(V.cyc===c.cycle?' on':'')+'" type="button" data-cyc="'+esc(c.cycle)+'">'+
        '<span class="t">Payroll '+esc(c.cycle)+'</span><span class="n">'+esc(A.shortMoney(tot))+'</span></button>'}).join('')
      :'<p style="padding:10px 12px;color:var(--rail-muted);font-size:12px">Chưa có kỳ lương nào.</p>')+
    '</div></div></div><button class="side-add" type="button" data-act="calc">Tính lương kỳ này</button></aside>'}

function view(){
  var body=V.view==='report'?reportHtml():V.view==='cfg'?cfgHtml():cycleHtml();
  return side()+'<section class="stage"><header class="topbar">'+A.navBtn()+
    '<h1>'+(V.view==='report'?'Payroll reports':V.view==='cfg'?'Cấu hình tính lương':'Bảng lương kỳ '+esc(V.cyc))+'</h1>'+
    '<span class="spacer"></span>'+
    (V.view!=='cfg'?'<input type="month" id="pyCyc" value="'+esc(V.cyc)+'" style="border:1px solid var(--line);border-radius:3px;padding:5px 8px;background:var(--panel-2)" aria-label="Chọn kỳ lương">'+
      '<button class="btn" type="button" data-act="calc">Tính lại từ bảng công</button>':'')+
    '</header><nav class="tabs"></nav><div class="content" id="content">'+body+'</div></section>'}

/* Xem được phiếu lương của ai: của chính mình, hoặc của người trong phạm vi mình phụ trách. */
function canSee(name){return A.isMe(name)||(A.can('payroll.all')&&A.inScope(name))}

/* Lọc bảng lương theo mảng kinh doanh. Ở đây chia dứt khoát — người "Dùng chung"
   đứng riêng một nhóm — để cộng các mảng lại đúng bằng tổng quỹ lương. */
function unitOfRow(r){var st=A.staffByName(r.staff);return st&&st.unit?String(st.unit).trim():''}
function inUnitRow(r){
  if(!V.unit)return true;
  var u=unitOfRow(r);
  return V.unit===A.UNIT_SHARED?!u:norm(u)===norm(V.unit)}
function unitBar(){
  var us=A.unitNames();
  if(!us.length)return '';
  function tab(v,l){
    return '<button class="btn '+(norm(V.unit)===norm(v)?'':'ghost')+'" type="button" data-punit="'+esc(v)+'">'+esc(l)+'</button>'}
  return '<div class="pad" style="padding-bottom:0"><div class="actionbar" style="border:0;padding:0 0 4px;gap:6px;flex-wrap:wrap">'+
    tab('','Toàn công ty')+us.map(function(x){return tab(x,x)}).join('')+tab(A.UNIT_SHARED,A.UNIT_SHARED)+
    '</div><p class="by" style="margin-bottom:10px">Cộng các mảng lại đúng bằng tổng toàn công ty — người làm cho mọi mảng nằm ở nhóm '+
    esc(A.UNIT_SHARED)+'.</p></div>'}

function cycleHtml(){
  var p=pay(V.cyc),c=cfg();
  var canAll=A.can('payroll.all'),sc=A.myScope();
  if(!p)return '<div class="empty"><h3>Kỳ '+esc(V.cyc)+' chưa được tính</h3>'+
    '<p>Bảng lương lấy ngày công từ ứng dụng Bảng công, trừ bảo hiểm bắt buộc (BHXH '+c.bhxh+'%, BHYT '+c.bhyt+'%, BHTN '+c.bhtn+'%),<br>'+
    'trừ giảm trừ gia cảnh rồi tính thuế TNCN luỹ tiến 7 bậc.</p>'+
    '<p style="margin-top:14px"><button class="btn" type="button" data-act="calc">Tính lương kỳ '+esc(V.cyc)+'</button></p></div>';
  var rows=(p.rows||[]).filter(function(r){return canSee(r.staff)&&inUnitRow(r)});
  var tot=function(k){return rows.reduce(function(n,r){return n+(r[k]||0)},0)};
  var h=unitBar()+'<div class="pad grid g4" style="padding-bottom:0">'+
    A.tile('Tổng thực nhận',A.shortMoney(tot('net')),'<span>kỳ '+esc(V.cyc)+'</span>','var(--green)')+
    A.tile('Tổng thu nhập',A.shortMoney(tot('gross')),'<span>lương theo công + phụ cấp</span>')+
    A.tile('Bảo hiểm bắt buộc',A.shortMoney(tot('ins')),'<span>BHXH + BHYT + BHTN phần NLĐ</span>')+
    A.tile('Thuế TNCN',A.shortMoney(tot('tax')),'<span>sau giảm trừ gia cảnh</span>',tot('tax')?'var(--red)':'')+
    '</div>'+
    (canAll&&!sc?'':'<div class="pad" style="padding-bottom:0"><div class="demo-note" style="color:var(--ink-2);background:var(--panel-2);border-color:var(--line)">'+
      '<span>'+(canAll
        ?'Bạn phụ trách '+esc(A.scopeNote()||'phần việc của mình')+' nên chỉ thấy lương trong phạm vi đó.'
        :'Bạn đang là '+esc(A.roleLabel())+' nên chỉ thấy phiếu lương của mình.')+'</span></div></div>')+
    '<div class="pad"><div class="tbl-wrap"><table class="t"><thead><tr>'+
    '<th>Nhân sự</th><th class="n">Ngày công</th><th class="n">Thu nhập</th><th class="n">Bảo hiểm</th>'+
    '<th class="n">Giảm trừ</th><th class="n">Chịu thuế</th><th class="n">Thuế TNCN</th><th class="n">Thực nhận</th><th></th></tr></thead><tbody>';
  rows.forEach(function(r){
    h+='<tr><td><span style="display:flex;gap:8px;align-items:center">'+A.av(r.staff,'s')+
      '<b style="font-weight:500">'+esc(r.staff)+'</b></span></td>'+
      '<td class="n">'+r.days+'/'+r.target+(r.late?'<span class="sub" style="display:block">muộn '+r.late+'′</span>':'')+'</td>'+
      '<td class="n">'+esc(A.money(r.gross))+'</td>'+
      '<td class="n">'+esc(A.money(r.ins||0))+'</td>'+
      '<td class="n sub">'+esc(A.shortMoney(r.relief||0))+'</td>'+
      '<td class="n">'+esc(A.money(r.taxable||0))+'</td>'+
      '<td class="n" style="'+(r.tax?'color:var(--red)':'')+'">'+esc(A.money(r.tax||0))+'</td>'+
      '<td class="n"><b style="font-weight:500">'+esc(A.money(r.net))+'</b></td>'+
      '<td class="n"><button class="linkbtn" type="button" data-slip="'+esc(r.staff)+'">Phiếu lương</button></td></tr>'});
  if(!rows.length)h+='<tr><td colspan="9" style="padding:20px;color:var(--muted)">Kỳ này chưa có dòng lương nào cho bạn.</td></tr>';
  h+='</tbody><tfoot><tr><td colspan="7" style="text-align:right;font-weight:500">Tổng cộng thực nhận</td>'+
    '<td class="n" style="font-weight:500">'+esc(A.money(tot('net')))+'</td><td></td></tr></tfoot></table></div>'+
    '<p class="by" style="margin-top:10px">Tính lúc '+esc(A.fmtDT(p.at))+' bởi '+esc(p.by||'—')+
    ' · Giảm trừ bản thân '+esc(A.shortMoney(c.personal))+', mỗi người phụ thuộc '+esc(A.shortMoney(c.dependent))+'.</p></div>';
  return h}

function slip(name){
  var p=pay(V.cyc);if(!p)return;
  var r=(p.rows||[]).filter(function(x){return norm(x.staff)===norm(name)})[0];
  if(!r)return;
  if(!canSee(r.staff)){
    A.toast(A.can('payroll.all')
      ?'Bạn chỉ xem được phiếu lương của người trong phạm vi mình phụ trách.'
      :'Bạn chỉ xem được phiếu lương của mình.',true);
    return}
  var c=cfg(),steps=taxSteps(r.taxable||0,c);
  function line(k,v,cls){return '<div style="display:flex;padding:5px 0;border-top:1px solid var(--line)'+(cls||'')+'">'+
    '<span style="flex:1">'+k+'</span><span class="num">'+v+'</span></div>'}
  A.form({title:'Phiếu lương '+r.staff+' · kỳ '+V.cyc,wide:true,fields:[],
    saveLabel:null,cancelLabel:'Đóng',
    extra:'<div style="font-size:12.5px">'+
      '<p class="sect-t">Thu nhập</p>'+
      line('Lương hợp đồng',A.money(r.salary||0))+
      line('Lương theo ngày công ('+r.days+'/'+r.target+' ngày)',A.money(r.base))+
      line('Phụ cấp',A.money(r.allowance))+
      line('<b>Tổng thu nhập</b>','<b>'+A.money(r.gross)+'</b>')+
      '<p class="sect-t" style="margin-top:14px">Các khoản trừ</p>'+
      line('BHXH ('+c.bhxh+'%)','−'+A.money(r.bhxh||0))+
      line('BHYT ('+c.bhyt+'%)','−'+A.money(r.bhyt||0))+
      line('BHTN ('+c.bhtn+'%)','−'+A.money(r.bhtn||0))+
      line('Khấu trừ đi muộn ('+(r.late||0)+' phút)','−'+A.money(r.deduction||0))+
      '<p class="sect-t" style="margin-top:14px">Thuế thu nhập cá nhân</p>'+
      line('Thu nhập trước thuế',A.money(Math.max(0,r.gross-(r.ins||0)-(r.deduction||0))))+
      line('Giảm trừ bản thân','−'+A.money(c.personal))+
      line('Giảm trừ '+(r.dependents||0)+' người phụ thuộc','−'+A.money((r.dependents||0)*c.dependent))+
      line('<b>Thu nhập chịu thuế</b>','<b>'+A.money(r.taxable||0)+'</b>')+
      (steps.length?'<div class="tbl-wrap" style="margin-top:8px"><table class="t"><thead><tr>'+
        '<th>Bậc</th><th class="n">Phần thu nhập</th><th class="n">Thuế suất</th><th class="n">Thuế</th></tr></thead><tbody>'+
        steps.map(function(s){
          return '<tr><td>Bậc '+s.i+'</td><td class="n">'+A.money(s.part)+'</td>'+
            '<td class="n">'+s.rate+'%</td><td class="n">'+A.money(s.amt)+'</td></tr>'}).join('')+
        '</tbody></table></div>':'<p class="by">Thu nhập chưa tới ngưỡng chịu thuế.</p>')+
      line('<b>Thuế TNCN phải nộp</b>','<b>−'+A.money(r.tax||0)+'</b>')+
      '<div style="display:flex;padding:12px 0 0;margin-top:10px;border-top:2px solid var(--ink);font-size:15px">'+
      '<span style="flex:1;font-weight:500">Thực nhận</span>'+
      '<span class="num" style="font-weight:500;color:var(--green)">'+A.money(r.net)+'</span></div>'+
      '</div>'})}

function cfgHtml(){
  var c=cfg();
  return '<div class="pad" style="max-width:760px"><div class="card-box">'+
    '<div class="sec-h"><div><h3>Cách hệ thống tính lương</h3>'+
    '<p class="ds">Áp dụng cho mọi kỳ tính sau khi lưu. Sửa khi chính sách hoặc mức giảm trừ thay đổi.</p></div>'+
    '<span class="spacer"></span><button class="btn" type="button" data-act="editcfg">Sửa cấu hình</button></div>'+
    '<div class="kv">'+
    kv('Giảm trừ bản thân',A.money(c.personal))+kv('Giảm trừ mỗi người phụ thuộc',A.money(c.dependent))+
    kv('BHXH (người lao động)',c.bhxh+'%')+kv('BHYT',c.bhyt+'%')+kv('BHTN',c.bhtn+'%')+
    kv('Trần đóng BHXH / BHYT',A.money(c.capIns))+kv('Trần đóng BHTN',A.money(c.capUnemp))+
    '</div></div>'+
    '<div class="card-box" style="margin-top:14px"><h3 style="margin-bottom:10px">Biểu thuế luỹ tiến từng phần</h3>'+
    '<div class="tbl-wrap"><table class="t"><thead><tr><th>Bậc</th><th>Phần thu nhập tính thuế / tháng</th>'+
    '<th class="n">Thuế suất</th></tr></thead><tbody>'+
    c.brackets.map(function(b,i){
      var prev=i?c.brackets[i-1].upto:0;
      return '<tr><td>Bậc '+(i+1)+'</td><td>'+(b.upto&&b.upto>0?
        'Trên '+A.shortMoney(prev)+' đến '+A.shortMoney(b.upto):'Trên '+A.shortMoney(prev))+'</td>'+
        '<td class="n">'+b.rate+'%</td></tr>'}).join('')+
    '</tbody></table></div>'+
    '<p class="by" style="margin-top:10px">Công thức: Thực nhận = (lương theo ngày công + phụ cấp) − bảo hiểm bắt buộc − khấu trừ đi muộn − thuế TNCN.</p>'+
    '</div></div>'}
function kv(k,v){return '<div><div class="k">'+esc(k)+'</div><div class="v">'+esc(v)+'</div></div>'}

function editCfg(){
  if(!A.needRole('settings.edit','Chỉ Quản trị hoặc Chủ sở hữu mới sửa được cấu hình lương.'))return;
  var c=cfg();
  A.form({title:'Cấu hình tính lương',wide:true,
    note:'Mức giảm trừ và tỉ lệ bảo hiểm theo quy định hiện hành. Sửa ở đây khi nhà nước điều chỉnh.',
    fields:[{k:'personal',label:'Giảm trừ bản thân (₫/tháng)',type:'number',value:c.personal,half:true},
      {k:'dependent',label:'Giảm trừ người phụ thuộc (₫)',type:'number',value:c.dependent,half:true},
      {k:'bhxh',label:'BHXH (%)',type:'number',step:'0.1',value:c.bhxh,half:true},
      {k:'bhyt',label:'BHYT (%)',type:'number',step:'0.1',value:c.bhyt,half:true},
      {k:'bhtn',label:'BHTN (%)',type:'number',step:'0.1',value:c.bhtn,half:true},
      {k:'capIns',label:'Trần đóng BHXH/BHYT (₫)',type:'number',value:c.capIns,half:true},
      {k:'capUnemp',label:'Trần đóng BHTN (₫)',type:'number',value:c.capUnemp,half:true},
      {k:'brackets',label:'Biểu thuế — mỗi dòng: trần bậc | thuế suất %  (bậc cuối để 0)',type:'textarea',rows:8,
       value:c.brackets.map(function(b){return (b.upto||0)+' | '+b.rate}).join('\n')}],
    onSave:function(v){
      var br=String(v.brackets||'').split('\n').map(function(l){return l.trim()}).filter(Boolean).map(function(l){
        var p=l.split('|');return {upto:Number(String(p[0]).replace(/[^\d]/g,''))||0,rate:Number(p[1])||0}});
      A.save('settings',{id:'payroll',personal:v.personal,dependent:v.dependent,bhxh:v.bhxh,bhyt:v.bhyt,bhtn:v.bhtn,
        capIns:v.capIns,capUnemp:v.capUnemp,brackets:br.length?br:DEF.brackets})}})}

function reportHtml(){
  var cs=stored().slice().reverse(),cur=pay(V.cyc),rows=cur&&cur.rows||[];
  var tot=function(k){return rows.reduce(function(n,r){return n+(r[k]||0)},0)};
  return '<div class="pad grid g4" style="padding-bottom:0">'+
    A.tile('Gross payroll total',A.shortMoney(cs.reduce(function(n,c){
      return n+(c.rows||[]).reduce(function(m,r){return m+(r.net||0)},0)},0)),'<span>tất cả các kỳ</span>')+
    A.tile('Kỳ đã chốt',cs.length,'<span>payroll cycles</span>')+
    A.tile('Thuế TNCN kỳ này',A.shortMoney(tot('tax')),'<span>'+esc(V.cyc)+'</span>','var(--red)')+
    A.tile('Bảo hiểm kỳ này',A.shortMoney(tot('ins')),'<span>phần người lao động</span>')+
    '</div><div class="pad grid g2">'+
    '<div class="card-box"><h3 style="margin-bottom:12px">Quỹ lương qua các kỳ</h3>'+
    (cs.length?A.line([{name:'Thực nhận (triệu ₫)',color:'var(--green)',
      pts:cs.map(function(c){return Math.round((c.rows||[]).reduce(function(n,r){return n+(r.net||0)},0)/1e6)})}],
      cs.map(function(c){return c.cycle.slice(5)+'/'+c.cycle.slice(2,4)}))
      :'<p class="by">Chưa có kỳ lương nào.</p>')+'</div>'+
    '<div class="card-box"><h3 style="margin-bottom:12px">Cơ cấu chi phí kỳ '+esc(V.cyc)+'</h3>'+
    (rows.length?A.donut([
      {v:tot('net'),c:'var(--green)',l:'Thực nhận'},
      {v:tot('ins'),c:'var(--blue)',l:'Bảo hiểm bắt buộc'},
      {v:tot('tax'),c:'var(--red)',l:'Thuế TNCN'},
      {v:tot('deduction'),c:'var(--amber)',l:'Khấu trừ muộn'}],
      A.shortMoney(tot('gross')),'tổng thu nhập')
      :'<p class="by">Kỳ này chưa được tính.</p>')+'</div>'+
    '<div class="card-box"><h3 style="margin-bottom:12px">Thực nhận theo nhân sự</h3>'+
    (rows.length?A.bars(rows.slice(0,8).map(function(r){
      return {l:A.initials(r.staff),v:Math.round((r.net||0)/1e6),c:'var(--app)'}}))+'<p class="by">Đơn vị: triệu đồng</p>'
      :'<p class="by">Chưa có dữ liệu.</p>')+'</div>'+
    '<div class="card-box"><h3 style="margin-bottom:12px">Thuế TNCN theo nhân sự</h3>'+
    (rows.length?'<div class="tbl-wrap"><table class="t"><thead><tr><th>Nhân sự</th><th class="n">Chịu thuế</th>'+
      '<th class="n">Thuế</th><th class="n">Tỉ lệ trên thu nhập</th></tr></thead><tbody>'+
      rows.map(function(r){
        return '<tr><td>'+esc(r.staff)+'</td><td class="n">'+esc(A.shortMoney(r.taxable||0))+'</td>'+
          '<td class="n">'+esc(A.shortMoney(r.tax||0))+'</td>'+
          '<td class="n">'+(r.gross?(r.tax/r.gross*100).toFixed(1):'0.0')+'%</td></tr>'}).join('')+
      '</tbody></table></div>':'<p class="by">Chưa có dữ liệu.</p>')+'</div></div>'}

function after(){
  A.$('#appRoot').addEventListener('click',function(e){
    var el=e.target.closest('[data-v],[data-cyc],[data-act],[data-slip],[data-punit]');
    if(!el)return;var g;
    if(el.hasAttribute('data-punit')){V.unit=el.getAttribute('data-punit');A.render();return}
    if((g=el.getAttribute('data-v'))){V.view=g;A.render();return}
    if((g=el.getAttribute('data-cyc'))){V.cyc=g;V.view='cycle';A.render();return}
    if((g=el.getAttribute('data-slip'))){slip(g);return}
    if((g=el.getAttribute('data-act'))){
      if(g==='calc')compute(V.cyc);else if(g==='editcfg')editCfg();return}});
  var c=A.$('#pyCyc');if(c)c.addEventListener('change',function(){V.cyc=this.value;A.render()})}

A.register({id:'payroll',name:'Bảng lương',desc:'Công, bảo hiểm và thuế TNCN',cat:'hrm',color:'#E8663D',icon:'payroll',
  side:true,info:false,view:view,after:after});
})();
