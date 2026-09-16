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
var duLieu = { tong_quan:null, tien:null, su_co:null };
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
var TEN_MAN = { tong_quan:'Tổng quan', tien:'Doanh thu &amp; Chi phí', su_co:'Sự cố' };
var TEN_VAI = { nhan_vien:'Nhân viên', thu_ngan:'Thu ngân',
                cua_hang_truong:'Cửa hàng trưởng', quan_ly:'Quản lý' };

function veKhung(than){
  var soSuCo = (duLieu.tong_quan && duLieu.tong_quan.su_co) ? duLieu.tong_quan.su_co.length : 0;
  var nav = (toi.man||[]).map(function(m){
    var d = (m==='su_co' && soSuCo) ? '<span class="cham">'+soSuCo+'</span>' : '';
    return '<button data-man="'+m+'" class="'+(m===man?'chon':'')+'">'+TEN_MAN[m]+d+'</button>';
  }).join('');
  return '<div class="dinh"><div class="dinh-in">'
    + '<div class="hieu">VẬN HÀNH<span>K&amp;H</span></div>'
    + '<div class="toi"><b>'+esc(toi.ten)+'</b> · '+esc(TEN_VAI[toi.vai]||toi.vai)
    + (toi.coso ? ' · '+esc(toi.coso) : '')
    + ' &nbsp;<button class="nut" id="btRa" style="padding:5px 11px">Thoát</button></div>'
    + '</div></div>'
    + '<div class="bao"><div class="nav">'+nav+'</div>'
    + (loi ? '<div class="bao-loi">'+esc(loi)+'</div>' : '')
    + (bao ? '<div class="bao-ok">'+esc(bao)+'</div>' : '')
    + than + '</div>';
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
  var h = '<div class="the"><h2>Tháng này</h2>'
    + '<div class="o-so">'
    + hopSo('Doanh thu', tien(s.thu))
    + hopSo('Chi phí', tien(s.chi))
    + hopSo('Còn lại', tien((s.thu||0)-(s.chi||0)))
    + hopSo('Lượt khách', (s.khach||0).toLocaleString('vi-VN'))
    + '</div>'
    + (s.cho_duyet ? '<p class="nho" style="margin-top:10px">⏳ Còn <b>'+s.cho_duyet
        +'</b> báo cáo doanh thu chờ duyệt.</p>' : '')
    + '</div>';

  var sc = d.su_co || [];
  h += '<div class="the"><h2>Sự cố đang mở ('+sc.length+')</h2>';
  h += sc.length ? bangSuCo(sc, false) : '<p class="nho">Không có sự cố nào đang mở.</p>';
  h += '</div>';
  return h;
}
function hopSo(l, n){
  return '<div class="box"><div class="n">'+esc(n)+'</div><div class="l">'+esc(l)+'</div></div>';
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
 * VẼ & NẠP
 * ========================================================================================== */
function ve(){
  var goc = g('ung-dung');
  if (!toi) { goc.innerHTML = veDangNhap(); noiDangNhap(); return; }
  var than = man==='tien' ? veTien() : (man==='su_co' ? veSuCo() : veTongQuan());
  goc.innerHTML = veKhung(than);

  document.querySelectorAll('[data-man]').forEach(function(o){
    o.addEventListener('click', function(){
      man = o.getAttribute('data-man'); loi=''; bao=''; ve(); napMan();
    });
  });
  g('btRa').addEventListener('click', function(){
    datThe(''); toi=null; duLieu={tong_quan:null,tien:null,su_co:null}; ve();
  });
  if (man==='tien') noiTien();
  if (man==='su_co') noiSuCo();
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
