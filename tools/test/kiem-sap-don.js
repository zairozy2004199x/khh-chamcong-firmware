/**
 * Kiểm THỨ TỰ danh sách đơn chi phí.
 *
 * Anh Thắng 25/08/2026: "đơn sắp xếp theo tuần nó đang lộn xộn". Ảnh chụp cho thấy một dòng
 * tuần 3/8-9/8 nằm chen giữa nhóm 10/8-16/8.
 *
 * 🔴 Nguyên nhân: danh sách giữ nguyên thứ tự máy chủ trả về, tức thứ tự TẠO ĐƠN. Ai lập bù
 * một đơn của tuần cũ là đơn đó chen vào giữa tuần mới. Danh sách vài chục dòng mà thứ tự
 * không đoán được thì tìm một đơn phải đọc từ đầu tới cuối.
 *
 * Dữ liệu dưới đây lấy ĐÚNG từ ảnh chụp, kể cả dòng lọt chỗ.
 *
 * Chạy: node tools/test/kiem-sap-don.js
 */

/* Hai hàm trích từ templates/app.html — giữ nguyên chữ. */
function _kyVal(k){
  k=String(k||'').trim();
  var m=/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/.exec(k); if(m) return (+m[3])*10000+(+m[2])*100+(+m[1]);
  var r=/\((\d{1,2})\/(\d{1,2})\s*-\s*(\d{1,2})\/(\d{1,2})\/(\d{4})\)/.exec(k);
  if(r){ var sd=+r[1], sm=+r[2], em=+r[4], yy=+r[5], sy=(sm>em)?yy-1:yy; return sy*10000+sm*100+sd; }
  var g=/(\d{1,2})\/(\d{1,2})\/(\d{4})/.exec(k); if(g) return (+g[3])*10000+(+g[2])*100+(+g[1]);
  var t=/T\s*(\d{1,2})\s*\/\s*(\d{4})/i.exec(k); if(t) return (+t[2])*10000+(+t[1])*100;
  return -1;
}
function _sapDon(ds){
  return (ds||[]).slice().sort(function(a,b){
    var ka=_kyVal(a&&a.ky), kb=_kyVal(b&&b.ky);
    if(ka!==kb) return kb-ka;
    var ca=String((a&&a.coso)||''), cb=String((b&&b.coso)||'');
    if(ca!==cb) return ca.localeCompare(cb,'vi');
    return String((a&&a.nguoiLap)||'').localeCompare(String((b&&b.nguoiLap)||''),'vi');
  });
}

var hong=0, dat=0;
function la(ten,mong,duoc){ if(JSON.stringify(mong)===JSON.stringify(duoc)){dat++;return;}
  console.log('  HỎNG '+ten+'\n    mong '+JSON.stringify(mong)+'\n    được '+JSON.stringify(duoc)); hong++; }
function that(ten,d){ la(ten,true,!!d); }

var T17='T8/2026 (17/8-23/8/2026)', T10='T8/2026 (10/8-16/8/2026)', T03='T8/2026 (3/8-9/8/2026)';
/* Đúng thứ tự trên ảnh — chú ý dòng NHÀ MA BÀ RỊA tuần 3/8 lọt vào giữa nhóm 10/8. */
var GOC=[
  {ky:T17,coso:'SNOW NHÀ TUYẾT BÌNH DƯƠNG',nguoiLap:'Trương Thanh Lâm'},
  {ky:T17,coso:'NHÀ MA BÌNH DƯƠNG',nguoiLap:'Lê Thị Mai Linh'},
  {ky:T17,coso:'NHÀ MA BÀ RỊA',nguoiLap:'Nguyễn Gia Huy'},
  {ky:T17,coso:'FUNZONE VŨNG TÀU',nguoiLap:'Nguyễn Mai Anh'},
  {ky:T17,coso:'FARM PHAN THIẾT',nguoiLap:'Nguyễn Văn Bin'},
  {ky:T17,coso:'FUNZONE ADVENTURE',nguoiLap:'Nguyễn Thị Mỹ Tiên'},
  {ky:T17,coso:'TÀU TÂN PHÚ',nguoiLap:'Trần Ngọc Minh Truyền'},
  {ky:T10,coso:'ADV GO! AN LẠC',nguoiLap:'Huỳnh Thị Thu Thảo'},
  {ky:T10,coso:'SNOW NHÀ TUYẾT BÌNH DƯƠNG',nguoiLap:'Trương Thanh Lâm'},
  {ky:T10,coso:'NHÀ MA BÀ RỊA',nguoiLap:'Nguyễn Gia Huy'},
  {ky:T10,coso:'FARM PHAN THIẾT',nguoiLap:'Nguyễn Văn Bin'},
  {ky:T10,coso:'FUNZONE VŨNG TÀU',nguoiLap:'Nguyễn Mai Anh'},
  {ky:T03,coso:'NHÀ MA BÀ RỊA',nguoiLap:'Nguyễn Gia Huy'},        // <-- LỌT CHỖ
  {ky:T10,coso:'NHÀ MA BÌNH DƯƠNG',nguoiLap:'Lê Thị Mai Linh'},
  {ky:T10,coso:'FUNZONE ADVENTURE',nguoiLap:'Nguyễn Thị Mỹ Tiên'},
  {ky:T03,coso:'SNOW NHÀ TUYẾT BÌNH DƯƠNG',nguoiLap:'Trương Thanh Lâm'},
  {ky:T03,coso:'VR TÂN AN',nguoiLap:'Huỳnh Thị Thu Thảo'},
];

var S=_sapDon(GOC);

console.log('— gom đúng theo kỳ —');
var kys=S.map(function(d){return _kyVal(d.ky);});
var giam=true; for(var i=1;i<kys.length;i++) if(kys[i]>kys[i-1]) giam=false;
that('kỳ giảm dần từ trên xuống', giam);

/* 🔴 CHỐT CHÍNH: mỗi kỳ phải là MỘT khối liền, không xen kẽ. */
var thay=[], truoc=null;
S.forEach(function(d){ var k=_kyVal(d.ky); if(k!==truoc){ thay.push(k); truoc=k; } });
la('mỗi kỳ đúng một khối liền', [20260817,20260810,20260803], thay);

console.log('— trong cùng kỳ xếp theo cơ sở —');
var t10=S.filter(function(d){return _kyVal(d.ky)===20260810;}).map(function(d){return d.coso;});
la('nhóm 10/8 theo A→Z', ['ADV GO! AN LẠC','FARM PHAN THIẾT','FUNZONE ADVENTURE','FUNZONE VŨNG TÀU','NHÀ MA BÀ RỊA','NHÀ MA BÌNH DƯƠNG','SNOW NHÀ TUYẾT BÌNH DƯƠNG'], t10);

console.log('— biên —');
la('mảng rỗng', [], _sapDon([]));
la('không truyền gì', [], _sapDon());
/* Kỳ đọc không ra phải rơi xuống CUỐI — thấy ngay là đơn đó có gì sai, thay vì nấp giữa danh sách. */
var L=_sapDon([{ky:'linh tinh',coso:'X'},{ky:T10,coso:'Y'},{ky:T17,coso:'Z'}]);
la('kỳ hỏng rơi xuống cuối', ['Z','Y','X'], L.map(function(d){return d.coso;}));
/* KHÔNG được sửa mảng gốc — nơi khác còn dùng BOOT.dons theo thứ tự của nó. */
that('không đụng mảng gốc', GOC[12].ky===T03 && GOC.length===17);
/* Đơn dự án (khoảng ngày tự chọn, vắt tháng) phải xếp đúng theo ngày BẮT ĐẦU. */
var D=_sapDon([{ky:T17,coso:'a'},{ky:'T8/2026 (25/8-5/9/2026)',coso:'b'},{ky:T03,coso:'c'}]);
la('đơn dự án vắt tháng xếp đúng chỗ', ['b','a','c'], D.map(function(d){return d.coso;}));

console.log();
if(hong){ console.log('🔴 HỎNG: '+hong+' | ĐẠT: '+dat); process.exit(1); }
console.log('✓ SẠCH — '+dat+' phép thứ tự đơn');
