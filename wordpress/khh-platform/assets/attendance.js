/* Checkin & Timesheet — dữ liệu chấm công và bảng công */
(function(){
'use strict';
var A=window.APP,esc=A.esc,norm=A.norm;
var METHODS=['Khuôn mặt','Thẻ từ','Vân tay','Điện thoại','Khai tay'];

/* ---------- ca chuẩn và luật tính công (chỉnh được ở tab Ca làm việc) ----------
   Mượn cách của Frappe HR (shift_type): mỗi ca khai cửa sổ vào sớm / ra muộn, ân hạn
   đi muộn / về sớm, và hai ngưỡng giờ làm để xếp Đủ / Nửa / Vắng. Mỗi cơ sở ghi đè
   được luật riêng — cửa hàng làm cả chủ nhật, văn phòng thì không. */
var CA_MAC_DINH=[{id:'C1',name:'Ca sáng',in:'08:30',out:'12:00'},{id:'C2',name:'Ca chiều',in:'13:30',out:'18:00'}];
var LUAT_MAC_DINH={vaoSom:60,raMuon:120,anHanMuon:5,anHanSom:5,nguongVang:2,nguongNua:4,ngayNghi:[0]};
function caLuat(){return A.setting('caLuat',{ca:CA_MAC_DINH,luat:LUAT_MAC_DINH,theoCoSo:{}})}
function shifts(){var c=caLuat().ca;return c&&c.length?c:CA_MAC_DINH}
function luatCua(cs){
  var d=caLuat(),o=Object.assign({},LUAT_MAC_DINH,d.luat||{});
  var r=d.theoCoSo&&cs&&d.theoCoSo[cs];
  if(r)Object.assign(o,r);
  if(!Array.isArray(o.ngayNghi))o.ngayNghi=LUAT_MAC_DINH.ngayNghi;
  return o}
function coSoCuaNV(sid){var s=A.find('staff',sid);return (s&&s.office)||''}
function phutGio(t){var a=String(t||'').split(':');return (+a[0]||0)*60+(+a[1]||0)}
/* Ca nào ứng với giờ vào này: ca đầu tiên mà giờ vào rơi trong [bắt đầu − vào sớm, kết thúc]. */
function caCuaGioVao(inT,cs){
  var L=luatCua(cs),v=phutGio(inT),ds=shifts().slice().sort(function(a,b){return phutGio(a.in)-phutGio(b.in)});
  for(var i=0;i<ds.length;i++){
    if(v>=phutGio(ds[i].in)-L.vaoSom&&v<=phutGio(ds[i].out))return ds[i]}
  return null}
/* Ca cuối cùng đã bắt đầu trước giờ ra — dùng để biết có về sớm không. */
function caCuaGioRa(outT,cs){
  var L=luatCua(cs),x=phutGio(outT),ds=shifts().slice().sort(function(a,b){return phutGio(a.in)-phutGio(b.in)}),ca=null;
  ds.forEach(function(c){if(phutGio(c.in)<=x&&x<=phutGio(c.out)+L.raMuon)ca=c});
  return ca}
function muonTheoLuat(inT,cs){
  if(!inT)return 0;
  var ca=caCuaGioVao(inT,cs);if(!ca)return 0;
  return Math.max(0,phutGio(inT)-phutGio(ca.in)-luatCua(cs).anHanMuon)}
function somTheoLuat(outT,cs){
  if(!outT)return 0;
  var ca=caCuaGioRa(outT,cs);if(!ca)return 0;
  return Math.max(0,phutGio(ca.out)-phutGio(outT)-luatCua(cs).anHanSom)}
/* Xếp loại một ngày. r: bản ghi thô; o: ô công (oTuDoc); cs: cơ sở; ngay: Date.
   tt = du | nua | vang | phep | nghi | trong (ngày chưa tới). */
function danhGia(r,o,cs,ngay){
  var L=luatCua(cs),homNay=new Date();homNay.setHours(0,0,0,0);
  if(r&&r.leave)return {tt:'phep',m:0,muon:0,som:0,leave:r.leave};
  var m=o?o.m:0;
  if(!m&&!(r&&r.in)){
    if(L.ngayNghi.indexOf(ngay.getDay())>=0)return {tt:'nghi',m:0,muon:0,som:0};
    return {tt:ngay<homNay?'vang':'trong',m:0,muon:0,som:0}}
  var tt=m/60<L.nguongVang?'vang':m/60<L.nguongNua?'nua':'du';
  return {tt:tt,m:m,muon:r&&r.in?muonTheoLuat(r.in,cs):0,som:r&&r.out?somTheoLuat(r.out,cs):0}}
var TT_NHAN={du:'Đủ công',nua:'Nửa công',vang:'Vắng',phep:'Nghỉ phép',nghi:'Ngày nghỉ',trong:'Chưa tới'};

/* ---------- dữ liệu chung ---------- */
function cycleId(staffId,cyc){return staffId+'_'+cyc.replace('-','')}
function att(staffId,cyc){return A.find('attendance',cycleId(staffId,cyc))}
function daysIn(cyc){var p=cyc.split('-');return new Date(+p[0],+p[1],0).getDate()}
function isWeekend(cyc,d){var p=cyc.split('-');var dt=new Date(+p[0],+p[1]-1,d);return dt.getDay()===0}
function dayRec(staffId,cyc,d){var a=att(staffId,cyc);return (a&&a.days&&a.days[A.pad(d)])||null}
function lateMin(inTime,cs){return muonTheoLuat(inTime,typeof cs==='string'&&cs.indexOf(':')<0?cs:'')}
function summary(staffId,cyc){
  var n=daysIn(cyc),work=0,late=0,leave=0,absent=0,target=0;
  for(var d=1;d<=n;d++){
    if(isWeekend(cyc,d))continue;
    target++;
    var r=dayRec(staffId,cyc,d);
    if(!r){var p=cyc.split('-');
      if(new Date(+p[0],+p[1]-1,d)<new Date())absent++;continue}
    if(r.leave){leave++;continue}
    if(r.in){work++;late+=r.late||0}else absent++}
  return {target:target,work:work,late:late,leave:leave,absent:absent}}
function setDay(staffId,cyc,d,rec){
  var id=cycleId(staffId,cyc),a=A.find('attendance',id);
  var o=a?Object.assign({},a):{id:id,staffId:staffId,cycle:cyc,days:{}};
  o.days=Object.assign({},o.days);
  if(rec===null)delete o.days[A.pad(d)];else o.days[A.pad(d)]=rec;
  A.save('attendance',o)}
function staffList(){return A.col('staff').filter(function(s){return s.status!=='left'})}

/* =========================================================
   ỨNG DỤNG 1 — CHECKIN
   ========================================================= */
var C={view:'log',date:A.ymd(new Date()),q:'',cyc:A.ymd(new Date()).slice(0,7),office:'',bp:'',tomTat:false,tuan:dauTuan(new Date()),caThang:false};

function cSide(){
  var nav=[['log','Dữ liệu chấm công','checkin'],['bcong','Bảng công cơ sở','timesheet'],['lichca','Lịch ca & đổi ca','timeoff'],
    ['bcngay','Báo cáo ngày cơ sở','flow'],['shifts','Ca làm việc','timeoff']];
  return '<aside class="side">'+A.sideUser()+'<div class="side-list"><div class="grp"><div class="grp-b">'+
    nav.map(function(n){
      return '<button class="pitem'+(C.view===n[0]?' on':'')+'" type="button" data-cv="'+n[0]+'">'+
        A.icon(n[2],'ic')+'<span class="t">'+esc(n[1])+'</span></button>'}).join('')+
    '</div></div><div class="grp"><div class="grp-h"><span>Cơ sở</span></div><div class="grp-b">'+
    A.officeAll().concat([NOOFF]).map(function(o){
      var sl=staffOfOffice(o);
      return '<button class="pitem'+((C.view==='bcong'||C.view==='lichca')&&o===coSo()?' on':'')+'" type="button" data-cs="'+esc(o)+'">'+
        A.icon('timesheet','ic')+'<span class="t">'+esc(o)+'</span><span class="n">'+sl.length+'</span></button>'}).join('')+
    '</div></div><div class="grp"><div class="grp-h"><span>Thiết bị</span></div><div class="grp-b">'+
    A.col('devices').map(function(d){
      return '<button class="pitem" type="button" data-dev="'+d.id+'">'+
        '<span class="dot" style="background:'+(d.online?'var(--green)':'var(--red)')+'"></span>'+
        '<span class="t">'+esc(d.name)+'</span></button>'}).join('')+
    '</div></div></div><button class="side-add" type="button" data-cact="newdev">+ Thêm máy chấm công</button></aside>'}

function cView(){
  var body,title,nut;
  if(C.view==='devices'){title='Máy chấm công';body=devicesHtml();nut=['newdev','Máy chấm công']}
  else if(C.view==='bcong'){title='Bảng công cơ sở';body=bcongHtml();nut=['fill','Nhập công hàng loạt']}
  else if(C.view==='shifts'){title='Ca làm việc & luật tính công';body=shiftsHtml();nut=['themca','Thêm ca']}
  else if(C.view==='lichca'){title='Lịch ca & đổi ca';body=lichCaHtml();nut=['xepca','Xếp ca']}
  else if(C.view==='bcngay'){title='Báo cáo ngày cơ sở';body=bcNgayHtml();nut=['nhapdt','Nhập doanh thu']}
  else {title='Dữ liệu chấm công';body=logHtml();nut=['newck','Ghi nhận chấm công']}
  var oi=A.officeAll();
  return cSide()+'<section class="stage"><header class="topbar">'+A.navBtn()+'<h1>'+esc(title)+'</h1>'+
    '<span class="spacer"></span>'+
    (C.view==='log'?'<input type="date" id="ckDate" value="'+esc(C.date)+'" style="border:1px solid var(--line);border-radius:3px;padding:5px 8px;background:var(--panel-2)" aria-label="Chọn ngày">':'')+
    (C.view==='log'?'<button class="btn ghost" type="button" data-cact="cam">Chấm công bằng camera</button>':'')+
    (C.view==='bcong'||C.view==='lichca'?'<select id="bcCs" style="border:1px solid var(--line);border-radius:3px;padding:5px 8px;background:var(--panel-2)" aria-label="Chọn cơ sở">'+
      oi.concat([NOOFF]).map(function(o){
        return '<option value="'+esc(o)+'"'+(o===coSo()?' selected':'')+'>'+esc(o)+'</option>'}).join('')+'</select>':'')+
    (C.view==='lichca'?'<span class="seg"><button type="button" data-cact="tuantruoc">‹</button><button type="button" data-cact="tuannay">Tuần này</button><button type="button" data-cact="tuansau">›</button></span>':'')+
    (C.view==='bcngay'?'<input type="date" id="bnDate" value="'+esc(C.date)+'" style="border:1px solid var(--line);border-radius:3px;padding:5px 8px;background:var(--panel-2)" aria-label="Chọn ngày">'+
      '<label class="tog" style="font-size:12px;color:var(--muted);display:flex;gap:6px;align-items:center"><input type="checkbox" id="bnThang"'+(C.caThang?' checked':'')+'> Cả tháng</label>':'')+
    (C.view==='bcong'?'<input type="month" id="bcCyc" value="'+esc(C.cyc)+'" style="border:1px solid var(--line);border-radius:3px;padding:5px 8px;background:var(--panel-2)" aria-label="Chọn tháng">':'')+
    '<button class="btn" type="button" data-cact="'+nut[0]+'">+ '+esc(nut[1])+'</button>'+
    '</header><nav class="tabs"></nav>'+
    '<div class="content" id="content">'+body+'</div></section>'}

/* =========================================================
   BẢNG CÔNG THEO CƠ SỞ
   Mỗi cơ sở một bảng, mỗi ô chỉ giữ GIÁ TRỊ CÔNG (số giờ + mã ca) —
   không đụng tới giờ vào/ra của dữ liệu chấm công thời gian thực.
   ========================================================= */
var TUAN=['CN','T2','T3','T4','T5','T6','T7'];
var NOOFF='Chưa gán cơ sở';

function coSo(){
  var ds=A.officeAll();
  if(C.office&&(C.office===NOOFF||ds.indexOf(C.office)>=0))return C.office;
  return ds.length?ds[0]:NOOFF}
function staffOfOffice(cs){
  return staffList().filter(function(s){
    var o=String(s.office||'').trim();
    return cs===NOOFF?!o:norm(o)===norm(cs)})}
function phutTrongNgay(t){
  var a=String(t||'').split(':');
  return (+a[0]||0)*60+(+a[1]||0)}

/* Công suy ra từ giờ vào/ra bên Dữ liệu chấm công.
   Lấy phần GIAO với từng ca chuẩn, nên nghỉ trưa không bị tính thành công:
   vào 08:10 ra 18:00 ra đúng 8h chứ không phải 9h50m. */
function congSuyRa(r){
  if(!r||!r.in||!r.out||r.leave)return null;
  var v=phutTrongNgay(r.in),x=phutTrongNgay(r.out);
  if(x<=v)return null;
  var t=0,ca=[];
  shifts().forEach(function(sh){
    var a=Math.max(v,phutTrongNgay(sh.in)),b=Math.min(x,phutTrongNgay(sh.out));
    if(b>a){t+=b-a;ca.push(sh.id)}});
  return t?{m:t,ca:ca.join('-')}:null}

/* Ô công của một ngày: số nhập tay được ưu tiên, không có thì lấy từ máy chấm công.
   Nhận sẵn bản ghi cả tháng để vẽ bảng 253 người không phải dò lại từng ô. */
function oTuDoc(a,d){
  var r=a&&a.days&&a.days[A.pad(d)];
  if(!r)return null;
  if(r.m)return {m:Number(r.m)||0,ca:r.ca||'',tay:true,r:r};
  var t=congSuyRa(r);
  return t?{m:t.m,ca:t.ca,tay:false,r:r}:null}
function oCong(sid,d){return oTuDoc(att(sid,C.cyc),d)}
function gioNgan(m){return (m/60).toFixed(1).replace(/\.0$/,'')}
function thu(d){var p=C.cyc.split('-');return new Date(+p[0],+p[1]-1,d).getDay()}

function bcongHtml(){
  var cs=coSo(),ssAll=staffOfOffice(cs),n=daysIn(C.cyc),pp=C.cyc.split('-');
  var ss=C.bp?ssAll.filter(function(s){return norm(s.dept||'')===norm(C.bp)}):ssAll;
  /* Một lượt duy nhất: lấy bản ghi tháng của từng người rồi tính luôn mọi thứ. */
  var tong=0,coCong=0,tuMay=0,tk={du:0,nua:0,vang:0,muon:0,phep:0};
  var dong=ss.map(function(s){
    var a=att(s.id,C.cyc),o=[],m=0,r={du:0,nua:0,vang:0,muon:0,som:0,phep:0};
    for(var d=1;d<=n;d++){
      var x=oTuDoc(a,d),rec=a&&a.days&&a.days[A.pad(d)],dg=danhGia(rec,x,cs,new Date(+pp[0],+pp[1]-1,d));
      o.push({o:x,dg:dg});
      if(x){m+=x.m;if(!x.tay)tuMay+=x.m}
      if(r[dg.tt]!==undefined)r[dg.tt]++;
      if(dg.muon)r.muon++;if(dg.som)r.som++}
    tong+=m;if(m)coCong++;
    Object.keys(tk).forEach(function(k){tk[k]+=r[k]});
    return {s:s,o:o,m:m,r:r}});
  var bps=A.deptAll().filter(function(d){return ssAll.some(function(s){return norm(s.dept||'')===norm(d)})});
  var h='<div class="pad grid g4" style="padding-bottom:0">'+
    A.tile('Tổng giờ công',A.gioPhut(tong),'<span>cơ sở '+esc(cs)+' · tháng '+esc(C.cyc)+(C.bp?' · '+esc(C.bp):'')+'</span>')+
    A.tile('Đủ công',tk.du,'<span>ngày công đủ · '+tk.nua+' nửa công</span>',tk.du?'var(--green)':'')+
    A.tile('Vắng',tk.vang,'<span>ngày vắng không phép · '+tk.phep+' ngày phép</span>',tk.vang?'var(--red)':'')+
    A.tile('Đi muộn',tk.muon,'<span>lượt vượt ân hạn · '+ss.length+' người</span>',tk.muon?'var(--amber)':'')+
    '</div><div class="pad">';
  if(!ssAll.length)
    return h+'<p class="empty">Chưa có nhân sự nào ở cơ sở này. Khai cơ sở cho từng người ở ứng dụng Hồ sơ nhân sự.</p></div>';
  h+='<div class="legend bc-legend" style="margin:0 0 10px">'+
    '<span><i class="sw tt-du"></i>Đủ công</span><span><i class="sw tt-nua"></i>Nửa công</span>'+
    '<span><i class="sw tt-vang"></i>Vắng</span><span><i class="sw tt-phep"></i>Nghỉ phép</span>'+
    '<span><i class="sw tt-muon"></i>Đi muộn / về sớm</span>'+
    '<span><b class="sr">8</b>&nbsp;Tự tính từ giờ vào/ra</span>'+
    '<span class="spacer"></span>'+
    (bps.length>1?'<select id="bcBp" aria-label="Lọc bộ phận"><option value="">Mọi bộ phận</option>'+bps.map(function(d){
      return '<option value="'+esc(d)+'"'+(norm(d)===norm(C.bp)?' selected':'')+'>'+esc(d)+'</option>'}).join('')+'</select>':'')+
    '<label class="tog"><input type="checkbox" id="bcTom"'+(C.tomTat?' checked':'')+'> Chỉ xem tổng kết</label></div>';
  if(!ss.length)return h+'<p class="empty">Không có ai thuộc bộ phận này ở cơ sở '+esc(cs)+'.</p></div>';
  h+='<div class="tbl-wrap"><table class="t bcong'+(C.tomTat?' tomtat':'')+'"><thead><tr><th class="nv">Nhân viên</th>';
  if(!C.tomTat)for(var d=1;d<=n;d++){
    var w=thu(d),ct=w===0||w===6;
    h+='<th class="d'+(ct?' ct':'')+'"><b>'+d+'</b><span>'+TUAN[w]+'</span></th>'}
  h+='<th class="tk">Đủ</th><th class="tk">Nửa</th><th class="tk">Vắng</th><th class="tk">Muộn</th><th class="tg">TỔNG</th></tr></thead><tbody>';
  dong.forEach(function(row){
    var s=row.s;
    h+='<tr><td class="nv"><span style="display:flex;gap:8px;align-items:center">'+A.av(s.name,'s',s.color)+
      '<span><b style="font-weight:500">'+esc(s.name)+'</b>'+
      '<span class="sub" style="display:block">'+esc(s.code||'')+(s.dept?' · '+esc(s.dept):'')+'</span></span></span></td>';
    if(!C.tomTat)for(var d2=1;d2<=n;d2++){
      var w2=thu(d2),ct2=w2===0||w2===6,c=row.o[d2-1],o=c.o,dg=c.dg;
      var ghi=esc(s.name)+' · ngày '+d2+' · '+TT_NHAN[dg.tt]+
        (dg.muon?' · muộn '+dg.muon+'′':'')+(dg.som?' · về sớm '+dg.som+'′':'')+
        (o&&!o.tay?' · tự tính từ giờ vào '+o.r.in+' – ra '+o.r.out:'');
      h+='<td class="c tt-'+dg.tt+(ct2?' ct':'')+(dg.muon||dg.som?' muon':'')+'"><button type="button" data-cong="'+s.id+':'+d2+'" '+
        'title="'+ghi+'">'+
        (o?'<b'+(o.tay?'':' class="sr"')+'>'+gioNgan(o.m)+'</b>'+(o.ca?'<span>'+esc(o.ca)+'</span>':'')
          :dg.tt==='phep'?'<span class="ph">P</span>':dg.tt==='vang'?'<span class="vg">V</span>':'<span class="cham">·</span>')+'</button></td>'}
    h+='<td class="tk">'+row.r.du+'</td><td class="tk">'+(row.r.nua||'<span class="cham">·</span>')+'</td>'+
      '<td class="tk'+(row.r.vang?' bad':'')+'">'+(row.r.vang||'<span class="cham">·</span>')+'</td>'+
      '<td class="tk'+(row.r.muon?' warn':'')+'">'+(row.r.muon||'<span class="cham">·</span>')+'</td>'+
      '<td class="tg">'+esc(A.gioPhut(row.m))+'</td></tr>'});
  h+='</tbody><tfoot><tr><td class="nv">'+ss.length+' người</td>'+
    (C.tomTat?'':'<td class="c" colspan="'+n+'"></td>')+
    '<td class="tk">'+tk.du+'</td><td class="tk">'+tk.nua+'</td><td class="tk">'+tk.vang+'</td><td class="tk">'+tk.muon+'</td>'+
    '<td class="tg">'+esc(A.gioPhut(tong))+'</td></tr></tfoot>';
  return h+'</table></div></div>'}

/* =========================================================
   LỊCH CA · ĐỔI CA · CA TRỐNG  (mượn Kintai)
   roster: {id, ngay, coso, staffId ('' = ca trống), ca, note}
   swaps : {id, loai: doi|nhan|tra, rosterId, tu, den, coso, ngay, ca, lyDo, tt: cho|duyet|tuchoi, by, at, quyetBoi, quyetLuc}
   ========================================================= */
function dauTuan(d){var x=new Date(d);x.setHours(0,0,0,0);var w=(x.getDay()+6)%7;x.setDate(x.getDate()-w);return x}
function ngayCong(d,n){var x=new Date(d);x.setDate(x.getDate()+n);return x}
function tuanNgay(){var out=[];for(var i=0;i<7;i++)out.push(ngayCong(C.tuan,i));return out}
function caCua(ngay,cs){var k=A.ymd(ngay);return A.col('roster').filter(function(r){return r.ngay===k&&norm(r.coso)===norm(cs)})}
function tenCa(id){var c=shifts().filter(function(x){return x.id===id})[0];return c?c.id+' · '+c.in+'–'+c.out:id}
function choDuyet(cs){return A.col('swaps').filter(function(x){return x.tt==='cho'&&(!cs||norm(x.coso)===norm(cs))})}
function laToi(sid){var s=A.find('staff',sid);return !!s&&A.isMe(s.name)}
function toiLa(){var me=A.staffByName(A.me());return me?me.id:''}

function lichCaHtml(){
  var cs=coSo(),ss=staffOfOffice(cs),ngay=tuanNgay(),qly=A.canFor('checkin.edit',null),homNay=A.ymd(new Date());
  var cho=choDuyet(cs),trong=0;
  ngay.forEach(function(d){trong+=caCua(d,cs).filter(function(r){return !r.staffId}).length});
  var h='<div class="pad grid g4" style="padding-bottom:0">'+
    A.tile('Tuần',A.fmtD(ngay[0])+' – '+A.fmtD(ngay[6]),'<span>cơ sở '+esc(cs)+'</span>')+
    A.tile('Ca đã xếp',ngay.reduce(function(n,d){return n+caCua(d,cs).filter(function(r){return r.staffId}).length},0),'<span>lượt ca có người trong tuần</span>')+
    A.tile('Ca trống',trong,'<span>chờ người nhận</span>',trong?'var(--amber)':'')+
    A.tile('Chờ duyệt',cho.length,'<span>xin đổi / nhận / trả ca</span>',cho.length?'var(--red)':'')+
    '</div><div class="pad">';
  if(!ss.length)return h+'<p class="empty">Cơ sở này chưa có nhân sự nào.</p></div>';
  /* --- chờ duyệt --- */
  if(cho.length||(window.KH_API&&window.KH_API.vhccDoiLich&&window.KH_API.vhccDoiLich.n)){
    h+='<div class="card-box" style="margin-bottom:14px"><div class="sec-h"><div><h3>Chờ duyệt</h3><p class="ds">Quản lý duyệt là lịch đổi ngay</p></div></div>';
    cho.forEach(function(x){
      var tu=A.find('staff',x.tu),den=A.find('staff',x.den);
      var loi=x.loai==='doi'?(tu?tu.name:'?')+' xin đổi ca '+esc(x.ca)+' ngày '+A.fmtD(x.ngay)+' cho '+(den?den.name:'?'):
        x.loai==='nhan'?(den?den.name:'?')+' xin nhận ca trống '+esc(x.ca)+' ngày '+A.fmtD(x.ngay):
        (tu?tu.name:'?')+' xin trả ca '+esc(x.ca)+' ngày '+A.fmtD(x.ngay)+' ra ca trống';
      h+='<div class="mini"><span class="chip '+(x.loai==='doi'?'info':x.loai==='nhan'?'ok':'amb')+'">'+(x.loai==='doi'?'Đổi':x.loai==='nhan'?'Nhận':'Trả')+'</span>'+
        '<span class="t">'+esc(loi)+(x.lyDo?' <span class="by">— '+esc(x.lyDo)+'</span>':'')+'</span>'+
        (qly?'<button class="btn sm" type="button" data-duyet="'+x.id+':1">Duyệt</button> <button class="btn sm ghost" type="button" data-duyet="'+x.id+':0">Từ chối</button>':'<span class="by">chờ quản lý</span>')+'</div>'});
    var vd=window.KH_API&&window.KH_API.vhccDoiLich;
    if(vd&&vd.n)h+='<p class="by" style="margin-top:10px">Bên <b>Chấm Công (K&amp;H)</b> còn <b>'+vd.n+'</b> yêu cầu đổi lịch đang chờ'+
      (vd.ds&&vd.ds.length?': '+vd.ds.slice(0,4).map(function(y){return esc(y.ho_ten)+' ('+esc(y.ngay||'')+')'}).join(', ')+(vd.n>4?'…':''):'')+
      (vd.url?' — <a href="'+esc(vd.url)+'" target="_blank" rel="noopener">duyệt tại Chấm Công</a>':'')+'.</p>';
    h+='</div>'}
  /* --- lưới tuần --- */
  h+='<div class="tbl-wrap"><table class="t lichca"><thead><tr><th class="nv">Nhân viên</th>'+
    ngay.map(function(d){var k=A.ymd(d);return '<th class="d'+(d.getDay()===0||d.getDay()===6?' ct':'')+(k===homNay?' hom':'')+'"><b>'+d.getDate()+'/'+(d.getMonth()+1)+'</b><span>'+TUAN[d.getDay()]+'</span></th>'}).join('')+'</tr></thead><tbody>';
  var hang=[{id:'',name:'Ca trống',trong:true}].concat(ss);
  hang.forEach(function(s){
    h+='<tr'+(s.trong?' class="trong"':'')+'><td class="nv">'+(s.trong?'<b style="font-weight:600;color:var(--amber)">Ca trống</b><span class="sub" style="display:block">ai cũng nhận được</span>'
      :'<span style="display:flex;gap:8px;align-items:center">'+A.av(s.name,'s',s.color)+'<span><b style="font-weight:500">'+esc(s.name)+'</b><span class="sub" style="display:block">'+esc(s.code||'')+'</span></span></span>')+'</td>';
    ngay.forEach(function(d){
      var k=A.ymd(d),cs2=caCua(d,cs).filter(function(r){return (r.staffId||'')===s.id});
      h+='<td class="c'+(d.getDay()===0||d.getDay()===6?' ct':'')+'">'+cs2.map(function(r){
        var xin=choDuyet(cs).filter(function(x){return x.rosterId===r.id}).length;
        var cls='cachip'+(s.trong?' trong':'')+(xin?' xin':'');
        var nut=s.trong?(qly?'':' data-nhan="'+r.id+'"'):(laToi(s.id)&&!qly?' data-xin="'+r.id+'"':'');
        return '<button type="button" class="'+cls+'" data-rid="'+r.id+'"'+nut+' title="'+esc(tenCa(r.ca))+(r.note?' · '+esc(r.note):'')+(xin?' · đang có yêu cầu chờ duyệt':'')+'">'+esc(r.ca)+(xin?' <i>•</i>':'')+'</button>'}).join('')+
        (qly?'<button type="button" class="them" data-them="'+k+'|'+s.id+'" aria-label="Xếp ca">+</button>':'')+'</td>'});
    h+='</tr>'});
  h+='</tbody></table></div>'+
    '<p class="by" style="margin-top:10px">'+(qly?'Bấm <b>+</b> để xếp ca, bấm vào ca để sửa hoặc xoá. Ca trống là ca chưa có người — nhân viên tự nhận, quản lý duyệt.'
      :'Bấm vào ca của mình để xin đổi cho đồng nghiệp hoặc trả ra ca trống. Ca trống bấm để xin nhận. Mọi việc đều chờ quản lý duyệt.')+'</p>';
  return h+'</div>'}

function xepCa(ngay,sid,rid){
  if(!A.needRole('checkin.edit','Chỉ Quản lý trở lên mới xếp ca.'))return;
  var cs=coSo(),ss=staffOfOffice(cs),r=rid?A.find('roster',rid):null;
  A.form({title:r?'Sửa ca':'Xếp ca',
    fields:[{k:'ngay',label:'Ngày',type:'date',value:r?r.ngay:(ngay||A.ymd(new Date())),half:true,required:true},
      {k:'ca',label:'Ca',type:'select',value:r?r.ca:shifts()[0].id,half:true,options:shifts().map(function(c){return {v:c.id,l:c.id+' · '+c.name+' '+c.in+'–'+c.out}})},
      {k:'staffId',label:'Người làm',type:'select',value:r?(r.staffId||''):(sid||''),options:[{v:'',l:'— Ca trống, chờ người nhận —'}].concat(ss.map(function(x){return {v:x.id,l:x.name}}))},
      {k:'note',label:'Ghi chú',value:r?(r.note||''):''}],
    onDelete:r?function(){A.remove('roster',r.id);A.render()}:null,deleteLabel:'Xoá ca',confirmDelete:'Xoá ca này khỏi lịch?',
    onSave:function(v){
      var trung=caCua(new Date(v.ngay+'T00:00:00'),cs).some(function(x){return x.id!==(r&&r.id)&&x.ca===v.ca&&x.staffId===v.staffId&&v.staffId});
      if(trung){A.toast('Người này đã có ca đó trong ngày rồi.',true);return false}
      var o=r?Object.assign({},r):{id:A.uid(),coso:cs,by:A.me()||'',at:new Date().toISOString()};
      o.ngay=v.ngay;o.ca=v.ca;o.staffId=v.staffId;o.note=v.note;
      A.save('roster',o);A.render()}})}

function xinDoiCa(rid){
  var r=A.find('roster',rid);if(!r)return;
  var me=toiLa();if(!me||r.staffId!==me){A.toast('Chỉ xin đổi được ca của chính mình.',true);return}
  if(choDuyet('').some(function(x){return x.rosterId===rid})){A.toast('Ca này đang có yêu cầu chờ duyệt rồi.',true);return}
  var ss=staffOfOffice(r.coso).filter(function(s){return s.id!==me});
  A.form({title:'Xin đổi ca '+r.ca+' ngày '+A.fmtD(r.ngay),
    note:'Gửi tới quản lý duyệt. Đồng nghiệp nhận ca sẽ được ghi vào lịch khi được duyệt.',
    fields:[{k:'den',label:'Đổi cho',type:'select',value:'',options:[{v:'',l:'— Trả ra ca trống, ai nhận cũng được —'}].concat(ss.map(function(x){return {v:x.id,l:x.name}}))},
      {k:'lyDo',label:'Lý do',value:'',ph:'VD: có việc gia đình'}],
    saveLabel:'Gửi yêu cầu',
    onSave:function(v){
      A.save('swaps',{id:A.uid(),loai:v.den?'doi':'tra',rosterId:r.id,tu:me,den:v.den||'',coso:r.coso,ngay:r.ngay,ca:r.ca,
        lyDo:v.lyDo,tt:'cho',by:A.me()||'',at:new Date().toISOString()});
      A.toast('Đã gửi yêu cầu, chờ quản lý duyệt.');A.render()}})}

function xinNhanCa(rid){
  var r=A.find('roster',rid);if(!r||r.staffId)return;
  var me=toiLa();if(!me){A.toast('Tài khoản của bạn chưa gắn với hồ sơ nhân sự.',true);return}
  if(choDuyet('').some(function(x){return x.rosterId===rid&&x.den===me})){A.toast('Bạn đã xin nhận ca này rồi.',true);return}
  if(!confirm('Xin nhận ca '+r.ca+' ngày '+A.fmtD(r.ngay)+' tại '+r.coso+'?'))return;
  A.save('swaps',{id:A.uid(),loai:'nhan',rosterId:r.id,tu:'',den:me,coso:r.coso,ngay:r.ngay,ca:r.ca,lyDo:'',tt:'cho',by:A.me()||'',at:new Date().toISOString()});
  A.toast('Đã xin nhận ca, chờ quản lý duyệt.');A.render()}

function duyetDoiCa(id,dongY){
  if(!A.needRole('checkin.edit','Chỉ Quản lý trở lên mới duyệt được.'))return;
  var x=A.find('swaps',id);if(!x||x.tt!=='cho')return;
  var o=Object.assign({},x,{tt:dongY?'duyet':'tuchoi',quyetBoi:A.me()||'',quyetLuc:new Date().toISOString()});
  if(dongY){
    var r=A.find('roster',x.rosterId);
    if(!r){A.toast('Ca này đã bị xoá khỏi lịch.',true);o.tt='tuchoi';A.save('swaps',o);A.render();return}
    var r2=Object.assign({},r,{staffId:x.loai==='tra'?'':x.den});
    A.save('roster',r2);
    /* các yêu cầu khác trên cùng ca thì hết cửa */
    choDuyet('').forEach(function(y){if(y.id!==x.id&&y.rosterId===x.rosterId)A.save('swaps',Object.assign({},y,{tt:'tuchoi',quyetBoi:A.me()||'',quyetLuc:new Date().toISOString(),ghiChu:'ca đã được duyệt cho yêu cầu khác'}))})}
  A.save('swaps',o);
  A.toast(dongY?'Đã duyệt, lịch ca đã đổi.':'Đã từ chối.');A.render()}

/* =========================================================
   BÁO CÁO NGÀY CƠ SỞ (mượn Kintai): giờ công đặt cạnh doanh thu
   revenue : đọc thẳng từ FABi {id, ngay, coso, dt, tt, hd}  (không sửa được)
   dailyrev: nhập tay {id, ngay, coso, dt}  — chỉ dùng khi FABi không có số
   ========================================================= */
function slugCs(s){return String(s||'').normalize('NFD').replace(/[̀-ͯ]/g,'').replace(/đ/gi,'d').toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'')}
function donGiaGio(){return Number(A.setting('baoCaoNgay',{donGia:25000}).donGia)||25000}
function doanhThu(cs,ngay){
  var f=A.col('revenue').filter(function(r){return r.ngay===ngay&&norm(r.coso)===norm(cs)})[0];
  if(f)return {dt:Number(f.dt)||0,hd:Number(f.hd)||0,nguon:'fabi'};
  var m=A.find('dailyrev','dr_'+ngay.replace(/-/g,'')+'_'+slugCs(cs));
  return m?{dt:Number(m.dt)||0,hd:0,nguon:'tay'}:null}
function congNgayCoSo(cs,ngay){
  var ss=staffOfOffice(cs),cyc=ngay.slice(0,7),d=+ngay.slice(8,10),m=0,coMat=0;
  ss.forEach(function(s){var a=att(s.id,cyc),o=oTuDoc(a,d),r=a&&a.days&&a.days[A.pad(d)];
    if(o)m+=o.m;if((r&&r.in)||o)coMat++});
  return {m:m,coMat:coMat,n:ss.length}}
function vnd(n){return Math.round(Number(n)||0).toLocaleString('vi-VN')+' ₫'}

function bcNgayHtml(){
  var css=A.officeAll(),dg=donGiaGio(),thang=C.date.slice(0,7);
  var ngayDs=[C.date];
  if(C.caThang){ngayDs=[];var n=daysIn(thang);for(var i=1;i<=n;i++){var k=thang+'-'+A.pad(i);if(k<=A.ymd(new Date()))ngayDs.push(k)}}
  var tong={m:0,dt:0,nc:0,coMat:0},rows=css.map(function(cs){
    var m=0,dt=0,coMat=0,coDt=false,nguon='';
    ngayDs.forEach(function(k){var c=congNgayCoSo(cs,k);m+=c.m;coMat+=c.coMat;var r=doanhThu(cs,k);if(r){dt+=r.dt;coDt=true;nguon=nguon||r.nguon}});
    var nc=m/60*dg;tong.m+=m;tong.dt+=dt;tong.nc+=nc;tong.coMat+=coMat;
    return {cs:cs,m:m,dt:dt,nc:nc,coMat:coMat,coDt:coDt,nguon:nguon,n:staffOfOffice(cs).length}});
  rows.sort(function(a,b){return b.dt-a.dt||b.m-a.m});
  var pct=tong.dt?Math.round(tong.nc/tong.dt*1000)/10:null;
  var h='<div class="pad grid g4" style="padding-bottom:0">'+
    A.tile('Doanh thu',vnd(tong.dt),'<span>'+(C.caThang?'tháng '+esc(thang)+' tới hôm nay':'ngày '+esc(A.fmtD(C.date)))+' · '+css.length+' cơ sở</span>')+
    A.tile('Giờ công',A.gioPhut(tong.m),'<span>'+tong.coMat+' lượt có mặt</span>')+
    A.tile('Chi phí nhân công',vnd(tong.nc),'<span>ước theo đơn giá '+vnd(dg)+'/giờ'+(A.can('device.edit')?' · <button class="linkbtn" type="button" data-cact="dongia">đổi</button>':'')+'</span>')+
    A.tile('NC / Doanh thu',pct===null?'—':pct+'%','<span>'+(pct===null?'chưa có doanh thu':pct>35?'cao — soát lại lịch ca':'trong mức thường')+'</span>',pct!==null&&pct>35?'var(--red)':pct!==null?'var(--green)':'')+
    '</div><div class="pad"><div class="tbl-wrap"><table class="t bcn"><thead><tr><th>Cơ sở</th><th class="n">Nhân sự</th><th class="n">Có mặt</th><th class="n">Giờ công</th>'+
    '<th class="n">Doanh thu</th><th class="n">Chi phí NC</th><th style="width:160px">NC / DT</th><th></th></tr></thead><tbody>';
  rows.forEach(function(r){
    var p=r.dt?Math.round(r.nc/r.dt*1000)/10:null;
    h+='<tr><td><b style="font-weight:500">'+esc(r.cs)+'</b></td><td class="n">'+r.n+'</td><td class="n">'+r.coMat+'</td>'+
      '<td class="n">'+esc(A.gioPhut(r.m))+'</td>'+
      '<td class="n">'+(r.coDt?esc(vnd(r.dt))+' <span class="chip '+(r.nguon==='fabi'?'info':'soft')+'">'+(r.nguon==='fabi'?'FABi':'nhập tay')+'</span>':'<span class="by">—</span>')+'</td>'+
      '<td class="n">'+esc(vnd(r.nc))+'</td>'+
      '<td>'+(p===null?'<span class="by">—</span>':A.wbar([{v:Math.min(p,100),c:p>35?'var(--red)':'var(--green)'}],100)+' <span class="num" style="font-size:12px">'+p+'%</span>')+'</td>'+
      '<td class="n">'+(!C.caThang&&r.nguon!=='fabi'&&A.canFor('checkin.edit',null)?'<button class="linkbtn" type="button" data-nhapdt="'+esc(r.cs)+'">'+(r.coDt?'Sửa DT':'Nhập DT')+'</button>':'')+'</td></tr>'});
  if(!rows.length)h+='<tr><td colspan="8" style="padding:18px;color:var(--muted)">Chưa có cơ sở nào. Khai cơ sở cho nhân sự trước.</td></tr>';
  h+='</tbody><tfoot><tr><td><b>'+rows.length+' cơ sở</b></td><td class="n">'+rows.reduce(function(a,r){return a+r.n},0)+'</td><td class="n">'+tong.coMat+'</td>'+
    '<td class="n">'+esc(A.gioPhut(tong.m))+'</td><td class="n">'+esc(vnd(tong.dt))+'</td><td class="n">'+esc(vnd(tong.nc))+'</td><td>'+(pct===null?'—':pct+'%')+'</td><td></td></tr></tfoot></table></div>'+
    '<p class="by" style="margin-top:10px">Doanh thu lấy thẳng từ Báo Cáo Doanh Thu FABi khi có; cơ sở nào FABi chưa có số thì nhập tay. Chi phí nhân công = giờ công × đơn giá giờ, là số ước để so giữa các cơ sở, không phải bảng lương.</p></div>';
  return h}

function nhapDoanhThu(cs){
  if(!A.needRole('checkin.edit','Chỉ Quản lý trở lên mới nhập doanh thu.'))return;
  var css=A.officeAll(),ngay=C.date;
  var hien=cs?doanhThu(cs,ngay):null;
  if(hien&&hien.nguon==='fabi'){A.toast('Cơ sở này đã có số từ FABi, không nhập tay đè lên.',true);return}
  A.form({title:'Doanh thu ngày '+A.fmtD(ngay),
    fields:[{k:'cs',label:'Cơ sở',type:'select',value:cs||css[0]||'',options:css.map(function(c){return {v:c,l:c}})},
      {k:'dt',label:'Doanh thu (đồng)',type:'number',value:hien?hien.dt:'',required:true,step:'1000'}],
    onSave:function(v){
      var id='dr_'+ngay.replace(/-/g,'')+'_'+slugCs(v.cs);
      A.save('dailyrev',{id:id,ngay:ngay,coso:v.cs,dt:Number(v.dt)||0,by:A.me()||'',at:new Date().toISOString()});A.render()}})}

function doiDonGia(){
  if(!A.needRole('device.edit','Chỉ Quản trị mới đổi đơn giá giờ.'))return;
  A.form({title:'Đơn giá giờ công',note:'Dùng để ước chi phí nhân công trên báo cáo ngày. Không ảnh hưởng bảng lương.',
    fields:[{k:'donGia',label:'Đồng / giờ',type:'number',value:donGiaGio(),required:true,step:'1000'}],
    onSave:function(v){A.save('settings',{id:'baoCaoNgay',donGia:Number(v.donGia)||25000});A.render()}})}

/* Sửa giá trị công của một ô. */
function editCong(sid,d){
  var s=A.find('staff',sid);
  if(!s)return;
  if(!A.needRoleFor('checkin.edit',s,'Chỉ Quản lý trở lên mới sửa được bảng công.'))return;
  var r=dayRec(sid,C.cyc,d)||{},o=oCong(sid,d);
  A.form({title:'Công ngày '+d+'/'+C.cyc.slice(5)+' · '+s.name,
    note:o&&!o.tay
      ?'Đang tự tính '+gioNgan(o.m)+'h từ giờ vào '+o.r.in+' – ra '+o.r.out+
       '. Lưu lại là chốt con số này, máy chấm công đổi cũng không ghi đè nữa.'
      :'Bảng công chỉ giữ số giờ công và mã ca. Giờ vào/ra nằm ở Dữ liệu chấm công.',
    fields:[{k:'gio',label:'Giờ công',value:o?gioNgan(o.m):'',half:true,ph:'4.9 hoặc 4:56'},
      {k:'ca',label:'Mã ca',value:(o?o.ca:'')||'',half:true,ph:'VD: C1-C2'},
      {k:'note',label:'Ghi chú',value:r.note||''}],
    actions:r.m?[{label:'Xoá số nhập tay',cls:'ghost danger',fn:function(){
      var o=Object.assign({},r);delete o.m;delete o.ca;
      setDay(sid,C.cyc,d,Object.keys(o).length?o:null);A.render()}}]:[],
    onSave:function(v){
      var m=A.docGio(v.gio);
      var o=Object.assign({},r);
      if(m)o.m=m;else delete o.m;
      o.ca=v.ca;o.note=v.note;
      if(!o.ca)delete o.ca;
      if(!o.note)delete o.note;
      setDay(sid,C.cyc,d,Object.keys(o).length?o:null);
      A.render()}})}

/* Nhập công hàng loạt: cả cơ sở hoặc một người, từ ngày đến ngày. */
function fillCong(){
  var cs=coSo(),ss=staffOfOffice(cs),n=daysIn(C.cyc);
  if(!ss.length){A.toast('Cơ sở này chưa có nhân sự nào.',true);return}
  if(!A.needRole('checkin.edit','Chỉ Quản lý trở lên mới nhập được bảng công.'))return;
  A.form({title:'Nhập công hàng loạt',
    note:'Điền cùng một số giờ cho nhiều ngày. Ngày đã có công sẽ bị ghi đè.',
    fields:[{k:'ai',label:'Cho ai',type:'select',value:'*',
       options:[{v:'*',l:'Cả cơ sở '+cs+' ('+ss.length+' người)'}].concat(ss.map(function(s){
         return {v:s.id,l:s.name}}))},
      {k:'tu',label:'Từ ngày',type:'number',value:1,half:true,step:'1'},
      {k:'den',label:'Đến ngày',type:'number',value:n,half:true,step:'1'},
      {k:'gio',label:'Giờ công mỗi ngày',value:'8',half:true,ph:'8 hoặc 7:30'},
      {k:'ca',label:'Mã ca',value:'',half:true,ph:'VD: C1-C2'},
      {k:'cuoituan',label:'Cuối tuần',type:'checkbox',value:false,cbLabel:'Điền cả thứ bảy và chủ nhật'}],
    saveLabel:'Điền công',
    onSave:function(v){
      var m=A.docGio(v.gio);
      if(!m){A.toast('Chưa nhập số giờ công.',true);return false}
      var tu=Math.max(1,Math.min(n,+v.tu||1)),den=Math.max(1,Math.min(n,+v.den||n));
      if(den<tu){A.toast('Ngày kết thúc phải sau ngày bắt đầu.',true);return false}
      var ds=v.ai==='*'?ss:ss.filter(function(s){return s.id===v.ai});
      var dem=0;
      ds.forEach(function(s){
        for(var d=tu;d<=den;d++){
          var w=thu(d);
          if(!v.cuoituan&&(w===0||w===6))continue;
          var o=Object.assign({},dayRec(s.id,C.cyc,d)||{});
          o.m=m;
          if(v.ca)o.ca=v.ca;
          setDay(s.id,C.cyc,d,o);dem++}});
      A.toast('Đã điền công cho '+dem+' ngày của '+ds.length+' người.');
      A.render()}})}

function logHtml(){
  var cyc=C.date.slice(0,7),d=+C.date.slice(8,10);
  var rows=staffList().map(function(s){return {s:s,r:dayRec(s.id,cyc,d)}});
  var inCount=rows.filter(function(x){return x.r&&x.r.in}).length;
  var lateCount=rows.filter(function(x){return x.r&&(x.r.late||0)>0}).length;
  var h='<div class="pad grid g4" style="padding-bottom:0">'+
    A.tile('Có mặt',inCount+'/'+rows.length,'<span>ngày '+esc(A.fmtD(C.date))+'</span>')+
    A.tile('Đi muộn',lateCount,'<span>trên tổng số có mặt</span>',lateCount?'var(--amber)':'')+
    A.tile('Vắng',rows.filter(function(x){return !x.r||!x.r.in}).length,'<span>chưa ghi nhận chấm công</span>')+
    A.tile('Thiết bị',A.col('devices').filter(function(x){return x.online}).length+'/'+A.col('devices').length,'<span>đang online</span>')+
    '</div><div class="pad"><div class="tbl-wrap"><table class="t"><thead><tr>'+
    '<th>Nhân sự</th><th>Ảnh</th><th>Giờ vào</th><th>Giờ ra</th><th class="n">Đi muộn</th><th>Thiết bị</th><th>Phương thức</th><th>Ghi chú</th><th></th></tr></thead><tbody>';
  rows.forEach(function(x){
    var r=x.r;
    h+='<tr><td><span style="display:flex;gap:8px;align-items:center">'+A.av(x.s.name,'s',x.s.color)+
      '<span><b style="font-weight:500">'+esc(x.s.name)+'</b><span class="sub" style="display:block">'+esc(x.s.code||'')+'</span></span></span></td>'+
      '<td>'+(r&&r.photo?'<button type="button" data-ckface="'+x.s.id+'" title="Đối chiếu với ảnh mẫu">'+
        '<img src="'+esc(r.photo)+'" alt="Ảnh chấm công '+esc(x.s.name)+'" '+
        'style="width:34px;height:34px;object-fit:cover;border-radius:3px;border:2px solid '+
        (r.verified==='ok'?'var(--green)':r.verified==='doubt'?'var(--red)':'var(--line)')+'"></button>'
        :'<span class="by">—</span>')+'</td>'+
      '<td class="num">'+(r&&r.in?'<b style="font-weight:500">'+esc(r.in)+'</b>':'<span class="by">—</span>')+'</td>'+
      '<td class="num">'+(r&&r.out?esc(r.out):'<span class="by">—</span>')+'</td>'+
      '<td class="n">'+(r&&r.late?'<span style="color:var(--amber)">'+r.late+" phút</span>":'0')+'</td>'+
      '<td class="sub">'+esc(r&&r.device||'—')+'</td>'+
      '<td>'+(r&&r.method?'<span class="chip info">'+esc(r.method)+'</span>':'<span class="by">—</span>')+'</td>'+
      '<td class="sub">'+(r&&r.leave?'<span class="chip amb">Nghỉ phép</span>':
        (r&&r.verified==='ok'?'<span class="chip ok">Đã đối chiếu</span> ':
         r&&r.verified==='doubt'?'<span class="chip hi">Nghi ngờ</span> ':'')+esc(r&&r.note||''))+'</td>'+
      '<td class="n"><button class="linkbtn" type="button" data-ckedit="'+x.s.id+'">Sửa</button></td></tr>'});
  if(!rows.length)h+='<tr><td colspan="9" style="padding:20px;color:var(--muted)">Chưa có nhân sự nào. Thêm nhân sự ở ứng dụng Hồ sơ nhân sự.</td></tr>';
  return h+'</tbody></table></div></div>'}

function devicesHtml(){
  var ds=A.col('devices');
  return '<div class="pad"><div class="grid g3">'+(ds.length?ds.map(function(d){
    var cnt=A.col('attendance').reduce(function(n,a){
      return n+Object.keys(a.days||{}).filter(function(k){return a.days[k].device===d.name}).length},0);
    return '<div class="card-box"><div style="display:flex;gap:10px;align-items:center">'+
      '<span class="av l" style="background:'+(d.online?'var(--green)':'var(--muted)')+'">'+A.icon('checkin','')+'</span>'+
      '<div><div style="font-weight:500">'+esc(d.name)+'</div>'+
      '<div class="by">'+esc(d.place||'')+'</div></div></div>'+
      '<div class="metarow"><span class="k">Tình trạng</span><span class="v">'+
        (d.online?'<span class="chip done">Online</span>':'<span class="chip late">Mất kết nối</span>')+'</span></div>'+
      '<div class="metarow"><span class="k">Firmware</span><span class="v num">'+esc(d.fw||'—')+'</span></div>'+
      '<div class="metarow"><span class="k">Kết nối</span><span class="v">'+esc(d.link||'WiFi')+'</span></div>'+
      '<div class="metarow"><span class="k">Lượt chấm công</span><b class="v num">'+cnt+'</b></div>'+
      '<div style="margin-top:10px;display:flex;gap:8px">'+
      '<button class="btn sm ghost" type="button" data-devedit="'+d.id+'">Sửa</button></div></div>'}).join('')
    :'<p class="empty">Chưa khai máy chấm công nào. Mỗi máy ESP32 ở cơ sở khai một dòng ở đây.</p>')+'</div></div>'}

function shiftsHtml(){
  var d=caLuat(),L=Object.assign({},LUAT_MAC_DINH,d.luat||{}),cs=A.officeAll(),sua=A.can('device.edit');
  function dongLuat(o,coso){
    return '<tr><td><b style="font-weight:500">'+esc(coso||'Mặc định')+'</b></td>'+
      '<td class="n">'+o.vaoSom+'′ / '+o.raMuon+'′</td><td class="n">'+o.anHanMuon+'′ / '+o.anHanSom+'′</td>'+
      '<td class="n">&lt; '+o.nguongVang+'h vắng · &lt; '+o.nguongNua+'h nửa</td>'+
      '<td>'+(o.ngayNghi&&o.ngayNghi.length?o.ngayNghi.map(function(w){return TUAN[w]}).join(', '):'Làm cả tuần')+'</td>'+
      (sua?'<td class="n"><button class="linkbtn" type="button" data-luat="'+esc(coso||'*')+'">Sửa</button>'+
        (coso?' <button class="linkbtn" type="button" data-boluat="'+esc(coso)+'">Bỏ</button>':'')+'</td>':'<td></td>')+'</tr>'}
  return '<div class="pad" style="max-width:980px"><div class="card-box">'+
    '<div class="sec-h"><div><h3>Ca chuẩn</h3><p class="ds">Dùng để nhận ra ca từ giờ vào, tính đi muộn / về sớm và giờ công khi chỉ có giờ vào/ra</p></div>'+
    (sua?'<button class="btn sm ghost" type="button" data-cact="themca">+ Thêm ca</button>':'')+'</div>'+
    shifts().map(function(c){
      return '<div class="mini"><span class="chip info">'+esc(c.id)+'</span><span class="t">'+esc(c.name)+'</span>'+
        '<span class="num">'+esc(c.in)+' – '+esc(c.out)+'</span>'+
        (sua?'<button class="linkbtn" type="button" data-suaca="'+esc(c.id)+'">Sửa</button>':'')+'</div>'}).join('')+
    '</div><div class="card-box" style="margin-top:14px">'+
    '<div class="sec-h"><div><h3>Luật tính công</h3><p class="ds">Theo mẫu Frappe HR. Cơ sở nào không khai riêng thì dùng dòng Mặc định.</p></div>'+
    (sua?'<button class="btn sm ghost" type="button" data-luat="+">+ Luật riêng cho cơ sở</button>':'')+'</div>'+
    '<div class="tbl-wrap"><table class="t"><thead><tr><th>Áp cho</th><th class="n">Vào sớm / ra muộn tối đa</th>'+
    '<th class="n">Ân hạn muộn / sớm</th><th class="n">Ngưỡng giờ làm</th><th>Ngày nghỉ</th><th></th></tr></thead><tbody>'+
    dongLuat(L,'')+Object.keys(d.theoCoSo||{}).sort().map(function(k){return dongLuat(Object.assign({},L,d.theoCoSo[k]),k)}).join('')+
    '</tbody></table></div>'+
    '<p class="by" style="margin-top:10px">Cách xếp loại một ngày: có đơn nghỉ → <b>Nghỉ phép</b>. Không có giờ nào: rơi vào ngày nghỉ → <b>Ngày nghỉ</b>, còn lại → <b>Vắng</b>. '+
    'Có giờ: dưới ngưỡng vắng → <b>Vắng</b>, dưới ngưỡng nửa → <b>Nửa công</b>, còn lại → <b>Đủ công</b>. Đi muộn / về sớm chỉ gắn cờ, không đổi loại.</p>'+
    '</div></div>'}

function luuCaLuat(o){
  if(!A.needRole('device.edit','Chỉ Quản trị mới sửa được ca và luật tính công.'))return false;
  var d=Object.assign({},caLuat(),o);d.id='caLuat';
  A.save('settings',d);A.render();return true}

function suaCa(id){
  var ds=shifts().slice(),c=id?ds.filter(function(x){return x.id===id})[0]:null;
  A.form({title:c?'Sửa ca '+c.id:'Thêm ca',
    fields:[{k:'id',label:'Mã ca',required:true,value:c?c.id:'C'+(ds.length+1),half:true,ph:'C3'},
      {k:'name',label:'Tên ca',required:true,value:c?c.name:'',half:true,ph:'Ca tối'},
      {k:'in',label:'Bắt đầu',type:'time',required:true,value:c?c.in:'18:00',half:true},
      {k:'out',label:'Kết thúc',type:'time',required:true,value:c?c.out:'22:00',half:true}],
    onDelete:c&&ds.length>1?function(){luuCaLuat({ca:ds.filter(function(x){return x.id!==c.id})})}:null,
    deleteLabel:'Xoá ca',confirmDelete:'Xoá ca này? Công đã tính không đổi, chỉ ngày sau không nhận ca này nữa.',
    onSave:function(v){
      var moi={id:v.id.toUpperCase(),name:v.name,in:v.in,out:v.out};
      if(phutGio(moi.out)<=phutGio(moi.in)){A.toast('Giờ kết thúc phải sau giờ bắt đầu.',true);return false}
      var out=c?ds.map(function(x){return x.id===c.id?moi:x}):ds.concat([moi]);
      return luuCaLuat({ca:out})}})}

function suaLuat(key){
  var d=caLuat(),macDinh=Object.assign({},LUAT_MAC_DINH,d.luat||{});
  var coso=key==='*'?'':key==='+'?'':key,o=coso?Object.assign({},macDinh,(d.theoCoSo||{})[coso]||{}):macDinh;
  var f=[{k:'vaoSom',label:'Vào sớm tối đa (phút)',type:'number',value:o.vaoSom,half:true},
    {k:'raMuon',label:'Ra muộn tối đa (phút)',type:'number',value:o.raMuon,half:true},
    {k:'anHanMuon',label:'Ân hạn đi muộn (phút)',type:'number',value:o.anHanMuon,half:true},
    {k:'anHanSom',label:'Ân hạn về sớm (phút)',type:'number',value:o.anHanSom,half:true},
    {k:'nguongVang',label:'Dưới bao nhiêu giờ là Vắng',type:'number',value:o.nguongVang,half:true,step:'0.5'},
    {k:'nguongNua',label:'Dưới bao nhiêu giờ là Nửa công',type:'number',value:o.nguongNua,half:true,step:'0.5'},
    {k:'ngayNghi',label:'Ngày nghỉ trong tuần',type:'chips',value:String((o.ngayNghi||[]).length?o.ngayNghi[0]:''),
     options:[{v:'',l:'Làm cả tuần'},{v:'0',l:'Chủ nhật'},{v:'6',l:'Thứ bảy'},{v:'06',l:'T7 + CN'}]}];
  if(key==='+')f.unshift({k:'coso',label:'Cơ sở',type:'datalist',options:A.officeAll(),required:true,ph:'Chọn cơ sở'});
  A.form({title:key==='*'?'Luật mặc định':key==='+'?'Luật riêng cho cơ sở':'Luật riêng · '+coso,
    note:'Ngưỡng tính trên giờ công thật của ngày. Đổi luật không đổi số giờ, chỉ đổi cách xếp loại.',
    fields:f,
    onSave:function(v){
      var ng=v.ngayNghi==='06'?[0,6]:v.ngayNghi===''?[]:[+v.ngayNghi];
      var l={vaoSom:+v.vaoSom||0,raMuon:+v.raMuon||0,anHanMuon:+v.anHanMuon||0,anHanSom:+v.anHanSom||0,
        nguongVang:+v.nguongVang||0,nguongNua:+v.nguongNua||0,ngayNghi:ng};
      if(l.nguongNua<l.nguongVang){A.toast('Ngưỡng nửa công phải lớn hơn hoặc bằng ngưỡng vắng.',true);return false}
      if(key==='*')return luuCaLuat({luat:l});
      var cs=key==='+'?A.officeCanon(v.coso):coso;
      if(!cs){A.toast('Chọn cơ sở.',true);return false}
      var t=Object.assign({},d.theoCoSo||{});t[cs]=l;
      return luuCaLuat({theoCoSo:t})}})}

function boLuat(coso){
  if(!confirm('Bỏ luật riêng của '+coso+'? Cơ sở này sẽ dùng luật mặc định.'))return;
  var t=Object.assign({},caLuat().theoCoSo||{});delete t[coso];luuCaLuat({theoCoSo:t})}

function editCheck(staffId){
  var s=A.find('staff',staffId);
  if(!A.needRoleFor('checkin.edit',s,'Chỉ Quản lý trở lên mới sửa được dữ liệu chấm công.'))return;
  var cyc=C.date.slice(0,7),d=+C.date.slice(8,10);
  var r=dayRec(staffId,cyc,d)||{};
  A.form({title:'Chấm công — '+(s?s.name:''),note:'Ngày '+A.fmtD(C.date),
    fields:[{k:'in',label:'Giờ vào',type:'time',value:r.in||'',half:true},
      {k:'out',label:'Giờ ra',type:'time',value:r.out||'',half:true},
      {k:'device',label:'Thiết bị',type:'select',value:r.device||'',half:true,
       options:[{v:'',l:'— Chọn máy —'}].concat(A.col('devices').map(function(x){return {v:x.name,l:x.name}}))},
      {k:'method',label:'Phương thức',type:'select',value:r.method||'Khuôn mặt',half:true,options:METHODS},
      {k:'leave',label:'Nghỉ',type:'select',value:r.leave||'',half:true,
       options:[{v:'',l:'Đi làm'},{v:'annual',l:'Nghỉ phép'},{v:'unpaid',l:'Nghỉ không lương'},{v:'holiday',l:'Nghỉ lễ'}]},
      {k:'note',label:'Ghi chú',value:r.note||'',half:true}],
    onDelete:(r.in||r.leave)?function(){setDay(staffId,cyc,d,null)}:null,
    deleteLabel:'Xoá bản ghi',
    onSave:function(v){
      setDay(staffId,cyc,d,{in:v.in,out:v.out,device:v.device,method:v.method,leave:v.leave,note:v.note,
        photo:r.photo||'',verified:r.verified||'',verifiedBy:r.verifiedBy||'',verifiedAt:r.verifiedAt||'',late:v.leave?0:muonTheoLuat(v.in,coSoCuaNV(staffId))})}})}

/* ---------- đối chiếu ảnh chấm công với ảnh mẫu ---------- */
function faceCheck(staffId){
  var cyc=C.date.slice(0,7),d=+C.date.slice(8,10);
  var s=A.find('staff',staffId),r=dayRec(staffId,cyc,d);
  if(!s||!r||!r.photo)return;
  function box(src,lab,sub){
    return '<div style="flex:1;min-width:180px"><p class="sect-t">'+lab+'</p>'+
      (src?'<img src="'+esc(src)+'" alt="'+lab+'" style="width:100%;aspect-ratio:1;object-fit:cover;border-radius:4px;border:1px solid var(--line)">'
        :'<div style="aspect-ratio:1;border:1px dashed var(--line-2);border-radius:4px;display:grid;place-items:center;color:var(--muted);text-align:center;padding:10px">Chưa có ảnh mẫu trong hồ sơ</div>')+
      '<p class="by" style="margin-top:6px">'+esc(sub)+'</p></div>'}
  A.form({title:'Đối chiếu khuôn mặt — '+s.name,wide:true,fields:[],
    cancelLabel:'Đóng',
    actions:[{label:'Nghi ngờ, cần kiểm lại',cls:'danger',fn:function(){
        setDay(staffId,cyc,d,Object.assign({},r,{verified:'doubt'}));
        A.toast('Đã đánh dấu nghi ngờ lượt chấm công này.')}},
      {label:'Đúng người',cls:'green',fn:function(){
        setDay(staffId,cyc,d,Object.assign({},r,{verified:'ok',verifiedBy:A.me()||'',verifiedAt:new Date().toISOString()}));
        A.toast('Đã xác nhận đúng người.')}}],
    extra:'<div style="display:flex;gap:14px;flex-wrap:wrap">'+
      box(s.face&&s.face.url,'Ảnh mẫu trong hồ sơ',s.name+' · '+(s.code||''))+
      box(r.photo,'Ảnh lúc chấm công',A.fmtD(C.date)+' · '+(r.in||'')+' · '+(r.device||''))+
      '</div><p class="by" style="margin-top:12px">Việc nhận diện khuôn mặt do máy chấm công ngoài hiện trường thực hiện. '+
      'Màn hình này để người phụ trách đối chiếu bằng mắt và ghi lại kết luận.</p>'+
      (r.verified?'<p style="margin-top:8px"><span class="chip '+(r.verified==='ok'?'ok':'hi')+'">'+
        (r.verified==='ok'?'Đã xác nhận đúng người':'Đang nghi ngờ')+'</span> '+
        (r.verifiedBy?'<span class="by">bởi '+esc(r.verifiedBy)+' · '+esc(A.ago(r.verifiedAt))+'</span>':'')+'</p>':'')})}

/* ---------- chấm công bằng camera ---------- */
var camStream=null;
function stopCam(){if(camStream){camStream.getTracks().forEach(function(t){t.stop()});camStream=null}}
function camDialog(){
  var ss=staffList();
  if(!ss.length){A.toast('Chưa có nhân sự nào.',true);return}
  var dlg=document.createElement('dialog');
  dlg.innerHTML='<form method="dialog"><div class="dlg-h"><h3>Chấm công bằng camera</h3>'+
    '<button type="button" class="icon-btn" data-x="close" aria-label="Đóng">✕</button></div>'+
    '<div class="dlg-b">'+
    '<p style="margin:0;color:var(--muted)">Ảnh chụp được lưu kèm lượt chấm công để đối chiếu sau. '+
    'Máy chấm công ngoài hiện trường vẫn là nơi nhận diện khuôn mặt — trang này chỉ ghi nhận và lưu ảnh.</p>'+
    '<div style="position:relative;background:#0B1622;border-radius:4px;overflow:hidden;aspect-ratio:4/3;max-width:100%">'+
      '<video id="camV" autoplay playsinline muted style="width:100%;height:100%;object-fit:cover"></video>'+
      '<canvas id="camC" style="display:none"></canvas>'+
      '<img id="camP" alt="Ảnh vừa chụp" style="display:none;width:100%;height:100%;object-fit:cover">'+
      '<p id="camErr" style="display:none;position:absolute;inset:0;display:none;place-items:center;color:#9FBBD6;padding:20px;text-align:center"></p>'+
    '</div>'+
    '<div class="two">'+
    '<div class="fld"><label for="camS">Nhân sự</label><select id="camS">'+
      ss.map(function(s){return '<option value="'+s.id+'">'+esc(s.name)+'</option>'}).join('')+'</select></div>'+
    '<div class="fld"><label for="camD">Thiết bị ghi nhận</label><select id="camD">'+
      '<option value="Camera trên trang web">Camera trên trang web</option>'+
      A.col('devices').map(function(d){return '<option value="'+esc(d.name)+'">'+esc(d.name)+'</option>'}).join('')+
      '</select></div></div>'+
    '<div class="fld"><label for="camF">Hoặc chọn ảnh có sẵn</label><input id="camF" type="file" accept="image/*"></div>'+
    '</div><div class="dlg-f"><span class="spacer"></span>'+
    '<button type="button" class="btn ghost" data-x="shot">Chụp ảnh</button>'+
    '<button type="button" class="btn" data-x="save">Ghi nhận chấm công</button></div></form>';
  document.body.appendChild(dlg);
  var blob=null;
  var v=A.$('#camV',dlg),cv=A.$('#camC',dlg),img=A.$('#camP',dlg),err=A.$('#camErr',dlg);
  function fail(msg){
    v.style.display='none';err.style.display='grid';
    err.textContent=msg+' Bạn vẫn có thể chọn ảnh có sẵn ở ô bên dưới.'}
  if(navigator.mediaDevices&&navigator.mediaDevices.getUserMedia){
    navigator.mediaDevices.getUserMedia({video:{facingMode:'user'},audio:false}).then(function(st){
      camStream=st;v.srcObject=st},function(){fail('Không mở được camera (trình duyệt chặn hoặc máy không có camera).')})}
  else fail('Trình duyệt này không cho phép mở camera.');
  A.$('#camF',dlg).addEventListener('change',function(){
    var f=this.files&&this.files[0];if(!f)return;
    blob=f;img.src=URL.createObjectURL(f);img.style.display='block';v.style.display='none';err.style.display='none'});
  dlg.addEventListener('click',function(e){
    var b=e.target.closest('[data-x]');if(!b)return;
    var x=b.getAttribute('data-x');
    if(x==='close'){close();return}
    if(x==='shot'){
      if(!camStream){A.toast('Camera chưa sẵn sàng — chọn ảnh có sẵn giúp mình.',true);return}
      cv.width=v.videoWidth||640;cv.height=v.videoHeight||480;
      cv.getContext('2d').drawImage(v,0,0,cv.width,cv.height);
      cv.toBlob(function(bl){
        blob=bl;img.src=URL.createObjectURL(bl);img.style.display='block';v.style.display='none'},'image/jpeg',0.82);
      return}
    if(x==='save'){
      var sid=A.$('#camS',dlg).value,dev=A.$('#camD',dlg).value;
      if(!blob){A.toast('Chụp ảnh hoặc chọn ảnh trước đã.',true);return}
      b.disabled=true;b.textContent='Đang lưu…';
      upload(blob).then(function(url){
        var now=new Date(),cyc=A.ymd(now).slice(0,7),d=now.getDate();
        var old=dayRec(sid,cyc,d)||{};
        var inn=old.in||A.pad(now.getHours())+':'+A.pad(now.getMinutes());
        var rec={in:inn,out:old.in?A.pad(now.getHours())+':'+A.pad(now.getMinutes()):(old.out||''),
          device:dev,method:'Khuôn mặt',leave:'',note:old.note||'',photo:url||old.photo||'',
          late:muonTheoLuat(inn,coSoCuaNV(sid)),verified:''};
        setDay(sid,cyc,d,rec);
        C.date=A.ymd(now);
        A.toast(url?'Đã ghi nhận chấm công kèm ảnh.':'Đã ghi nhận chấm công (không lưu được ảnh trên bản này).');
        close()},function(){
        b.disabled=false;b.textContent='Ghi nhận chấm công';
        A.toast('Không lưu được ảnh. Thử lại hoặc ghi nhận thủ công.',true)});
      return}});
  function close(){stopCam();dlg.close();dlg.remove();A.render()}
  dlg.addEventListener('cancel',function(){stopCam();setTimeout(function(){dlg.remove()},0)});
  dlg.showModal()}

function upload(blob){
  var c=window.claude;
  if(!c||typeof c.use!=='function')return Promise.resolve('');
  return c.use('assets').then(function(as){
    if(!as||!as.upload)return '';
    return as.upload(blob).then(function(r){return (r&&r.url)||''},function(){return ''})},function(){return ''})}

function editDevice(id){
  if(!A.needRole('device.edit','Chỉ Quản trị mới khai được máy chấm công.'))return;
  var d=id?A.find('devices',id):null;
  A.form({title:d?'Sửa máy chấm công':'Thêm máy chấm công',
    fields:[{k:'name',label:'Tên máy',required:true,value:d?d.name:'',ph:'VD: Máy cơ sở Tân Bình'},
      {k:'place',label:'Vị trí lắp',value:d?(d.place||''):'',half:true,ph:'Cổng chính tầng 1'},
      {k:'fw',label:'Phiên bản firmware',value:d?(d.fw||''):'',half:true,ph:'2026-09-11-abc1234'},
      {k:'link',label:'Kết nối',type:'select',value:d?(d.link||'WiFi'):'WiFi',half:true,options:['WiFi','4G','LAN']},
      {k:'online',label:'Tình trạng',type:'select',value:d?(d.online?'1':'0'):'1',half:true,
       options:[{v:'1',l:'Online'},{v:'0',l:'Mất kết nối'}]}],
    onDelete:d?function(){A.remove('devices',d.id)}:null,confirmDelete:'Xoá máy chấm công này?',
    onSave:function(v){
      var o=d?Object.assign({},d):{id:A.uid()};
      o.name=v.name;o.place=v.place;o.fw=v.fw;o.link=v.link;o.online=v.online==='1';
      A.save('devices',o)}})}

function cAfter(){
  var root=A.$('#appRoot');
  root.addEventListener('click',function(e){
    var el=e.target.closest('[data-cv],[data-cact],[data-ckedit],[data-ckface],[data-devedit],[data-dev],[data-cong],[data-cs],[data-suaca],[data-luat],[data-boluat],[data-them],[data-xin],[data-nhan],[data-rid],[data-duyet],[data-nhapdt]');
    if(!el)return;var g;
    if((g=el.getAttribute('data-them'))){var q1=g.split('|');xepCa(q1[0],q1[1],null);return}
    if((g=el.getAttribute('data-xin'))){xinDoiCa(g);return}
    if((g=el.getAttribute('data-nhan'))){xinNhanCa(g);return}
    if((g=el.getAttribute('data-duyet'))){var q2=g.split(':');duyetDoiCa(q2[0],q2[1]==='1');return}
    if((g=el.getAttribute('data-nhapdt'))){nhapDoanhThu(g);return}
    if((g=el.getAttribute('data-rid'))){if(A.canFor('checkin.edit',null))xepCa(null,null,g);return}
    if((g=el.getAttribute('data-suaca'))){suaCa(g);return}
    if((g=el.getAttribute('data-luat'))){suaLuat(g);return}
    if((g=el.getAttribute('data-boluat'))){boLuat(g);return}
    if((g=el.getAttribute('data-cv'))){C.view=g;A.render();return}
    if((g=el.getAttribute('data-cs'))){C.office=g;if(C.view!=='lichca')C.view='bcong';A.render();return}
    if((g=el.getAttribute('data-cong'))){var q=g.split(':');editCong(q[0],+q[1]);return}
    if((g=el.getAttribute('data-ckface'))){faceCheck(g);return}
    if((g=el.getAttribute('data-ckedit'))){editCheck(g);return}
    if((g=el.getAttribute('data-devedit'))){editDevice(g);return}
    if((g=el.getAttribute('data-dev'))){C.view='devices';A.render();return}
    if((g=el.getAttribute('data-cact'))){
      if(g==='cam'){camDialog();return}
      if(g==='fill'){fillCong();return}
      if(g==='themca'){suaCa(null);return}
      if(g==='xepca'){xepCa(null,null,null);return}
      if(g==='nhapdt'){nhapDoanhThu('');return}
      if(g==='dongia'){doiDonGia();return}
      if(g==='tuantruoc'){C.tuan=ngayCong(C.tuan,-7);A.render();return}
      if(g==='tuansau'){C.tuan=ngayCong(C.tuan,7);A.render();return}
      if(g==='tuannay'){C.tuan=dauTuan(new Date());A.render();return}
      if(g==='newdev')editDevice(null);
      else if(g==='newck'){
        var ss=staffList();
        if(!ss.length){A.toast('Chưa có nhân sự nào.',true);return}
        A.form({title:'Ghi nhận chấm công',
          fields:[{k:'staff',label:'Nhân sự',type:'select',options:ss.map(function(s){return {v:s.id,l:s.name}})}],
          saveLabel:'Tiếp tục',onSave:function(v){setTimeout(function(){editCheck(v.staff)},60)}})}
      return}});
  var dt=A.$('#ckDate');
  if(dt)dt.addEventListener('change',function(){C.date=this.value;A.render()});
  var cs=A.$('#bcCs');
  if(cs)cs.addEventListener('change',function(){C.office=this.value;C.bp='';A.render()});
  var bp=A.$('#bcBp');
  if(bp)bp.addEventListener('change',function(){C.bp=this.value;A.render()});
  var tm=A.$('#bcTom');
  if(tm)tm.addEventListener('change',function(){C.tomTat=this.checked;A.render()});
  var bn=A.$('#bnDate');
  if(bn)bn.addEventListener('change',function(){if(this.value){C.date=this.value;A.render()}});
  var bt=A.$('#bnThang');
  if(bt)bt.addEventListener('change',function(){C.caThang=this.checked;A.render()});
  var cy=A.$('#bcCyc');
  if(cy)cy.addEventListener('change',function(){if(this.value){C.cyc=this.value;A.render()}})}

A.register({id:'checkin',name:'Chấm công',desc:'Dữ liệu từ máy chấm công',cat:'hrm',color:'#0E9AA7',icon:'checkin',
  side:true,info:false,view:cView,after:cAfter,
  onEnter:function(arg){if(typeof arg==='string'&&arg.indexOf('cs:')===0){C.office=arg.slice(3);C.bp='';C.view='bcong'}}});

/* =========================================================
   ỨNG DỤNG 2 — TIMESHEET
   ========================================================= */
var T={view:'sheet',cyc:A.ymd(new Date()).slice(0,7),sid:null};

function cycles(){
  var out=[],d=new Date();
  for(var i=0;i<12;i++){out.push(d.getFullYear()+'-'+A.pad(d.getMonth()+1));d.setMonth(d.getMonth()-1)}
  return out}

function tSide(){
  var nav=[['over','Tổng quan','timesheet'],['sheet','Bảng công','checkin'],['report','Báo cáo','flow']];
  return '<aside class="side">'+A.sideUser()+'<div class="side-list"><div class="grp"><div class="grp-b">'+
    nav.map(function(n){
      return '<button class="pitem'+(T.view===n[0]?' on':'')+'" type="button" data-tv="'+n[0]+'">'+
        A.icon(n[2],'ic')+'<span class="t">'+esc(n[1])+'</span></button>'}).join('')+
    '</div></div><div class="grp"><div class="grp-h"><span>Chu kỳ gần đây</span></div><div class="grp-b">'+
    cycles().slice(0,8).map(function(c){
      return '<button class="pitem'+(T.cyc===c?' on':'')+'" type="button" data-cyc="'+c+'">'+
        '<span class="t">'+esc(c)+' · Full-Time</span></button>'}).join('')+
    '</div></div></div></aside>'}

function tView(){
  var ss=staffList();
  if(!T.sid||!A.find('staff',T.sid))T.sid=ss.length?ss[0].id:null;
  var body;
  if(T.view==='over')body=overHtml();
  else if(T.view==='report')body=tReportHtml();
  else body=sheetHtml();
  return tSide()+'<section class="stage"><header class="topbar">'+A.navBtn()+
    '<h1>Bảng công · Cycle '+esc(T.cyc)+'</h1><span class="spacer"></span>'+
    (T.view==='sheet'?'<select id="tsStaff" style="border:1px solid var(--line);border-radius:3px;padding:5px 8px;background:var(--panel-2)" aria-label="Chọn nhân sự">'+
      ss.map(function(s){return '<option value="'+s.id+'"'+(s.id===T.sid?' selected':'')+'>'+esc(s.name)+'</option>'}).join('')+'</select>':'')+
    '<button class="btn ghost" type="button" data-tact="prev">‹</button>'+
    '<button class="btn ghost" type="button" data-tact="next">›</button>'+
    '</header><nav class="tabs"></nav><div class="content" id="content">'+body+'</div></section>'}

function legend(){
  return '<div class="legend">'+
    '<span><span class="dot" style="background:var(--green)"></span>Ngày đủ công</span>'+
    '<span><span class="dot" style="background:var(--amber)"></span>Ngày đi muộn</span>'+
    '<span><span class="dot" style="background:var(--red)"></span>Vắng không phép</span>'+
    '<span><span class="dot" style="background:var(--panel-3)"></span>Ngày nghỉ</span>'+
    '<span><span class="dot" style="background:var(--blue)"></span>Nghỉ có phép</span></div>'}

function sheetHtml(){
  var s=A.find('staff',T.sid);
  if(!s)return '<div class="empty"><h3>Chưa có nhân sự</h3><p>Thêm nhân sự ở ứng dụng Hồ sơ nhân sự trước.</p></div>';
  var sm=summary(s.id,T.cyc),n=daysIn(T.cyc),p=T.cyc.split('-');
  var first=new Date(+p[0],+p[1]-1,1).getDay();
  var lead=(first+6)%7;
  var h=legend()+'<div class="pad">'+
    '<div class="grid g4" style="margin-bottom:14px">'+
      A.tile('Số công',sm.work+'/'+sm.target,'<span>ngày làm việc trong kỳ</span>')+
      A.tile('Đi muộn',sm.late+' phút','<span>cộng dồn cả kỳ</span>',sm.late?'var(--amber)':'')+
      A.tile('Nghỉ phép',sm.leave+' ngày','<span>đã được duyệt</span>')+
      A.tile('Vắng',sm.absent+' ngày','<span>không có dữ liệu chấm công</span>',sm.absent?'var(--red)':'')+
    '</div><div class="cal">'+
    ['Thứ hai','Thứ ba','Thứ tư','Thứ năm','Thứ sáu','Thứ bảy','Chủ nhật'].map(function(d){
      return '<div class="hd">'+esc(d)+'</div>'}).join('');
  for(var i=0;i<lead;i++)h+='<div class="cd off"></div>';
  for(var d2=1;d2<=n;d2++){
    var r=dayRec(s.id,T.cyc,d2),we=isWeekend(T.cyc,d2);
    var cls=we?'off':r&&r.leave?'off':r&&r.in?((r.late||0)>0?'late':'ok'):
      (new Date(+p[0],+p[1]-1,d2)<new Date()?'abs':'off');
    h+='<button class="cd '+cls+'" type="button" data-day="'+d2+'">'+
      '<span class="dh"><b>'+A.pad(d2)+'/'+p[1]+'</b><span class="spacer"></span>'+
      (r&&r.in?'<span class="num">'+esc(r.in)+(r.out?' – '+esc(r.out):'')+'</span>':'')+'</span>'+
      '<span class="bd">'+
      (we?'<span class="by">Ngày nghỉ</span>':
       r&&r.leave?'<span class="chip info">'+(r.leave==='annual'?'Nghỉ phép':r.leave==='holiday'?'Nghỉ lễ':'Nghỉ không lương')+'</span>':
       r&&r.in?shifts().map(function(sh){return '<span class="sh">▸ '+sh.id+': '+sh.in+' – '+sh.out+'</span>'}).join('')+
         '<span class="by" style="display:block;margin-top:3px">Muộn '+(r.late||0)+' phút</span>':
       '<span class="by">Chưa có dữ liệu</span>')+
      '</span></button>'}
  return h+'</div></div>'}

function overHtml(){
  var ss=staffList();
  var h='<div class="pad"><div class="tbl-wrap"><table class="t"><thead><tr><th>Nhân sự</th>'+
    '<th class="n">Số công</th><th class="n">Đi muộn</th><th class="n">Nghỉ phép</th><th class="n">Vắng</th>'+
    '<th style="width:120px">Tỉ lệ công</th><th></th></tr></thead><tbody>';
  ss.forEach(function(s){
    var sm=summary(s.id,T.cyc);
    h+='<tr><td><span style="display:flex;gap:8px;align-items:center">'+A.av(s.name,'s',s.color)+
      '<span><b style="font-weight:500">'+esc(s.name)+'</b><span class="sub" style="display:block">'+esc(s.title||'')+'</span></span></span></td>'+
      '<td class="n">'+sm.work+'/'+sm.target+'</td>'+
      '<td class="n" style="'+(sm.late?'color:var(--amber)':'')+'">'+sm.late+' phút</td>'+
      '<td class="n">'+sm.leave+'</td>'+
      '<td class="n" style="'+(sm.absent?'color:var(--red)':'')+'">'+sm.absent+'</td>'+
      '<td>'+A.wbar([{v:sm.work,c:'var(--green)'},{v:sm.leave,c:'var(--blue)'},{v:sm.absent,c:'var(--red)'}],sm.target||1)+'</td>'+
      '<td class="n"><button class="linkbtn" type="button" data-open="'+s.id+'">Xem bảng công</button></td></tr>'});
  if(!ss.length)h+='<tr><td colspan="7" style="padding:20px;color:var(--muted)">Chưa có nhân sự nào.</td></tr>';
  return h+'</tbody></table></div></div>'}

function tReportHtml(){
  var ss=staffList(),cs=cycles().slice(0,6).reverse();
  var totalWork=0,totalLate=0;
  ss.forEach(function(s){var sm=summary(s.id,T.cyc);totalWork+=sm.work;totalLate+=sm.late});
  return '<div class="pad grid g4" style="padding-bottom:0">'+
    A.tile('Tổng ngày công',totalWork,'<span>kỳ '+esc(T.cyc)+'</span>')+
    A.tile('Tổng phút muộn',totalLate,'<span>toàn công ty</span>',totalLate?'var(--amber)':'')+
    A.tile('Nhân sự',ss.length,'<span>đang theo dõi công</span>')+
    A.tile('Thiết bị',A.col('devices').length,'<span>máy chấm công</span>')+
    '</div><div class="pad grid g2">'+
    '<div class="card-box"><h3 style="margin-bottom:12px">Ngày công theo nhân sự</h3>'+
    A.bars(ss.slice(0,8).map(function(s){return {l:A.initials(s.name),v:summary(s.id,T.cyc).work,c:'var(--app)'}}))+'</div>'+
    '<div class="card-box"><h3 style="margin-bottom:12px">Ngày công toàn công ty qua các kỳ</h3>'+
    A.line([{name:'Tổng ngày công',color:'var(--green)',pts:cs.map(function(c){
      return ss.reduce(function(n,s){return n+summary(s.id,c).work},0)})}],cs.map(function(c){return c.slice(5)}))+'</div>'+
    '<div class="card-box"><h3 style="margin-bottom:12px">Phương thức chấm công</h3>'+
    (function(){var m={};
      A.col('attendance').forEach(function(a){Object.keys(a.days||{}).forEach(function(k){
        var mt=a.days[k].method;if(mt)m[mt]=(m[mt]||0)+1})});
      var ks=Object.keys(m);
      return ks.length?A.donut(ks.map(function(k,i){return {v:m[k],c:A.AVC[i%A.AVC.length],l:k}}),
        ks.reduce(function(n,k){return n+m[k]},0),'lượt'):'<p class="by">Chưa có dữ liệu chấm công.</p>'})()+
    '</div></div>'}

function tAfter(){
  var root=A.$('#appRoot');
  root.addEventListener('click',function(e){
    var el=e.target.closest('[data-tv],[data-cyc],[data-tact],[data-day],[data-open]');
    if(!el)return;var g;
    if((g=el.getAttribute('data-tv'))){T.view=g;A.render();return}
    if((g=el.getAttribute('data-cyc'))){T.cyc=g;A.render();return}
    if((g=el.getAttribute('data-open'))){T.sid=g;T.view='sheet';A.render();return}
    if((g=el.getAttribute('data-day'))){
      var s=A.find('staff',T.sid);if(!s)return;
      C.date=T.cyc+'-'+A.pad(+g);editCheck(s.id);return}
    if((g=el.getAttribute('data-tact'))){
      var p=T.cyc.split('-'),d=new Date(+p[0],+p[1]-1,1);
      d.setMonth(d.getMonth()+(g==='next'?1:-1));
      T.cyc=d.getFullYear()+'-'+A.pad(d.getMonth()+1);A.render();return}});
  var sel=A.$('#tsStaff');
  if(sel)sel.addEventListener('change',function(){T.sid=this.value;A.render()})}

A.register({id:'timesheet',name:'Bảng công',desc:'Tổng hợp công theo kỳ',cat:'hrm',color:'#2F80ED',icon:'timesheet',
  side:true,info:false,view:tView,after:tAfter});
})();
