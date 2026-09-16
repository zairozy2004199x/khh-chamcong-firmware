<?php
/**
 * TRANG VẬN HÀNH — một trang, tự vẽ bằng JS, nói chuyện với máy chủ qua đúng một cửa REST.
 *
 * 🔴 TRANG NÀY KHÔNG GIỮ BÍ MẬT GÌ. Nó chỉ giữ một chuỗi thẻ phiên. Mọi con số, mọi quyền đều
 *    hỏi máy chủ; ẩn một cái nút ở đây KHÔNG phải là chặn, chặn nằm ở `VHVH_API::cong()`.
 *
 * @package VHCP_VanHanh
 */

defined( 'ABSPATH' ) || exit;

$vhvh_goc = esc_url_raw( rest_url( VHVH_API::NS . '/viec' ) );
?><!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Vận Hành — K&amp;H</title>
<style>
:root{
  --nen:#f5f6f8; --mat:#fff; --mat2:#eef0f4; --vien:#dde1e8;
  --chu:#1a2233; --mo:#69707e; --xanh:#2f5fce; --xanh2:#1f45a8;
  --luc:#1f8f5c; --luc-n:#e7f7ee; --cam:#b8791f; --cam-n:#fdf3e2;
  --do:#d13438; --do-n:#fbeaea; --bong:0 1px 2px rgba(16,24,40,.05),0 6px 18px rgba(16,24,40,.06);
}
*{box-sizing:border-box}
body{margin:0;background:var(--nen);color:var(--chu);
  font:15px/1.55 'Be Vietnam Pro',-apple-system,'Segoe UI',Roboto,Arial,sans-serif}
button,input,select,textarea{font:inherit}
.bao{max-width:1120px;margin:0 auto;padding:0 16px 64px}
.dinh{background:var(--mat);border-bottom:1px solid var(--vien)}
.dinh-in{max-width:1120px;margin:0 auto;padding:12px 16px;display:flex;gap:14px;
  align-items:center;justify-content:space-between;flex-wrap:wrap}
.hieu{font-weight:800;font-size:20px;color:var(--xanh);letter-spacing:.02em}
.hieu span{font-weight:400;font-size:13px;color:var(--mo);border-left:1px solid var(--vien);
  padding-left:10px;margin-left:10px}
.toi{font-size:13px;color:var(--mo)}
.toi b{color:var(--chu)}
/* ══ BỐ CỤC HAI CỘT: thanh dọc + thân trang ══════════════════════════════════════════════
   Thanh dọc CỐ ĐỊNH khi cuộn (position:sticky) — bảng số liệu dài hai màn hình mà thanh trôi
   mất thì muốn đổi mục phải cuộn ngược lên đầu. */
.khung{display:grid;grid-template-columns:238px 1fr;gap:0;min-height:calc(100vh - 58px)}
.ben{background:var(--mat);border-right:1px solid var(--vien);padding:14px 10px 40px;
  position:sticky;top:0;align-self:start;max-height:100vh;overflow-y:auto}
.ben .nhom{font-size:11px;font-weight:700;color:var(--mo);letter-spacing:.06em;
  padding:14px 10px 6px}
.ben .nhom:first-child{padding-top:2px}
.ben button{display:flex;align-items:center;gap:9px;width:100%;text-align:left;background:none;
  border:0;border-radius:9px;padding:9px 10px;cursor:pointer;color:var(--chu);font-size:14px}
.ben button:hover{background:var(--mat2)}
.ben button.chon{background:#eaf0fd;color:var(--xanh2);font-weight:600;
  box-shadow:inset 3px 0 0 var(--xanh)}
.ben button.chua{color:var(--mo);cursor:default}
.ben button.chua:hover{background:none}
.ben .sap{margin-left:auto;font-size:10px;font-weight:600;color:var(--cam);
  background:var(--cam-n);border-radius:999px;padding:1px 7px}
.than{padding:22px 24px 64px;min-width:0}
.than h1{margin:0;font-size:26px}
.than .duoi{color:var(--mo);font-size:14px;margin:4px 0 0}
.mo-ben{display:none;background:none;border:1px solid var(--vien);border-radius:9px;
  padding:7px 11px;cursor:pointer}
@media(max-width:900px){
  .khung{grid-template-columns:1fr}
  .ben{position:static;max-height:none;border-right:0;border-bottom:1px solid var(--vien);
    display:none}
  .ben.hien{display:block}
  .mo-ben{display:inline-block}
  .than{padding:16px 14px 56px}
}
/* ══ thẻ cơ sở & bảng điểm ══════════════════════════════════════════════════════════════ */
.cl-dong{display:flex;gap:10px;align-items:flex-start;padding:7px 2px;
  border-bottom:1px solid var(--vien);cursor:pointer;font-size:14px}
.cl-dong:last-child{border-bottom:0}
.cl-dong input{margin-top:3px;width:17px;height:17px;flex:none;cursor:pointer}
.coso-the{max-width:420px}
.coso-the .ten{font-weight:700;font-size:15px}
.doi{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:10px}
.doi .box{background:var(--mat2);border-radius:10px;padding:10px}
.doi .box .l{font-size:11px;color:var(--mo);font-weight:600;letter-spacing:.04em}
.nhan{display:inline-block;padding:2px 9px;border-radius:999px;font-size:12px;font-weight:600;
  margin-top:4px}
.nhan.xau{background:var(--do-n);color:var(--do)}
.nhan.tot{background:var(--luc-n);color:var(--luc)}
.nhan.chu-y{background:var(--cam-n);color:var(--cam)}
.nhan.chua{background:var(--mat2);color:var(--mo)}

.nav{display:flex;gap:6px;flex-wrap:wrap;padding:10px 0 2px}
.nav button{background:none;border:1px solid transparent;border-radius:9px;padding:8px 13px;
  cursor:pointer;color:var(--mo);font-weight:500}
.nav button:hover{background:var(--mat2)}
.nav button.chon{background:var(--mat);border-color:var(--vien);color:var(--xanh);
  font-weight:600;box-shadow:var(--bong)}
.nav .cham{display:inline-block;min-width:18px;padding:0 5px;margin-left:6px;border-radius:9px;
  background:var(--do);color:#fff;font-size:11px;font-weight:700;text-align:center;line-height:18px}
.the{background:var(--mat);border:1px solid var(--vien);border-radius:12px;padding:16px;
  box-shadow:var(--bong);margin-top:14px}
.the h2{margin:0 0 4px;font-size:17px}
.the h3{margin:18px 0 8px;font-size:14px;color:var(--mo);text-transform:uppercase;
  letter-spacing:.04em}
.nho{font-size:13px;color:var(--mo)}
.hang{display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end}
.o{display:flex;flex-direction:column;gap:4px}
.o label{font-size:12px;color:var(--mo);font-weight:500}
.o input,.o select,.o textarea{border:1px solid var(--vien);border-radius:9px;padding:8px 10px;
  background:var(--mat);color:var(--chu);min-width:120px}
.o input:focus,.o select:focus,.o textarea:focus{outline:2px solid var(--xanh);outline-offset:-1px}
.nut{border:1px solid var(--vien);background:var(--mat);border-radius:9px;padding:9px 15px;
  cursor:pointer;font-weight:600;color:var(--chu)}
.nut:hover{background:var(--mat2)}
.nut.chinh{background:var(--xanh);border-color:var(--xanh);color:#fff}
.nut.chinh:hover{background:var(--xanh2)}
.nut.luc{background:var(--luc);border-color:var(--luc);color:#fff}
.nut.do{background:var(--do);border-color:var(--do);color:#fff}
.nut[disabled]{opacity:.5;cursor:not-allowed}
.o-so{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-top:8px}
.o-so .box{background:var(--mat2);border-radius:10px;padding:12px}
.o-so .box .n{font-size:22px;font-weight:700}
.o-so .box .l{font-size:12px;color:var(--mo)}
table{width:100%;border-collapse:collapse;font-size:14px}
th,td{padding:8px 10px;border-bottom:1px solid var(--vien);text-align:left;vertical-align:top}
th{font-size:12px;color:var(--mo);text-transform:uppercase;letter-spacing:.03em;font-weight:600}
td.so,th.so{text-align:right;font-variant-numeric:tabular-nums}
.cuon{overflow-x:auto}
.the-tt{display:inline-block;padding:2px 9px;border-radius:999px;font-size:12px;font-weight:600}
.tt-cho{background:var(--cam-n);color:var(--cam)}
.tt-duyet{background:var(--luc-n);color:var(--luc)}
.tt-tu_choi{background:var(--do-n);color:var(--do)}
.tt-mo{background:var(--cam-n);color:var(--cam)}
.tt-dong{background:var(--luc-n);color:var(--luc)}
.tre{background:var(--do-n);color:var(--do)}
.ve-nhom{margin-top:12px}
.ve-nhom .ten{font-size:12px;font-weight:700;color:var(--mo);text-transform:uppercase;
  letter-spacing:.04em;margin-bottom:6px}
.ve-luoi{display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:8px}
.ve-o{display:flex;align-items:center;gap:8px;background:var(--mat2);border-radius:9px;padding:6px 10px}
.ve-o .nh{flex:1;font-size:13px}
.ve-o .gia{font-size:11px;color:var(--mo)}
.ve-o input{width:64px;text-align:right;border:1px solid var(--vien);border-radius:7px;padding:5px 7px}
.dong-tien{display:flex;gap:8px;margin-top:6px}
.dong-tien input.nhan{flex:1}
.dong-tien input.tien{width:130px;text-align:right}
.bao-loi{background:var(--do-n);color:var(--do);border-radius:9px;padding:10px 12px;margin-top:10px;
  font-size:14px}
.bao-ok{background:var(--luc-n);color:var(--luc);border-radius:9px;padding:10px 12px;margin-top:10px;
  font-size:14px}
.giua{max-width:380px;margin:60px auto}
@media(max-width:620px){ .dinh-in{padding:10px 12px} .the{padding:13px} }
</style>
</head>
<body>
<div id="ung-dung"></div>

<script>
var VHVH_GOC = <?php echo wp_json_encode( $vhvh_goc ); ?>;
</script>
<script>
(function(){
"use strict";

/* 🔴 Khoá phiên RIÊNG của trang này. Cố ý không dùng chung tên với trang chấm công: hai trang
   cùng một khoá thì thoát ở trang này là đá văng luôn người đang chấm công dở ở trang kia. */
var KHOA = 'vhvh_the';

var toi = null, man = 'tong_quan', banDau = true;
var loi = '', bao = '';
var duLieu = { tong_quan:null, tien:null, su_co:null, checklist:null, kho:null,
  danh_gia:null, tiktok:null, trich_cam:null, thong_bao:null, giao_viec:null, bao_cao:null };
var cosoChon = '', ngayChon = homNay(), dangBan = false;

function g(id){ return document.getElementById(id); }
function esc(s){ return String(s==null?'':s).replace(/[&<>"']/g, function(c){
  return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }
function hai(n){ return (n<10?'0':'')+n; }
function homNay(){ var d=new Date(); return d.getFullYear()+'-'+hai(d.getMonth()+1)+'-'+hai(d.getDate()); }
function dauThang(){ var d=new Date(); return d.getFullYear()+'-'+hai(d.getMonth()+1)+'-01'; }
function tien(n){ return (Number(n)||0).toLocaleString('vi-VN')+'₫'; }
function ngayVN(s){ if(!s) return ''; var p=String(s).split('-'); return p.length===3?p[2]+'/'+p[1]+'/'+p[0]:s; }

function the(){ try{ return localStorage.getItem(KHOA)||''; }catch(e){ return ''; } }
function datThe(t){ try{ t?localStorage.setItem(KHOA,t):localStorage.removeItem(KHOA); }catch(e){} }

/* ---------------------------------------------------------------------------------------------
 * GỌI MÁY CHỦ
 * ⚠️ Thẻ đi trong HEADER, không đi trên đường dẫn: đường dẫn nằm lại trong nhật ký máy chủ và
 *    trong lịch sử trình duyệt.
 * ⚠️ Máy chủ luôn trả HTTP 200 kèm `ok:false` khi chối. Bắt lỗi bằng mã HTTP là bỏ sót mọi câu
 *    chối có lý do — và người dùng chỉ thấy "có lỗi xảy ra".
 * ------------------------------------------------------------------------------------------- */
function goi(viec, than){
  than = than || {};
  than.viec = viec;
  return fetch(VHVH_GOC, {
    method:'POST',
    headers:{ 'Content-Type':'application/json', 'X-VHVH-The': the() },
    body: JSON.stringify(than)
  }).then(function(r){
    return r.text().then(function(t){
      var j;
      try{ j = JSON.parse(t); }
      catch(e){
        /* Thân không phải JSON = một plugin khác in ra trước, hoặc máy chủ chặn. Nói thẳng ra
           thay vì để "Unexpected token <" đập vào mặt người dùng. */
        throw new Error('Máy chủ trả về thứ không đọc được (HTTP '+r.status+').');
      }
      if (j && j.het_phien) { datThe(''); toi = null; }
      return j;
    });
  });
}

/* ============================================================================================
 * ĐĂNG NHẬP
 * ========================================================================================== */
function veDangNhap(){
  return '<div class="giua"><div class="the">'
    + '<h2>Vận Hành — K&amp;H</h2>'
    + '<p class="nho">Dùng đúng mã PIN của hệ chấm công. Không có PIN riêng cho trang này.</p>'
    + '<div class="o" style="margin-top:12px"><label>Mã PIN</label>'
    + '<input id="oPin" type="password" inputmode="numeric" autocomplete="current-password" '
    + 'maxlength="8" placeholder="4–8 chữ số"></div>'
    + (loi ? '<div class="bao-loi">'+esc(loi)+'</div>' : '')
    + '<button class="nut chinh" id="btVao" style="width:100%;margin-top:12px">VÀO</button>'
    + '</div></div>';
}
function noiDangNhap(){
  var o = g('oPin'), b = g('btVao');
  if (!o || !b) return;
  o.focus();
  function vao(){
    if (dangBan) return;
    dangBan = true; b.disabled = true; b.textContent = 'Đang kiểm…';
    goi('dang_nhap', { pin: o.value }).then(function(j){
      dangBan = false;
      if (!j || !j.ok) { loi = (j && j.error) || 'PIN không đúng.'; ve(); return; }
      datThe(j.the); toi = j.toi; loi = '';
      man = (toi.man && toi.man[0]) || 'tong_quan';
      cosoChon = toi.coso || (toi.ds_coso && toi.ds_coso[0]) || '';
      ve(); napMan();
    }).catch(function(e){ dangBan=false; loi = e.message; ve(); });
  }
  b.addEventListener('click', vao);
  o.addEventListener('keydown', function(e){ if(e.key==='Enter') vao(); });
}

/* ============================================================================================
 * KHUNG
 * ========================================================================================== */
var TEN_VAI = { nhan_vien:'Nhân viên', thu_ngan:'Thu ngân',
                cua_hang_truong:'Cửa hàng trưởng', quan_ly:'Quản lý' };
var benHien = false;

/** Tên mục, tra từ bảng máy chủ gửi về — không gõ lại ở đây để hai nơi khỏi lệch. */
function tenMuc(ma){
  var ra = ma;
  (toi.muc||[]).forEach(function(n){ n.muc.forEach(function(m){ if(m.ma===ma) ra = m.ten; }); });
  return ra;
}

/**
 * 🔴 MỤC CHƯA LÀM VẪN HIỆN, và nói thẳng là chưa. Giấu đi thì người dùng tưởng trang này chỉ có
 *    bấy nhiêu việc rồi đi mở app cũ làm phần còn lại — số liệu nằm hai nơi từ đó.
 */
function veBen(){
  var soSuCo = (duLieu.tong_quan && duLieu.tong_quan.su_co) ? duLieu.tong_quan.su_co.length : 0;
  var h = '';
  (toi.muc||[]).forEach(function(n){
    h += '<div class="nhom">'+esc(n.nhom)+'</div>';
    n.muc.forEach(function(m){
      var duoc = m.xong && (toi.man||[]).indexOf(m.ma) >= 0;
      var cham = (m.ma==='su_co' && soSuCo) ? '<span class="cham">'+soSuCo+'</span>' : '';
      if (duoc) {
        h += '<button data-man="'+esc(m.ma)+'" class="'+(m.ma===man?'chon':'')+'">'
          + '<span>'+m.icon+'</span><span>'+esc(m.ten)+'</span>'+cham+'</button>';
      } else if (m.noi === 'cham_cong' && toi.url_cham_cong) {
        /* Mấy mục này KHÔNG dựng lại ở đây — plugin chấm công đã có sổ thật. Nối sang cho bấm
           được, thay vì dựng sổ thứ hai rồi hai bên lệch nhau. */
        h += '<a class="ben-noi" href="'+esc(toi.url_cham_cong)+'" '
          + 'style="display:flex;align-items:center;gap:9px;padding:9px 10px;border-radius:9px;'
          + 'color:var(--mo);text-decoration:none;font-size:14px">'
          + '<span>'+m.icon+'</span><span>'+esc(m.ten)+'</span>'
          + '<span class="sap" style="color:var(--xanh);background:#eaf0fd">chấm công</span></a>';
      } else {
        h += '<button class="chua" disabled><span>'+m.icon+'</span><span>'+esc(m.ten)+'</span>'
          + '<span class="sap">đang làm</span></button>';
      }
    });
  });
  return h;
}

function veKhung(tieu, duoi, than){
  return '<div class="dinh"><div class="dinh-in">'
    + '<div style="display:flex;align-items:center;gap:12px">'
    + '<button class="mo-ben" id="btBen">☰</button>'
    + '<div class="hieu">VẬN HÀNH<span>đa cơ sở</span></div></div>'
    + '<div class="toi"><b>'+esc(toi.ten)+'</b> · '+esc(TEN_VAI[toi.vai]||toi.vai)
    + (toi.coso ? ' · '+esc(toi.coso) : '')
    + ' &nbsp;<button class="nut" id="btRa" style="padding:5px 11px">Đăng xuất</button></div>'
    + '</div></div>'
    + '<div class="khung">'
    + '<div class="ben'+(benHien?' hien':'')+'" id="thanhBen">'+veBen()+'</div>'
    + '<div class="than"><h1>'+esc(tieu)+'</h1>'
    + (duoi ? '<p class="duoi">'+esc(duoi)+'</p>' : '')
    + (loi ? '<div class="bao-loi">'+esc(loi)+'</div>' : '')
    + (bao ? '<div class="bao-ok">'+esc(bao)+'</div>' : '')
    + than + '</div></div>';
}

function oCoSo(id, gt){
  var ds = toi.ds_coso || [];
  if (!ds.length) { return '<div class="o"><label>Cơ sở</label><input id="'+id+'" value="'
    + esc(gt||'') + '" placeholder="Chưa có danh mục cơ sở"></div>'; }
  return '<div class="o"><label>Cơ sở</label><select id="'+id+'">'
    + ds.map(function(c){ return '<option value="'+esc(c)+'"'+(c===gt?' selected':'')+'>'+esc(c)+'</option>'; }).join('')
    + '</select></div>';
}

/* ============================================================================================
 * MÀN TỔNG QUAN
 * ========================================================================================== */
function veTongQuan(){
  var d = duLieu.tong_quan;
  if (!d) return '<div class="the">Đang tải…</div>';
  var s = d.so || {};

  var h = '<div class="o-so" style="margin-top:16px">'
    + hopSo('CƠ SỞ', s.so_coso, (s.so_coso||0)+' đang hoạt động')
    + hopSo('NHÂN SỰ', s.so_nhan_su === null ? '—' : s.so_nhan_su,
        s.so_nhan_su === null ? 'chưa đọc được sổ nhân sự' : '')
    + hopSo('CHECKLIST TB HÔM NAY', s.checklist_tb === null ? '—' : s.checklist_tb+'%', '')
    + hopSo('SỰ CỐ ĐANG MỞ', s.su_co_mo||0, '')
    + '</div>';

  /* Thẻ từng cơ sở — thứ người trực nhìn đầu tiên mỗi sáng. */
  (s.hang||[]).forEach(function(c){
    h += '<div class="the coso-the"><div class="ten">'+esc(c.coso)+'</div>'
      + veNhan(c.trang_thai, c.diem)
      + '<div class="doi">'
      + '<div class="box"><div class="l">DOANH THU HÔM NAY</div>'
      + (c.bc_hnay ? '<span class="nhan tot">Đã nhập</span>' : '<span class="nhan xau">Chưa nhập</span>')
      + '</div>'
      + '<div class="box"><div class="l">CHECKLIST</div>'
      + (c.checklist === null ? '<span class="nhan xau">Chưa có báo cáo hôm nay</span>'
          : '<span class="nhan tot">'+c.checklist+'%</span>')
      + '</div></div>'
      + '<div class="doi"><div class="box"><div class="l">SỰ CỐ ĐANG MỞ</div>'
      + (c.su_co_mo ? '<span class="nhan xau">'+c.su_co_mo+'</span>'
          : '<span class="nhan tot">0</span>')
      + '</div>'
      + '<div class="box"><div class="l">DOANH THU / CHỈ TIÊU</div>'
      + (c.chi_tieu === null ? '<span class="nhan chua">Chưa đặt chỉ tiêu</span>'
          : '<span class="nhan '+(c.phan_tram>=90?'tot':(c.phan_tram>=70?'chu-y':'xau'))+'">'
            + c.phan_tram+'%</span>')
      + '</div></div></div>';
  });

  /* Bảng gom — xem được nhiều cơ sở một lúc. */
  h += '<div class="the"><h2>Tình hình hoạt động các cơ sở</h2>';
  if (!(s.hang||[]).length) {
    h += '<p class="nho">Chưa có cơ sở nào trong danh mục. Danh mục cơ sở lấy từ plugin Chấm Công.</p>';
  } else {
    h += '<div class="cuon"><table><tr><th>Cơ sở</th><th>Trạng thái</th><th class="so">Điểm</th>'
      + '<th class="so">Doanh thu/chỉ tiêu</th><th class="so">Checklist</th>'
      + '<th class="so">Sự cố mở</th>'+(laVai('quan_ly')?'<th></th>':'')+'</tr>';
    s.hang.forEach(function(c){
      h += '<tr><td>'+esc(c.coso)+'</td>'
        + '<td>'+veNhan(c.trang_thai, null)+'</td>'
        + '<td class="so">'+(c.diem===null?'—':c.diem)+'</td>'
        + '<td class="so">'+(c.phan_tram===null?'—':c.phan_tram+'%')+'</td>'
        + '<td class="so">'+(c.checklist===null?'—':c.checklist+'%')+'</td>'
        + '<td class="so">'+(c.su_co_mo||0)+'</td>'
        + (laVai('quan_ly') ? '<td><button class="nut" data-ct="'+esc(c.coso)+'" '
            + 'data-ct-so="'+(c.chi_tieu||'')+'" style="padding:5px 11px">Chỉ tiêu</button></td>' : '')
        + '</tr>';
    });
    h += '</table></div>';
  }
  /* 🔴 Nói rõ mảnh nào KHÔNG có dữ liệu thì bị bỏ qua, không tính 0đ. Không nói ra thì cửa hàng
     trưởng thấy điểm thấp và đi sửa những thứ họ không sửa được. */
  h += '<p class="nho" style="margin-top:10px">Điểm là trung bình của những mảnh <b>có dữ liệu</b>: '
    + 'doanh thu/chỉ tiêu · checklist · sự cố. Mảnh nào chưa có dữ liệu (ví dụ cơ sở chưa được '
    + 'đặt chỉ tiêu) thì <b>bỏ qua khi tính</b>, không tính là 0 điểm — chấm 0 cho một việc người '
    + 'khác chưa làm thì cửa hàng trưởng không có cách nào sửa. '
    + '90–100 Tốt · 70–89 Cần chú ý · dưới 70 Có vấn đề.</p></div>';

  h += '<div class="the"><h2>Sự cố đang mở ('+((d.su_co||[]).length)+')</h2>'
    + ((d.su_co||[]).length ? bangSuCo(d.su_co, false) : '<p class="nho">Không có sự cố nào đang mở.</p>')
    + '</div>';
  return h;
}
function veNhan(tt, diem){
  var m = { tot:['tot','Tốt'], chu_y:['chu-y','Cần chú ý'],
            co_van_de:['xau','Có vấn đề'], chua_du:['chua','Chưa đủ dữ liệu'] };
  var x = m[tt] || m.chua_du;
  return '<span class="nhan '+x[0]+'">'+x[1]+(diem===null||diem===undefined?'':' · '+diem)+'</span>';
}
function hopSo(l, n, phu){
  return '<div class="box"><div class="l">'+esc(l)+'</div><div class="n">'+esc(n)+'</div>'
    + (phu ? '<div class="l" style="margin-top:2px">'+esc(phu)+'</div>' : '')+'</div>';
}

/* ============================================================================================
 * MÀN DOANH THU & CHI PHÍ
 * ========================================================================================== */
function veTien(){
  var d = duLieu.tien;
  if (!d) return '<div class="the">Đang tải…</div>';
  var ban = d.ban, dsVe = d.ds_ve || [];
  var daDuyet = ban && ban.tt === 'duyet';

  var h = '<div class="the"><h2>Báo cáo ngày</h2>'
    + '<div class="hang" style="margin-top:10px">'
    + oCoSo('tCoSo', cosoChon)
    + '<div class="o"><label>Ngày</label><input id="tNgay" type="date" value="'+esc(ngayChon)+'"></div>'
    + '<button class="nut" id="btMo">Mở</button>'
    + (ban ? '<span class="the-tt tt-'+esc(ban.tt)+'" style="margin-bottom:9px">'
        + (ban.tt==='duyet'?'Đã duyệt':(ban.tt==='tu_choi'?'Bị từ chối':'Chờ duyệt'))
        + '</span>' : '')
    + '</div>';

  if (daDuyet) {
    h += '<p class="nho" style="margin-top:10px">🔒 Ngày này đã chốt'
      + (ban.nguoi_duyet ? ' bởi <b>'+esc(ban.nguoi_duyet)+'</b>' : '')
      + '. Muốn sửa thì Quản lý phải mở lại.</p>';
  }

  /* --- vé, xếp theo nhóm --- */
  var nhom = {};
  dsVe.forEach(function(v){ (nhom[v.nhom] = nhom[v.nhom] || []).push(v); });
  var TEN_NHOM = <?php echo wp_json_encode( VHVH_Tien::NHOM_NHAN ); ?>;
  h += '<h3>Vé bán trong ngày</h3>';
  Object.keys(nhom).forEach(function(k){
    h += '<div class="ve-nhom"><div class="ten">'+esc(TEN_NHOM[k]||k)+'</div><div class="ve-luoi">';
    nhom[k].forEach(function(v){
      var so = (ban && ban.ve && ban.ve[v.khoa]) || '';
      h += '<div class="ve-o"><div class="nh">'+esc(v.nhan)
        + '<div class="gia">'+tien(v.gia)+'</div></div>'
        + '<input type="number" min="0" step="1" data-ve="'+esc(v.khoa)+'" value="'+esc(so)+'"'
        + (daDuyet?' disabled':'') + '></div>';
    });
    h += '</div></div>';
  });

  /* --- thu tiền theo hình thức --- */
  var tt = (ban && ban.tra_tien) || {};
  h += '<h3>Thu theo hình thức</h3><div class="hang">'
    + oTien('pMat','Tiền mặt', tt.mat, daDuyet)
    + oTien('pCk','Chuyển khoản', tt.ck, daDuyet)
    + oTien('pMomo','Momo', tt.momo, daDuyet)
    + '</div>'
    + '<p class="nho" id="soLech" style="margin-top:6px"></p>';

  /* --- khách theo khung giờ --- */
  h += '<h3>Khách theo khung giờ</h3><div id="dsGio">';
  var gio = (ban && ban.khach_gio && ban.khach_gio.length) ? ban.khach_gio
          : [{gio:'11:00',so:''},{gio:'15:00',so:''},{gio:'18:00',so:''},{gio:'20:00',so:''}];
  gio.forEach(function(x){ h += dongGio(x.gio, x.so, daDuyet); });
  h += '</div>'
    + (daDuyet?'':'<button class="nut" id="btThemGio" style="margin-top:8px">+ Khung giờ</button>');

  /* --- chi phí --- */
  h += '<h3>Chi phí trong ngày</h3><div id="dsChi">';
  var chi = (ban && ban.chi && ban.chi.length) ? ban.chi : [{nhan:'',tien:''}];
  chi.forEach(function(x){ h += dongTien('chi', x.nhan, x.tien, daDuyet); });
  h += '</div>'
    + (daDuyet?'':'<button class="nut" id="btThemChi" style="margin-top:8px">+ Dòng chi</button>');

  /* --- thu khác --- */
  h += '<h3>Thu khác</h3><div id="dsKhac">';
  var khac = (ban && ban.thu_khac && ban.thu_khac.length) ? ban.thu_khac : [{nhan:'',tien:''}];
  khac.forEach(function(x){ h += dongTien('khac', x.nhan, x.tien, daDuyet); });
  h += '</div>'
    + (daDuyet?'':'<button class="nut" id="btThemKhac" style="margin-top:8px">+ Dòng thu</button>');

  h += '<h3>Ghi chú</h3><textarea id="tGhi" rows="2" style="width:100%;border:1px solid var(--vien);'
    + 'border-radius:9px;padding:8px 10px"'+(daDuyet?' disabled':'')+'>'
    + esc(ban&&ban.ghi||'') + '</textarea>';

  /* --- tổng, tính lại ngay trên trang cho người nhập thấy; máy chủ vẫn tính lại khi lưu --- */
  h += '<div class="o-so" style="margin-top:14px">'
    + '<div class="box"><div class="n" id="tgThu">0₫</div><div class="l">Doanh thu (máy chủ tính lại khi lưu)</div></div>'
    + '<div class="box"><div class="n" id="tgChi">0₫</div><div class="l">Chi phí</div></div>'
    + '<div class="box"><div class="n" id="tgKhach">0</div><div class="l">Lượt khách</div></div>'
    + '</div>';

  if (!daDuyet) {
    h += '<button class="nut chinh" id="btLuu" style="width:100%;margin-top:14px">LƯU BÁO CÁO NGÀY</button>';
  }
  /* Nút duyệt: chỉ hiện cho người đủ vai — nhưng chốt thật nằm ở máy chủ. */
  if (ban && ban.tt === 'cho' && laVai('cua_hang_truong')) {
    h += '<div class="hang" style="margin-top:10px">'
      + '<button class="nut luc" id="btDuyet">DUYỆT</button>'
      + '<button class="nut do" id="btChoi">TỪ CHỐI</button></div>';
  }
  if (daDuyet && laVai('quan_ly')) {
    h += '<button class="nut" id="btMoLai" style="margin-top:10px">Mở lại để sửa</button>';
  }
  h += '</div>';
  return h;
}
function oTien(id, nhan, gt, khoa){
  return '<div class="o"><label>'+esc(nhan)+'</label><input id="'+id+'" type="number" min="0" '
    + 'step="1000" value="'+esc(gt||'')+'"'+(khoa?' disabled':'')+'></div>';
}
function dongTien(loai, nhan, sotien, khoa){
  return '<div class="dong-tien" data-loai="'+loai+'">'
    + '<input class="nhan" placeholder="Nội dung" value="'+esc(nhan||'')+'"'+(khoa?' disabled':'')+'>'
    + '<input class="tien" type="number" min="0" step="1000" placeholder="0" value="'
    + esc(sotien||'')+'"'+(khoa?' disabled':'')+'></div>';
}
function dongGio(gio, so, khoa){
  return '<div class="dong-tien" data-gio="1">'
    + '<input class="nhan" placeholder="19:00" value="'+esc(gio||'')+'"'+(khoa?' disabled':'')+'>'
    + '<input class="tien" type="number" min="0" placeholder="0" value="'+esc(so||'')+'"'
    + (khoa?' disabled':'')+'></div>';
}
function laVai(can){
  var bac = ['nhan_vien','thu_ngan','cua_hang_truong','quan_ly'];
  return bac.indexOf(toi.vai) >= bac.indexOf(can);
}

/** Đọc mọi ô trên màn tiền thành một gói gửi lên. KHÔNG gửi tổng — máy chủ tự tính. */
function gomTien(){
  var ve = {};
  document.querySelectorAll('[data-ve]').forEach(function(o){
    var n = parseInt(o.value, 10);
    if (n > 0) { ve[o.getAttribute('data-ve')] = n; }
  });
  function dong(sel){
    var ra = [];
    document.querySelectorAll(sel+' .dong-tien').forEach(function(d){
      var nhan = d.querySelector('.nhan').value.trim();
      var t = parseInt(d.querySelector('.tien').value, 10) || 0;
      if (nhan || t) ra.push({ nhan: nhan, tien: t });
    });
    return ra;
  }
  var gio = [];
  document.querySelectorAll('#dsGio .dong-tien').forEach(function(d){
    var gv = d.querySelector('.nhan').value.trim();
    var s = parseInt(d.querySelector('.tien').value, 10) || 0;
    if (gv || s) gio.push({ gio: gv, so: s });
  });
  return {
    coso: g('tCoSo').value, ngay: g('tNgay').value,
    ve: ve, chi: dong('#dsChi'), thu_khac: dong('#dsKhac'),
    khach_gio: gio,
    tra_tien: { mat:+g('pMat').value||0, ck:+g('pCk').value||0, momo:+g('pMomo').value||0 },
    ghi: g('tGhi').value
  };
}

/** Cộng tạm trên trang để người nhập thấy ngay. Con số thật do máy chủ trả về sau khi lưu. */
function congTam(){
  var d = duLieu.tien; if (!d) return;
  var gia = {}, vaoThu = {}, vaoKhach = {};
  (d.ds_ve||[]).forEach(function(v){ gia[v.khoa]=v.gia; vaoThu[v.khoa]=v.vao_thu; vaoKhach[v.khoa]=v.vao_khach; });
  var thu=0, khach=0;
  document.querySelectorAll('[data-ve]').forEach(function(o){
    var k=o.getAttribute('data-ve'), n=parseInt(o.value,10)||0;
    if(n<=0) return;
    if(vaoThu[k]) thu += n*gia[k];
    if(vaoKhach[k]) khach += n;
  });
  document.querySelectorAll('#dsKhac .dong-tien').forEach(function(x){
    thu += parseInt(x.querySelector('.tien').value,10)||0; });
  var chi=0;
  document.querySelectorAll('#dsChi .dong-tien').forEach(function(x){
    chi += parseInt(x.querySelector('.tien').value,10)||0; });
  if(g('tgThu')) g('tgThu').textContent = tien(thu);
  if(g('tgChi')) g('tgChi').textContent = tien(chi);
  if(g('tgKhach')) g('tgKhach').textContent = khach.toLocaleString('vi-VN');

  /* Ba ô thu tiền phải khớp doanh thu. Lệch thì NÓI RA chứ không chặn — cuối ca lệch vài nghìn
     là chuyện thật, chặn cứng thì người ta bịa số cho khớp, và lúc ấy sổ sạch mà tiền thì không. */
  var tra = (+g('pMat').value||0)+(+g('pCk').value||0)+(+g('pMomo').value||0);
  var o = g('soLech');
  if (o) {
    if (!tra) { o.textContent=''; o.className='nho'; }
    else if (tra === thu) { o.textContent='✔ Khớp doanh thu.'; o.className='nho'; o.style.color='var(--luc)'; }
    else { o.textContent='⚠ Lệch '+tien(Math.abs(tra-thu))+' so với doanh thu ('+tien(thu)+').';
           o.className='nho'; o.style.color='var(--cam)'; }
  }
}

function noiTien(){
  ['tCoSo','tNgay'].forEach(function(id){
    var o=g(id); if(o) o.addEventListener('change', function(){
      cosoChon = g('tCoSo').value; ngayChon = g('tNgay').value; });
  });
  if (g('btMo')) g('btMo').addEventListener('click', function(){
    cosoChon = g('tCoSo').value; ngayChon = g('tNgay').value; napMan(); });

  document.querySelectorAll('[data-ve], #dsChi input, #dsKhac input, #pMat, #pCk, #pMomo')
    .forEach(function(o){ o.addEventListener('input', congTam); });
  congTam();

  function themDong(nutId, khoId, ham){
    var b=g(nutId); if(!b) return;
    b.addEventListener('click', function(){
      var d=document.createElement('div'); d.innerHTML=ham();
      g(khoId).appendChild(d.firstChild);
      g(khoId).lastChild.querySelectorAll('input').forEach(function(o){
        o.addEventListener('input', congTam); });
    });
  }
  themDong('btThemChi','dsChi', function(){ return dongTien('chi','','',false); });
  themDong('btThemKhac','dsKhac', function(){ return dongTien('khac','','',false); });
  themDong('btThemGio','dsGio', function(){ return dongGio('','',false); });

  if (g('btLuu')) g('btLuu').addEventListener('click', function(){
    var b=this; if(dangBan) return; dangBan=true; b.disabled=true; b.textContent='Đang lưu…';
    goi('tien_luu', gomTien()).then(function(j){
      dangBan=false;
      if(!j||!j.ok){ loi=(j&&j.error)||'Không lưu được.'; bao=''; ve(); return; }
      loi=''; bao='Đã lưu báo cáo ngày '+ngayVN(ngayChon)+' — doanh thu '+tien(j.ban.tong_thu)+'.';
      duLieu.tien.ban = j.ban; ve();
    }).catch(function(e){ dangBan=false; loi=e.message; ve(); });
  });

  [['btDuyet','duyet'],['btChoi','tu_choi'],['btMoLai','mo_lai']].forEach(function(x){
    var b=g(x[0]); if(!b) return;
    b.addEventListener('click', function(){
      if (x[1]==='mo_lai' && !confirm('Mở lại báo cáo đã chốt của ngày '+ngayVN(ngayChon)+'?')) return;
      goi('tien_tt', { coso:cosoChon, ngay:ngayChon, lam:x[1] }).then(function(j){
        if(!j||!j.ok){ loi=(j&&j.error)||'Không đổi được.'; ve(); return; }
        loi=''; bao='Đã cập nhật.'; duLieu.tien.ban=j.ban; ve();
      }).catch(function(e){ loi=e.message; ve(); });
    });
  });
}

/* ============================================================================================
 * MÀN CHECKLIST ĐẦU / CUỐI NGÀY
 * ==========================================================================================
 * 🔴 KHÔNG khoá sau khi lưu. Người ta tích dần trong ca rồi bổ sung nốt mục quên; khoá lại là
 *    lần sau họ chờ xong hết mới tích một lượt, và cái danh sách mất tác dụng nhắc việc.
 * ========================================================================================== */
var clBuoi = 'dau_ngay';
function veChecklist(){
  var d = duLieu.checklist;
  if (!d) return '<div class="the">Đang tải…</div>';
  var ban = d.ban, tich = (ban && ban.muc) || {};

  var h = '<div class="the"><div class="hang">'
    + oCoSo('clCoSo', cosoChon)
    + '<div class="o"><label>Ngày</label><input id="clNgay" type="date" value="'+esc(ngayChon)+'"></div>'
    + '<div class="o"><label>Buổi</label><select id="clBuoi">'
    + '<option value="dau_ngay"'+(clBuoi==='dau_ngay'?' selected':'')+'>Đầu ngày</option>'
    + '<option value="cuoi_ngay"'+(clBuoi==='cuoi_ngay'?' selected':'')+'>Cuối ngày</option>'
    + '</select></div>'
    + '<button class="nut" id="btClMo">Mở</button>'
    + '</div>'
    + '<p class="nho" style="margin-top:8px" id="clTien">'
    + (ban ? 'Đã tích <b>'+ban.xong+'/'+ban.tong+'</b>'
        + (ban.nguoi ? ' — người ghi gần nhất: <b>'+esc(ban.nguoi)+'</b>' : '')
      : 'Chưa có báo cáo cho buổi này.')
    + '</p></div>';

  (d.dm||[]).forEach(function(khu){
    h += '<div class="the"><h2>'+esc(khu.ten)+'</h2>';
    khu.muc.forEach(function(m, i){
      var k = khu.khoa+'_'+i;
      h += '<label class="cl-dong"><input type="checkbox" data-cl="'+esc(k)+'"'
        + (tich[k]?' checked':'')+'><span>'+esc(m)+'</span></label>';
    });
    h += '</div>';
  });

  h += '<button class="nut chinh" id="btClLuu" style="width:100%;margin-top:14px">LƯU CHECKLIST</button>';
  return h;
}
function noiChecklist(){
  ['clCoSo','clNgay','clBuoi'].forEach(function(id){
    var o=g(id); if(o) o.addEventListener('change', function(){
      cosoChon=g('clCoSo').value; ngayChon=g('clNgay').value; clBuoi=g('clBuoi').value; napMan(); });
  });
  if (g('btClMo')) g('btClMo').addEventListener('click', napMan);

  /* Đếm tại chỗ khi tích — không phải bấm Lưu mới biết còn thiếu mấy mục. */
  function demTam(){
    var tong = document.querySelectorAll('[data-cl]').length;
    var xong = document.querySelectorAll('[data-cl]:checked').length;
    var o = g('clTien');
    if (o) o.innerHTML = 'Đang tích <b>'+xong+'/'+tong+'</b>'
      + (xong===tong && tong ? ' — đủ hết.' : '');
  }
  document.querySelectorAll('[data-cl]').forEach(function(o){
    o.addEventListener('change', demTam);
  });

  if (g('btClLuu')) g('btClLuu').addEventListener('click', function(){
    var b=this; if(dangBan) return; dangBan=true; b.disabled=true; b.textContent='Đang lưu…';
    var muc = {};
    document.querySelectorAll('[data-cl]').forEach(function(o){
      if (o.checked) muc[o.getAttribute('data-cl')] = 1;
    });
    goi('cl_luu', { coso:cosoChon, ngay:ngayChon, buoi:clBuoi, muc:muc }).then(function(j){
      dangBan=false;
      if(!j||!j.ok){ loi=(j&&j.error)||'Không lưu được.'; bao=''; ve(); return; }
      loi=''; bao='Đã lưu checklist — '+j.ban.xong+'/'+j.ban.tong+' mục.';
      duLieu.checklist.ban = j.ban; ve();
    }).catch(function(e){ dangBan=false; loi=e.message; ve(); });
  });
}

/* ============================================================================================
 * MÀN KIỂM KHO
 * ==========================================================================================
 * Đếm theo TUẦN. Máy chủ quy mọi ngày về thứ Hai của tuần ấy, nên kiểm ngày nào trong tuần cũng
 * rơi vào đúng một bản ghi.
 * ========================================================================================== */
function veKho(){
  var d = duLieu.kho;
  if (!d) return '<div class="the">Đang tải…</div>';
  var ban = d.ban, co = (ban && ban.muc) || {}, ss = d.so_sanh || {};

  var h = '<div class="the"><div class="hang">'
    + oCoSo('khCoSo', cosoChon)
    + '<div class="o"><label>Ngày kiểm</label><input id="khNgay" type="date" value="'+esc(ngayChon)+'"></div>'
    + '<button class="nut" id="btKhMo">Mở</button></div>'
    + '<p class="nho" style="margin-top:8px">Tuần bắt đầu <b>'+ngayVN(d.tuan_tu)+'</b> (thứ Hai). '
    + 'Kiểm ngày nào trong tuần cũng ghi vào đúng bản này.'
    + (ban && ban.nguoi ? ' Người ghi gần nhất: <b>'+esc(ban.nguoi)+'</b>.' : '')
    + '</p></div>';

  var coLech = Object.keys(ss).length;
  if (coLech) {
    /* Thứ người ta thật sự cần biết: mất mát và xuống cấp so với tuần trước. */
    h += '<div class="the"><h2>Lệch so với tuần trước</h2><div class="cuon"><table>'
      + '<tr><th>Món</th><th class="so">Số lượng</th><th class="so">Tình trạng</th></tr>';
    Object.keys(ss).forEach(function(k){
      var x = ss[k];
      h += '<tr><td>'+esc(tenMon(d.dm, k))+'</td>'
        + '<td class="so">'+(x.lech===0?'—':(x.lech>0?'+':'')+x.lech)+'</td>'
        + '<td class="so">'+(x.hao===null||x.hao===0?'—':(x.hao>0?'+':'')+x.hao+'%')+'</td></tr>';
    });
    h += '</table></div></div>';
  }

  (d.dm||[]).forEach(function(nh){
    h += '<div class="the"><h2>'+esc(nh.ten)+'</h2><div class="cuon"><table>'
      + '<tr><th>Món</th><th class="so">Số lượng</th><th class="so">Còn dùng được</th><th>Ghi chú</th></tr>';
    nh.muc.forEach(function(m){
      var v = co[m.khoa] || {};
      h += '<tr><td>'+esc(m.ten)+'</td>'
        + '<td class="so"><input type="number" min="0" data-kho="'+esc(m.khoa)+'" data-o="so" '
        + 'style="width:80px;text-align:right" value="'+esc(v.so===undefined?'':v.so)+'"></td>'
        + '<td class="so">'
        + (m.kieu==='dem_tinh'
            ? '<input type="number" min="0" max="100" data-kho="'+esc(m.khoa)+'" data-o="tinh" '
              + 'style="width:74px;text-align:right" value="'+esc(v.tinh===undefined?'':v.tinh)+'">%'
            : '<span class="nho">—</span>')
        + '</td>'
        + '<td><input data-kho="'+esc(m.khoa)+'" data-o="ghi" style="width:100%" '
        + 'value="'+esc(v.ghi||'')+'" placeholder="hỏng, mất, cần thay…"></td></tr>';
    });
    h += '</table></div></div>';
  });

  h += '<button class="nut chinh" id="btKhLuu" style="width:100%;margin-top:14px">LƯU SỔ KHO TUẦN NÀY</button>';
  return h;
}
function tenMon(dm, khoa){
  var ra = khoa;
  (dm||[]).forEach(function(n){ n.muc.forEach(function(m){ if(m.khoa===khoa) ra = m.ten; }); });
  return ra;
}
function noiKho(){
  ['khCoSo','khNgay'].forEach(function(id){
    var o=g(id); if(o) o.addEventListener('change', function(){
      cosoChon=g('khCoSo').value; ngayChon=g('khNgay').value; napMan(); });
  });
  if (g('btKhMo')) g('btKhMo').addEventListener('click', napMan);

  if (g('btKhLuu')) g('btKhLuu').addEventListener('click', function(){
    var b=this; if(dangBan) return; dangBan=true; b.disabled=true; b.textContent='Đang lưu…';
    var muc = {};
    document.querySelectorAll('[data-kho]').forEach(function(o){
      var k = o.getAttribute('data-kho'), t = o.getAttribute('data-o');
      var v = o.value;
      /* Ô để trống thì KHÔNG gửi món ấy lên — gửi 0 nghĩa là "đếm rồi, còn 0 cái", khác hẳn
         "chưa đếm tới". Hai chuyện ấy mà nhập làm một thì sổ kho báo mất sạch đồ. */
      if (t === 'so' && String(v).trim() === '') { return; }
      muc[k] = muc[k] || {};
      muc[k][t] = (t === 'ghi') ? v : (parseInt(v, 10) || 0);
    });
    /* Món chỉ có ghi chú mà chưa đếm thì cũng bỏ — cùng lý do trên. */
    Object.keys(muc).forEach(function(k){ if (muc[k].so === undefined) delete muc[k]; });
    goi('kho_luu', { coso:cosoChon, ngay:ngayChon, muc:muc }).then(function(j){
      dangBan=false;
      if(!j||!j.ok){ loi=(j&&j.error)||'Không lưu được.'; bao=''; ve(); return; }
      loi=''; bao='Đã lưu sổ kho tuần bắt đầu '+ngayVN(duLieu.kho.tuan_tu)+'.';
      napMan();
    }).catch(function(e){ dangBan=false; loi=e.message; ve(); });
  });
}

/* ============================================================================================
 * MÀN SỰ CỐ
 * ========================================================================================== */
function veSuCo(){
  var ds = duLieu.su_co;
  if (!ds) return '<div class="the">Đang tải…</div>';
  var h = '<div class="the"><h2>Báo sự cố mới</h2>'
    + '<div class="hang" style="margin-top:10px">'
    + oCoSo('sCoSo', cosoChon)
    + '<div class="o" style="flex:1;min-width:220px"><label>Tiêu đề</label>'
    + '<input id="sTieu" placeholder="VD: Đèn hành lang phòng dâu chập chờn"></div>'
    + '<div class="o"><label>Mức</label><select id="sMuc">'
    + '<option value="nhe">Nhẹ</option><option value="canh_bao" selected>Cảnh báo</option>'
    + '<option value="nang">Nghiêm trọng</option></select></div>'
    + '</div><div class="hang" style="margin-top:8px">'
    + '<div class="o" style="flex:1;min-width:200px"><label>Người phụ trách xử lý</label>'
    + '<input id="sGiao" placeholder="Họ tên"></div>'
    + '<div class="o"><label>Hạn xử lý</label><input id="sHan" type="date" value="'+esc(homNay())+'"></div>'
    + '</div>'
    + '<div class="o" style="margin-top:8px"><label>Mô tả</label>'
    + '<textarea id="sMoTa" rows="2" style="width:100%;border:1px solid var(--vien);'
    + 'border-radius:9px;padding:8px 10px"></textarea></div>'
    + '<button class="nut chinh" id="btSuCo" style="margin-top:10px">GHI SỰ CỐ</button>'
    + '<p class="nho" style="margin-top:8px">Bắt buộc có <b>người phụ trách</b> và <b>hạn</b>: '
    + 'việc không gắn tên ai thì không ai làm, việc không có hạn thì không bao giờ trễ nên cũng '
    + 'không bao giờ được nhắc.</p>'
    + '</div>';

  h += '<div class="the"><h2>Danh sách</h2>'
    + '<div class="hang" style="margin:8px 0"><div class="o"><label>Trạng thái</label>'
    + '<select id="sLoc"><option value="mo">Đang mở</option><option value="dong">Đã đóng</option>'
    + '<option value="">Tất cả</option></select></div></div>'
    + (ds.length ? bangSuCo(ds, true) : '<p class="nho">Chưa có sự cố nào.</p>')
    + '</div>';
  return h;
}
function bangSuCo(ds, coNut){
  var h = '<div class="cuon"><table><tr><th>Sự cố</th><th>Cơ sở</th><th>Phụ trách</th>'
    + '<th>Hạn</th><th>Trạng thái</th>'+(coNut?'<th></th>':'')+'</tr>';
  ds.forEach(function(s){
    h += '<tr><td><b>'+esc(s.tieu_de)+'</b>'
      + (s.mo_ta ? '<div class="nho">'+esc(s.mo_ta)+'</div>' : '')
      + '</td><td>'+esc(s.coso)+'</td><td>'+esc(s.giao_ten)+'</td>'
      + '<td>'+ngayVN(s.han)+(s.tre?' <span class="the-tt tre">Trễ</span>':'')+'</td>'
      + '<td><span class="the-tt tt-'+esc(s.tt)+'">'+(s.tt==='mo'?'Đang mở':'Đã đóng')+'</span></td>'
      + (coNut ? '<td><button class="nut" data-sc="'+s.id+'" data-lam="'
          + (s.tt==='mo'?'dong':'mo_lai')+'" style="padding:5px 11px">'
          + (s.tt==='mo'?'Đóng':'Mở lại')+'</button></td>' : '')
      + '</tr>';
  });
  return h + '</table></div>';
}
function noiSuCo(){
  var b = g('btSuCo');
  if (b) b.addEventListener('click', function(){
    if(dangBan) return; dangBan=true; b.disabled=true;
    goi('su_co_them', {
      coso: g('sCoSo').value, tieu_de: g('sTieu').value, mo_ta: g('sMoTa').value,
      muc: g('sMuc').value, giao_ten: g('sGiao').value, han: g('sHan').value
    }).then(function(j){
      dangBan=false;
      if(!j||!j.ok){ loi=(j&&j.error)||'Không ghi được.'; bao=''; ve(); return; }
      loi=''; bao='Đã ghi sự cố.'; napMan();
    }).catch(function(e){ dangBan=false; loi=e.message; ve(); });
  });
  var l = g('sLoc');
  if (l) l.addEventListener('change', function(){
    goi('su_co_ds', { tt: l.value }).then(function(j){
      if(j&&j.ok){ duLieu.su_co=j.ds; ve(); }
    }).catch(function(){});
  });
  document.querySelectorAll('[data-sc]').forEach(function(o){
    o.addEventListener('click', function(){
      goi('su_co_tt', { id:+o.getAttribute('data-sc'), lam:o.getAttribute('data-lam') })
        .then(function(j){
          if(!j||!j.ok){ loi=(j&&j.error)||'Không đổi được.'; ve(); return; }
          loi=''; napMan();
        }).catch(function(e){ loi=e.message; ve(); });
    });
  });
}

/* ============================================================================================
 * MÀN ĐÁNH GIÁ NHÂN VIÊN
 * ==========================================================================================
 * 🔴 Trang chỉ gửi *ai · lỗi gì · ghi chú*. Mức độ, lần thứ mấy, điểm trừ, mức phạt đều do máy
 *    chủ tra từ bảng phạt — nhận điểm do trang gửi thì ai cũng tự ghi cho mình một vi phạm 0đ.
 * ========================================================================================== */
var kyChon = '';
function veDanhGia(){
  var d = duLieu.danh_gia;
  if (!d) return '<div class="the">Đang tải…</div>';
  if (!kyChon) kyChon = d.ky;

  var h = '<div class="the"><div class="hang">'
    + oCoSo('dgCoSo', cosoChon)
    + '<div class="o"><label>Kỳ</label><input id="dgKy" type="month" value="'+esc(kyChon)+'"></div>'
    + '<button class="nut" id="btDgMo">Xem</button></div></div>';

  /* Bảng xếp loại — thứ quản lý nhìn trước. */
  h += '<div class="the"><h2>Xếp loại kỳ '+esc(kyChon)+'</h2>';
  if (!(d.xep||[]).length) {
    h += '<p class="nho">Chưa ghi nhận gì trong kỳ này.</p>';
  } else {
    h += '<div class="cuon"><table><tr><th>Người</th><th>Cơ sở</th><th class="so">Điểm</th>'
      + '<th>Xếp loại</th><th class="so">Vi phạm</th><th class="so">Khen</th></tr>';
    d.xep.forEach(function(x){
      var m = { xuat_sac:['tot','Xuất sắc'], tot:['tot','Tốt'],
                trung_binh:['chu-y','Trung bình'], kem:['xau','Kém'] }[x.xep] || ['chua','—'];
      h += '<tr><td><b>'+esc(x.ten)+'</b></td><td>'+esc(x.coso)+'</td>'
        + '<td class="so">'+x.diem+'</td>'
        + '<td><span class="nhan '+m[0]+'">'+m[1]+'</span>'
        + (x.co_nghiem_trong ? ' <span class="nhan xau">có lỗi nghiêm trọng</span>' : '')+'</td>'
        + '<td class="so">'+x.vi_pham+'</td><td class="so">'+x.khen+'</td></tr>';
    });
    h += '</table></div>'
      /* Nói ra cái chặn, không để người ta ngạc nhiên vì sao điểm 95 mà vẫn Trung bình. */
      + '<p class="nho" style="margin-top:8px">Bắt đầu 100 điểm, trừ dần theo mức vi phạm (lần 2 '
      + 'nặng gấp rưỡi, lần 3 trở đi gấp đôi), cộng lại khi có khen. '
      + '<b>Có một lỗi mức Nghiêm trọng thì tối đa chỉ đạt Trung bình</b>, dù điểm còn cao — gian '
      + 'lận doanh thu một lần mà vẫn xếp Tốt thì bảng này chẳng còn nghĩa gì.</p>';
  }
  h += '</div>';

  /* Ghi mới */
  h += '<div class="the"><h2>Ghi nhận</h2><div class="hang">'
    + '<div class="o"><label>Loại</label><select id="dgLoai">'
    + '<option value="vi_pham">Vi phạm</option><option value="khen">Khen</option></select></div>'
    + '<div class="o" style="flex:1;min-width:200px"><label>Họ tên</label>'
    + '<input id="dgTen" placeholder="Nhập đúng tên trong sổ nhân sự"></div>'
    + '<div class="o"><label>Mã NV (nếu có)</label><input id="dgMa" style="width:120px"></div>'
    + '</div>'
    + '<div class="o" style="margin-top:8px"><label>Nội dung</label><select id="dgKhoa">'
    + optViPham(d) + '</select></div>'
    + '<div class="o" style="margin-top:8px"><label>Ghi chú</label>'
    + '<textarea id="dgGhi" rows="2" style="width:100%;border:1px solid var(--vien);'
    + 'border-radius:9px;padding:8px 10px"></textarea></div>'
    + '<button class="nut chinh" id="btDgGhi" style="margin-top:10px">GHI NHẬN</button>'
    + '<p class="nho" style="margin-top:8px">Không có Mã NV thì sổ khoá theo <b>tên</b> — hai người '
    + 'trùng tên sẽ bị cộng chung. Điền Mã NV nếu biết.</p></div>';

  /* Danh sách */
  h += '<div class="the"><h2>Đã ghi trong kỳ ('+((d.ds||[]).length)+')</h2>';
  if (!(d.ds||[]).length) { h += '<p class="nho">Chưa có dòng nào.</p>'; }
  else {
    h += '<div class="cuon"><table><tr><th>Người</th><th>Nội dung</th><th>Mức</th>'
      + '<th class="so">Lần</th><th class="so">Điểm</th><th>Phạt (tham khảo)</th>'
      + '<th>Người ghi</th>'+(laVai('quan_ly')?'<th></th>':'')+'</tr>';
    d.ds.forEach(function(x){
      var muc = { nhe:'Nhẹ', trung_binh:'Trung bình', nang:'Nặng',
                  nghiem_trong:'Nghiêm trọng' }[x.muc] || x.muc;
      h += '<tr><td><b>'+esc(x.ten)+'</b></td><td>'+esc(x.nhan)
        + (x.ghi?'<div class="nho">'+esc(x.ghi)+'</div>':'')+'</td>'
        + '<td>'+(x.loai==='khen'?'<span class="nhan tot">Khen</span>':esc(muc))+'</td>'
        + '<td class="so">'+x.lan_thu+'</td>'
        + '<td class="so">'+(x.loai==='khen'?'+':'−')+x.diem+'</td>'
        + '<td class="nho">'+esc(x.phat||'')+'</td><td class="nho">'+esc(x.nguoi_ghi)+'</td>'
        + (laVai('quan_ly')?'<td><button class="nut" data-dgx="'+x.id+'" '
            + 'style="padding:4px 9px">Xoá</button></td>':'')
        + '</tr>';
    });
    h += '</table></div>';
  }
  return h + '</div>';
}
function optViPham(d){
  var h = '';
  (d.dm||[]).forEach(function(n){
    h += '<optgroup label="'+esc(n.ten)+'" data-loai="vi_pham">';
    n.muc.forEach(function(m){ h += '<option value="'+esc(m.khoa)+'">'+esc(m.ten)+'</option>'; });
    h += '</optgroup>';
  });
  return h;
}
function optKhen(d){
  return (d.dm_khen||[]).map(function(m){
    return '<option value="'+esc(m.khoa)+'">'+esc(m.ten)+' (+'+m.diem+')</option>';
  }).join('');
}
function noiDanhGia(){
  var d = duLieu.danh_gia;
  ['dgCoSo','dgKy'].forEach(function(id){
    var o=g(id); if(o) o.addEventListener('change', function(){
      cosoChon=g('dgCoSo').value; kyChon=g('dgKy').value; napMan(); });
  });
  if (g('btDgMo')) g('btDgMo').addEventListener('click', napMan);

  /* Đổi loại thì đổi luôn danh sách nội dung — chọn "Khen" mà vẫn hiện danh sách vi phạm thì
     người ta ghi nhầm, và ghi nhầm ở sổ này là trừ oan điểm của một người. */
  var oLoai = g('dgLoai'), oKhoa = g('dgKhoa');
  if (oLoai && oKhoa) oLoai.addEventListener('change', function(){
    oKhoa.innerHTML = (oLoai.value === 'khen') ? optKhen(d) : optViPham(d);
  });

  if (g('btDgGhi')) g('btDgGhi').addEventListener('click', function(){
    var b=this; if(dangBan) return; dangBan=true; b.disabled=true;
    goi('dg_ghi', { coso:g('dgCoSo').value, ky:kyChon, loai:g('dgLoai').value,
      khoa:g('dgKhoa').value, ten:g('dgTen').value, ma_nv:g('dgMa').value, ghi:g('dgGhi').value })
      .then(function(j){
        dangBan=false;
        if(!j||!j.ok){ loi=(j&&j.error)||'Không ghi được.'; bao=''; ve(); return; }
        loi=''; bao='Đã ghi nhận — '+(j.ban.loai==='khen'?'+':'−')+j.ban.diem+' điểm'
          + (j.ban.phat?', mức tham khảo: '+j.ban.phat:'')+'.';
        napMan();
      }).catch(function(e){ dangBan=false; loi=e.message; ve(); });
  });
  document.querySelectorAll('[data-dgx]').forEach(function(o){
    o.addEventListener('click', function(){
      if (!confirm('Xoá dòng này?')) return;
      goi('dg_xoa', { id:+o.getAttribute('data-dgx') }).then(function(j){
        if(!j||!j.ok){ loi=(j&&j.error)||'Không xoá được.'; ve(); return; }
        loi=''; bao='Đã xoá.'; napMan();
      }).catch(function(e){ loi=e.message; ve(); });
    });
  });
}

/* ============================================================================================
 * MÀN TIKTOK
 * ========================================================================================== */
function veTikTok(){
  var d = duLieu.tiktok;
  if (!d) return '<div class="the">Đang tải…</div>';
  if (!kyChon) kyChon = d.ky;
  var tong = 0; (d.ds||[]).forEach(function(x){ tong += x.tien||0; });

  var h = '<div class="the"><div class="hang">'
    + oCoSo('tkCoSo', cosoChon)
    + '<div class="o"><label>Kỳ</label><input id="tkKy" type="month" value="'+esc(kyChon)+'"></div>'
    + '<button class="nut" id="btTkMo">Xem</button></div>'
    + '<p class="nho" style="margin-top:8px">Tổng thưởng trong kỳ: <b>'+tien(tong)+'</b> · '
    + 'Bậc: '+(d.bac||[]).map(function(b){
        return '≥'+(b.tu/1000)+'k → '+(b.tien/1000)+'k'; }).join(' · ')+'</p></div>';

  h += '<div class="the"><h2>Thêm clip</h2><div class="hang">'
    + '<div class="o"><label>Tên kênh</label><input id="tkKenh" placeholder="ghosthouse.gobr"></div>'
    + '<div class="o" style="flex:1;min-width:240px"><label>Đường dẫn clip</label>'
    + '<input id="tkLink" placeholder="https://www.tiktok.com/@…"></div>'
    + '<div class="o"><label>Ngày đăng</label><input id="tkNgay" type="date" value="'+esc(homNay())+'"></div>'
    + '</div><button class="nut chinh" id="btTkThem" style="margin-top:10px">THÊM CLIP</button>'
    + '<p class="nho" style="margin-top:8px">Bắt buộc có đường dẫn: thưởng trả theo lượt xem tự '
    + 'khai, không có link thì không ai kiểm được clip có thật không.</p></div>';

  h += '<div class="the"><h2>Clip trong kỳ ('+((d.ds||[]).length)+')</h2>';
  if (!(d.ds||[]).length) { h += '<p class="nho">Chưa có clip nào.</p>'; }
  else {
    h += '<div class="cuon"><table><tr><th>Ngày</th><th>Người</th><th>Kênh</th>'
      + '<th class="so">Lượt xem</th><th class="so">Thưởng</th><th>Trạng thái</th><th></th></tr>';
    d.ds.forEach(function(x){
      h += '<tr><td>'+ngayVN(x.ngay_dang)+'</td><td>'+esc(x.ten)+'</td>'
        + '<td><a href="'+esc(x.duong_dan)+'" target="_blank" rel="noopener">'+esc(x.kenh)+'</a></td>'
        + '<td class="so">'+(x.chot_luc
            ? (x.luot_xem===null?'—':x.luot_xem.toLocaleString('vi-VN'))
            : '<input type="number" min="0" data-tkl="'+x.id+'" style="width:110px;text-align:right" '
              + 'value="'+(x.luot_xem===null?'':x.luot_xem)+'">')+'</td>'
        + '<td class="so">'+tien(x.tien)+'</td>'
        + '<td>'+(x.chot_luc?'<span class="nhan tot">Đã chốt</span>':'<span class="nhan chua">Đang mở</span>')+'</td>'
        + '<td>'+(laVai('cua_hang_truong')
            ? '<button class="nut" data-tkc="'+x.id+'" data-chot="'+(x.chot_luc?'0':'1')+'" '
              + 'style="padding:4px 9px">'+(x.chot_luc?'Mở lại':'Chốt')+'</button>' : '')+'</td></tr>';
    });
    h += '</table></div>';
  }
  return h + '</div>';
}
function noiTikTok(){
  ['tkCoSo','tkKy'].forEach(function(id){
    var o=g(id); if(o) o.addEventListener('change', function(){
      cosoChon=g('tkCoSo').value; kyChon=g('tkKy').value; napMan(); });
  });
  if (g('btTkMo')) g('btTkMo').addEventListener('click', napMan);
  if (g('btTkThem')) g('btTkThem').addEventListener('click', function(){
    var b=this; if(dangBan) return; dangBan=true; b.disabled=true;
    goi('tk_them', { coso:g('tkCoSo').value, kenh:g('tkKenh').value,
      duong_dan:g('tkLink').value, ngay_dang:g('tkNgay').value }).then(function(j){
      dangBan=false;
      if(!j||!j.ok){ loi=(j&&j.error)||'Không thêm được.'; bao=''; ve(); return; }
      loi=''; bao='Đã thêm clip.'; napMan();
    }).catch(function(e){ dangBan=false; loi=e.message; ve(); });
  });
  /* Khai lượt xem xong là gửi luôn — máy chủ tra bậc rồi trả về tiền. */
  document.querySelectorAll('[data-tkl]').forEach(function(o){
    o.addEventListener('change', function(){
      goi('tk_luot', { id:+o.getAttribute('data-tkl'), luot:parseInt(o.value,10)||0 })
        .then(function(j){
          if(!j||!j.ok){ loi=(j&&j.error)||'Không lưu được.'; ve(); return; }
          loi=''; bao='Lượt xem đã lưu — thưởng '+tien(j.tien)+'.'; napMan();
        }).catch(function(e){ loi=e.message; ve(); });
    });
  });
  document.querySelectorAll('[data-tkc]').forEach(function(o){
    o.addEventListener('click', function(){
      goi('tk_chot', { id:+o.getAttribute('data-tkc'), chot:o.getAttribute('data-chot')==='1' })
        .then(function(j){
          if(!j||!j.ok){ loi=(j&&j.error)||'Không đổi được.'; ve(); return; }
          loi=''; napMan();
        }).catch(function(e){ loi=e.message; ve(); });
    });
  });
}

/* ============================================================================================
 * MÀN TRÍCH CAM
 * ========================================================================================== */
function veTrichCam(){
  var d = duLieu.trich_cam;
  if (!d) return '<div class="the">Đang tải…</div>';
  if (!kyChon) kyChon = d.ky;
  var xong = 0; (d.ds||[]).forEach(function(x){ if(x.xong) xong++; });

  var h = '<div class="the"><div class="hang">'
    + oCoSo('tcCoSo', cosoChon)
    + '<div class="o"><label>Kỳ</label><input id="tcKy" type="month" value="'+esc(kyChon)+'"></div>'
    + '<button class="nut" id="btTcMo">Xem</button></div>'
    + '<p class="nho" style="margin-top:8px">Trong kỳ: <b>'+((d.ds||[]).length)+'</b> đăng ký · '
    + 'đã xong <b>'+xong+'</b>.</p></div>';

  h += '<div class="the"><h2>Đăng ký mới</h2><div class="hang">'
    + '<div class="o"><label>Số hoá đơn</label><input id="tcHd" placeholder="HD202609160012"></div>'
    + '<div class="o"><label>SĐT khách</label><input id="tcSdt" inputmode="numeric"></div>'
    + '<div class="o"><label>Ngày</label><input id="tcNgay" type="date" value="'+esc(homNay())+'"></div>'
    + '</div><button class="nut chinh" id="btTcThem" style="margin-top:10px">ĐĂNG KÝ</button>'
    + '<p class="nho" style="margin-top:8px">Một hoá đơn chỉ đăng ký được <b>một lần</b> trong cùng '
    + 'cơ sở — đăng ký hai lần là một lượt đếm đôi, mà khoản ấy tính vào thành tích.</p></div>';

  h += '<div class="the"><h2>Danh sách</h2>';
  if (!(d.ds||[]).length) { h += '<p class="nho">Chưa có dòng nào.</p>'; }
  else {
    h += '<div class="cuon"><table><tr><th>Ngày</th><th>Hoá đơn</th><th>SĐT</th>'
      + '<th>Người đăng ký</th><th>Trạng thái</th><th></th></tr>';
    d.ds.forEach(function(x){
      h += '<tr><td>'+ngayVN(x.ngay_dk)+'</td><td><b>'+esc(x.so_hd)+'</b></td>'
        + '<td>'+esc(x.sdt||'')+'</td><td>'+esc(x.ten)+'</td>'
        + '<td>'+(x.xong?'<span class="nhan tot">Đã xong</span>':'<span class="nhan chua">Chưa</span>')+'</td>'
        + '<td><button class="nut" data-tcx="'+x.id+'" data-xong="'+(x.xong?'0':'1')+'" '
        + 'style="padding:4px 9px">'+(x.xong?'Bỏ đánh dấu':'Đánh dấu xong')+'</button></td></tr>';
    });
    h += '</table></div>';
  }
  return h + '</div>';
}
function noiTrichCam(){
  ['tcCoSo','tcKy'].forEach(function(id){
    var o=g(id); if(o) o.addEventListener('change', function(){
      cosoChon=g('tcCoSo').value; kyChon=g('tcKy').value; napMan(); });
  });
  if (g('btTcMo')) g('btTcMo').addEventListener('click', napMan);
  if (g('btTcThem')) g('btTcThem').addEventListener('click', function(){
    var b=this; if(dangBan) return; dangBan=true; b.disabled=true;
    goi('tc_them', { coso:g('tcCoSo').value, so_hd:g('tcHd').value, sdt:g('tcSdt').value,
      ngay_dk:g('tcNgay').value }).then(function(j){
      dangBan=false;
      if(!j||!j.ok){ loi=(j&&j.error)||'Không đăng ký được.'; bao=''; ve(); return; }
      loi=''; bao='Đã đăng ký.'; napMan();
    }).catch(function(e){ dangBan=false; loi=e.message; ve(); });
  });
  document.querySelectorAll('[data-tcx]').forEach(function(o){
    o.addEventListener('click', function(){
      goi('tc_xong', { id:+o.getAttribute('data-tcx'), xong:o.getAttribute('data-xong')==='1' })
        .then(function(j){
          if(!j||!j.ok){ loi=(j&&j.error)||'Không đổi được.'; ve(); return; }
          loi=''; napMan();
        }).catch(function(e){ loi=e.message; ve(); });
    });
  });
}

/* ============================================================================================
 * MÀN THÔNG BÁO
 * ========================================================================================== */
function veThongBao(){
  var d = duLieu.thong_bao;
  if (!d) return '<div class="the">Đang tải…</div>';
  var h = '';
  if (laVai('cua_hang_truong')) {
    h += '<div class="the"><h2>Đăng thông báo</h2>'
      + '<div class="o"><label>Tiêu đề</label><input id="tbTieu"></div>'
      + '<div class="o" style="margin-top:8px"><label>Nội dung</label>'
      + '<textarea id="tbThan" rows="3" style="width:100%;border:1px solid var(--vien);'
      + 'border-radius:9px;padding:8px 10px"></textarea></div>'
      + '<div class="hang" style="margin-top:8px">'
      + '<div class="o"><label>Gửi cho</label><select id="tbCoSo">'
      + (laVai('quan_ly') ? '<option value="">Toàn hệ (mọi cơ sở)</option>' : '')
      + (toi.ds_coso||[]).map(function(c){
          return '<option value="'+esc(c)+'"'+(c===cosoChon?' selected':'')+'>'+esc(c)+'</option>';
        }).join('')
      + '</select></div>'
      + '<button class="nut chinh" id="btTbDang">ĐĂNG</button></div>'
      /* Nói thẳng ranh giới giữa thông báo và giao việc — dùng nhầm là việc của không ai cả. */
      + '<p class="nho" style="margin-top:8px">Thông báo là <b>nói với nhiều người</b>, không đòi '
      + 'ai làm gì. Cần ai đó làm một việc trước một hạn thì dùng màn <b>Giao việc</b>: ở đấy có '
      + 'tên người nhận và có chỗ đánh dấu xong.</p></div>';
  }
  h += '<div class="the"><h2>Bảng tin</h2>';
  if (!(d.ds||[]).length) { h += '<p class="nho">Chưa có thông báo nào.</p>'; }
  else {
    d.ds.forEach(function(x){
      h += '<div style="padding:12px 0;border-bottom:1px solid var(--vien)">'
        + '<div style="display:flex;gap:8px;align-items:baseline;flex-wrap:wrap">'
        + '<b>'+esc(x.tieu_de)+'</b>'
        + '<span class="nhan '+(x.coso?'chua':'tot')+'">'+(x.coso?esc(x.coso):'Toàn hệ')+'</span>'
        + '<span class="nho">'+esc(x.nguoi)+' · '+esc(x.tao)+'</span>'
        + (laVai('quan_ly')?'<button class="nut" data-tbx="'+x.id+'" '
            + 'style="padding:3px 8px;margin-left:auto">Xoá</button>':'')
        + '</div>'
        + (x.than?'<div class="nho" style="margin-top:4px;white-space:pre-wrap">'+esc(x.than)+'</div>':'')
        + '</div>';
    });
  }
  return h + '</div>';
}
function noiThongBao(){
  if (g('btTbDang')) g('btTbDang').addEventListener('click', function(){
    var b=this; if(dangBan) return; dangBan=true; b.disabled=true;
    goi('tb_dang', { tieu_de:g('tbTieu').value, than:g('tbThan').value, coso:g('tbCoSo').value })
      .then(function(j){
        dangBan=false;
        if(!j||!j.ok){ loi=(j&&j.error)||'Không đăng được.'; bao=''; ve(); return; }
        loi=''; bao='Đã đăng.'; napMan();
      }).catch(function(e){ dangBan=false; loi=e.message; ve(); });
  });
  document.querySelectorAll('[data-tbx]').forEach(function(o){
    o.addEventListener('click', function(){
      if (!confirm('Xoá thông báo này?')) return;
      goi('tb_xoa', { id:+o.getAttribute('data-tbx') }).then(function(j){
        if(j&&j.ok){ napMan(); } else { loi=(j&&j.error)||'Không xoá được.'; ve(); }
      }).catch(function(e){ loi=e.message; ve(); });
    });
  });
}

/* ============================================================================================
 * MÀN GIAO VIỆC
 * ========================================================================================== */
function veGiaoViec(){
  var d = duLieu.giao_viec;
  if (!d) return '<div class="the">Đang tải…</div>';
  var h = '';
  if (laVai('cua_hang_truong')) {
    h += '<div class="the"><h2>Giao việc mới</h2><div class="hang">'
      + oCoSo('gvCoSo', cosoChon)
      + '<div class="o" style="flex:1;min-width:220px"><label>Việc</label><input id="gvTieu"></div>'
      + '</div><div class="hang" style="margin-top:8px">'
      + '<div class="o" style="flex:1;min-width:200px"><label>Người nhận</label>'
      + '<input id="gvNhan" placeholder="Họ tên"></div>'
      + '<div class="o"><label>Hạn</label><input id="gvHan" type="date" value="'+esc(homNay())+'"></div>'
      + '</div>'
      + '<div class="o" style="margin-top:8px"><label>Mô tả</label>'
      + '<textarea id="gvMoTa" rows="2" style="width:100%;border:1px solid var(--vien);'
      + 'border-radius:9px;padding:8px 10px"></textarea></div>'
      + '<button class="nut chinh" id="btGvGiao" style="margin-top:10px">GIAO VIỆC</button>'
      + '<p class="nho" style="margin-top:8px">Bắt buộc có <b>người nhận</b> và <b>hạn</b>: việc '
      + 'không gắn tên ai là việc của không ai cả, và việc không có hạn thì không bao giờ trễ nên '
      + 'cũng không bao giờ được nhắc.</p></div>';
  }
  h += '<div class="the"><h2>Danh sách</h2>'
    + '<div class="hang" style="margin:8px 0"><div class="o"><label>Trạng thái</label>'
    + '<select id="gvLoc"><option value="chua">Chưa xong</option>'
    + '<option value="xong">Đã xong</option><option value="">Tất cả</option></select></div></div>';
  if (!(d.ds||[]).length) { h += '<p class="nho">Không có việc nào.</p>'; }
  else {
    h += '<div class="cuon"><table><tr><th>Việc</th><th>Cơ sở</th><th>Người nhận</th>'
      + '<th>Hạn</th><th>Trạng thái</th><th></th></tr>';
    d.ds.forEach(function(x){
      h += '<tr><td><b>'+esc(x.tieu_de)+'</b>'
        + (x.mo_ta?'<div class="nho">'+esc(x.mo_ta)+'</div>':'')
        + '<div class="nho">giao bởi '+esc(x.nguoi_giao)+'</div></td>'
        + '<td>'+esc(x.coso)+'</td><td>'+esc(x.giao_ten)+'</td>'
        + '<td>'+ngayVN(x.han)+(x.tre?' <span class="nhan xau">Trễ</span>':'')+'</td>'
        + '<td>'+(x.tt==='xong'?'<span class="nhan tot">Đã xong</span>':'<span class="nhan chua">Chưa</span>')+'</td>'
        + '<td><button class="nut" data-gv="'+x.id+'" data-lam="'+(x.tt==='xong'?'mo_lai':'xong')+'" '
        + 'style="padding:4px 9px">'+(x.tt==='xong'?'Mở lại':'Xong')+'</button></td></tr>';
    });
    h += '</table></div>';
  }
  return h + '</div>';
}
function noiGiaoViec(){
  if (g('btGvGiao')) g('btGvGiao').addEventListener('click', function(){
    var b=this; if(dangBan) return; dangBan=true; b.disabled=true;
    goi('gv_giao', { coso:g('gvCoSo').value, tieu_de:g('gvTieu').value,
      giao_ten:g('gvNhan').value, han:g('gvHan').value, mo_ta:g('gvMoTa').value })
      .then(function(j){
        dangBan=false;
        if(!j||!j.ok){ loi=(j&&j.error)||'Không giao được.'; bao=''; ve(); return; }
        loi=''; bao='Đã giao việc.'; napMan();
      }).catch(function(e){ dangBan=false; loi=e.message; ve(); });
  });
  var l = g('gvLoc');
  if (l) l.addEventListener('change', function(){
    goi('gv_ds', { tt:l.value }).then(function(j){
      if(j&&j.ok){ duLieu.giao_viec=j; ve(); }
    }).catch(function(){});
  });
  document.querySelectorAll('[data-gv]').forEach(function(o){
    o.addEventListener('click', function(){
      goi('gv_tt', { id:+o.getAttribute('data-gv'), lam:o.getAttribute('data-lam') })
        .then(function(j){
          if(!j||!j.ok){ loi=(j&&j.error)||'Không đổi được.'; ve(); return; }
          loi=''; napMan();
        }).catch(function(e){ loi=e.message; ve(); });
    });
  });
}

/* ============================================================================================
 * MÀN XUẤT BÁO CÁO
 * ==========================================================================================
 * Máy chủ sinh CSV, trang tự dựng tệp rồi tải xuống. Không mở tab mới với đường dẫn mang thẻ:
 * đường dẫn nằm lại trong lịch sử trình duyệt và trong nhật ký máy chủ.
 * ========================================================================================== */
function veBaoCao(){
  var d = duLieu.bao_cao;
  if (!d) return '<div class="the">Đang tải…</div>';
  if (!kyChon) kyChon = d.ky;
  var o = '';
  Object.keys(d.loai||{}).forEach(function(k){
    o += '<option value="'+esc(k)+'">'+esc(d.loai[k])+'</option>';
  });
  return '<div class="the"><h2>Xuất ra tệp CSV</h2><div class="hang" style="margin-top:10px">'
    + '<div class="o" style="flex:1;min-width:240px"><label>Báo cáo</label>'
    + '<select id="bcLoai">'+o+'</select></div>'
    + oCoSo('bcCoSo', cosoChon)
    + '<div class="o"><label>Kỳ</label><input id="bcKy" type="month" value="'+esc(kyChon)+'"></div>'
    + '<button class="nut chinh" id="btBcXuat">TẢI VỀ</button></div>'
    + '<p class="nho" style="margin-top:10px">Tệp CSV mở được bằng Excel, Google Sheet và '
    + 'LibreOffice. Chỉ xuất phần anh được phép xem — cửa hàng trưởng xuất ra cũng chỉ có cơ sở '
    + 'của mình.</p>'
    + '<p class="nho">Mấy báo cáo <i>Sự cố</i> và <i>Giao việc</i> xuất <b>toàn bộ</b>, không cắt '
    + 'theo kỳ: một sự cố mở từ tháng trước mà chưa đóng thì nó vẫn là việc của tháng này.</p>'
    + '</div>';
}
function noiBaoCao(){
  if (!g('btBcXuat')) return;
  g('btBcXuat').addEventListener('click', function(){
    var b=this; if(dangBan) return; dangBan=true; b.disabled=true; b.textContent='Đang dựng…';
    goi('bc_xuat', { loai:g('bcLoai').value, coso:g('bcCoSo').value, ky:g('bcKy').value })
      .then(function(j){
        dangBan=false; b.disabled=false; b.textContent='TẢI VỀ';
        if(!j||!j.ok){ loi=(j&&j.error)||'Không xuất được.'; ve(); return; }
        var blob = new Blob([j.csv], { type:'text/csv;charset=utf-8' });
        var a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = j.ten;
        document.body.appendChild(a); a.click();
        setTimeout(function(){ URL.revokeObjectURL(a.href); a.remove(); }, 1000);
        loi=''; bao='Đã tải '+j.ten+'.'; ve();
      }).catch(function(e){ dangBan=false; loi=e.message; ve(); });
  });
}

/* ============================================================================================
 * VẼ & NẠP
 * ========================================================================================== */
function ve(){
  var goc = g('ung-dung');
  if (!toi) { goc.innerHTML = veDangNhap(); noiDangNhap(); return; }

  var tieu, duoi, than;
  if (man === 'tien') {
    tieu = tenMuc('tien'); duoi = 'Nhập theo ngày, từng cơ sở. Tổng do máy chủ cộng lại.';
    than = veTien();
  } else if (man === 'checklist') {
    tieu = tenMuc('checklist'); duoi = 'Tích dần trong ca — lưu lại bao nhiêu lần cũng được.';
    than = veChecklist();
  } else if (man === 'kho') {
    tieu = tenMuc('kho'); duoi = 'Đếm theo tuần, và so với tuần trước để thấy mất mát.';
    than = veKho();
  } else if (man === 'danh_gia') {
    tieu = tenMuc('danh_gia'); duoi = 'Vi phạm & khen theo bảng phạt — mức tăng dần theo lần 1/2/3.';
    than = veDanhGia();
  } else if (man === 'tiktok') {
    tieu = tenMuc('tiktok'); duoi = 'Clip và thưởng theo bậc lượt xem.';
    than = veTikTok();
  } else if (man === 'trich_cam') {
    tieu = tenMuc('trich_cam'); duoi = 'Đăng ký trích cam theo hoá đơn.';
    than = veTrichCam();
  } else if (man === 'thong_bao') {
    tieu = tenMuc('thong_bao'); duoi = 'Nói với nhiều người — không phải chỗ giao việc.';
    than = veThongBao();
  } else if (man === 'giao_viec') {
    tieu = tenMuc('giao_viec'); duoi = 'Mỗi việc một người nhận và một hạn.';
    than = veGiaoViec();
  } else if (man === 'bao_cao') {
    tieu = tenMuc('bao_cao'); duoi = 'Tải về CSV — chỉ phần anh được phép xem.';
    than = veBaoCao();
  } else if (man === 'su_co') {
    tieu = tenMuc('su_co'); duoi = 'Việc hỏng ngoài hiện trường — phải có người phụ trách và hạn.';
    than = veSuCo();
  } else {
    tieu = 'Tổng quan';
    duoi = 'Trạng thái vận hành ngày ' + ngayVN((duLieu.tong_quan && duLieu.tong_quan.hnay) || homNay()) + '.';
    than = veTongQuan();
  }
  goc.innerHTML = veKhung(tieu, duoi, than);

  document.querySelectorAll('[data-man]').forEach(function(o){
    o.addEventListener('click', function(){
      man = o.getAttribute('data-man'); loi=''; bao=''; benHien=false; ve(); napMan();
    });
  });
  var bt = g('btBen');
  if (bt) bt.addEventListener('click', function(){
    benHien = !benHien;
    var b = g('thanhBen'); if (b) b.classList.toggle('hien', benHien);
  });
  g('btRa').addEventListener('click', function(){
    datThe(''); toi=null; duLieu={tong_quan:null,tien:null,su_co:null,checklist:null,kho:null,danh_gia:null,
      tiktok:null,trich_cam:null,thong_bao:null,giao_viec:null,bao_cao:null}; ve();
  });

  /* Đặt chỉ tiêu doanh thu tháng — chỉ quản lý thấy nút này, và máy chủ hỏi lại quyền một lần nữa. */
  document.querySelectorAll('[data-ct]').forEach(function(o){
    o.addEventListener('click', function(){
      var cu = o.getAttribute('data-ct-so') || '';
      var v = prompt('Chỉ tiêu doanh thu THÁNG của "'+o.getAttribute('data-ct')+'" (đồng).\n'
        + 'Để trống hoặc 0 = chưa đặt, và mảnh này sẽ không tham gia chấm điểm.', cu);
      if (v === null) return;
      goi('dat_chi_tieu', { coso: o.getAttribute('data-ct'), so: parseInt(v,10)||0 })
        .then(function(j){
          if(!j||!j.ok){ loi=(j&&j.error)||'Không đặt được.'; ve(); return; }
          loi=''; bao='Đã đặt chỉ tiêu.'; napMan();
        }).catch(function(e){ loi=e.message; ve(); });
    });
  });

  if (man==='tien') noiTien();
  if (man==='checklist') noiChecklist();
  if (man==='kho') noiKho();
  if (man==='su_co') noiSuCo();
  if (man==='danh_gia') noiDanhGia();
  if (man==='tiktok') noiTikTok();
  if (man==='trich_cam') noiTrichCam();
  if (man==='thong_bao') noiThongBao();
  if (man==='giao_viec') noiGiaoViec();
  if (man==='bao_cao') noiBaoCao();
}

function napMan(){
  if (!toi) return;
  if (man === 'tong_quan') {
    goi('tong_quan', { tu: dauThang(), den: homNay() }).then(function(j){
      if (j && j.ok) { duLieu.tong_quan = j; ve(); }
      else if (j) { loi = j.error||''; ve(); }
    }).catch(function(e){ loi=e.message; ve(); });
  } else if (man === 'tien') {
    if (!cosoChon) { cosoChon = toi.coso || (toi.ds_coso&&toi.ds_coso[0]) || ''; }
    goi('tien_doc', { coso: cosoChon, ngay: ngayChon }).then(function(j){
      if (j && j.ok) { duLieu.tien = j; ve(); }
      else if (j) { loi = j.error||''; ve(); }
    }).catch(function(e){ loi=e.message; ve(); });
  } else if (man === 'checklist') {
    if (!cosoChon) { cosoChon = toi.coso || (toi.ds_coso&&toi.ds_coso[0]) || ''; }
    goi('cl_doc', { coso: cosoChon, ngay: ngayChon, buoi: clBuoi }).then(function(j){
      if (j && j.ok) { duLieu.checklist = j; ve(); }
      else if (j) { loi = j.error||''; ve(); }
    }).catch(function(e){ loi=e.message; ve(); });
  } else if (man === 'kho') {
    if (!cosoChon) { cosoChon = toi.coso || (toi.ds_coso&&toi.ds_coso[0]) || ''; }
    goi('kho_doc', { coso: cosoChon, ngay: ngayChon }).then(function(j){
      if (j && j.ok) { duLieu.kho = j; ve(); }
      else if (j) { loi = j.error||''; ve(); }
    }).catch(function(e){ loi=e.message; ve(); });
  } else if (man === 'danh_gia' || man === 'tiktok' || man === 'trich_cam') {
    if (!cosoChon) { cosoChon = toi.coso || (toi.ds_coso&&toi.ds_coso[0]) || ''; }
    var viec = { danh_gia:'dg_ds', tiktok:'tk_ds', trich_cam:'tc_ds' }[man];
    var oMan = man;
    goi(viec, { coso: cosoChon, ky: kyChon }).then(function(j){
      if (j && j.ok) { duLieu[oMan] = j; ve(); }
      else if (j) { loi = j.error||''; ve(); }
    }).catch(function(e){ loi=e.message; ve(); });
  } else if (man === 'thong_bao') {
    goi('tb_ds').then(function(j){
      if (j && j.ok) { duLieu.thong_bao = j; ve(); }
      else if (j) { loi = j.error||''; ve(); }
    }).catch(function(e){ loi=e.message; ve(); });
  } else if (man === 'giao_viec') {
    goi('gv_ds', { tt:'chua' }).then(function(j){
      if (j && j.ok) { duLieu.giao_viec = j; ve(); }
      else if (j) { loi = j.error||''; ve(); }
    }).catch(function(e){ loi=e.message; ve(); });
  } else if (man === 'bao_cao') {
    goi('bc_loai').then(function(j){
      if (j && j.ok) { duLieu.bao_cao = j; ve(); }
      else if (j) { loi = j.error||''; ve(); }
    }).catch(function(e){ loi=e.message; ve(); });
  } else if (man === 'su_co') {
    goi('su_co_ds', { tt:'mo' }).then(function(j){
      if (j && j.ok) { duLieu.su_co = j.ds; ve(); }
      else if (j) { loi = j.error||''; ve(); }
    }).catch(function(e){ loi=e.message; ve(); });
  }
}

/* Mở trang: có thẻ thì hỏi máy chủ xem thẻ còn sống không. KHÔNG tin chuỗi trong máy — thẻ hết
   hạn mà vẫn vẽ màn quản trị là người dùng bấm gì cũng hỏng mà không hiểu vì sao. */
function batDau(){
  if (!the()) { ve(); return; }
  goi('toi').then(function(j){
    if (j && j.ok) {
      toi = j.toi;
      man = (toi.man && toi.man[0]) || 'tong_quan';
      cosoChon = toi.coso || (toi.ds_coso && toi.ds_coso[0]) || '';
      ve(); napMan();
    } else { toi = null; ve(); }
  }).catch(function(){ toi = null; ve(); });
}
batDau();
})();
</script>
</body>
</html>
