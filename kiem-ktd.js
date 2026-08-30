/* Kiểm luật SẮP XẾP + LỌC của màn Duyệt báo cáo — chạy thật hàm sort/filter, không dò chuỗi.
   Trích thẳng từ mã nguồn để nếu ai đổi luật mà quên sửa bài kiểm thì đỏ ngay. */
const fs=require('fs');
const src=fs.readFileSync(process.argv[2],'utf8');
let DAT=0; const TRUOT=[];
function t(n,ok,them){ if(ok){DAT++;} else {TRUOT.push(n+(them!=null?(' → '+JSON.stringify(them)):''));} }

/* Bốc đúng hai biểu thức trong ktdLoad ra rồi chạy chúng — cách duy nhất canh được LUẬT thật
   mà không phải dựng cả DOM. */
const iLoc = src.indexOf('var rows0 = r.rows.filter(function(o){');
const iLocEnd = src.indexOf('});', iLoc)+3;
const iSort = src.indexOf('var rows = rows0.slice().sort(function(a,b){');
const iSortEnd = src.indexOf('});', iSort)+3;
t('trích được biểu thức lọc', iLoc>0 && iLocEnd>iLoc);
t('trích được biểu thức sắp xếp', iSort>0 && iSortEnd>iSort);

const than = `
  var KTD_COSO=arguments[1], KTD_NV=arguments[2], KTD_NGAY=arguments[3];
  var r={rows:arguments[0]};
  ${src.slice(iLoc,iLocEnd)}
  ${src.slice(iSort,iSortEnd)}
  return rows;
`;
const chay = new Function(than);

const R = (id,ngay,taoLuc,chairs,ok)=>({key:id,ngay:ngay,taoLuc:taoLuc,coso:'CS '+id,staff:'NV '+id,chairs:chairs,confirmedChairs:ok});

/* ---- MỚI NỘP NHẤT LÊN ĐẦU, KHÔNG THEO NGÀY BÁO CÁO ----
   Báo cáo của ngày 27 nộp muộn ngày 30 phải đứng TRÊN báo cáo ngày 29 nộp hôm 29. */
let ra = chay([
  R('cu','2026-08-29','2026-08-29 10:00:00',2,0),
  R('moi','2026-08-27','2026-08-30 08:00:00',2,0),
],'','','');
t('🔴 mới NỘP nhất lên đầu, dù ngày báo cáo cũ hơn', ra[0].key==='moi', ra.map(x=>x.key));

/* ---- CHƯA DUYỆT LÊN TRƯỚC ĐÃ DUYỆT, dù nộp cũ hơn ---- */
ra = chay([
  R('daduyet','2026-08-30','2026-08-30 09:00:00',2,2),
  R('chuaduyet','2026-08-20','2026-08-20 09:00:00',2,0),
],'','','');
t('🔴 chưa duyệt lên trước, dù nộp cũ hơn', ra[0].key==='chuaduyet', ra.map(x=>x.key));

/* ---- DUYỆT DỞ DANG cũng tính là CHƯA duyệt ---- */
ra = chay([
  R('xong','2026-08-30','2026-08-30 09:00:00',3,3),
  R('dodang','2026-08-29','2026-08-29 09:00:00',3,1),
],'','','');
t('duyệt dở dang (1/3 ghế) vẫn xếp vào nhóm chưa duyệt', ra[0].key==='dodang', ra.map(x=>x.key));

/* ---- TRONG CÙNG NHÓM: mới nộp trước ---- */
ra = chay([
  R('a','2026-08-01','2026-08-01 09:00:00',1,0),
  R('c','2026-08-03','2026-08-03 09:00:00',1,0),
  R('b','2026-08-02','2026-08-02 09:00:00',1,0),
],'','','');
t('trong cùng nhóm chưa duyệt: mới nộp trước', ra.map(x=>x.key).join()==='c,b,a', ra.map(x=>x.key));

/* ---- BÁO CÁO CŨ KHÔNG CÓ MỐC NỘP thì rơi về ngày báo cáo ----
   Nạp từ sổ cũ có trước khi cột `tao_luc` ra đời; không có mốc nào thì đừng đảo bừa. */
ra = chay([
  R('cu1','2026-08-05','',1,0),
  R('cu2','2026-08-09','',1,0),
],'','','');
t('không có mốc nộp -> rơi về ngày báo cáo, mới nhất trước', ra[0].key==='cu2', ra.map(x=>x.key));

/* ---- LỌC THEO NGÀY ---- */
const ds4 = [
  R('n27','2026-08-27','2026-08-27 09:00:00',1,0),
  R('n28','2026-08-28','2026-08-28 09:00:00',1,0),
  R('n28b','2026-08-28','2026-08-28 10:00:00',1,0),
];
t('🔴 lọc đúng một ngày', chay(ds4,'','','2026-08-28').map(x=>x.key).sort().join()==='n28,n28b',
  chay(ds4,'','','2026-08-28').map(x=>x.key));
t('để trống ô ngày -> cả tháng', chay(ds4,'','','').length===3);
t('ngày không có báo cáo nào -> rỗng, không phải trả hết', chay(ds4,'','','2026-08-01').length===0);

/* ---- LỌC NGÀY CHỒNG VỚI LỌC CƠ SỞ / NHÂN VIÊN ---- */
const ds5 = [
  {key:'x',ngay:'2026-08-28',taoLuc:'2026-08-28 09:00:00',coso:'GO DĨ AN',staff:'Huyền Diệu',chairs:1,confirmedChairs:0},
  {key:'y',ngay:'2026-08-28',taoLuc:'2026-08-28 10:00:00',coso:'SƠN TIÊN',staff:'Huyền Diệu',chairs:1,confirmedChairs:0},
];
t('lọc ngày + cơ sở cùng lúc', chay(ds5,'GO DĨ AN','','2026-08-28').map(x=>x.key).join()==='x');
t('lọc ngày + nhân viên cùng lúc', chay(ds5,'','Huyền Diệu','2026-08-28').length===2);
t('cơ sở không khớp -> rỗng', chay(ds5,'KHÔNG CÓ','','').length===0);

/* ---- GIAO DIỆN phải có ô ngày và nút xoá lọc ---- */
t('🔴 có ô nhập ngày', src.indexOf('type="date" id="ktd-ngay"')>0);
t('có nút xoá lọc ngày', src.indexOf("id=\"ktd-ngay-xoa\"")>0);
/* 🔴 Chọn ngày thuộc tháng khác thì phải KÉO Ô THÁNG THEO — `kt_ds` chỉ trả về một tháng, không
   kéo là lọc trên tập không chứa ngày ấy và ra rỗng. */
t('🔴 chọn ngày khác tháng thì kéo ô tháng theo',
  /if\(th && th!==KTD_THANG\)\{ KTD_THANG=th;/.test(src));
t('🔴 đổi tháng thì bỏ lọc ngày lệch tháng',
  /if\(KTD_NGAY && KTD_NGAY\.slice\(0,7\)!==KTD_THANG\)\{/.test(src));

if(TRUOT.length){ console.log('HỎNG: '+TRUOT.length); TRUOT.forEach(x=>console.log('  ✗ '+x)); console.log('ĐẠT: '+DAT); process.exit(1); }
console.log('✓ ĐẠT '+DAT+' phép — thứ tự duyệt báo cáo và bộ lọc ngày.');
