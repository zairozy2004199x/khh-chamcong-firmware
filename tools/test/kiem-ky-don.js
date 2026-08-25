/**
 * Kiểm CHUỖI KỲ của đơn chi phí.
 *
 * Anh Thắng 25/08/2026: nhân viên Văn phòng lên dự án thì đơn theo lịch linh động, thời gian
 * bất kỳ — gom theo tuần chỉ đúng với nhân viên cơ sở. Nghĩa là kỳ không còn luôn luôn là một
 * tuần bị cắt ở cuối tháng nữa, mà là một khoảng ngày người ta tự chọn.
 *
 * 🔴 VÌ SAO PHẢI KIỂM: chuỗi kỳ chỉ mang MỘT năm, nằm ở cuối ('T12/2026 (28/12-5/1/2027)').
 * Mọi báo cáo xếp đơn theo _kyVal() đọc ngược từ chuỗi đó ra. Đợt vắt năm mà đọc sai là đơn
 * nhảy sang năm khác trong báo cáo, và không có gì báo. Đơn tuần không bao giờ chạm ca này vì
 * nó bị cắt ở cuối tháng; đơn dự án thì chạm thường xuyên.
 *
 * Chạy: node tools/test/kiem-ky-don.js
 */
function _kyRange(s,e){ return 'T'+(s.getMonth()+1)+'/'+s.getFullYear()+' ('+s.getDate()+'/'+(s.getMonth()+1)+'-'+e.getDate()+'/'+(e.getMonth()+1)+'/'+e.getFullYear()+')'; }
function _kyVal(k){
  k=String(k||'').trim();
  var m=/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/.exec(k); if(m) return (+m[3])*10000+(+m[2])*100+(+m[1]);
  var r=/\((\d{1,2})\/(\d{1,2})\s*-\s*(\d{1,2})\/(\d{1,2})\/(\d{4})\)/.exec(k);
  if(r){ var sd=+r[1], sm=+r[2], em=+r[4], yy=+r[5], sy=(sm>em)?yy-1:yy; return sy*10000+sm*100+sd; }
  return -1;
}
var hong=0, dat=0;
function la(ten,mong,duoc){ if(mong===duoc){dat++;return;} console.log('  HỎNG '+ten+' — mong '+mong+' được '+duoc); hong++; }
function D(y,m,d){ return new Date(y,m-1,d); }

// đợt trong một tháng
la('25/8 -> 30/8/2026 ra chuỗi đúng', 'T8/2026 (25/8-30/8/2026)', _kyRange(D(2026,8,25),D(2026,8,30)));
la('  đọc lại ra 20260825', 20260825, _kyVal(_kyRange(D(2026,8,25),D(2026,8,30))));
// đợt VẮT THÁNG — tuần thường bị cắt ở cuối tháng, đợt dự án thì không
la('25/8 -> 5/9/2026 vắt tháng', 'T8/2026 (25/8-5/9/2026)', _kyRange(D(2026,8,25),D(2026,9,5)));
la('  đọc lại vẫn ra tháng 8', 20260825, _kyVal(_kyRange(D(2026,8,25),D(2026,9,5))));
// đợt VẮT NĂM — chỗ dễ sai nhất: chuỗi chỉ mang MỘT năm, ở cuối
la('28/12/2026 -> 5/1/2027 vắt năm', 'T12/2026 (28/12-5/1/2027)', _kyRange(D(2026,12,28),D(2027,1,5)));
la('  đọc lại phải ra NĂM 2026', 20261228, _kyVal(_kyRange(D(2026,12,28),D(2027,1,5))));
// đợt dài một ngày, và đợt dài hai tháng
la('1 ngày', 20260901, _kyVal(_kyRange(D(2026,9,1),D(2026,9,1))));
la('đợt dài 2 tháng', 20260710, _kyVal(_kyRange(D(2026,7,10),D(2026,9,20))));
console.log(hong ? ('\n🔴 HỎNG: '+hong+' | ĐẠT: '+dat) : ('\n✓ SẠCH — '+dat+' phép chuỗi kỳ'));
process.exit(hong?1:0);
