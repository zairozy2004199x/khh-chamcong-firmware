/* Báo cáo Dự Án — TRANG TỔNG.

   Ứng dụng Công việc & Dự án trả lời "dự án NÀY đang thế nào". Trang này trả lời câu
   khác hẳn: "TẤT CẢ dự án đang thế nào" — thứ mà tới giờ phải mở từng dự án ra cộng tay.

   Hai loại số, cố ý tách bạch và ghi rõ trên màn hình, vì trộn vào nhau là báo cáo sai:
     · "hiện tại"  — tồn đọng tính tại lúc mở trang: còn bao nhiêu việc, quá hạn bao nhiêu.
                     Kỳ báo cáo KHÔNG đụng tới nhóm này; việc quá hạn từ tháng trước vẫn
                     đang quá hạn hôm nay, lọc nó ra khỏi kỳ là giấu mất chỗ đang cháy.
     · "trong kỳ"  — nhịp độ làm việc: mở mới bao nhiêu, xong bao nhiêu, đúng hạn mấy phần.
                     Nhóm này đi theo kỳ đã chọn, đọc theo `at` (lúc tạo) và `doneAt` (lúc xong).

   Bộ lọc bộ phận dùng ô "Bộ phận" của dự án (`group`), cùng danh mục với Hồ sơ nhân sự. */
(function(){
'use strict';
var A=window.APP,esc=A.esc,norm=A.norm;

var TABS=[['tong','Tổng quan','grid'],['bophan','Theo bộ phận','hrm'],
          ['nguoi','Theo người','work'],['canhbao','Cần xử lý','flow']];
var KY=[['30','30 ngày'],['90','90 ngày'],['nam','Năm nay'],['all','Tất cả']];
var PS={ontrack:{l:'Đúng tiến độ',c:'ok'},risk:{l:'Có rủi ro cao',c:'hi'},late:{l:'Chậm tiến độ',c:'late'}};
var SAP=7;   /* "sắp đến hạn" = trong bấy nhiêu ngày tới */
var IM=14;   /* dự án "đứng im" = bấy nhiêu ngày không có động tĩnh gì */

var V={tab:A.ls('kh.bc.tab')||'tong',ky:A.ls('kh.bc.ky')||'90',
       bp:A.ls('kh.bc.bp')||'',sap:A.ls('kh.bc.sap')||'late'};

/* ---------- mốc thời gian ---------- */
var NGAY=864e5;

/** Mốc đầu kỳ, null nghĩa là "tất cả". */
function moc(){
  var n=new Date();
  if(V.ky==='nam')return new Date(n.getFullYear(),0,1).getTime();
  if(V.ky==='all')return null;
  return Date.now()-(Number(V.ky)||90)*NGAY}

function trongKy(v){
  if(!v)return false;
  var m=moc();
  if(m===null)return true;
  var t=new Date(v).getTime();
  return !isNaN(t)&&t>=m}

function nhanKy(){
  for(var i=0;i<KY.length;i++)if(KY[i][0]===V.ky)return KY[i][1];
  return V.ky}

/* ---------- dữ liệu ---------- */
function moiDuAn(){return A.col('projects').slice().sort(function(a,b){return (a.order||0)-(b.order||0)})}
function boPhanCo(){
  var seen={},out=[];
  moiDuAn().forEach(function(p){var g=p.group||'Chưa có bộ phận';
    if(!seen[g]){seen[g]=1;out.push(g)}});
  return out.sort(function(a,b){return a.localeCompare(b,'vi')})}

/** Dự án sau khi lọc bộ phận. */
function duAn(){
  if(!V.bp)return moiDuAn();
  return moiDuAn().filter(function(p){return (p.group||'Chưa có bộ phận')===V.bp})}

/** Công việc thuộc các dự án đang xét. Việc mồ côi (dự án đã xoá) bỏ qua — nó không
    thuộc bộ phận nào nên cộng vào là tổng của các bộ phận không bằng tổng chung. */
function viec(){
  var co={};duAn().forEach(function(p){co[p.id]=1});
  return A.col('tasks').filter(function(t){return co[t.pid]})}

function quaHan(t){return t.status!=='done'&&!!t.due&&new Date(t.due).getTime()<Date.now()}
function sapHan(t){
  if(t.status==='done'||!t.due)return false;
  var d=new Date(t.due).getTime(),n=Date.now();
  return d>=n&&d-n<=SAP*NGAY}
function xongDungHan(t){return t.status==='done'&&t.due&&t.doneAt&&new Date(t.doneAt)<=new Date(t.due)}
function xongMuon(t){return t.status==='done'&&t.due&&t.doneAt&&new Date(t.doneAt)>new Date(t.due)}

/** Bộ số dùng chung cho mọi bảng: một danh sách việc vào, một khối số ra. */
function thongKe(ts){
  var r={n:ts.length,done:0,doing:0,todo:0,late:0,soon:0,xong:0,mo:0,dung:0,muon:0};
  ts.forEach(function(t){
    if(t.status==='done')r.done++;else if(t.status==='doing')r.doing++;else r.todo++;
    if(quaHan(t))r.late++;
    if(sapHan(t))r.soon++;
    if(trongKy(t.at))r.mo++;
    if(t.status==='done'&&trongKy(t.doneAt)){
      r.xong++;
      if(xongDungHan(t))r.dung++;
      if(xongMuon(t))r.muon++}});
  r.pct=A.pct(r.done,r.n);
  /* Chỉ đếm trên việc CÓ HẠN: việc không đặt hạn thì không có gì để đúng hay muộn,
     gom nó vào mẫu số là tỷ lệ đúng hạn tự đẹp lên theo số việc quên đặt hạn. */
  r.coHan=r.dung+r.muon;
  r.pctDung=A.pct(r.dung,r.coHan);
  return r}

/** Lần cuối dự án có động tĩnh: việc xong, việc mới, bình luận, nhật ký. */
function dongGanNhat(p){
  var m=p.at?new Date(p.at).getTime():0;
  function an(v){var t=v?new Date(v).getTime():0;if(!isNaN(t)&&t>m)m=t}
  (p.log||[]).forEach(function(x){an(x.at)});
  (p.cmts||[]).forEach(function(x){an(x.at)});
  A.col('tasks').forEach(function(t){if(t.pid===p.id){an(t.at);an(t.doneAt)}});
  return m}

/* ---------- thanh bên ---------- */
function side(){
  var h='<aside class="side">'+A.sideUser()+'<div class="side-list">';
  h+='<div class="grp"><div class="grp-b">'+TABS.map(function(t){
    return '<button class="pitem'+(V.tab===t[0]?' on':'')+'" type="button" data-tab="'+t[0]+'">'+
      A.icon(t[2],'ic')+'<span class="t">'+esc(t[1])+'</span></button>'}).join('')+'</div></div>';

  h+='<div class="grp"><button class="grp-h" type="button" disabled><span>Kỳ báo cáo</span></button>'+
     '<div class="grp-b">'+KY.map(function(k){
       return '<button class="pitem'+(V.ky===k[0]?' on':'')+'" type="button" data-ky="'+k[0]+'">'+
         '<span class="t">'+esc(k[1])+'</span></button>'}).join('')+'</div></div>';

  var bp=boPhanCo();
  h+='<div class="grp"><button class="grp-h" type="button" disabled><span>Bộ phận</span></button><div class="grp-b">'+
     '<button class="pitem'+(V.bp?'':' on')+'" type="button" data-bp=""><span class="t">Toàn công ty</span>'+
     '<span class="n">'+moiDuAn().length+'</span></button>'+
     bp.map(function(g){
       var n=moiDuAn().filter(function(p){return (p.group||'Chưa có bộ phận')===g}).length;
       return '<button class="pitem'+(V.bp===g?' on':'')+'" type="button" data-bp="'+esc(g)+'">'+
         '<span class="t">'+esc(g)+'</span><span class="n">'+n+'</span></button>'}).join('')+
     '</div></div>';
  return h+'</div></aside>'}

/* ---------- khung ---------- */
function view(){
  A.ls('kh.bc.tab',V.tab);A.ls('kh.bc.ky',V.ky);A.ls('kh.bc.bp',V.bp);
  var ten='Báo cáo Dự Án';
  for(var i=0;i<TABS.length;i++)if(TABS[i][0]===V.tab)ten=TABS[i][1];
  var head='<header class="topbar">'+A.navBtn()+'<h1>'+esc(ten)+'</h1>'+
    '<span class="chip info">'+esc(V.bp||'Toàn công ty')+'</span>'+
    '<span class="chip soft">Kỳ: '+esc(nhanKy())+'</span>'+
    '<span class="spacer"></span>'+
    '<button class="linkbtn" type="button" data-act="csv">Tải CSV</button>'+
    '<button class="linkbtn" type="button" data-act="in">In trang</button>'+
    '</header><nav class="tabs">'+TABS.map(function(t){
      return '<button class="tab'+(V.tab===t[0]?' on':'')+'" type="button" data-tab="'+t[0]+'">'+
        esc(t[1])+'</button>'}).join('')+'</nav>';
  var body;
  if(V.tab==='bophan')body=tabBoPhan();
  else if(V.tab==='nguoi')body=tabNguoi();
  else if(V.tab==='canhbao')body=tabCanhBao();
  else body=tabTong();
  return side()+'<section class="stage">'+head+'<div class="content" id="content">'+body+'</div></section>'}

function trong(tx){return '<p class="empty">'+esc(tx)+'</p>'}

/* ---------- tab: tổng quan ---------- */
function tabTong(){
  var ps=duAn(),ts=viec(),k=thongKe(ts);
  var canChu=ps.filter(function(p){
    var s=p.status||'ontrack';
    return s==='risk'||s==='late'||thongKe(A.col('tasks').filter(function(t){return t.pid===p.id})).late>0}).length;
  var xongHan=ps.filter(function(p){
    var st=thongKe(A.col('tasks').filter(function(t){return t.pid===p.id}));
    return st.n>0&&st.done===st.n}).length;
  var dungToi=ps.filter(function(p){return trongKy(dongGanNhat(p))}).length;

  var h='<div class="pad grid g4" style="padding-bottom:0">'+
    A.tile('Dự án',ps.length,'<span>'+xongHan+' đã xong</span><span>'+canChu+' cần chú ý</span>')+
    A.tile('Việc chưa xong',k.n-k.done,'<span>'+k.doing+' đang làm</span><span>'+k.todo+' chưa bắt đầu</span>')+
    A.tile('Quá hạn',k.late,'<span>trên '+k.n+' công việc</span>',k.late?'var(--red)':'')+
    A.tile('Sắp đến hạn',k.soon,'<span>trong '+SAP+' ngày tới</span>',k.soon?'var(--amber)':'')+
    '</div>';

  h+='<div class="pad grid g4" style="padding-bottom:0">'+
    A.tile('Xong trong kỳ',k.xong,'<span>'+esc(nhanKy())+'</span>')+
    A.tile('Mở mới trong kỳ',k.mo,'<span>'+esc(nhanKy())+'</span>')+
    A.tile('Đúng hạn',k.coHan?k.pctDung+'%':'—',
      k.coHan?'<span>'+k.dung+' đúng hạn · '+k.muon+' muộn</span>':'<span>chưa có việc nào có hạn xong trong kỳ</span>',
      k.coHan&&k.pctDung<80?'var(--amber)':'')+
    A.tile('Dự án có động tĩnh',dungToi,'<span>trên '+ps.length+' dự án</span>')+
    '</div>';

  h+='<div class="pad grid g2">';
  h+='<div class="card-box"><h3 style="margin-bottom:4px">Trạng thái công việc</h3>'+
    '<p class="by" style="margin-bottom:12px">Tính tại lúc mở trang, không theo kỳ</p>'+
    (k.n?A.donut([{v:k.done,c:'var(--green)',l:'Hoàn thành'},
                  {v:k.doing,c:'var(--blue)',l:'Đang làm'},
                  {v:k.late,c:'var(--red)',l:'Quá hạn'},
                  {v:Math.max(0,k.todo-k.late),c:'var(--panel-3)',l:'Chưa bắt đầu'}],k.n,'công việc')
       :trong('Chưa có công việc nào.'))+'</div>';
  h+='<div class="card-box"><h3 style="margin-bottom:4px">Nhịp độ theo tuần</h3>'+
    '<p class="by" style="margin-bottom:12px">Việc mở mới và việc hoàn thành trong '+esc(nhanKy().toLowerCase())+'</p>'+
    nhipDo(ts)+'</div>';
  h+='</div>';

  h+='<div class="pad"><div class="card-box"><h3 style="margin-bottom:12px">Từng dự án</h3>'+bangDuAn(ps)+'</div></div>';
  return h}

/** Đường mở mới / hoàn thành theo tuần. Gom tối đa 12 mốc cho vừa bề ngang màn hình. */
function nhipDo(ts){
  var m=moc();
  if(m===null){
    var som=Date.now();
    ts.forEach(function(t){
      [t.at,t.doneAt].forEach(function(v){var x=v?new Date(v).getTime():0;if(x&&x<som)som=x})});
    m=som}
  var tuan=Math.ceil((Date.now()-m)/(7*NGAY))||1;
  if(tuan<2)tuan=2;
  var gop=Math.ceil(tuan/12),so=Math.ceil(tuan/gop);
  var mo=[],xong=[],nhan=[],i;
  for(i=0;i<so;i++){mo.push(0);xong.push(0);nhan.push('')}
  var rong=gop*7*NGAY;
  function o(v){
    if(!v)return -1;
    var t=new Date(v).getTime();
    if(isNaN(t)||t<m)return -1;
    var j=Math.floor((t-m)/rong);
    return j>=so?so-1:j}
  ts.forEach(function(t){
    var a=o(t.at);if(a>=0)mo[a]++;
    if(t.status==='done'){var b=o(t.doneAt);if(b>=0)xong[b]++}});
  for(i=0;i<so;i++)nhan[i]=A.fmtDM(new Date(m+i*rong));
  return A.line([{name:'Mở mới',color:'var(--blue)',pts:mo},
                 {name:'Hoàn thành',color:'var(--green)',pts:xong}],nhan)}

var COT=[['ten','Dự án'],['bp','Bộ phận'],['xong','Xong'],['late','Quá hạn'],
         ['han','Hạn chót'],['tiendo','Tiến độ']];

function bangDuAn(ps){
  if(!ps.length)return trong('Chưa có dự án nào trong phạm vi này.');
  var rows=ps.map(function(p){
    var st=thongKe(A.col('tasks').filter(function(t){return t.pid===p.id}));
    return {p:p,st:st,con:p.end?Math.ceil((new Date(p.end).getTime()-Date.now())/NGAY):null}});
  rows.sort(sapXep);
  var h='<div class="tbl-wrap"><table class="t"><thead><tr>'+COT.map(function(c){
    return '<th'+(c[0]==='xong'||c[0]==='late'?' class="n"':'')+'>'+
      '<button class="linkbtn" type="button" data-sap="'+c[0]+'">'+esc(c[1])+
      (V.sap===c[0]?' ▾':'')+'</button></th>'}).join('')+'<th>Trạng thái</th></tr></thead><tbody>';
  rows.forEach(function(r){
    var p=r.p,st=r.st,ps2=PS[p.status||'ontrack'];
    h+='<tr><td><button type="button" data-proj="'+p.id+'" style="display:flex;gap:9px;align-items:center;text-align:left">'+
      A.av(p.name,'s',p.color)+'<span><b style="font-weight:500">'+esc(p.name)+'</b>'+
      (p.demo?' <span class="chip soft">ví dụ</span>':'')+'</span></button></td>'+
      '<td><span class="chip info">'+esc(p.group||'Chưa có')+'</span></td>'+
      '<td class="n">'+st.done+'/'+st.n+'</td>'+
      '<td class="n"'+(st.late?' style="color:var(--red);font-weight:500"':'')+'>'+st.late+'</td>'+
      '<td>'+hanChot(p,r.con,st)+'</td>'+
      '<td style="min-width:120px">'+A.wbar([{v:st.done,c:'var(--green)'},{v:st.doing,c:'var(--blue)'},
        {v:Math.max(0,st.todo),c:'var(--panel-3)'}],st.n||1)+
      '<span class="by">'+st.pct+'%</span></td>'+
      '<td><span class="chip '+ps2.c+'">'+ps2.l+'</span></td></tr>'});
  return h+'</tbody></table></div>'}

function hanChot(p,con,st){
  if(!p.end)return '<span class="by">chưa đặt</span>';
  var xong=st.n>0&&st.done===st.n;
  var tx=A.fmtD(p.end);
  if(xong)return '<span class="due">'+esc(tx)+'</span>';
  if(con<0)return '<span class="due crit">'+esc(tx)+' · trễ '+(-con)+' ngày</span>';
  if(con<=SAP)return '<span class="due warn">'+esc(tx)+' · còn '+con+' ngày</span>';
  return '<span class="due">'+esc(tx)+' · còn '+con+' ngày</span>'}

function sapXep(a,b){
  if(V.sap==='ten')return a.p.name.localeCompare(b.p.name,'vi');
  if(V.sap==='bp')return String(a.p.group||'').localeCompare(String(b.p.group||''),'vi');
  if(V.sap==='xong')return b.st.pct-a.st.pct;
  if(V.sap==='tiendo')return a.st.pct-b.st.pct;
  if(V.sap==='han'){
    /* Dự án chưa đặt hạn xuống cuối: không có hạn thì không có gì để gấp. */
    if(a.con===null&&b.con===null)return 0;
    if(a.con===null)return 1;
    if(b.con===null)return -1;
    return a.con-b.con}
  return b.st.late-a.st.late}

/* ---------- tab: theo bộ phận ---------- */
function tabBoPhan(){
  var nhom={},thu=[];
  duAn().forEach(function(p){
    var g=p.group||'Chưa có bộ phận';
    if(!nhom[g]){nhom[g]={ps:[],ts:[]};thu.push(g)}
    nhom[g].ps.push(p);
    A.col('tasks').forEach(function(t){if(t.pid===p.id)nhom[g].ts.push(t)})});
  if(!thu.length)return '<div class="pad">'+trong('Chưa có dự án nào.')+'</div>';

  var hang=thu.map(function(g){return {g:g,ps:nhom[g].ps,k:thongKe(nhom[g].ts)}})
              .sort(function(a,b){return b.k.late-a.k.late||b.k.n-a.k.n});

  var h='<div class="pad grid g2" style="padding-bottom:0">'+
    '<div class="card-box"><h3 style="margin-bottom:4px">Việc quá hạn theo bộ phận</h3>'+
    '<p class="by" style="margin-bottom:12px">Tính tại lúc mở trang</p>'+
    A.bars(hang.slice(0,8).map(function(r){
      return {l:r.g.length>10?r.g.slice(0,9)+'…':r.g,v:r.k.late,
              c:r.k.late?'var(--red)':'var(--panel-3)'}}))+'</div>'+
    '<div class="card-box"><h3 style="margin-bottom:4px">Xong trong kỳ theo bộ phận</h3>'+
    '<p class="by" style="margin-bottom:12px">'+esc(nhanKy())+'</p>'+
    A.bars(hang.slice(0,8).map(function(r){
      return {l:r.g.length>10?r.g.slice(0,9)+'…':r.g,v:r.k.xong,c:'var(--green)'}}))+'</div></div>';

  h+='<div class="pad"><div class="card-box"><h3 style="margin-bottom:12px">Bảng bộ phận</h3>'+
    '<div class="tbl-wrap"><table class="t"><thead><tr><th>Bộ phận</th><th class="n">Dự án</th>'+
    '<th class="n">Công việc</th><th class="n">Xong</th><th class="n">Quá hạn</th>'+
    '<th class="n">Xong trong kỳ</th><th class="n">Đúng hạn</th><th style="width:130px">Tiến độ</th></tr></thead><tbody>';
  hang.forEach(function(r){
    h+='<tr><td><button class="linkbtn" type="button" data-bp="'+esc(r.g)+'">'+esc(r.g)+'</button></td>'+
      '<td class="n">'+r.ps.length+'</td><td class="n">'+r.k.n+'</td><td class="n">'+r.k.done+'</td>'+
      '<td class="n"'+(r.k.late?' style="color:var(--red);font-weight:500"':'')+'>'+r.k.late+'</td>'+
      '<td class="n">'+r.k.xong+'</td>'+
      '<td class="n">'+(r.k.coHan?r.k.pctDung+'%':'—')+'</td>'+
      '<td>'+A.wbar([{v:r.k.done,c:'var(--green)'},{v:r.k.doing,c:'var(--blue)'},
        {v:Math.max(0,r.k.todo),c:'var(--panel-3)'}],r.k.n||1)+'</td></tr>'});
  return h+'</tbody></table></div></div></div>'}

/* ---------- tab: theo người ---------- */
function tabNguoi(){
  var per={},ten=[];
  viec().forEach(function(t){
    var k=String(t.assignee||'').trim()||'Chưa giao';
    if(!per[k]){per[k]=[];ten.push(k)}
    per[k].push(t)});
  if(!ten.length)return '<div class="pad">'+trong('Chưa có công việc nào được giao.')+'</div>';

  var hang=ten.map(function(k){return {ten:k,k:thongKe(per[k])}})
              .sort(function(a,b){return b.k.late-a.k.late||b.k.n-a.k.n});

  var h='<div class="pad"><div class="card-box"><h3 style="margin-bottom:4px">Công việc theo người</h3>'+
    '<p class="by" style="margin-bottom:12px">Cột "Xong trong kỳ" và "Đúng hạn" theo '+esc(nhanKy().toLowerCase())+
    '; các cột còn lại tính tại lúc mở trang</p>'+
    '<div class="tbl-wrap"><table class="t"><thead><tr><th>Người phụ trách</th><th class="n">Được giao</th>'+
    '<th class="n">Đang làm</th><th class="n">Xong</th><th class="n">Quá hạn</th>'+
    '<th class="n">Xong trong kỳ</th><th class="n">Đúng hạn</th><th style="width:130px">Tiến độ</th></tr></thead><tbody>';
  hang.forEach(function(r){
    var chua=r.ten==='Chưa giao';
    h+='<tr><td><span style="display:flex;gap:9px;align-items:center">'+
      (chua?'<span class="av s" style="background:var(--panel-3)">?</span>':A.av(r.ten,'s'))+
      '<span'+(A.isMe(r.ten)?' style="font-weight:500"':'')+'>'+esc(r.ten)+
      (A.isMe(r.ten)?' <span class="chip vio">bạn</span>':'')+'</span></span></td>'+
      '<td class="n">'+r.k.n+'</td><td class="n">'+r.k.doing+'</td><td class="n">'+r.k.done+'</td>'+
      '<td class="n"'+(r.k.late?' style="color:var(--red);font-weight:500"':'')+'>'+r.k.late+'</td>'+
      '<td class="n">'+r.k.xong+'</td>'+
      '<td class="n">'+(r.k.coHan?r.k.pctDung+'%':'—')+'</td>'+
      '<td>'+A.wbar([{v:r.k.done,c:'var(--green)'},{v:r.k.doing,c:'var(--blue)'},
        {v:Math.max(0,r.k.todo),c:'var(--panel-3)'}],r.k.n||1)+'</td></tr>'});
  return h+'</tbody></table></div></div></div>'}

/* ---------- tab: cần xử lý ---------- */
function tabCanhBao(){
  var ps=duAn(),ts=viec(),now=Date.now();

  var treHan=ps.map(function(p){
    var st=thongKe(A.col('tasks').filter(function(t){return t.pid===p.id}));
    return {p:p,st:st}})
    .filter(function(r){
      if(!r.p.end)return false;
      if(r.st.n>0&&r.st.done===r.st.n)return false;
      return new Date(r.p.end).getTime()<now})
    .sort(function(a,b){return new Date(a.p.end)-new Date(b.p.end)});

  var dungIm=ps.map(function(p){return {p:p,m:dongGanNhat(p)}})
    .filter(function(r){
      var st=thongKe(A.col('tasks').filter(function(t){return t.pid===r.p.id}));
      if(st.n>0&&st.done===st.n)return false;
      return !r.m||now-r.m>IM*NGAY})
    .sort(function(a,b){return a.m-b.m});

  var tre=ts.filter(quaHan).sort(function(a,b){return new Date(a.due)-new Date(b.due)});
  var chuaGiao=ts.filter(function(t){return t.status!=='done'&&!String(t.assignee||'').trim()});
  var khongHan=ts.filter(function(t){return t.status!=='done'&&!t.due});

  var h='<div class="pad grid g4" style="padding-bottom:0">'+
    A.tile('Dự án trễ hạn',treHan.length,'<span>quá ngày kết thúc mà chưa xong</span>',treHan.length?'var(--red)':'')+
    A.tile('Dự án đứng im',dungIm.length,'<span>không động tĩnh '+IM+' ngày</span>',dungIm.length?'var(--amber)':'')+
    A.tile('Việc quá hạn',tre.length,'',tre.length?'var(--red)':'')+
    A.tile('Việc chưa giao ai',chuaGiao.length,'<span>'+khongHan.length+' việc chưa đặt hạn</span>',
      chuaGiao.length?'var(--amber)':'')+'</div>';

  h+='<div class="pad grid g2">';
  h+='<div class="card-box"><h3 style="margin-bottom:12px">Dự án trễ hạn</h3>'+
    (treHan.length?treHan.map(function(r){
      var tre2=Math.ceil((now-new Date(r.p.end).getTime())/NGAY);
      return '<div class="mini">'+A.av(r.p.name,'',r.p.color)+
        '<span class="t"><button type="button" data-proj="'+r.p.id+'">'+esc(r.p.name)+'</button>'+
        '<span class="by" style="display:block">còn '+(r.st.n-r.st.done)+' việc chưa xong</span></span>'+
        '<span class="due crit">trễ '+tre2+' ngày</span></div>'}).join('')
      :trong('Không có dự án nào trễ hạn.'))+'</div>';

  h+='<div class="card-box"><h3 style="margin-bottom:4px">Dự án đứng im</h3>'+
    '<p class="by" style="margin-bottom:12px">Không có việc nào mở mới, hoàn thành hay bình luận trong '+IM+' ngày</p>'+
    (dungIm.length?dungIm.map(function(r){
      return '<div class="mini">'+A.av(r.p.name,'',r.p.color)+
        '<span class="t"><button type="button" data-proj="'+r.p.id+'">'+esc(r.p.name)+'</button>'+
        '<span class="by" style="display:block">'+esc(r.p.group||'Chưa có bộ phận')+'</span></span>'+
        '<span class="due">'+(r.m?esc(A.ago(r.m)):'chưa có hoạt động')+'</span></div>'}).join('')
      :trong('Dự án nào cũng có động tĩnh gần đây.'))+'</div>';
  h+='</div>';

  h+='<div class="pad"><div class="card-box"><h3 style="margin-bottom:12px">Việc quá hạn lâu nhất</h3>'+
    (tre.length?'<div class="tbl-wrap"><table class="t"><thead><tr><th>Công việc</th><th>Dự án</th>'+
      '<th>Người phụ trách</th><th>Hạn</th><th class="n">Trễ</th></tr></thead><tbody>'+
      tre.slice(0,25).map(function(t){
        var p=A.find('projects',t.pid);
        return '<tr><td><button type="button" data-proj="'+t.pid+'" style="text-align:left">'+esc(t.title)+'</button></td>'+
          '<td><span class="by">'+esc(p?p.name:'—')+'</span></td>'+
          '<td>'+(t.assignee?A.av(t.assignee,'s')+' '+esc(t.assignee):'<span class="chip amb">chưa giao</span>')+'</td>'+
          '<td><span class="due crit">'+esc(A.fmtD(t.due))+'</span></td>'+
          '<td class="n">'+Math.ceil((now-new Date(t.due).getTime())/NGAY)+' ngày</td></tr>'}).join('')+
      '</tbody></table></div>'+(tre.length>25?'<p class="by" style="margin-top:8px">Còn '+(tre.length-25)+
        ' việc quá hạn nữa — tải CSV để xem hết.</p>':'')
      :trong('Không có việc nào quá hạn.'))+'</div></div>';
  return h}

/* ---------- xuất CSV ---------- */
/* Dấu ; và BOM là để Excel bản tiếng Việt mở phát ra đúng cột và đúng dấu, không phải
   dồn hết một cột rồi hiện tên có dấu thành ký tự lạ. */
function o(v){
  var s=String(v==null?'':v);
  return '"'+s.replace(/"/g,'""')+'"'}

function taiCsv(){
  var ps=duAn();
  var d=[['Dự án','Bộ phận','Trạng thái','Bắt đầu','Kết thúc','Công việc','Xong','Đang làm',
          'Chưa bắt đầu','Quá hạn','Sắp đến hạn','Xong trong kỳ','Đúng hạn trong kỳ','Muộn trong kỳ','Tiến độ %']];
  ps.forEach(function(p){
    var st=thongKe(A.col('tasks').filter(function(t){return t.pid===p.id}));
    d.push([p.name,p.group||'',(PS[p.status||'ontrack']).l,A.fmtD(p.start),A.fmtD(p.end),
            st.n,st.done,st.doing,st.todo,st.late,st.soon,st.xong,st.dung,st.muon,st.pct])});
  var csv='﻿'+d.map(function(r){return r.map(o).join(';')}).join('\r\n');
  var ten='bao-cao-du-an-'+A.ymd(new Date())+'.csv';
  var url=URL.createObjectURL(new Blob([csv],{type:'text/csv;charset=utf-8'}));
  var a=document.createElement('a');a.href=url;a.download=ten;
  document.body.appendChild(a);a.click();a.remove();
  setTimeout(function(){URL.revokeObjectURL(url)},1000);
  A.toast('Đã tải '+ten)}

/* ---------- sự kiện ---------- */
function after(){
  var root=A.$('#appRoot');
  if(!root)return;
  root.addEventListener('click',function(e){
    var el=e.target.closest('[data-tab],[data-ky],[data-bp],[data-sap],[data-proj],[data-act]');
    if(!el)return;
    var g=el.getAttribute('data-tab');
    if(g){V.tab=g;A.render();return}
    if((g=el.getAttribute('data-ky'))){V.ky=g;A.render();return}
    if((g=el.getAttribute('data-bp'))!==null&&el.hasAttribute('data-bp')){V.bp=g;A.render();return}
    if((g=el.getAttribute('data-sap'))){
      V.sap=g;A.ls('kh.bc.sap',g);A.render();return}
    if((g=el.getAttribute('data-proj'))){A.go('wework',g);return}
    if((g=el.getAttribute('data-act'))){
      if(g==='csv')taiCsv();
      else if(g==='in')window.print()}})}

A.register({id:'baocao',name:'Báo cáo Dự Án',desc:'Trang tổng toàn bộ dự án',cat:'work',
  color:'#7E57C2',icon:'report',side:true,info:false,view:view,after:after});
})();
