/* K&H Platform — lõi dùng chung: dữ liệu, điều hướng, giao diện nền */
(function(){
'use strict';
var W=window;
var APP=W.APP={apps:[],byId:{},cur:null};

/* ---------- tiện ích ---------- */
function $(s,r){return (r||document).querySelector(s)}
function $$(s,r){return Array.prototype.slice.call((r||document).querySelectorAll(s))}
function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){
  return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]})}
function uid(){return Date.now().toString(36)+Math.random().toString(36).slice(2,7)}
function pad(n){return (n<10?'0':'')+n}
function norm(s){return String(s||'').trim().toLowerCase()}
var AVC=['#E0464E','#E0912B','#27A567','#1177D8','#7E57C2','#0E9AA7','#D9518C','#5C6BC0','#C2185B','#00897B','#3949AB','#00838F'];
function hue(s){var h=0;s=String(s||'?');for(var i=0;i<s.length;i++)h=(h*31+s.charCodeAt(i))>>>0;return AVC[h%AVC.length]}
function initials(s){s=String(s||'').trim();if(!s)return '?';var p=s.split(/\s+/);
  return p.length>1?(p[p.length-2][0]+p[p.length-1][0]):s.slice(0,2)}
function av(name,cls,color){return '<span class="av '+(cls||'')+'" style="background:'+(color||hue(name))+'">'+esc(initials(name))+'</span>'}
function stack(names,max){
  max=max||6;var h='<span class="stack">';
  names.slice(0,max).forEach(function(n){h+=av(n,'s')});
  if(names.length>max)h+='<span class="av s more">+'+(names.length-max)+'</span>';
  return h+'</span>'}

var DOW=['Chủ nhật','Thứ hai','Thứ ba','Thứ tư','Thứ năm','Thứ sáu','Thứ bảy'];
function D(v){if(!v)return null;var d=v instanceof Date?v:new Date(v);return isNaN(d)?null:d}
function fmtD(v){var d=D(v);return d?pad(d.getDate())+'/'+pad(d.getMonth()+1)+'/'+d.getFullYear():''}
function fmtDM(v){var d=D(v);return d?pad(d.getDate())+'/'+pad(d.getMonth()+1):''}
function fmtDT(v){var d=D(v);return d?pad(d.getHours())+':'+pad(d.getMinutes())+' '+pad(d.getDate())+'/'+pad(d.getMonth()+1)+'/'+d.getFullYear():''}
function fmtT(v){var d=D(v);return d?pad(d.getHours())+':'+pad(d.getMinutes()):''}
function ymd(v){var d=D(v)||new Date();return d.getFullYear()+'-'+pad(d.getMonth()+1)+'-'+pad(d.getDate())}
function toLocal(v){var d=D(v);return d?d.getFullYear()+'-'+pad(d.getMonth()+1)+'-'+pad(d.getDate())+'T'+pad(d.getHours())+':'+pad(d.getMinutes()):''}
function ago(v){var d=D(v);if(!d)return '';
  var s=(Date.now()-d.getTime())/1000;
  if(s<60)return 'vừa xong';
  if(s<3600)return Math.floor(s/60)+' phút trước';
  if(s<86400)return Math.floor(s/3600)+' giờ trước';
  if(s<2592000)return Math.floor(s/86400)+' ngày trước';
  return fmtD(d)}
function money(n){n=Number(n)||0;return n.toLocaleString('vi-VN')+' ₫'}
function shortMoney(n){n=Number(n)||0;
  if(n>=1e9)return (n/1e9).toFixed(2)+' tỷ';
  if(n>=1e6)return (n/1e6).toFixed(1)+' tr';
  return n.toLocaleString('vi-VN')}
function daysBetween(a,b){var x=D(a),y=D(b);if(!x||!y)return 0;
  return Math.round((new Date(y.getFullYear(),y.getMonth(),y.getDate())-new Date(x.getFullYear(),x.getMonth(),x.getDate()))/86400000)}
function pct(a,b){return b?Math.round(a/b*1000)/10:0}

/* ---------- bộ nhớ cục bộ ---------- */
function ls(k,v){try{if(v===undefined)return localStorage.getItem(k);localStorage.setItem(k,v)}catch(e){}return null}
function lsj(k,v){if(v===undefined){try{return JSON.parse(ls(k)||'null')}catch(e){return null}}ls(k,JSON.stringify(v))}

/* ---------- trạng thái chung ---------- */
var S=APP.S={
  me:ls('kh.me')||'', role:ls('kh.role')||'', app:ls('kh.app')||'home',
  ready:false, db:null, nav:false, star:lsj('kh.star')||{}
};
if(ls('kh.theme'))document.documentElement.setAttribute('data-theme',ls('kh.theme'));

/* ---------- lớp dữ liệu ---------- */
var COLLS=['projects','tasks','requests','reqtypes','flows','jobs','staff','depts','timeoffs','attendance','payrolls','posts','meetings','rooms','devices','channels','messages','feed','resources','bookings','settings'];
var DATA={};COLLS.forEach(function(c){DATA[c]=[]});
APP.col=function(c){return DATA[c]||[]};
APP.find=function(c,id){var a=DATA[c]||[];for(var i=0;i<a.length;i++)if(a[i].id===id)return a[i];return null};
function bodyOf(o){var b={},k;for(k in o)if(k!=='id'&&o[k]!==undefined)b[k]=o[k];return b}
function upsert(arr,o){for(var i=0;i<arr.length;i++)if(arr[i].id===o.id){arr[i]=o;return}arr.push(o)}
function errMsg(e){var c=e&&e.code;
  if(c==='quota_exceeded')return 'Kho dữ liệu đã đầy. Xoá bớt bản ghi cũ rồi thử lại.';
  if(c==='resource_exhausted')return 'Thao tác quá nhanh, chờ một chút rồi thử lại.';
  if(c==='not_granted'||c==='revoked'||c==='capability_disabled')return 'Bản này chỉ xem được, chưa lưu được thay đổi.';
  if(c==='invalid_argument')return 'Máy chủ từ chối ghi: mục này chỉ người được chia sẻ quyền sửa mới ghi được.';
  return 'Chưa lưu được thay đổi. Thử lại giúp mình.'}
APP.save=function(coll,o){
  if(!o.id)o.id=uid();
  upsert(DATA[coll]=DATA[coll]||[],o);paint();
  if(S.wp)return wpWrite('doc',{coll:coll,id:o.id,data:bodyOf(o)}).then(function(){return o});
  if(!S.db){saveLocal(coll);return Promise.resolve(o)}
  return S.db.doc(coll+'/'+o.id).set(bodyOf(o)).then(function(){return o},function(e){toast(errMsg(e),true);return o})};
APP.remove=function(coll,id){
  var a=DATA[coll]||[];for(var i=a.length-1;i>=0;i--)if(a[i].id===id)a.splice(i,1);
  paint();
  if(S.wp)return wpWrite('delete',{coll:coll,id:id});
  if(!S.db){saveLocal(coll);return Promise.resolve()}
  return S.db.doc(coll+'/'+id).delete().catch(function(e){toast(errMsg(e),true)})};
APP.demoCount=function(){var n=0;COLLS.forEach(function(c){n+=(DATA[c]||[]).filter(function(o){return o.demo}).length});return n};
APP.wipeDemo=function(){
  var pids=(DATA.projects||[]).filter(function(p){return p.demo}).map(function(p){return p.id});
  (DATA.tasks||[]).forEach(function(t){if(pids.indexOf(t.pid)>=0)APP.remove('tasks',t.id)});
  var fids=(DATA.flows||[]).filter(function(f){return f.demo}).map(function(f){return f.id});
  (DATA.jobs||[]).forEach(function(j){if(fids.indexOf(j.flowId)>=0)APP.remove('jobs',j.id)});
  COLLS.forEach(function(c){
    (DATA[c]||[]).slice().forEach(function(o){if(o.demo)APP.remove(c,o.id)})});
  toast('Đã xoá toàn bộ dữ liệu ví dụ trên tất cả ứng dụng.')};
APP.removeWhere=function(coll,fn){(DATA[coll]||[]).filter(fn).forEach(function(o){APP.remove(coll,o.id)})};

/* Ghi nhiều bản ghi cùng lúc — dùng khi đổi tên bộ phận cho hàng trăm nhân sự.
   Trên WordPress gửi theo lô 100 bản ghi một lần thay vì 100 lượt gọi riêng. */
APP.saveMany=function(coll,arr){
  arr=(arr||[]).filter(function(o){return o&&o.id});
  if(!arr.length)return Promise.resolve(0);
  DATA[coll]=DATA[coll]||[];
  arr.forEach(function(o){upsert(DATA[coll],o)});
  paint();
  function chunk(a,n){var out=[],i;for(i=0;i<a.length;i+=n)out.push(a.slice(i,i+n));return out}
  if(S.wp){
    var lots=chunk(arr.map(function(o){return {id:o.id,data:bodyOf(o)}}),100);
    return lots.reduce(function(p,lot){
      return p.then(function(n){
        return wpWrite('bulk',{coll:coll,docs:lot}).then(function(r){
          return n+(r&&r.saved!=null?r.saved:0)})})},Promise.resolve(0))}
  if(!S.db){saveLocal(coll);return Promise.resolve(arr.length)}
  var bad=0;
  return chunk(arr,20).reduce(function(p,lot){
    return p.then(function(){
      return Promise.all(lot.map(function(o){
        return S.db.doc(coll+'/'+o.id).set(bodyOf(o)).catch(function(){bad++;return null})}))})},
    Promise.resolve()).then(function(){
      if(bad)toast('Còn '+bad+' bản ghi chưa lưu được, thử lại giúp mình.',true);
      return arr.length-bad})};

/* Khi trang chạy ngoài môi trường Artifact (mở file trực tiếp hoặc tự host),
   không có máy chủ dữ liệu — lưu tạm vào trình duyệt để vẫn dùng được một máy. */
function lsKey(c){return 'kh.local.'+c}
function loadLocal(){
  var any=false;
  COLLS.forEach(function(c){
    var raw=ls(lsKey(c));
    if(raw){try{var a=JSON.parse(raw);if(a&&a.length){DATA[c]=a;any=true}}catch(e){}}});
  return any}
function saveLocal(c){
  try{localStorage.setItem(lsKey(c),JSON.stringify(DATA[c]||[]))}catch(e){}}
APP.saveLocal=saveLocal;
APP.wipeLocal=function(){
  COLLS.forEach(function(c){DATA[c]=[];try{localStorage.removeItem(lsKey(c))}catch(e){}});
  paint()};
function seedLocal(){
  var S2=W.KH_SEED;
  if(!S2)return;
  Object.keys(S2).forEach(function(c){
    if(!DATA[c])DATA[c]=[];
    S2[c].forEach(function(d){upsert(DATA[c],Object.assign({id:d.id},d.body))});
    saveLocal(c)})}
function offline(){
  S.ready=true;S.offline=true;
  if(!loadLocal())seedLocal();
  paint()}

/* ================= WordPress backend =================
   Khi chạy trong plugin WordPress, window.KH_API do PHP đặt sẵn:
   {rest, media, nonce, me, title, role}. Dữ liệu nằm trong bảng
   {prefix}khh_docs, đọc/ghi qua REST, đồng bộ lại mỗi 8 giây. */
var lastSync=0,syncT=null;
function wpHeaders(json){
  var h={'X-WP-Nonce':W.KH_API.nonce};
  if(json)h['Content-Type']='application/json';
  return h}
/* Ghép đường dẫn REST.

   WordPress để "Đường dẫn tĩnh = Mặc định" thì rest_url() trả về dạng
   .../index.php?rest_route=/khh/v1/ — đã có sẵn dấu ?. Nối thêm "state?since=0"
   vào đó sẽ thành ?rest_route=/khh/v1/state?since=0, WordPress không nhận ra
   tuyến nào và trả 404: cả nền tảng trắng trơn, không một dòng dữ liệu. */
function wpUrl(path,qs){
  var b=W.KH_API.rest;
  if(!qs)return b+path;
  return b+path+(b.indexOf('?')>=0?'&':'?')+qs}
APP.restUrl=wpUrl;
function wpPull(since){
  return fetch(wpUrl('state','since='+(since||0)),
      {credentials:'same-origin',headers:wpHeaders(false)})
    .then(function(r){return r.ok?r.json():null})
    .then(function(j){
      if(!j)return;
      lastSync=j.now||lastSync;
      var changed=false;
      Object.keys(j.docs||{}).forEach(function(c){
        if(COLLS.indexOf(c)<0)return;
        DATA[c]=DATA[c]||[];
        (j.docs[c]||[]).forEach(function(d){upsert(DATA[c],d);changed=true})});
      (j.deleted||[]).forEach(function(x){
        var a=DATA[x.coll]||[];
        for(var i=a.length-1;i>=0;i--)if(a[i].id===x.id){a.splice(i,1);changed=true}});
      if(changed)paint()},
      function(){})}
function wpWrite(path,body){
  return fetch(wpUrl(path,''),{method:'POST',credentials:'same-origin',
      headers:wpHeaders(true),body:JSON.stringify(body)})
    .then(function(r){
      if(r.ok)return r.json();
      if(r.status===403)toast('Máy chủ từ chối: tài khoản của bạn không có quyền sửa mục này.',true);
      else toast('Chưa lưu được lên máy chủ (mã '+r.status+').',true);
      return null},
      function(){toast('Mất kết nối máy chủ — thay đổi chưa được lưu.',true);return null})}
function connectWP(){
  S.wp=true;
  var api=W.KH_API;
  if(api.me){S.me=api.me;ls('kh.me',S.me)}
  if(api.title){S.role=api.title;ls('kh.role',S.role)}
  wpPull(0).then(function(){
    S.ready=true;paint();
    if(syncT)clearInterval(syncT);
    syncT=setInterval(function(){wpPull(lastSync)},8000);
    W.addEventListener('focus',function(){wpPull(lastSync)})})}

function connect(){
  if(W.KH_API&&W.KH_API.rest){connectWP();return}
  var c=W.claude;
  if(!c||typeof c.use!=='function'){offline();return}
  c.use('db').then(function(db){
    S.ready=true;
    if(!db){offline();toast('Không nối được kho dữ liệu chung — đang lưu tạm trên máy bạn.');return}
    S.db=db;
    COLLS.forEach(function(coll){
      db.collection(coll).onSnapshot(function(s){
        DATA[coll]=s.docs.map(function(d){var o=d.data()||{};o.id=d.id;return o});paint();
      },function(e){toast(errMsg(e),true)})});
  },function(){offline()});
}

/* ---------- tệp đính kèm ---------- */
/* Lý do lần tải tệp gần nhất thất bại — để màn hình báo đúng bệnh
   thay vì câu chung chung "không tải được". */
APP.uploadErr='';
APP.upload=function(blob,name){
  APP.uploadErr='';
  if(W.KH_API&&W.KH_API.media){
    var fd=new FormData();
    fd.append('file',blob,name||blob.name||('anh-'+Date.now()+'.jpg'));
    return fetch(W.KH_API.media,{method:'POST',credentials:'same-origin',
        headers:{'X-WP-Nonce':W.KH_API.nonce},body:fd})
      .then(function(r){
        return r.text().then(function(txt){
          var j=null;try{j=JSON.parse(txt)}catch(e){}
          if(!r.ok){
            /* WordPress trả mã lỗi kèm câu tiếng Việt; giữ nguyên cho người dùng đọc. */
            var m=j&&(j.message||j.code);
            if(!m&&r.status===413)m='Tệp lớn hơn mức máy chủ cho phép.';
            if(!m&&r.status===403)m='Tài khoản của bạn chưa có quyền tải tệp lên.';
            if(!m&&r.status===401)m='Phiên đăng nhập đã cũ — tải lại trang rồi thử lại.';
            APP.uploadErr='máy chủ trả mã '+r.status+(m?': '+m:'');
            return null}
          if(!j||!j.source_url){APP.uploadErr='máy chủ trả về nội dung lạ, không có đường dẫn tệp';return null}
          return {url:j.source_url,id:j.id,name:name||'tep',
                  type:j.mime_type||blob.type||'',size:blob.size||0}})},
        function(e){APP.uploadErr='không gọi được máy chủ ('+(e&&e.message||'mất kết nối')+')';return null})}
  var c=W.claude;
  if(!c||typeof c.use!=='function'){APP.uploadErr='bản này chạy ngoài máy chủ nên chưa có kho tệp';
    return Promise.resolve(null)}
  return c.use('assets').then(function(as){
    if(!as||!as.upload){APP.uploadErr='kho tệp chưa được bật cho bản này';return null}
    return as.upload(blob).then(function(r){
      if(!r){APP.uploadErr='kho tệp từ chối nhận tệp';return null}
      return {url:r.url||('/_blob/'+r.id),id:r.id,name:name||blob.name||'tep',
              type:r.contentType||blob.type||'',size:r.sizeBytes||blob.size||0}},
      function(e){APP.uploadErr=(e&&e.message)||'kho tệp báo lỗi';return null})},
    function(e){APP.uploadErr=(e&&e.message)||'không mở được kho tệp';return null})};
APP.isImage=function(t){return /^image\//.test(String(t||''))};

/* Danh sách bộ phận đã dùng ở mọi nơi: sổ bộ phận của Nhân sự, bộ phận ghi trên
   hồ sơ nhân viên, cộng thêm những gì gọi tới đưa vào (VD: bộ phận đã gõ ở các
   dự án). Gõ một lần rồi lần sau chọn lại, khỏi gõ lại và khỏi sai chính tả. */
function gomTen(){
  var seen={},out=[];
  function them(n){
    n=String(n==null?'':n).trim();
    if(!n)return;
    var k=n.toLowerCase();
    if(seen[k])return;
    seen[k]=1;out.push(n)}
  Array.prototype.forEach.call(arguments,function(arr){(arr||[]).forEach(them)});
  return out.sort(function(a,b){return String(a).localeCompare(String(b),'vi')})}
function cotTen(coll,k){return APP.col(coll).map(function(x){return x[k]})}

APP.deptAll=function(extra){
  return gomTen(cotTen('depts','name'),cotTen('staff','dept'),extra)};

/* Cơ sở làm việc — lấy từ hồ sơ nhân sự, dùng để tách bảng công theo từng nơi. */
APP.officeAll=function(extra){return gomTen(cotTen('staff','office'),extra)};

/* Gõ "khu vui chơi" trong khi sổ đã có "KHU VUI CHƠI" thì lấy lại đúng cách viết
   cũ — không thì một bộ phận nằm hai chỗ, lọc và gộp đều sai. */
APP.canonIn=function(nm,ds){
  var s=String(nm==null?'':nm).trim();
  if(!s)return '';
  var k=s.toLowerCase(),co='';
  (ds||[]).forEach(function(d){if(!co&&String(d).toLowerCase()===k)co=d});
  return co||s};
APP.deptCanon=function(nm,extra){return APP.canonIn(nm,APP.deptAll(extra))};
APP.officeCanon=function(nm,extra){return APP.canonIn(nm,APP.officeAll(extra))};

/* Giờ công lưu bằng PHÚT cho khỏi sai số cộng dồn; hiện ra thì "4h 56m". */
APP.gioPhut=function(m){
  m=Math.round(Number(m)||0);
  if(m<=0)return '0h';
  var h=Math.floor(m/60),p=m%60;
  return (h?h+'h':'')+(p?(h?' ':'')+p+'m':(h?'':'0h'))};
/* Nhận "4.9", "4,9", "4:56" hay "4h56" — người nhập quen kiểu nào cũng được. */
APP.docGio=function(v){
  var s=String(v==null?'':v).trim().replace(',','.');
  if(!s)return 0;
  var m=/^(\d+)\s*[:h]\s*(\d{1,2})?$/.exec(s);
  if(m)return (+m[1])*60+(+(m[2]||0));
  var n=parseFloat(s);
  return isNaN(n)||n<0?0:Math.round(n*60)};

/* ---------- nhân sự / người dùng ---------- */
APP.me=function(){return S.me};
APP.isMe=function(n){return !!S.me&&norm(n)===norm(S.me)};
APP.people=function(){
  var m={};
  APP.col('staff').forEach(function(s){if(s.name)m[s.name]=1});
  APP.col('tasks').forEach(function(t){if(t.assignee)m[t.assignee]=1});
  APP.col('projects').forEach(function(p){(p.members||[]).forEach(function(n){if(n)m[n]=1})});
  if(S.me)m[S.me]=1;
  return Object.keys(m).sort(function(a,b){return a.localeCompare(b,'vi')})};
APP.staffByName=function(n){var a=APP.col('staff');for(var i=0;i<a.length;i++)if(norm(a[i].name)===norm(n))return a[i];return null};

/* ---------- vai trò & quyền ---------- */
var ROLES={owner:'Chủ sở hữu',admin:'Quản trị',manager:'Quản lý',staff:'Nhân viên'};
var PERMS={
  owner:['*'],
  admin:['hr.edit','payroll.run','payroll.all','timeoff.approve','device.edit','post.publish',
         'checkin.edit','project.manage','flow.manage','booking.manage','channel.manage','settings.edit'],
  manager:['timeoff.approve','post.publish','checkin.edit','project.manage','flow.manage','booking.manage','payroll.all'],
  staff:[]};
APP.ROLES=ROLES;
APP.role=function(){
  if(W.KH_API&&W.KH_API.role)return W.KH_API.role;
  var s=APP.staffByName(S.me);
  if(s&&s.role)return s.role;
  if(!S.me)return 'staff';
  return (DATA.staff||[]).length?'staff':'owner'};
APP.roleLabel=function(){return ROLES[APP.role()]||ROLES.staff};
APP.can=function(k){
  var r=APP.role(),list=PERMS[r]||[];
  return list.indexOf('*')>=0||list.indexOf(k)>=0};
APP.needRole=function(k,msg){
  if(APP.can(k))return true;
  toast(msg||('Chỉ '+ROLES.admin+' hoặc '+ROLES.owner+' mới làm được việc này. Bạn đang là '+APP.roleLabel()+'.'),true);
  return false};

/* ---------- phạm vi quản lý theo bộ phận ----------
   Quản trị và Chủ sở hữu quản lý toàn công ty. Quản lý thì chỉ được thao tác
   lên người trong bộ phận của mình, cộng thêm những bộ phận mà họ được đặt làm
   trưởng bộ phận. Tắt được ở màn hình Bộ phận nếu muốn quay lại kiểu cũ. */
var SCOPED={'timeoff.approve':1,'checkin.edit':1,'payroll.all':1,'hr.edit':1};
APP.scopeOn=function(){return Number(APP.setting('scope',{dept:1}).dept)?true:false};
APP.deptDoc=function(name){
  var a=APP.col('depts'),k=norm(name);
  if(!k)return null;
  for(var i=0;i<a.length;i++)if(norm(a[i].name)===k)return a[i];
  return null};
/* Danh sách bộ phận mà mình quản lý; null nghĩa là quản lý tất cả. */
APP.myScope=function(){
  if(APP.role()!=='manager'||!APP.scopeOn())return null;
  var out={},me=APP.staffByName(S.me);
  if(me&&me.dept)out[norm(me.dept)]=1;
  APP.col('depts').forEach(function(d){
    if(d.name&&d.head&&norm(d.head)===norm(S.me))out[norm(d.name)]=1});
  return Object.keys(out)};
APP.scopeLabel=function(){
  var sc=APP.myScope();
  if(!sc)return '';
  var seen={},names=[];
  APP.col('staff').forEach(function(s){
    var k=norm(s.dept);
    if(k&&sc.indexOf(k)>=0&&!seen[k]){seen[k]=1;names.push(s.dept)}});
  APP.col('depts').forEach(function(d){
    var k=norm(d.name);
    if(k&&sc.indexOf(k)>=0&&!seen[k]){seen[k]=1;names.push(d.name)}});
  return names.join(', ')};

/* ---------- mảng kinh doanh ----------
   Công ty có nhiều mảng (Posh, HVC…). Phòng ban thì dùng chung giữa các mảng,
   nên mảng ghi ngay trên hồ sơ từng người: ô "unit". Để trống nghĩa là dùng chung,
   ai bên mảng nào cũng làm việc với người đó được. */
var UNIT_SHARED='Dùng chung';
APP.UNIT_SHARED=UNIT_SHARED;
APP.unitNames=function(){
  var raw=APP.setting('units',{list:[]}).list,out=[],seen={};
  (raw&&raw.length?raw:[]).forEach(function(x){
    var v=String(x||'').trim(),k=norm(v);
    if(v&&!seen[k]){seen[k]=1;out.push(v)}});
  out.sort(function(a,b){return a.localeCompare(b,'vi')});
  return out};
APP.unitOf=function(who){
  var s=who&&who.id?who:APP.staffByName(who);
  return s&&s.unit?String(s.unit).trim():''};
APP.myUnit=function(){var s=APP.staffByName(S.me);return s&&s.unit?String(s.unit).trim():''};
APP.unitScopeOn=function(){return Number(APP.setting('units',{scope:1}).scope)?true:false};
/* Gõ "posh" thì trả về đúng "Posh" đã khai, khỏi sinh mảng trùng. */
APP.canonUnit=function(nm){
  var k=norm(nm);if(!k)return '';
  var a=APP.unitNames();
  for(var i=0;i<a.length;i++)if(norm(a[i])===k)return a[i];
  return String(nm).trim()};

function unitOk(s){
  if(APP.role()!=='manager'||!APP.unitScopeOn())return true;
  var mine=APP.myUnit();
  if(!mine)return true;                 /* mình dùng chung thì làm việc với mọi mảng */
  var theirs=s&&s.unit?norm(s.unit):'';
  if(!theirs)return true;               /* người dùng chung thì mảng nào cũng quản được */
  return theirs===norm(mine)}

APP.inScope=function(who){
  var s=who&&who.id?who:APP.staffByName(who);
  var nm=s?s.name:who;
  if(S.me&&nm&&norm(nm)===norm(S.me))return true;
  var sc=APP.myScope();
  if(sc&&sc.indexOf(norm(s&&s.dept))<0)return false;
  return unitOk(s)};
/* Câu giải thích phạm vi hiện tại, để các màn hình nói cho người dùng biết vì sao thấy ít. */
APP.scopeNote=function(){
  if(APP.role()!=='manager')return '';
  var sc=APP.myScope(),mine=APP.myUnit(),parts=[];
  if(sc)parts.push('bộ phận '+(APP.scopeLabel()||'của mình'));
  if(APP.unitScopeOn()&&mine)parts.push('mảng '+mine+' (cộng người dùng chung)');
  return parts.length?parts.join(' và '):''};
APP.canFor=function(k,who){return APP.can(k)&&(!SCOPED[k]||APP.inScope(who))};
APP.needRoleFor=function(k,who,msg){
  if(SCOPED[k]&&APP.can(k)&&!APP.inScope(who)){
    var sc=APP.myScope()||[];
    toast(sc.length
      ? 'Bạn chỉ quản lý được người thuộc '+(APP.scopeLabel()||'bộ phận của mình')+'.'
      : 'Bạn chưa được gán bộ phận nên chưa quản lý được ai. Nhờ Quản trị gán bộ phận hoặc đặt bạn làm trưởng bộ phận.',
      true);
    return false}
  return APP.needRole(k,msg)};

/* ---------- cấu hình hệ thống ---------- */
APP.setting=function(id,def){
  var d=APP.find('settings',id);
  return d?Object.assign({},def||{},d):Object.assign({id:id},def||{})};

/* ---------- phòng trực tuyến (ai đang mở trang) ---------- */
var ROOM=null,PEERS=[],roomSubs=[];
APP.peers=function(){return PEERS};
APP.room=function(){return ROOM};
APP.onRoom=function(topic,fn){roomSubs.push([topic,fn]);if(ROOM)ROOM.on(topic,fn)};
APP.emit=function(topic,data){if(ROOM)try{ROOM.emit(topic,data)}catch(e){}};
function connectRoom(){
  var c=W.claude;
  if(!c||typeof c.use!=='function')return;
  c.use('room').then(function(r){
    if(!r)return;
    ROOM=r;
    roomSubs.forEach(function(x){try{r.on(x[0],x[1])}catch(e){}});
    try{r.onPeers(function(list){PEERS=list||[];paint()})}catch(e){}
    APP.announce();
  },function(){})}
APP.announce=function(){
  if(!ROOM)return;
  try{ROOM.presence({name:S.me||'Khách',role:S.role||'',app:S.app,at:Date.now()})}catch(e){}};

/* ---------- vẽ lại ---------- */
var painting=false;
function paint(){
  if(painting)return;painting=true;
  requestAnimationFrame(function(){painting=false;APP.render()})}
APP.paint=paint;

APP.keepFocus=function(fn){
  var a=document.activeElement,id=a&&a.id,st=null,en=null;
  try{if(a&&a.setSelectionRange){st=a.selectionStart;en=a.selectionEnd}}catch(e){}
  fn();
  if(id){var b=document.getElementById(id);
    if(b&&b!==document.activeElement){b.focus();
      try{if(st!=null&&b.setSelectionRange)b.setSelectionRange(st,en)}catch(e){}}}};

/* ---------- thông báo nhỏ ---------- */
var toastT;
function toast(msg,bad){
  var el=$('#toast');
  if(!el){el=document.createElement('div');el.id='toast';document.body.appendChild(el)}
  el.className='toast'+(bad?' err':'');el.textContent=msg;
  clearTimeout(toastT);toastT=setTimeout(function(){el.remove()},3600)}

/* ---------- menu thả xuống ---------- */
var openMenuEl=null;
function closeMenu(){if(openMenuEl){openMenuEl.remove();openMenuEl=null}}
function menu(anchor,items,current,cb){
  closeMenu();
  var m=document.createElement('div');m.className='menu';
  (Array.isArray(items)?items:Object.keys(items).map(function(k){return {k:k,l:items[k]}})).forEach(function(it){
    var b=document.createElement('button');b.type='button';b.textContent=it.l;
    if(it.k===current)b.className='on';
    b.addEventListener('click',function(){closeMenu();cb(it.k)});
    m.appendChild(b)});
  document.body.appendChild(m);
  var r=anchor.getBoundingClientRect();
  m.style.top=Math.min(r.bottom+4,W.innerHeight-m.offsetHeight-8)+'px';
  m.style.left=Math.max(8,Math.min(r.left,W.innerWidth-m.offsetWidth-8))+'px';
  openMenuEl=m;
  setTimeout(function(){document.addEventListener('click',closeMenu,{once:true})},0)}

/* ---------- hộp thoại biểu mẫu dùng chung ---------- */
function fieldHtml(f,i){
  var id='f_'+i,v=f.value==null?'':f.value,cls=f.span==='full'?'':'';
  var lab='<label for="'+id+'">'+esc(f.label)+(f.required?' *':'')+'</label>';
  var inp='';
  if(f.type==='textarea')inp='<textarea id="'+id+'" '+(f.rows?'rows="'+f.rows+'"':'')+' placeholder="'+esc(f.ph||'')+'">'+esc(v)+'</textarea>';
  else if(f.type==='select'){
    inp='<select id="'+id+'">'+(f.options||[]).map(function(o){
      var ov=o.v!==undefined?o.v:o,ol=o.l!==undefined?o.l:o;
      return '<option value="'+esc(ov)+'"'+(String(ov)===String(v)?' selected':'')+'>'+esc(ol)+'</option>'}).join('')+'</select>'}
  else if(f.type==='people')
    inp='<input id="'+id+'" list="dl_people" value="'+esc(v)+'" placeholder="'+esc(f.ph||'')+'">';
  else if(f.type==='datalist')
    inp='<input id="'+id+'" list="dl_'+id+'" value="'+esc(v)+'" placeholder="'+esc(f.ph||'')+'">'+
        '<datalist id="dl_'+id+'">'+(f.options||[]).map(function(o){
          return '<option value="'+esc(o.v!==undefined?o.v:o)+'"></option>'}).join('')+'</datalist>';
  else if(f.type==='checkbox')
    inp='<label style="display:flex;gap:8px;align-items:center;font-size:12.5px;text-transform:none;letter-spacing:0;font-weight:400;color:var(--ink)">'+
        '<input id="'+id+'" type="checkbox"'+(v?' checked':'')+' style="width:auto"> '+esc(f.cbLabel||'')+'</label>';
  else if(f.type==='color')
    inp='<div class="pick" id="'+id+'">'+AVC.map(function(c){
      return '<button type="button" class="swatch'+(c===v?' on':'')+'" data-c="'+c+'" style="background:'+c+'" aria-label="Màu"></button>'}).join('')+'</div>';
  else if(f.type==='chips')
    inp='<div class="pick" id="'+id+'">'+(f.options||[]).map(function(o){
      var ov=o.v!==undefined?o.v:o,ol=o.l!==undefined?o.l:o;
      return '<button type="button" data-v="'+esc(ov)+'"'+(String(ov)===String(v)?' class="on"':'')+'>'+esc(ol)+'</button>'}).join('')+'</div>';
  else inp='<input id="'+id+'" type="'+(f.type||'text')+'" value="'+esc(v)+'" placeholder="'+esc(f.ph||'')+'"'+
      (f.type==='number'?' step="'+(f.step||'1')+'"':'')+'>';
  /* Chỗ ghi chú luôn có sẵn (ẩn khi rỗng) để onChange viết vào được — VD nhắc
     ai vẫn phụ trách nhóm công việc vừa chọn. */
  return '<div class="fld '+cls+'">'+(f.type==='checkbox'?'':lab)+inp+
    '<span class="hint" id="h_'+i+'"'+(f.hint?'':' hidden')+'>'+esc(f.hint||'')+'</span></div>'}

APP.form=function(o){
  var dlg=document.createElement('dialog');
  if(o.wide)dlg.className='wide';
  var fields=o.fields||[];
  var rows='',i=0,buf=[];
  function flush(){
    if(!buf.length)return;
    rows+=buf.length===1?buf[0]:'<div class="'+(buf.length===3?'three':'two')+'">'+buf.join('')+'</div>';
    buf=[]}
  fields.forEach(function(f){
    var html=fieldHtml(f,i++);
    if(f.half){buf.push(html);if(buf.length===(f.third?3:2))flush()}
    else{flush();rows+=html}});
  flush();
  dlg.innerHTML='<form method="dialog"><div class="dlg-h"><h3>'+esc(o.title||'')+'</h3></div>'+
    '<div class="dlg-b">'+(o.note?'<p style="margin:0;color:var(--muted)">'+esc(o.note)+'</p>':'')+rows+
    (o.extra||'')+'</div>'+
    '<div class="dlg-f">'+(o.onDelete?'<button type="button" class="btn danger" data-x="del">'+esc(o.deleteLabel||'Xoá')+'</button>':'')+
    '<span class="spacer"></span>'+(o.actions||[]).map(function(a,k){
      return '<button type="button" class="btn '+(a.cls||'ghost')+'" data-x="a'+k+'">'+esc(a.label)+'</button>'}).join('')+
    '<button type="button" class="btn ghost" data-x="cancel">'+esc(o.cancelLabel||'Huỷ')+'</button>'+
    (o.onSave?'<button type="button" class="btn" data-x="save">'+esc(o.saveLabel||'Lưu')+'</button>':'')+'</div></form>';
  document.body.appendChild(dlg);
  function vals(){
    var out={},j=0;
    fields.forEach(function(f){
      var el=$('#f_'+j,dlg),v;
      if(f.type==='color'){v='';$$('.swatch',el).forEach(function(b){if(b.className.indexOf('on')>=0)v=b.getAttribute('data-c')})}
      else if(f.type==='chips'){v='';$$('button',el).forEach(function(b){if(b.className.indexOf('on')>=0)v=b.getAttribute('data-v')})}
      else if(f.type==='checkbox')v=el.checked;
      else if(f.type==='number')v=el.value===''?'':Number(el.value);
      else v=el.value;
      if(typeof v==='string'&&f.type!=='textarea')v=v.trim();
      out[f.k]=v;j++});
    return out}
  dlg.addEventListener('click',function(e){
    var sw=e.target.closest('.swatch');
    if(sw){$$('.swatch',sw.parentNode).forEach(function(b){b.className='swatch'});sw.className='swatch on';return}
    var ch=e.target.closest('.pick button[data-v]');
    if(ch){$$('button',ch.parentNode).forEach(function(b){b.className=''});ch.className='on';return}
    var b=e.target.closest('[data-x]');if(!b)return;
    var x=b.getAttribute('data-x');
    if(x==='cancel'){close();return}
    if(x==='del'){if(!o.confirmDelete||confirm(o.confirmDelete)){o.onDelete();close()}return}
    if(x==='save'){
      var v=vals(),bad=null,j=0;
      fields.forEach(function(f){if(f.required&&!String(v[f.k]||'').trim()&&!bad)bad='#f_'+j;j++});
      if(bad){var el=$(bad,dlg);if(el)el.focus();toast('Còn ô bắt buộc chưa điền.',true);return}
      if(o.onSave(v)===false)return;
      close();return}
    var m=/^a(\d+)$/.exec(x);
    if(m){var act=o.actions[+m[1]];if(act.fn(vals())===false)return;close()}});
  /* Ô này đổi thì ô kia theo — API nhỏ cho o.onChange(khoa, giaTri, api). */
  function idxOf(k){var j=-1;fields.forEach(function(f,n){if(f.k===k&&j<0)j=n});return j}
  function fldEl(k){var j=idxOf(k);return j<0?null:$('#f_'+j,dlg)}
  var api={
    vals:vals,el:fldEl,dlg:dlg,
    set:function(k,v){var el=fldEl(k);if(el&&'value' in el)el.value=v==null?'':v;return el},
    /* html đi thẳng vào trang nên bên gọi phải tự bọc tên người dùng nhập */
    hint:function(k,html){
      var j=idxOf(k);if(j<0)return;
      var el=$('#h_'+j,dlg);if(!el)return;
      el.innerHTML=html||'';el.hidden=!html}};
  if(o.onChange){
    dlg.addEventListener('change',function(e){
      var id=e.target&&e.target.id||'';
      if(id.slice(0,2)!=='f_')return;
      var f=fields[+id.slice(2)];
      if(f)o.onChange(f.k,vals(),api)});
  }
  dlg.addEventListener('keydown',function(e){
    if(e.key==='Enter'&&e.target.tagName==='INPUT'){e.preventDefault();var sv=$('[data-x="save"]',dlg);if(sv)sv.click()}});
  function close(){dlg.close();dlg.remove()}
  dlg.addEventListener('cancel',function(){setTimeout(function(){dlg.remove()},0)});
  dlg.showModal();
  /* Gọi một lần lúc mở, khoá rỗng: ô đã có sẵn giá trị cũng được gợi ý ngay,
     không phải bắt người dùng đổi qua đổi lại mới thấy. */
  if(o.onChange)o.onChange('',vals(),api);
  setTimeout(function(){var f=$('input,textarea,select',dlg);if(f)f.focus()},30);
  return {close:close,el:dlg,api:api}};

/* ---------- biểu tượng ---------- */
var IC={
  home:'<path d="M3 9.5 10 4l7 5.5V16a1 1 0 0 1-1 1h-3v-5H7v5H4a1 1 0 0 1-1-1Z"/>',
  work:'<rect x="3" y="5" width="14" height="12" rx="1.5"/><path d="M7 5V3.8h6V5M3 9.5h14"/>',
  request:'<path d="M5 3h7l4 4v10a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z"/><path d="M11.5 3v4.5H16M7 11h6M7 14h4"/>',
  flow:'<circle cx="5" cy="5" r="2"/><circle cx="15" cy="10" r="2"/><circle cx="5" cy="15" r="2"/><path d="M7 5.6h4a2 2 0 0 1 2 2v.6M7 14.4h4a2 2 0 0 0 2-2v-.6"/>',
  hrm:'<circle cx="10" cy="7" r="3"/><path d="M4 17c0-3.2 2.7-5 6-5s6 1.8 6 5"/>',
  checkin:'<circle cx="10" cy="10" r="7"/><path d="M10 6v4.3l3 1.7"/>',
  timeoff:'<path d="M5.5 3h9M5.5 17h9"/><path d="M6.5 3.4 10 9.6 13.5 3.4Z"/><path d="M6.5 16.6 10 10.4l3.5 6.2Z"/>',
  timesheet:'<rect x="3" y="4.5" width="14" height="12.5" rx="1.5"/><path d="M3 8.5h14M7 3v3M13 3v3M6.5 12h3M6.5 14.5h7"/>',
  payroll:'<rect x="2.5" y="5" width="15" height="10" rx="1.5"/><circle cx="10" cy="10" r="2.4"/><path d="M5 10h.01M15 10h.01"/>',
  inside:'<path d="M4 6.5h12v8H8.5L5 17.5V14.5H4Z"/><path d="M7 9.5h6M7 12h4"/>',
  wiki:'<path d="M4 4.5h5a2 2 0 0 1 2 2V16a2 2 0 0 0-2-1.6H4Z"/><path d="M16 4.5h-5a2 2 0 0 0-2 2V16a2 2 0 0 1 2-1.6h5Z"/>',
  meeting:'<rect x="2.5" y="5" width="10" height="10" rx="1.5"/><path d="M12.5 9l5-2.5v7L12.5 11Z"/>',
  bell:'<path d="M10 3.5a4.5 4.5 0 0 1 4.5 4.5v3l1.5 2.5H4l1.5-2.5V8A4.5 4.5 0 0 1 10 3.5Z"/><path d="M8.4 16.2a1.8 1.8 0 0 0 3.2 0"/>',
  moon:'<path d="M15.5 11.8A6.3 6.3 0 0 1 8.2 4.5a6.3 6.3 0 1 0 7.3 7.3Z"/>',
  grid:'<rect x="3" y="3" width="5.5" height="5.5" rx="1"/><rect x="11.5" y="3" width="5.5" height="5.5" rx="1"/><rect x="3" y="11.5" width="5.5" height="5.5" rx="1"/><rect x="11.5" y="11.5" width="5.5" height="5.5" rx="1"/>',
  search:'<circle cx="8.5" cy="8.5" r="5"/><path d="M12.4 12.4 17 17"/>',
  plus:'<path d="M10 4v12M4 10h12"/>',
  chev:'<path d="M7.5 4 13 10l-5.5 6"/>',
  message:'<path d="M4 5.5h12a1 1 0 0 1 1 1v6a1 1 0 0 1-1 1H9l-4 3.2V13.5H4a1 1 0 0 1-1-1v-6a1 1 0 0 1 1-1Z"/><path d="M6.5 8.5h7M6.5 11h4.5"/>',
  square:'<circle cx="10" cy="6.4" r="2.4"/><path d="M4.5 16.5c0-3 2.5-4.6 5.5-4.6s5.5 1.6 5.5 4.6"/><path d="M15.5 4.5h2.2M16.6 3.4v2.2"/>',
  booking:'<rect x="3" y="5" width="14" height="12" rx="1.5"/><path d="M3 9h14M7 3.5v3M13 3.5v3"/><path d="M6.5 12.5h3.5M6.5 14.8h6"/>',
  camera:'<path d="M3.5 6.5h3l1-1.6h5l1 1.6h3a1 1 0 0 1 1 1v7a1 1 0 0 1-1 1h-13a1 1 0 0 1-1-1v-7a1 1 0 0 1 1-1Z"/><circle cx="10" cy="11" r="2.8"/>',
  shield:'<path d="M10 3 4.5 5.2v4.3c0 3.4 2.3 6.5 5.5 7.5 3.2-1 5.5-4.1 5.5-7.5V5.2Z"/><path d="M7.6 10.2 9.4 12l3.2-3.4"/>'
};
APP.icon=function(n,cls){return '<svg class="'+(cls||'ic')+'" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">'+(IC[n]||'')+'</svg>'};

/* ---------- đăng ký ứng dụng ---------- */
APP.register=function(def){APP.apps.push(def);APP.byId[def.id]=def};
APP.go=function(id,arg){
  if(!APP.byId[id])return;
  S.app=id;ls('kh.app',id);
  document.body.classList.remove('nav');
  var d=APP.byId[id];
  if(d.onEnter)d.onEnter(arg);
  APP.announce();
  APP.render();
  var c=$('#content');if(c)c.scrollTop=0};

/* ---------- khung ---------- */
APP.render=function(){
  var d=APP.byId[S.app]||APP.byId.home;
  APP.cur=d;
  document.documentElement.style.setProperty('--app',d.color||'#1177D8');
  var wk=$('#wk');
  wk.className='wk'+(d.side?' has-side':'')+(d.info?' has-info':'');
  clearTip();
  APP.keepFocus(function(){
    renderRail();
    /* thay hẳn nút gốc để mọi listener của lượt vẽ trước biến mất,
       nếu không mỗi lần vẽ lại sẽ chồng thêm một listener và sự kiện chạy nhiều lần */
    var old=$('#appRoot'),root=old.cloneNode(false);
    root.innerHTML=d.view();
    old.parentNode.replaceChild(root,old);
    if(d.after)d.after();
    renderNotif();
    var dl=$('#dl_people');
    if(dl)dl.innerHTML=APP.people().map(function(n){return '<option value="'+esc(n)+'"></option>'}).join('');
  })};

function renderRail(){
  var h='<button class="logo" type="button" data-go="home" title="Trang chủ">KH</button>';
  var cats=[['work','CÔNG VIỆC'],['hrm','NHÂN SỰ'],['info','THÔNG TIN']],last=null;
  APP.apps.forEach(function(a){
    if(a.id==='home')return;
    if(a.cat!==last){h+='<span class="ir-sep"></span>';last=a.cat}
    var n=a.badge?a.badge():0;
    h+='<button class="ir'+(a.id===S.app?' on':'')+'" type="button" data-go="'+a.id+'" data-tip="'+esc(a.name)+'"'+
       (n?' data-n="'+n+'"':'')+' aria-label="'+esc(a.name)+'">'+APP.icon(a.icon)+'</button>'});
  h+='<span class="spacer"></span><span class="ir-sep"></span>'+
     '<button class="ir" type="button" id="railNotif" data-tip="Thông báo" aria-label="Thông báo"'+
     (notifItems().length?' data-n="'+notifItems().length+'"':'')+'>'+APP.icon('bell')+'</button>'+
     '<button class="ir" type="button" id="railTheme" data-tip="Nền sáng/tối" aria-label="Đổi nền">'+APP.icon('moon')+'</button>';
  $('#iconrail').innerHTML=h}

/* ---------- thanh bên dùng chung ---------- */
APP.sideUser=function(){
  return '<button class="side-user" type="button" id="meBtn">'+
    av(S.me||'?','l')+'<span><span class="nm">'+esc(S.me||'Chưa đặt tên')+'</span>'+
    '<span class="rl">'+esc(S.role||(S.me?'Thành viên':'Bấm để nhận tên bạn'))+'</span></span></button>'};
APP.sideSearch=function(id,ph,val){
  return '<div class="side-search"><input id="'+id+'" type="search" placeholder="'+esc(ph)+'" value="'+esc(val||'')+'" aria-label="'+esc(ph)+'"></div>'};
APP.navBtn=function(){return '<button class="icon-btn" id="navBtn" type="button" aria-label="Mở danh sách">'+
  '<svg width="16" height="16" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 5h14M3 10h14M3 15h14"/></svg></button>'};

/* ---------- thông báo ---------- */
function notifItems(){
  var out=[],now=Date.now();
  APP.col('tasks').forEach(function(t){
    if(t.status!=='done'&&t.due&&new Date(t.due).getTime()<now&&APP.isMe(t.assignee))
      out.push({ic:'work',app:'wework',color:'#E0464E',tx:'<b>'+esc(t.title)+'</b> đã quá hạn',tm:fmtDT(t.due),arg:t.pid})});
  APP.col('requests').forEach(function(r){
    if(r.status==='pending'&&(r.approvers||[]).some(function(a){return APP.isMe(a.name)&&a.state==='pending'}))
      out.push({ic:'request',app:'request',color:'#27A567',tx:'<b>'+esc(r.title)+'</b> đang chờ bạn duyệt',tm:ago(r.at),arg:r.id})});
  APP.col('timeoffs').forEach(function(t){
    if(t.status==='pending')
      out.push({ic:'timeoff',app:'timeoff',color:'#E0912B',tx:esc(t.staff)+' xin nghỉ '+t.days+' ngày, chờ duyệt',tm:ago(t.at),arg:t.id})});
  APP.col('posts').filter(function(p){return p.kind==='announce'}).slice(0,3).forEach(function(p){
    out.push({ic:'inside',app:'inside',color:'#C0559B',tx:'Thông báo: <b>'+esc(p.title)+'</b>',tm:ago(p.at),arg:p.id})});
  return out}
APP.notifCount=function(){return notifItems().length};
function renderNotif(){
  var items=notifItems();
  $('#notifBody').innerHTML=items.length?items.map(function(n){
    return '<button class="nrow" type="button" data-go="'+n.app+'" data-arg="'+esc(n.arg||'')+'">'+
      '<span class="av s" style="background:'+n.color+'">'+'</span>'+
      '<span class="tx">'+n.tx+'<span class="tm">'+esc(n.tm)+'</span></span></button>'}).join('')
    :'<p class="empty">Không có thông báo mới.</p>'}

/* ---------- sự kiện chung ---------- */
document.addEventListener('click',function(e){
  var g=e.target.closest('[data-go]');
  if(g){APP.go(g.getAttribute('data-go'),g.getAttribute('data-arg')||null);document.body.classList.remove('notif-open');return}
  var t=e.target.closest('#meBtn');
  if(t){meDialog();return}
  if(e.target.closest('#navBtn')){document.body.classList.toggle('nav');return}
  if(e.target.closest('#railNotif')){document.body.classList.toggle('notif-open');return}
  if(e.target.closest('#notifClose')){document.body.classList.remove('notif-open');return}
  if(e.target.closest('#scrim')){document.body.classList.remove('nav');document.body.classList.remove('notif-open');return}
  if(e.target.closest('#railTheme')){
    var r=document.documentElement,now=r.getAttribute('data-theme');
    var dark=now?now==='dark':W.matchMedia('(prefers-color-scheme: dark)').matches;
    var nx=dark?'light':'dark';r.setAttribute('data-theme',nx);ls('kh.theme',nx);return}
});
var tipEl=null;
function clearTip(){if(tipEl){tipEl.remove();tipEl=null}}
APP.clearTip=clearTip;
document.addEventListener('mouseover',function(e){
  var b=e.target.closest('[data-tip]');
  if(!b){clearTip();return}
  clearTip();
  tipEl=document.createElement('div');tipEl.className='tip';tipEl.textContent=b.getAttribute('data-tip');
  document.body.appendChild(tipEl);
  var r=b.getBoundingClientRect();
  tipEl.style.left=(r.right+8)+'px';
  tipEl.style.top=Math.max(4,Math.min(r.top+r.height/2-tipEl.offsetHeight/2,window.innerHeight-tipEl.offsetHeight-4))+'px'});
document.addEventListener('mouseleave',clearTip);
document.addEventListener('click',clearTip,true);
document.addEventListener('keydown',function(e){if(e.key==='Escape'){closeMenu();document.body.classList.remove('notif-open')}});
W.addEventListener('resize',closeMenu);

function meDialog(){
  if(W.KH_API&&W.KH_API.me){
    var api=W.KH_API;
    APP.form({title:'Tài khoản của bạn',fields:[],cancelLabel:'Đóng',
      extra:'<div style="display:flex;gap:12px;align-items:center">'+av(S.me,'xl')+
        '<div><div style="font-size:15px;font-weight:500">'+esc(S.me)+'</div>'+
        '<div class="by">'+esc(S.role||'')+'</div>'+
        (api.login?'<div class="by">Tên đăng nhập: <b>'+esc(api.login)+'</b></div>':'')+
        '<div style="margin-top:6px"><span class="chip vio">'+esc(APP.roleLabel())+'</span></div></div></div>'+
        '<p class="by" style="margin-top:12px">Tên và quyền lấy từ tài khoản WordPress đang đăng nhập. '+
        'Muốn đổi quyền thì sửa vai trò người dùng trong WordPress.</p>',
      actions:api.out?[{label:'Thoát',cls:'ghost danger',fn:function(){
        if(!confirm('Thoát khỏi nền tảng trên máy này?'))return false;
        W.location.href=api.out}}]:[]});
    return}
  var names=APP.people();
  APP.form({title:'Bạn là ai?',
    note:'Tên này dùng để ghi nhận người tạo, lọc “việc của tôi”, duyệt đề xuất và xác định quyền. Chỉ lưu trên máy bạn — không phải đăng nhập, nên đây là quy ước nội bộ chứ không phải bảo mật.',
    fields:[{k:'name',label:'Tên',value:S.me,type:'people',ph:'VD: Quang Thắng',half:true},
            {k:'role',label:'Chức danh',value:S.role,ph:'VD: Quản lý dự án',half:true}],
    extra:'<p class="by">Quyền hiện tại: <b>'+esc(APP.roleLabel())+'</b>. Vai trò lấy từ hồ sơ nhân sự — sửa ở ứng dụng Hồ sơ nhân sự.</p>',
    onSave:function(v){S.me=v.name;S.role=v.role;ls('kh.me',S.me);ls('kh.role',S.role);APP.announce();APP.render()}})}

/* ---------- xuất API ---------- */
APP.$=$;APP.$$=$$;APP.esc=esc;APP.uid=uid;APP.pad=pad;APP.norm=norm;
APP.hue=hue;APP.initials=initials;APP.av=av;APP.stack=stack;APP.AVC=AVC;
APP.fmtD=fmtD;APP.fmtDM=fmtDM;APP.fmtDT=fmtDT;APP.fmtT=fmtT;APP.ymd=ymd;APP.toLocal=toLocal;
APP.ago=ago;APP.money=money;APP.shortMoney=shortMoney;APP.daysBetween=daysBetween;APP.pct=pct;APP.DOW=DOW;APP.D=D;
APP.toast=toast;APP.menu=menu;APP.ls=ls;APP.lsj=lsj;

/* ---------- khởi động ---------- */
APP.boot=function(){
  APP.apps.sort(function(a,b){
    var o={platform:0,work:1,hrm:2,info:3};
    return (o[a.cat]||9)-(o[b.cat]||9)});
  APP.byId={};APP.apps.forEach(function(a){APP.byId[a.id]=a});
  if(!APP.byId[S.app])S.app='home';
  APP.render();connect();connectRoom()};
})();
