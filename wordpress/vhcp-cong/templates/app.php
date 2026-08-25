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
	.khoa{opacity:.55}
</style>
</head>
<body>
<div class="bao">

	<h1>Chấm công — nạp dữ liệu</h1>
	<p class="phu">
		<?php if ( $vai ) : ?>
			Đang dùng: <b><?php echo esc_html( $nguoi['ten'] ? $nguoi['ten'] : $vai ); ?></b>
			· vai <b><?php echo esc_html( $vai ); ?></b>
		<?php else : ?>
			Chưa đăng nhập.
		<?php endif; ?>
	</p>

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
					return;
				}
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
						+ r.nguoi_sua + '</b> · thêm <b>' + r.gan_them + '</b> lượt gán cơ sở.</div>'
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
})();
</script>
</body>
</html>
