/**
 * Kiểm phần TÍNH GIỜ của màn bảng chấm công (bản JavaScript).
 *
 * 🔴 VÌ SAO PHẢI CÓ RIÊNG: cùng một phép tính "số giờ của một lượt" đang nằm ở HAI NƠI —
 * VCG_DB::so_gio() bên PHP và soGiay() bên giao diện. Hai bản lệch nhau là bảng trên màn hình
 * ra một số, bảng lương ra số khác, và không ai biết bên nào đúng. Chính `Code.gs` của bản cũ
 * đã tự cảnh báo đúng điều này.
 *
 * Bộ thử này chạy CÙNG BỘ SỐ với phần "số giờ một lượt" trong tools/test/kiem-nap-cong.php.
 *
 * Chạy: node tools/test/kiem-bang-cong.js
 */

/* Trích nguyên văn từ wordpress/vhcp-cong/templates/app.php */
function hhmm(g){
  if (g===null||g===undefined) return '';
  g=Number(g); if(isNaN(g)) return '';
  var t=((g%86400)+86400)%86400;
  var h=Math.floor(t/3600), p=Math.floor((t%3600)/60);
  return (h<10?'0':'')+h+':'+(p<10?'0':'')+p;
}
function soGiay(vao,ra){
  if(vao===null||vao===undefined||ra===null||ra===undefined) return null;
  vao=Number(vao); ra=Number(ra);
  if(ra<vao) ra+=86400;
  return ra-vao;
}
function gioNgan(giay){
  if(giay===null) return '';
  var h=Math.floor(giay/3600), p=Math.round((giay%3600)/60);
  if(p===60){ h++; p=0; }
  return h+'h'+(p?(' '+p+'p'):'');
}

var hong=0, dat=0;
function la(ten,mong,duoc){ if(mong===duoc){dat++;return;}
  console.log('  HỎNG '+ten+' — mong '+JSON.stringify(mong)+' được '+JSON.stringify(duoc)); hong++; }
function that(ten,d){ la(ten,true,!!d); }
var H=3600, P=60;

console.log('— số giờ, khớp từng con số với bản PHP —');
la('ca ngày 08:00-17:00',        9*H, soGiay(8*H, 17*H));
la('ca 08:30-17:15',             8*H+45*P, soGiay(8*H+30*P, 17*H+15*P));
la('CA ĐÊM 22:00-06:00',         8*H, soGiay(22*H, 6*H));
la('CA ĐÊM 23:30-07:30',         8*H, soGiay(23*H+30*P, 7*H+30*P));
that('ca đêm KHÔNG ra số âm',    soGiay(22*H, 6*H) > 0);
la('thiếu giờ ra -> null',       null, soGiay(8*H, null));
la('thiếu giờ vào -> null',      null, soGiay(null, 17*H));
la('trống cả hai -> null',       null, soGiay(null, null));
la('vào bằng ra -> 0',           0, soGiay(8*H, 8*H));
la('giờ đã trải phẳng 22:00->30:00', 8*H, soGiay(22*H, 30*H));
/* undefined phải hành xử như null — dữ liệu JSON thiếu khoá là ra undefined, không phải null. */
la('undefined -> null',          null, soGiay(undefined, 17*H));

console.log('— hiện giờ ra chữ —');
la('08:30',        '08:30', hhmm(8*H+30*P));
la('00:05',        '00:05', hhmm(5*P));
la('23:59',        '23:59', hhmm(23*H+59*P));
/* 🔴 Giờ ca đêm ĐÃ TRẢI PHẲNG (30:00) phải hiện là 06:00, không phải "30:00" — người đọc bảng
   cần thấy giờ trong ngày. Phần TÍNH thì vẫn dùng số giây gốc. */
la('30:00 hiện thành 06:00', '06:00', hhmm(30*H));
la('null -> rỗng',           '', hhmm(null));

console.log('— gộp giờ thành chữ ngắn —');
la('9 tiếng',        '9h', gioNgan(9*H));
la('8 tiếng 45 phút','8h 45p', gioNgan(8*H+45*P));
la('0 giây',         '0h', gioNgan(0));
la('null -> rỗng',   '', gioNgan(null));
/* 59 phút 45 giây làm tròn lên 60 phút -> phải thành "1h", không phải "0h 60p". */
la('làm tròn 59p45s -> 1h', '1h', gioNgan(59*P+45));

console.log();
if(hong){ console.log('🔴 HỎNG: '+hong+' | ĐẠT: '+dat); process.exit(1); }
console.log('✓ SẠCH — '+dat+' phép bảng công (JS)');
