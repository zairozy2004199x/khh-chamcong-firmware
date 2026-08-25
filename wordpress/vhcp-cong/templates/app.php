<?php
/**
 * Màn nạp CSV — nằm NGOÀI trang quản trị, ở `khmatrix.com/cong`.
 *
 * Không dùng khung giao diện của theme: trang này là công cụ, cần đầy màn hình và không có
 * thanh bên nào chen vào. Tự dựng HTML từ đầu.
 *
 * @var array $nguoi  vai · ten · co_so
 * @package vhcp-cong
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

$vai       = $nguoi['vai'];
$nap_nv    = VCG_Quyen::nap_nhan_vien( $vai );
$nap_cs    = VCG_Quyen::nap_co_so( $vai );
$xem_duoc  = VCG_Quyen::xem( $vai );
$nonce     = wp_create_nonce( 'vcg_nap' );
$ajax      = admin_url( 'admin-ajax.php' );
?><!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Chấm công — nạp dữ liệu</title>
<style>
	:root{
		--nen:#f6f7f9; --the:#fff; --vien:#e2e5ea; --chu:#1a1d21; --mo:#666e78;
		--xanh:#1f6feb; --xanh-nhat:#eaf2ff; --do:#c0392b; --do-nhat:#fdecea;
		--luc:#1a7f37; --luc-nhat:#e9f5ec;
	}
	*{box-sizing:border-box}
	body{margin:0;background:var(--nen);color:var(--chu);
		font:15px/1.55 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif}
	.bao{max-width:880px;margin:0 auto;padding:24px 16px 64px}
	h1{font-size:22px;margin:0 0 4px}
	.phu{color:var(--mo);font-size:14px;margin:0 0 24px}
	.the{background:var(--the);border:1px solid var(--vien);border-radius:12px;
		padding:20px;margin-bottom:16px}
	.the h2{font-size:16px;margin:0 0 6px}
	.the p.gt{color:var(--mo);font-size:13.5px;margin:0 0 16px}
	label.tep{display:block;border:2px dashed var(--vien);border-radius:10px;padding:22px;
		text-align:center;cursor:pointer;color:var(--mo);transition:.15s}
	label.tep:hover{border-color:var(--xanh);background:var(--xanh-nhat);color:var(--xanh)}
	label.tep.co{border-style:solid;border-color:var(--luc);background:var(--luc-nhat);color:var(--luc)}
	input[type=file]{display:none}
	button{font:inherit;font-weight:600;border:0;border-radius:8px;padding:10px 18px;cursor:pointer}
	button.chinh{background:var(--xanh);color:#fff}
	button.chinh:disabled{background:#b9c6d8;cursor:not-allowed}
	button.phu2{background:#eef0f3;color:var(--chu);margin-left:8px}
	.hang{display:flex;align-items:center;gap:8px;margin-top:14px;flex-wrap:wrap}
	.tom{margin-top:16px;border:1px solid var(--vien);border-radius:10px;overflow:hidden}
	.tom .dau{background:#f0f3f7;padding:10px 14px;font-weight:600;font-size:14px}
	.tom .than{padding:14px}
	.so{display:flex;gap:22px;flex-wrap:wrap;margin-bottom:12px}
	.so div{min-width:88px}
	.so b{display:block;font-size:24px;line-height:1.2}
	.so span{color:var(--mo);font-size:12.5px}
	table{width:100%;border-collapse:collapse;font-size:13px}
	th,td{text-align:left;padding:6px 8px;border-top:1px solid var(--vien)}
	th{color:var(--mo);font-weight:600}
	.bao-loi{background:var(--do-nhat);color:var(--do);border:1px solid #f5c6c2;
		border-radius:8px;padding:12px 14px;margin-top:14px;font-size:14px}
	.bao-ok{background:var(--luc-nhat);color:var(--luc);border:1px solid #bfe3c8;
		border-radius:8px;padding:12px 14px;margin-top:14px;font-size:14px}
	.chan{color:var(--mo);font-size:13px;margin-top:8px}
	/* Bảng cảnh báo: màu VÀNG chứ không đỏ. Đỏ là "hỏng, không nạp được"; vàng là "nạp rồi,
	   nhưng Sheet gốc có chỗ nên sửa". Dùng đỏ cho cả hai là người ta hoặc hoảng vô cớ, hoặc
	   quen mắt rồi bỏ qua luôn cái đỏ thật. */
	.canh{margin-top:14px;border:1px solid #f0d48a;border-radius:8px;overflow:hidden;background:#fffbef}
	.canh-dau{background:#fdf2d0;color:#8a6100;padding:10px 14px;font-weight:600;font-size:14px}
	.canh table{padding:0 14px}
	.canh td,.canh th{border-top:1px solid #f4e3b4}
	.canh .chan{padding:0 14px 12px}
	.tu-dien{font-size:13.5px;color:var(--mo);display:flex;align-items:center;gap:8px}
	.tu-dien input{font:inherit;font-size:14px;color:var(--chu);padding:7px 10px;
		border:1px solid var(--vien);border-radius:8px;width:200px}
	.tu-dien input:focus{outline:0;border-color:var(--xanh)}
	.tu-dien input.can{border-color:var(--do);background:var(--do-nhat)}
	.pb{font-size:12px;font-weight:600;color:var(--mo);background:#eef0f3;
		border-radius:20px;padding:3px 10px;vertical-align:middle;margin-left:6px}
	.khoa{opacity:.55}
	.tabs{display:flex;gap:6px;margin:0 0 16px}
	.tab{font:inherit;font-size:14px;font-weight:600;border:1px solid var(--vien);background:var(--the);
		color:var(--mo);border-radius:8px;padding:8px 14px;cursor:pointer}
	.tab.dang{background:var(--xanh);border-color:var(--xanh);color:#fff}
	/* Bảng công: cột NGÀY hẹp và NHIỀU — phải cuộn ngang trong khung riêng, không để cả trang
	   trôi ngang. Cột tên GHIM TRÁI, nếu không thì cuộn tới ngày 20 là không biết dòng của ai. */
	.bcw{overflow-x:auto;border:1px solid var(--vien);border-radius:10px;margin-top:14px}
	table.bc{border-collapse:separate;border-spacing:0;font-size:12px;white-space:nowrap}
	table.bc th,table.bc td{border-bottom:1px solid var(--vien);border-right:1px solid var(--vien);padding:5px 7px}
	table.bc thead th{background:#f0f3f7;position:sticky;top:0;z-index:2;font-weight:600;color:var(--mo)}
	table.bc .ten{position:sticky;left:0;background:var(--the);z-index:3;text-align:left;min-width:170px;
		box-shadow:1px 0 0 var(--vien)}
	table.bc thead .ten{z-index:4;background:#f0f3f7}
	table.bc td.o{text-align:center;font-variant-numeric:tabular-nums}
	table.bc td.cn{background:#fafafa}
	table.bc td.thieu{background:#fffbef;color:#8a6100;font-weight:600}
	table.bc td.trong{color:#cbd5e1}
	table.bc td.tong{font-weight:700;background:#f6f9f6}
	.bc-tt{display:flex;gap:20px;flex-wrap:wrap;margin-top:10px;font-size:13px;color:var(--mo)}
	.bc-tt b{color:var(--chu);font-size:16px}
	@media print{ .tabs,.hang,.phu,#man-nap{display:none!important}
		.bcw{overflow:visible;border:0} table.bc{font-size:10px} }
</style>
</head>
<body>
<div class="bao">

	<h1>Chấm công <span class="pb">b<?php echo esc_html( VCG_PHIEN_BAN ); ?></span></h1>
	<!-- 🔴 SỐ PHIÊN BẢN HIỆN NGAY TRÊN TRANG. Cài đè plugin mà tệp cũ còn nằm lại là chuyện có
	     thật, và khi đó mọi con số đều lệch một cách không giải thích được — mất cả buổi để đoán.
	     In số phiên bản ra là một giây biết ngay đang chạy bản nào. -->
	<p class="phu">
		<?php if ( $vai ) : ?>
			Đang dùng: <b><?php echo esc_html( $nguoi['ten'] ? $nguoi['ten'] : $vai ); ?></b>
			· vai <b><?php echo esc_html( $vai ); ?></b>
		<?php else : ?>
			Chưa đăng nhập.
		<?php endif; ?>
	</p>

	<!-- Hai màn, một trang. Đổi màn bằng JS chứ không tải lại — người ta nạp xong là muốn xem
	     ngay bảng, tải lại trang là mất luôn kết quả nạp vừa hiện. -->
	<div class="tabs">
		<button class="tab dang" data-man="bang">📅 Bảng chấm công</button>
		<button class="tab" data-man="nap">⬆️ Nạp dữ liệu</button>
	</div>

	<div id="man-bang">
		<div class="the">
			<h2>Bảng chấm công theo cơ sở</h2>
			<p class="gt">Chọn cơ sở và tháng. Mỗi dòng một người, mỗi cột một ngày —
			ô ghi <b>giờ vào → giờ ra</b>. Ô vàng là <b>thiếu giờ ra</b>.</p>
			<div class="hang">
				<select id="b-coso" style="min-width:230px"><option value="">— chọn cơ sở —</option></select>
				<select id="b-thang" style="min-width:150px"><option value="">— tháng —</option></select>
				<input type="text" id="b-tim" placeholder="Lọc theo tên hoặc mã NV…" style="min-width:200px">
				<button class="chinh" id="b-in" style="display:none">🖨 In / PDF</button>
			</div>
			<div id="b-kq"></div>
		</div>
	</div>

	<div id="man-nap" style="display:none">

<?php if ( ! $xem_duoc ) : ?>
	<div class="the">
		<h2>Chưa đăng nhập</h2>
		<p class="gt">Đăng nhập bằng tài khoản quản trị, hoặc bằng PIN ở trang chấm công, rồi quay lại đây.</p>
	</div>
<?php else : ?>

	<!-- ============ SHEET CƠ SỞ ============ -->
	<div class="the <?php echo $nap_cs ? '' : 'khoa'; ?>">
		<h2>Sheet cơ sở — bảng chấm công</h2>
		<p class="gt">
			Tệp CSV xuất từ sheet <code>CS_&lt;tên cơ sở&gt;</code>. Giữ nguyên tên tệp thì hệ tự
			nhận ra cơ sở. Nạp lại nhiều lần không sao — giờ vào/giờ ra chỉ nới rộng, không bao
			giờ bị cắt ngắn.
		</p>
		<?php if ( $nap_cs ) : ?>
			<label class="tep" id="nhan-cs">
				<input type="file" accept=".csv,text/csv" id="tep-cs">
				<span class="ten-tep">Bấm để chọn tệp CSV cơ sở</span>
			</label>
			<div class="hang">
				<button class="chinh" id="xem-cs" disabled>Xem trước</button>
				<button class="phu2" id="nap-cs" style="display:none">Xác nhận nạp</button>
			</div>
			<!-- Ô TỰ ĐIỀN — để cái cửa "không đoán được cơ sở" không bao giờ chặn được nữa.
			     Để trống thì hệ tự lấy từ tên tệp như thường; điền vào thì lấy đúng cái điền.
			     Tự đoán tên tệp tiện, nhưng tiện mà tắc thì người ta đứng luôn ở đó. -->
			<div class="hang">
				<label class="tu-dien">Cơ sở
					<input type="text" id="co-so-cs" placeholder="tự lấy từ tên tệp"
						autocomplete="off" spellcheck="false">
				</label>
				<span class="chan">Chỉ điền khi hệ không tự nhận ra, hoặc khi muốn nạp vào một
				cơ sở khác với tên tệp.</span>
			</div>
			<div id="kq-cs"></div>
		<?php else : ?>
			<p class="chan">Vai <b><?php echo esc_html( $vai ); ?></b> không nạp được sheet cơ sở.
			Chỉ Admin, quản lý vùng và cửa hàng trưởng mới nạp được.</p>
		<?php endif; ?>
	</div>

	<!-- ============ SHEET NHÂN VIÊN ============ -->
	<div class="the <?php echo $nap_nv ? '' : 'khoa'; ?>">
		<h2>Sheet nhân viên — hồ sơ chung</h2>
		<p class="gt">
			Tệp CSV xuất từ sheet <code>NV_NguonCongT</code>. Một người làm nhiều cơ sở thì giữ
			đủ cả, không đè lên nhau.
		</p>
		<?php if ( $nap_nv ) : ?>
			<label class="tep" id="nhan-nv">
				<input type="file" accept=".csv,text/csv" id="tep-nv">
				<span class="ten-tep">Bấm để chọn tệp CSV nhân viên</span>
			</label>
			<div class="hang">
				<button class="chinh" id="xem-nv" disabled>Xem trước</button>
				<button class="phu2" id="nap-nv" style="display:none">Xác nhận nạp</button>
			</div>
			<div id="kq-nv"></div>
		<?php else : ?>
			<p class="chan">Chỉ <b>Admin</b> mới nạp được sheet nhân viên — đây là dữ liệu chung
			của mọi phần mềm, nạp nhầm là sai lan sang cả hệ.</p>
		<?php endif; ?>
	</div>

<?php endif; ?>
	</div><!-- /man-nap -->
</div>

<script>
(function(){
	var AJAX  = <?php echo wp_json_encode( $ajax ); ?>;
	var NONCE = <?php echo wp_json_encode( $nonce ); ?>;

	/* Thoát HTML cho MỌI giá trị lấy từ tệp. Nội dung CSV là do người dùng đưa vào — một ô tên
	   chứa thẻ script là chạy ngay trong trang. Không ai cố tình, nhưng dán nhầm từ web vào ô
	   Sheet thì có thật. */
	function thoat(s){
		return String(s).replace(/[&<>"']/g, function(c){
			return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c];
		});
	}
	function chu(n){ return (n===null||n===undefined) ? '—' : thoat(n); }
	/* Tệp này vốn gọi thẳng document.getElementById ở mọi chỗ. Màn bảng công dùng nhiều lần nên
	   đặt một tên ngắn — và trả về null an toàn khi phần tử chưa có (màn kia đang ẩn). */
	function el(id){ return document.getElementById(id); }

	/* ===================== MÀN BẢNG CHẤM CÔNG =====================
	   Dữ liệu về là PHẲNG (mỗi lượt một dòng); màn này xoay nó thành bảng NGANG như Sheet mà
	   anh Thắng đang dùng — người ta đọc quen kiểu đó rồi, đổi cách trình bày là phải học lại. */
	var BANG=null;

	function hhmm(g){
		if (g===null||g===undefined) return '';
		g=Number(g); if(isNaN(g)) return '';
		/* Ca đêm đã được trải phẳng (+86400) lúc nạp, nên giờ có thể vượt 24h. Đưa về trong ngày
		   để đọc, chứ không phải để tính — phần tính giờ dùng số giây gốc. */
		var t=((g%86400)+86400)%86400;
		var h=Math.floor(t/3600), p=Math.floor((t%3600)/60);
		return (h<10?'0':'')+h+':'+(p<10?'0':'')+p;
	}
	/** Số giờ của một lượt. Giờ ra nhỏ hơn giờ vào = qua nửa đêm -> cộng 24h, không thì ra ÂM. */
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

	function goiB(viec, them, xong){
		var d=new FormData();
		d.append('action', viec); d.append('nonce', NONCE);
		for(var k in (them||{})) d.append(k, them[k]);
		fetch(AJAX,{method:'POST',body:d,credentials:'same-origin'})
			.then(function(r){return r.json();}).then(xong)
			.catch(function(){ el('b-kq').innerHTML='<div class="bao-loi">Không gọi được máy chủ.</div>'; });
	}

	function napChon(coso, xong){
		goiB('vcg_chon', coso?{co_so:coso}:{}, function(r){
			if(!r||!r.ok){ el('b-kq').innerHTML='<div class="bao-loi">'+chu(r&&r.loi)+'</div>'; return; }
			if(!coso){
				var s1=el('b-coso');
				s1.innerHTML='<option value="">— chọn cơ sở —</option>'+r.coSo.map(function(x){
					return '<option value="'+thoat(x.ten)+'">'+thoat(x.ten)+' ('+x.so+' lượt)</option>';
				}).join('');
				if(!r.coSo.length) el('b-kq').innerHTML='<div class="bao-loi">Chưa có dữ liệu chấm công nào. Sang tab <b>Nạp dữ liệu</b> để nạp CSV.</div>';
			}
			var s2=el('b-thang');
			s2.innerHTML='<option value="">— tháng —</option>'+(r.thang||[]).map(function(t){
				return '<option value="'+thoat(t)+'">'+thoat(t)+'</option>'; }).join('');
			if(typeof xong==='function') xong(r);
		});
	}

	function taiBang(){
		var cs=el('b-coso').value, th=el('b-thang').value;
		if(!cs||!th){ BANG=null; el('b-kq').innerHTML=''; el('b-in').style.display='none'; return; }
		el('b-kq').innerHTML='<p class="chan">Đang đọc…</p>';
		goiB('vcg_bang', {co_so:cs, thang:th}, function(r){
			if(!r||!r.ok){ el('b-kq').innerHTML='<div class="bao-loi">'+chu(r&&r.loi)+'</div>'; return; }
			BANG=r; veBang();
		});
	}

	function veBang(){
		if(!BANG){ el('b-kq').innerHTML=''; return; }
		var q=(el('b-tim').value||'').trim().toLowerCase();
		var nam=+BANG.thang.slice(0,4), thg=+BANG.thang.slice(5,7);
		var ngays=[];
		for(var d=1; d<=BANG.so_ngay; d++){
			var dd=(d<10?'0':'')+d;
			ngays.push({s:BANG.thang+'-'+dd, d:d, thu:new Date(nam, thg-1, d).getDay()});
		}
		var THU=['CN','T2','T3','T4','T5','T6','T7'];

		var h='<div class="bcw"><table class="bc"><thead><tr><th class="ten">Nhân viên</th>';
		ngays.forEach(function(x){
			h+='<th'+(x.thu===0?' style="background:#eef1f5"':'')+'>'+x.d+'<br><span style="font-weight:400;font-size:10px">'+THU[x.thu]+'</span></th>';
		});
		h+='<th>Ngày công</th><th>Tổng giờ</th></tr></thead><tbody>';

		var soNguoi=0, tongCaThang=0;
		for(var ma in BANG.nguoi){
			var ten=BANG.nguoi[ma]||'';
			if(q && ten.toLowerCase().indexOf(q)<0 && ma.toLowerCase().indexOf(q)<0) continue;
			soNguoi++;
			var oc=BANG.o[ma]||{}, ngayCong=0, tong=0;
			h+='<tr><td class="ten">'+thoat(ten)+'<br><span style="color:#94a3b8;font-size:10.5px">'+thoat(ma)+'</span></td>';
			ngays.forEach(function(x){
				var o=oc[x.s];
				if(!o){ h+='<td class="o trong'+(x.thu===0?' cn':'')+'">·</td>'; return; }
				ngayCong++;
				var g=soGiay(o.vao,o.ra);
				if(g!==null) tong+=g;
				/* 🔴 THIẾU GIỜ RA phải NHÌN THẤY. Ô chỉ có giờ vào nghĩa là người ta quên bấm ra
				   — để nó trông y hệt ô đủ là tới cuối tháng mới phát hiện, lúc đó không ai nhớ
				   hôm đó về mấy giờ nữa. */
				var thieu=(o.vao!==null&&o.ra===null);
				h+='<td class="o'+(thieu?' thieu':'')+(x.thu===0?' cn':'')+'" title="'+thoat(x.s)+'">'
					+ (o.vao!==null?hhmm(o.vao):'—')
					+ '<br><span style="font-size:10.5px;color:'+(thieu?'#8a6100':'#64748b')+'">'
					+ (o.ra!==null?hhmm(o.ra):'thiếu ra')+'</span></td>';
			});
			tongCaThang+=tong;
			h+='<td class="o tong">'+ngayCong+'</td><td class="o tong">'+thoat(gioNgan(tong))+'</td></tr>';
		}
		h+='</tbody></table></div>';
		if(!soNguoi) h='<div class="bao-loi">Không có ai khớp bộ lọc.</div>';

		el('b-kq').innerHTML=h
			+'<div class="bc-tt"><div><b>'+soNguoi+'</b> người</div>'
			+'<div><b>'+thoat(gioNgan(tongCaThang)||'0h')+'</b> tổng giờ cả bảng</div>'
			+'<div>Cơ sở <b>'+thoat(BANG.co_so)+'</b> · tháng <b>'+thoat(BANG.thang)+'</b></div></div>';
		el('b-in').style.display=soNguoi?'':'none';
	}

	function noiBang(){
		if(!el('b-coso')) return;
		el('b-coso').addEventListener('change', function(){
			napChon(el('b-coso').value, function(){ taiBang(); });
		});
		el('b-thang').addEventListener('change', taiBang);
		el('b-tim').addEventListener('input', veBang);
		el('b-in').addEventListener('click', function(){ window.print(); });
		napChon('');
	}

	/* Đổi màn. Giữ cả hai màn trong DOM, chỉ ẩn/hiện — nạp xong tải lại trang là mất luôn kết
	   quả nạp vừa hiện ra. */
	function noiTab(){
		Array.prototype.forEach.call(document.querySelectorAll('.tab'), function(b){
			b.addEventListener('click', function(){
				var m=b.getAttribute('data-man');
				Array.prototype.forEach.call(document.querySelectorAll('.tab'), function(x){
					x.classList.toggle('dang', x===b); });
				el('man-bang').style.display=(m==='bang')?'':'none';
				el('man-nap').style.display=(m==='nap')?'':'none';
			});
		});
	}
	function gio(g){
		if (g === null || g === undefined) return '—';
		var h = Math.floor(g/3600), p = Math.floor((g%3600)/60);
		return (h<10?'0':'')+h + ':' + (p<10?'0':'')+p;
	}

	function noi(loai){
		var chon = document.getElementById('tep-'+loai);
		var nhan = document.getElementById('nhan-'+loai);
		var bXem = document.getElementById('xem-'+loai);
		var bNap = document.getElementById('nap-'+loai);
		var kq   = document.getElementById('kq-'+loai);
		if (!chon) return;
		var mac_dinh = nhan.querySelector('.ten-tep').textContent;

		chon.addEventListener('change', function(){
			var f = chon.files[0];
			bXem.disabled = !f;
			bNap.style.display = 'none';
			kq.innerHTML = '';
			/* Đổi chữ qua một thẻ có tên, KHÔNG qua childNodes[2]. Đếm nút con là hễ ai thêm
			   một khoảng trắng trong HTML là chỉ số lệch, và nhãn im lặng không đổi chữ. */
			var ten = nhan.querySelector('.ten-tep');
			if (f){ nhan.classList.add('co'); ten.textContent = f.name; }
			else  { nhan.classList.remove('co'); ten.textContent = mac_dinh; }
		});

		function goi(viec, xong){
			var f = chon.files[0];
			if (!f) return;
			var d = new FormData();
			d.append('action', viec);
			d.append('nonce', NONCE);
			d.append('loai', loai);
			d.append('tep', f);
			var oCS = document.getElementById('co-so-cs');
			if (loai === 'cs' && oCS && oCS.value.trim()){ d.append('co_so', oCS.value.trim()); }
			fetch(AJAX, { method:'POST', body:d, credentials:'same-origin' })
				.then(function(r){ return r.json(); })
				.then(xong)
				.catch(function(){
					kq.innerHTML = '<div class="bao-loi">Không gọi được máy chủ. Kiểm tra mạng rồi thử lại.</div>';
				});
		}

		bXem.addEventListener('click', function(){
			bXem.disabled = true; bXem.textContent = 'Đang đọc…';
			goi('vcg_xem_truoc', function(r){
				bXem.disabled = false; bXem.textContent = 'Xem trước';
				if (!r.ok){
					kq.innerHTML = '<div class="bao-loi">' + chu(r.loi) + '</div>';
					bNap.style.display = 'none';
					/* Chặn xong thì ĐƯA NGƯỜI TA TỚI CHỖ SỬA, đừng bắt tự mò. */
					var o = document.getElementById('co-so-cs');
					if (loai === 'cs' && o && !o.value.trim()){ o.classList.add('can'); o.focus(); }
					return;
				}
				var o2 = document.getElementById('co-so-cs');
				if (o2){ o2.classList.remove('can'); }
				kq.innerHTML = (loai === 'nv') ? tomNV(r) : tomCS(r);
				/* Chỉ hiện nút nạp SAU khi đã xem trước. Người ta phải nhìn con số một lần
				   trước khi ghi — nạp nhầm tệp thì không có nút hoàn tác. */
				bNap.style.display = '';
			});
		});

		bNap.addEventListener('click', function(){
			bNap.disabled = true; bNap.textContent = 'Đang ghi…';
			goi('vcg_nap', function(r){
				bNap.disabled = false; bNap.textContent = 'Xác nhận nạp';
				if (!r.ok){ kq.innerHTML = '<div class="bao-loi">' + chu(r.loi) + '</div>'; return; }
				kq.innerHTML = (loai === 'nv')
					? '<div class="bao-ok">Xong. Thêm mới <b>' + r.nguoi_them + '</b> người · cập nhật <b>'
						+ r.nguoi_sua + '</b> · gán cơ sở: đọc được <b>'
						+ (r.gan_doc === undefined ? r.gan_them : r.gan_doc)
						+ '</b>, thêm mới <b>' + r.gan_them + '</b>'
						+ (r.gan_doc !== undefined && r.gan_doc > r.gan_them
							? ' (<b>' + (r.gan_doc - r.gan_them) + '</b> cặp đã có sẵn trong bảng)'
							: '')
						+ '.</div>'
					: '<div class="bao-ok">Xong — cơ sở <b>' + chu(r.co_so) + '</b>. Thêm <b>' + r.them
						+ '</b> lượt · nới rộng <b>' + r.noi + '</b> · giữ nguyên <b>' + r.giu
						+ '</b>.</div>' + veCanhBao(r.canh_bao);
				bNap.style.display = 'none';
			});
		});
	}

	function tomCS(r){
		var h = '<div class="tom"><div class="dau">Xem trước — cơ sở ' + chu(r.co_so) + '</div><div class="than">';
		h += '<div class="so">'
			+ '<div><b>' + r.luot + '</b><span>lượt chấm công</span></div>'
			+ '<div><b>' + r.nguoi + '</b><span>người</span></div>'
			+ '<div><b>' + r.khoi + '</b><span>khối tháng</span></div>'
			+ '<div><b>' + chu(r.thang.join(', ') || '—') + '</b><span>tháng</span></div>'
			+ '</div>';
		if (r.mau && r.mau.length){
			h += '<table><tr><th>Ngày</th><th>Mã NV</th><th>Họ tên</th><th>Vào</th><th>Ra</th></tr>';
			r.mau.forEach(function(x){
				h += '<tr><td>' + chu(x.ngay) + '</td><td>' + chu(x.ma_nv) + '</td><td>' + chu(x.ho_ten)
				  + '</td><td>' + gio(x.vao) + '</td><td>' + gio(x.ra) + '</td></tr>';
			});
			h += '</table>';
		}
		h += veCanhBao(r.canh_bao);
		return h + '</div></div>';
	}

	/* Bảng CHỖ CẦN SỬA TRONG SHEET.
	   Đây không phải lỗi nạp — tệp vẫn nạp được. Đây là chỗ hỏng trong sheet gốc mà từ trước
	   tới giờ không ai thấy, vì bản cũ lặng lẽ bỏ qua. Ba loại, xếp theo mức nặng:
	     mot_nguoi_nhieu_ma  nặng nhất — công của một người bị chia ra thành hai người
	     o_nhieu_moc         ô giờ gõ tay hai mốc; máy tự xử được nhưng gốc vẫn nên sửa
	     ma_so_tran          ID chưa cấp mã chuẩn, dễ đụng nhau giữa các cơ sở
	     gio_la              ô có chữ số mà không đọc ra giờ nào — chỗ này thì MẤT dữ liệu thật */
	function veCanhBao(ds){
		if (!ds || !ds.length){ return ''; }
		var ten = {
			mot_nguoi_nhieu_ma: 'Một người mang nhiều mã',
			o_nhieu_moc:        'Ô giờ có hai mốc',
			ma_so_tran:         'Mã NV là số trần',
			gio_la:             'Ô giờ không đọc được'
		};
		var h = '<div class="canh"><div class="canh-dau">Nạp được, nhưng ' + ds.length
		      + ' chỗ nên sửa trong Sheet gốc</div><table>'
		      + '<tr><th>Chỗ</th><th>Mã NV</th><th>Họ tên</th><th>Ô</th></tr>';
		ds.forEach(function(c){
			h += '<tr><td>' + chu(ten[c.kieu] || c.kieu) + '</td><td>' + chu(c.ma_nv || '')
			  + '</td><td>' + chu(c.ho_ten || '') + '</td><td>' + chu(c.o || (c.ngay || '')) + '</td></tr>';
		});
		return h + '</table><p class="chan">Sửa ở Sheet rồi xuất lại CSV và nạp đè — luật nới rộng '
		     + 'làm nạp lại bao nhiêu lần cũng ra một kết quả.</p></div>';
	}

	function tomNV(r){
		var h = '<div class="tom"><div class="dau">Xem trước — hồ sơ nhân viên</div><div class="than">';
		h += '<div class="so">'
			+ '<div><b>' + r.nguoi + '</b><span>người</span></div>'
			+ '<div><b>' + r.gan + '</b><span>lượt gán cơ sở</span></div>'
			+ '<div><b>' + r.don_vi + '</b><span>đơn vị</span></div>'
			+ '</div>';
		if (r.gan > r.nguoi){
			h += '<p class="chan">' + (r.gan - r.nguoi) + ' lượt gán nhiều hơn số người — '
			  + 'đó là những người làm ở nhiều cơ sở. Giữ đủ cả.</p>';
		}
		if (r.mau && r.mau.length){
			h += '<table><tr><th>Mã NV</th><th>Họ tên</th><th>Phòng ban</th><th>Hợp đồng</th></tr>';
			r.mau.forEach(function(x){
				h += '<tr><td>' + chu(x.ma_nv) + '</td><td>' + chu(x.ho_ten) + '</td><td>'
				  + chu(x.phong_ban) + '</td><td>' + chu(x.loai_hop_dong) + '</td></tr>';
			});
			h += '</table>';
		}
		return h + '</div></div>';
	}

	noi('cs');
	noi('nv');
	noiTab();
	noiBang();
})();
</script>
</body>
</html>
