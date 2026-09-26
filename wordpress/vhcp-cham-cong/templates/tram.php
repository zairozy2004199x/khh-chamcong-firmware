<?php
/**
 * GIAO DIỆN TRẠM CHẤM CÔNG. Nhận $VHCC_TRAM_CFG từ VHCC_Tram::render().
 *
 * Trang này CỐ Ý không nạp theme: nhân viên mở bằng 3G ở cơ sở, một theme WordPress kéo theo
 * jQuery + font + slider là mười giây trắng màn trước khi thấy nút bấm.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$cfg = isset( $VHCC_TRAM_CFG ) ? $VHCC_TRAM_CFG : array( 'cong' => '', 'ver' => '' );
?><!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex,nofollow">
<title>Chấm công — K&amp;H</title>
<?php
/* Manifest + biểu tượng + đăng ký worker. Đặt TRƯỚC <style> để iPhone đọc được `theme-color`
   ngay từ mảnh HTML đầu tiên — muộn hơn thì nó nháy một khung trắng rồi mới tối lại. Nội dung
   và lý do từng thẻ nằm ở `VHCC_PWA::the_head()`. */
VHCC_PWA::the_head();
?>
<style>
/* ══════════════════════════════════════════════════════════════════════════════════════════
 * BỘ ÁO CHUNG, MẶT SÁNG — cùng TÊN BIẾN, cùng BO GÓC, cùng NHỊP, và từ 17/09/2026 cùng cả MÀU.
 *
 * `tools/test/kiem-bo-ao-tron.php` canh bảy trang của cả nhà đi cùng một bộ. Sáu bo góc và
 * sáu bước nhịp phải khớp từng chữ số với các trang kia, nếu không thì mở màn này rồi mở
 * bảng công là thấy hai phần mềm khác nhau.
 *
 * ══════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 MÀN NÀY TỪNG LÀ MẶT TỐI. ĐỔI SANG SÁNG LÀ MỘT ĐÁNH ĐỔI CÓ Ý THỨC, KHÔNG PHẢI SƠ SUẤT
 * ══════════════════════════════════════════════════════════════════════════════════════════
 * Lý do cũ, vẫn đúng về mặt vật lý: màn này mở camera soi mặt để chấm công và chạy cả ca đêm.
 * Nền sáng thì màn hình điện thoại hắt vào mặt người đang đứng chụp, ảnh bệt hơn — mà đúng
 * tấm ảnh ấy là thứ quản lý dùng để đối chiếu khi tranh cãi.
 *
 * Anh Thắng 17/09/2026 chốt SÁNG TOÀN BỘ, đổi lại được sự đồng nhất với bảy trang còn lại.
 * Ghi lại đây để người sau đọc được cả hai vế, thay vì thấy một quyết định trần trụi rồi
 * đoán là ai đó dán nhầm bảng màu.
 *
 * ⚠️ THỨ CẦN THEO DÕI, KHÔNG PHẢI THỨ CẦN SỢ. Nếu về sau ảnh chấm công ca đêm bị phàn nàn là
 *    mờ hoặc bệt mặt, thì đây là chỗ đầu tiên phải nghi — không phải camera, không phải mạng.
 *    Cách chữa nhẹ nhất mà không quay lại nền tối cho cả trang: cho RIÊNG màn chụp (`#mChup`)
 *    một bảng màu tối, vì chỉ năm giây đứng chụp mới có vấn đề hắt sáng. Phép thử ở mục 5 của
 *    `kiem-bo-ao-tron.php` đã đổi theo hướng đó — nó canh màn chụp, không canh cả trang.
 *
 * ⚠️ TOKEN KHAI NGAY TẠI ĐÂY, KHÔNG GOM VÀO TỆP DÙNG CHUNG. Màn này và bảng công có MƯỜI MỘT
 *    lớp trùng tên mà khác nghĩa — `.an` ở đây là `display:none`, ở bảng công là *ẩn với mắt
 *    nhưng trình đọc màn hình vẫn đọc*; rồi `.bao` `.the` `.mo` `.hang` `.luoi` `.chinh`
 *    `.phu` `.trong` `.vang` `.ct`. Chúng không bao giờ ở chung một trang nên để yên là đúng;
 *    gom hai bộ luật vào một chỗ mới là mười một lớp đè nhau.
 * ══════════════════════════════════════════════════════════════════════════════════════════ */
:root{
	/* --- màu: mặt SÁNG, khớp từng mã với bảng công và ba trang Chi phí --- */
	--nen:#f9f8f6; --the:#ffffff; --nen-2:#f4f1ec;
	--vien:#f0e9e1; --vien-dam:#e2d6c7;
	--chu:#171417; --chu-dam:#0c1754; --chu-mo:#8c8781;
	--nhan:#2545ff; --nhan-dam:#1a34c9; --nhan-nhat:#eaebf8;
	--do:#e7000b;
	/* Chỉ dùng ở một chỗ (dòng nhắc "cần gõ PIN riêng" trên ô POSH), nhưng vẫn KHAI chứ không
	   dán thẳng mã màu vào luật: `kiem-bo-ao-tron.php` mục 2 bắt đúng lỗi này, và nó bắt đúng —
	   một hex dán tay là chỗ đầu tiên lệch khỏi bộ áo chung khi ai đó đổi bảng màu. Mã lấy từ
	   `VHCC_Web::css()`, cùng một thứ vàng với bảy trang kia. */
	--vang-dam:#b45309;
	/* Bốn nền nhạt của ô ứng dụng. KHAI chứ không dán hex vào từng luật, cùng lý do như
	   `--vang-dam` ngay trên: bốn mã này lấy từ `VHCC_Web::css()`, nên đổi bảng màu ở đó là
	   đổi được cả đây. Dán tay bốn hex là bốn chỗ phải nhớ sửa, và sẽ quên. */
	--luc-nhat:#f0fdf4; --vang-nhat:#fffbeb; --tim-nhat:#f5f3ff; --cam-nhat:#fff7ed;
	/* Xanh "đúng" của màn đăng nhập (dấu ✔ khi PIN đúng) — cùng mã `--luc` của `VHCC_Web::css()`. */
	--luc:#16a34a;
	/* --- hình: SÁU con số phải khớp từng chữ số với sáu trang kia --- */
	--d1:4px; --d2:8px; --d3:12px; --d4:16px; --d5:20px; --d6:24px;
	--bo-the:16px; --bo-nut:18px; --bo-o:10px; --bo-o-bang:6px; --bo-nho:8px; --bo-badge:16px;
	/* --- chiều sâu: bóng đổ ĐEN, không phải navy loãng. Trên nền tối thì navy loãng không
	       thấy gì; thứ tách được hai mặt phẳng tối là một vùng đen sâu hơn. --- */
	--bong:0 1px 2px rgba(0,0,0,.40);
	--bong-2:0 4px 14px rgba(0,0,0,.50);
	--bong-3:0 12px 32px rgba(0,0,0,.60);
}
*{box-sizing:border-box}
html,body{margin:0;padding:0}
/* Nền 3D mặt tối: cùng ba vầng sáng như bảng công, chỉ đậm hơn để đọc ra trên nền tối.
   `fixed` vì cùng lý do — danh sách lượt chấm cuộn dài, nền chạy theo là cả màn trôi. */
body{font:15px/1.55 -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;
	color:var(--chu);-webkit-text-size-adjust:100%;
	background:
	  radial-gradient(40rem 30rem at 15% -10%,rgba(56,189,248,.16),transparent 62%),
	  radial-gradient(36rem 28rem at 95% 8%,rgba(124,58,237,.14),transparent 64%),
	  radial-gradient(38rem 26rem at 50% 110%,rgba(8,145,178,.12),transparent 60%),
	  var(--nen);
	background-attachment:fixed}
/* Đáy chừa 76px cho thanh tab + khoảng an toàn. Thiếu chỗ này thì nút Thoát và dòng phiên
   bản nằm khuất dưới thanh, và không cuộn xuống thêm được nữa. */
.bao{max-width:520px;margin:0 auto;padding:14px 14px calc(76px + env(safe-area-inset-bottom))}
/* Màn đăng nhập và mấy màn phủ không có thanh tab — trả lại khoảng đáy bình thường. */
.mn .bao,#mVao.bao,#mQuen.bao{padding-bottom:calc(28px + env(safe-area-inset-bottom))}
h1{font-size:18px;margin:0 0 2px}
.mo{color:var(--chu-mo);font-size:12.5px;margin:0 0 14px}
.the{background:var(--the);border:1px solid var(--vien);border-radius:var(--bo-the);
	padding:var(--d4);margin:0 0 var(--d3);box-shadow:var(--bong)}
label{display:block;font-size:12.5px;color:var(--chu-mo);margin:0 0 5px}
/* Ô TÍCH. Cả dòng chữ là vùng chạm (label `for=`), không phải mỗi cái ô vuông 16px — ngón cái
   không trúng ô vuông ấy, và trượt một nhát thì người ta tưởng máy không nhận. */
/* NÚT PHÁ. Đỏ đặc, không phải viền đỏ — nó phải khác hẳn mọi nút khác trên màn, kể cả khi
   nhìn lướt. ⚠️ Đỏ là để BÁO, không phải để cấm: việc này hợp lệ và có người cần làm hằng
   tuần, nên nút vẫn nằm chỗ dễ thấy chứ không giấu sau một menu. */
/* DÒNG BẤM ĐƯỢC trong bảng công cơ sở. Vùng chạm là cả dòng (cao ~44px nhờ đệm của ô), nên
   không cần nút riêng — xem chú thích ở `napCongCH()`. */
/* Dòng ĐÃ KHOÁ (ngày đã qua, cửa hàng trưởng hết hạn sửa). Mờ đủ để đọc ra là "khác", và
   KHÔNG có con trỏ bấm — nói "đây không phải nút" trước cả khi người ta chạm vào. */
tr.ng-khoa{opacity:.55}
tr.hang-mo{cursor:pointer}
tr.hang-mo:active{background:var(--nhan-nhat)}
tr.hang-mo td{padding-top:12px;padding-bottom:12px}
.mui{color:var(--chu-mo);font-weight:700;margin-left:2px}
button.nguy{background:#dc2626;border-color:#dc2626;color:#fff;font-weight:700}
button.nguy:active{background:#b91c1c}
label.tich{display:flex;align-items:center;gap:9px;min-height:44px;margin:0;
	font-size:13.5px;color:var(--chu);cursor:pointer}
label.tich input{flex:0 0 auto;width:19px;height:19px;margin:0}
/* Hàng hai ô nhập cạnh nhau trong màn sửa — gãy xuống một cột ở máy rất hẹp. */
#mNguoi .hang{gap:10px;flex-wrap:wrap}
#mNguoi .hang .fldx{min-width:120px}
/* Dòng giờ khác: tên việc rộng, số giờ hẹp — đúng nhịp của biểu mẫu bên trang quản trị. */
.dong-gio{display:flex;gap:8px;margin:0 0 8px}
.dong-gio input.viec{flex:1;min-width:0}
.dong-gio input.gio{flex:0 0 84px}
.dong-gio button{flex:0 0 42px}
/* Lưới khoản tiền: hai cột, nhãn nhỏ trên ô. Chín khoản xếp một cột là cuộn mãi không hết. */
.luoi-khoan{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
.luoi-khoan .fldx{margin:0}
/* 🔴 `textarea` PHẢI NẰM TRONG LUẬT NÀY. Bỏ sót nó thì trình duyệt dùng kiểu mặc định: phông
   monospace, cỡ chữ nhỏ, và KHÔNG rộng hết thẻ — một ô con con nép bên trái giữa một tấm thẻ
   trắng. Anh Thắng 21/09/2026, ảnh chụp khung chat: *"giao diện bị xấu"*. Trước đó cả trang
   không có `textarea` nào nên chỗ sót này chưa bao giờ lộ ra. */
input,select,textarea{width:100%;padding:12px 13px;font-size:16px;border-radius:var(--bo-o);
	border:1px solid var(--vien-dam);background:var(--nen);color:var(--chu);font-family:inherit}
input:focus,select:focus,textarea:focus{outline:none;border-color:var(--nhan);
	box-shadow:0 0 0 3px rgba(56,189,248,.22)}
/* Kéo cao được, KHÔNG kéo ngang: kéo ngang thì ô thò ra khỏi thẻ và cả bố cục vỡ. */
textarea{resize:vertical;min-height:52px;line-height:1.45}
/* ══════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 Ô NGÀY / GIỜ TRÊN iOS TRÀN RA NGOÀI THẺ.
 *
 * Anh Thắng 18/09/2026, hai ảnh chụp iPhone (màn Xin phép đi trễ và Xin nghỉ): *"Lệch ô"* — ô
 * NGÀY thò hẳn ra khỏi mép phải tấm thẻ trắng, trong khi ô Lý do ngay dưới thì vừa khít.
 *
 * Vì sao: `width:100%` KHÔNG thắng được chiều rộng nội tại của ô ngày trên Safari. Safari dựng
 * `input[type=date]` bằng một hộp flex chứa mấy ô con (ngày / tháng / năm) và đặt cho nó một
 * `min-width` tối thiểu đủ chứa chuỗi dài nhất — với định dạng tiếng Việt là *"ngày 18 thg 9,
 * 2026"*. Khi `min-width` nội tại lớn hơn 100% của thẻ cha, nó thắng, và ô đè ra ngoài.
 *
 * `box-sizing:border-box` (đã có ở `*`) KHÔNG chữa được chuyện này: nó chỉ tính padding vào
 * chiều rộng, không hạ được chiều rộng tối thiểu.
 *
 * Chữa bằng đúng hai thứ:
 *   · `min-width:0` — gỡ cái sàn nội tại, cho `width:100%` có hiệu lực;
 *   · `-webkit-appearance:none` — bỏ vỏ native, không thì Safari tự đặt lại kích thước.
 * Thêm `max-width:100%` làm chốt chặn cuối: dù ngày nào Safari đổi cách dựng, ô cũng không
 * tràn được ra khỏi thẻ nữa.
 *
 * ⚠️ CHỈ ĐỘNG TỚI Ô NGÀY/GIỜ. Gỡ `appearance` của mọi ô là mất luôn mũi tên của `<select>` —
 *    người dùng không biết ô ấy bấm được.
 * ══════════════════════════════════════════════════════════════════════════════════════════ */
input[type=date],input[type=month],input[type=time],input[type=datetime-local]{
	min-width:0;max-width:100%;-webkit-appearance:none;appearance:none}
/* Nút nhún khi bấm — cùng nhịp với bảng công, để hai màn nói một thứ tiếng. Ở đây nó còn
   đáng giá hơn: người bấm đang cầm điện thoại một tay, cần biết ngay là đã trúng nút. */
button{font-family:inherit;font-size:15px;border:0;border-radius:var(--bo-nut);padding:13px var(--d4);
	background:var(--nen-2);color:var(--chu);cursor:pointer;box-shadow:var(--bong);
	transition:transform .12s ease,box-shadow .12s ease}
button:hover:not(:disabled){transform:translateY(-1px);box-shadow:var(--bong-2)}
button:active:not(:disabled){transform:translateY(0);box-shadow:var(--bong)}
button:disabled{opacity:.5;cursor:not-allowed}
.chinh{background:var(--nhan);color:#04283a;font-weight:700;width:100%}
.to{font-size:19px;padding:20px 16px;font-weight:800;letter-spacing:.4px}
.phu{background:transparent;border:1px solid var(--vien-dam);color:var(--chu-mo);box-shadow:none}
.hang{display:flex;gap:9px}
.hang>*{flex:1}
/* ⚠️ BA LỚP NÀY SÓT LẠI TỪ BẢN NỀN TỐI — sửa 17/09/2026.
   Lúc trang chuyển sang nền sáng, ba lớp báo trạng thái vẫn giữ nền tối (#7f1d1d, #064e3b,
   #422006). Trên nền kem chúng thành ba mảng nâu/đỏ sẫm — vẫn ĐỌC ĐƯỢC nên không ai báo lỗi,
   nhưng nhìn như dán nhầm từ trang khác sang. Đây đúng kiểu lỗi mà đổi bảng màu hay bỏ sót:
   thứ chỉ hiện ra trong mấy trạng thái, mà lúc thử thì không ai cố tình làm cho nó lỗi.

   ⚠️ NGOẠI LỆ: bên TRONG thẻ chấm công (nền tối) thì ba lớp này lại phải tối — xem luật
      `.the-cham .dong` ở dưới. */
.dong{background:#fef2f2;border:1px solid #fecaca;color:#991b1b;border-radius:var(--bo-nho);
	padding:11px 13px;margin:10px 0;font-size:13.5px}
.xanh{background:var(--luc-nhat);border:1px solid #bbf7d0;color:#166534;border-radius:var(--bo-nho);
	padding:11px 13px;margin:10px 0;font-size:13.5px}
.vang{background:var(--vang-nhat);border:1px solid #fde68a;color:var(--vang-dam);
	border-radius:var(--bo-nho);padding:11px 13px;margin:10px 0;font-size:13px}
/* Trong thẻ tối thì ngược lại — nền nhạt trên nền tối là một mảng chói giữa màn. */
.the-cham .dong{background:rgba(127,29,29,.5);border-color:#b91c1c;color:#fecaca}
.the-cham .xanh{background:rgba(6,78,59,.5);border-color:#059669;color:#bbf7d0}
.the-cham .vang{background:rgba(66,32,6,.6);border-color:#a16207;color:#fde68a}
/* ══════════════════════════════════════════════════════════════════════════════════════════
 * THẺ CHẤM CÔNG — mảng tối duy nhất của trang sáng.
 *
 * ⚠️ MÃ MÀU DÁN TAY, KHÔNG DÙNG TTOKEN. Đây là ngoại lệ có chủ ý và là ngoại lệ DUY NHẤT:
 *    các biến `--nen` `--the` `--chu` của trang nay là bảng SÁNG, mà thẻ này cố ý tối — dùng
 *    chúng thì ra một thẻ trắng trên nền trắng. Khai thêm một bộ biến tối chỉ cho một thẻ là
 *    sáu tên biến nữa trong :root mà sáu trang kia không có, và `kiem-bo-ao-tron.php` mục 1
 *    canh đúng bộ tên ấy. Nên: dán thẳng, và ghi rõ ở đây để người sau biết là cố ý.
 * ══════════════════════════════════════════════════════════════════════════════════════════ */
.the-cham{background:linear-gradient(160deg,#132038 0%,#1b2b4a 55%,#16243d 100%);
	border-radius:var(--bo-the);padding:var(--d5) var(--d4) var(--d6);margin:0 0 var(--d3);
	text-align:center;position:relative;overflow:hidden}
/* Vầng sáng rất loãng sau đồng hồ — cùng thủ pháp "nền 3D" của sáu trang kia, chỉ đổi tông. */
.the-cham::before{content:'';position:absolute;top:-70px;left:50%;transform:translateX(-50%);
	width:280px;height:280px;border-radius:50%;
	background:radial-gradient(circle,rgba(201,168,76,.13) 0%,transparent 68%);pointer-events:none}
.cc-ngay{color:#93a3bd;font-size:12.5px;margin:0 0 2px;position:relative}
.cc-gio{font-variant-numeric:tabular-nums;font-size:44px;font-weight:800;letter-spacing:1px;
	margin:2px 0 0;color:#fff;position:relative;line-height:1.05}
.cc-nhan{color:#93a3bd;font-size:12px;margin:4px 0 0;position:relative}

/* NÚT TRÒN.
   🔴 160px là con số có lý do: đây là nút người ta bấm bằng một tay, thường đang cầm thêm
      thứ khác, đôi khi trong ánh sáng kém ở cơ sở. Nút chữ nhật cao 48px đủ chuẩn vùng chạm
      nhưng vẫn phải NHÌN mới bấm trúng; một vòng tròn to giữa màn thì bấm được không cần nhìn.
   ⚠️ Không dùng `aspect-ratio` — Safari cũ trên máy nhân viên bỏ qua nó và nút thành hình
      thuôn. Khai thẳng width + height. */
.nut-tron{width:160px;height:160px;border-radius:50%;margin:var(--d4) auto 0;
	display:flex;flex-direction:column;align-items:center;justify-content:center;gap:5px;
	border:3px solid #C9A84C;background:rgba(201,168,76,.12);cursor:pointer;
	font:inherit;position:relative;
	box-shadow:0 0 0 9px rgba(201,168,76,.055), 0 0 0 18px rgba(201,168,76,.028);
	transition:transform .12s, background .12s}
.nut-tron:active{transform:scale(.96);background:rgba(201,168,76,.22)}
.nut-tron i{font-style:normal;font-size:33px;line-height:1}
.nut-tron span{color:#C9A84C;font-size:13px;font-weight:800;letter-spacing:1.1px}
.nut-tron:disabled{opacity:.45;border-color:#455873;box-shadow:none;cursor:default}
.nut-tron:disabled span{color:#93a3bd}
/* Đã chấm vào, đang chờ ra: đổi sang xanh lá. Cùng nghĩa với tông trạng thái của sáu trang
   kia — xanh lá = xong, không phải trang trí. */
.nut-tron.dang-lam{border-color:#4ade80;background:rgba(74,222,128,.14);
	box-shadow:0 0 0 9px rgba(74,222,128,.06), 0 0 0 18px rgba(74,222,128,.03)}
.nut-tron.dang-lam span{color:#4ade80}
/* Trạng thái và báo lỗi nằm TRONG thẻ tối nên phải đổi chữ, không thì chữ tối trên nền tối. */
.the-cham #trangThai,.the-cham #baoCham{color:#e7ecf5;position:relative;margin-top:var(--d3)}
.the-cham #trangThai:empty,.the-cham #baoCham:empty{margin-top:0}

.dhho{font-variant-numeric:tabular-nums;font-size:38px;font-weight:800;letter-spacing:1px;
	text-align:center;margin:2px 0 0;color:var(--chu-dam)}
.dngay{text-align:center;color:var(--chu-mo);font-size:12.5px;margin:0 0 2px}
.nhan{display:inline-block;font-size:11px;padding:2px var(--d2);border-radius:var(--bo-badge);
	background:var(--nen-2);color:var(--chu-mo);margin-left:6px;vertical-align:2px}
table{width:100%;border-collapse:collapse;font-size:13px}
th,td{padding:7px 6px;border-bottom:1px solid var(--vien);text-align:left}
th{color:var(--chu-mo);font-weight:600;font-size:11.5px;text-transform:uppercase;letter-spacing:.4px}
td.g{font-variant-numeric:tabular-nums}
.trong{color:var(--chu-mo)}
video,canvas.xem{width:100%;border-radius:var(--bo-the);background:#000;display:block}
.bando{position:relative;width:100%;height:200px;border:1px solid var(--vien);border-radius:var(--bo-the);
	background:var(--the);overflow:hidden;margin:10px 0 0}
.bando .luoi{position:absolute;left:50%;top:50%;width:768px;height:768px;
	display:grid;grid-template-columns:repeat(3,256px);grid-template-rows:repeat(3,256px)}
.bando .o{display:block;width:256px;height:256px;background:var(--the)}
.bando .cham{position:absolute;left:50%;top:50%;width:16px;height:16px;margin:-8px 0 0 -8px;
	border-radius:50%;background:#ef4444;border:3px solid #fff;box-shadow:0 0 0 2px rgba(0,0,0,.35)}
.bando .ghi{position:absolute;right:4px;bottom:2px;font-size:10px;color:#0f172a;
	background:rgba(255,255,255,.72);padding:0 5px;border-radius:var(--bo-o-bang)}
/* ⚠️ Thanh dính phải ĐỤC. Nền trang là gradient; để thanh trong suốt là ba vầng sáng chạy
   qua dưới chữ khi cuộn, và chữ trên nút nhoè theo từng nhịp cuộn. */
/* ══════════════════════════════════════════════════════════════════════════════════════════
 * THANH TAB DƯỚI ĐÁY
 *
 * 🔴 `bottom:0` KÈM `padding-bottom: env(safe-area-inset-bottom)`, KHÔNG PHẢI `bottom: env(…)`.
 *    Đặt `bottom` bằng khoảng an toàn thì thanh nổi lơ lửng, dưới nó là một dải nền trống —
 *    trên iPhone có thanh gạt về nhà thì dải ấy cao 34px và nhìn như lỗi hiển thị. Cách đúng
 *    là dán sát đáy rồi đẩy NỘI DUNG BÊN TRONG lên.
 *
 * ⚠️ z-index 8 — THẤP HƠN `.mn` (9). Màn chụp ảnh phải phủ kín thanh này: đang đứng trước ống
 *    kính mà bấm trúng một tab là thoát giữa chừng, ảnh không có mà giờ cũng không được ghi.
 * ══════════════════════════════════════════════════════════════════════════════════════════ */
#thanhTab{position:fixed;left:0;right:0;bottom:0;z-index:8;display:flex;
	background:var(--the);border-top:1px solid var(--vien);
	padding-bottom:env(safe-area-inset-bottom)}
.tab-nut{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;
	gap:3px;padding:9px 4px 8px;border:0;background:transparent;cursor:pointer;
	font:inherit;font-size:11px;font-weight:600;color:var(--chu-mo);
	/* 48px: ngưỡng vùng chạm của Apple. Thấp hơn là ngón cái trượt sang tab bên cạnh. */
	min-height:48px}
.tab-nut span{font-size:19px;line-height:1}
/* ══════════════════════════════════════════════════════════════════════════════════════════
 * THANH NĂM Ô — chỉ cửa hàng trưởng mới có.
 *
 * 🔴 CHÚ THÍCH CŨ (và `kiem-xin-phep.php`) CHỐT "ĐÚNG BỐN Ô", vì ô thứ năm làm chữ bị cắt cụt
 *    ở cả bốn ô kia trên điện thoại hẹp. Chốt ấy ĐÚNG và vẫn giữ: mặc định vẫn là bốn ô, và
 *    nhân viên thường không bao giờ thấy ô thứ năm.
 *
 * Nhưng cửa hàng trưởng thì có năm. 360px chia năm còn 72px/ô, mà "Công của tôi" ở 11px đã
 * ~66px cộng đệm là tràn. Nên khi — và CHỈ khi — ô thứ năm hiện ra, cả thanh thu chữ xuống
 * 10px và bớt đệm ngang. Thu cho MỌI NGƯỜI thì bốn ô của 95% người dùng xấu đi vì một ô mà
 * họ không có.
 *
 * ⚠️ VÙNG CHẠM `min-height:48px` KHÔNG ĐỔI. Thu là thu CHỮ, không thu chỗ để ngón tay chạm.
 * ══════════════════════════════════════════════════════════════════════════════════════════ */
#thanhTab.tab5 .tab-nut{font-size:10px;padding:9px 2px 8px}
#thanhTab.tab5 .tab-nut span{font-size:17px}
.tab-nut.dang{color:var(--nhan)}
.tab-o{animation:hienTab .18s ease-out}
@keyframes hienTab{from{opacity:0;transform:translateY(4px)}to{opacity:1;transform:none}}
/* ══════════════════════════════════════════════════════════════════════════════════════════
 * LƯỚI ỨNG DỤNG — BA CỘT, Ô NHỎ, CHIA NHÓM
 *
 * Anh Thắng 17/09/2026: *"tính năng nhiều thì ô chức năng nhỏ lại như này. Nhiều tính năng thì
 * tách phân loại theo từng tính năng"*, kèm ảnh một app chia mục WORKPLACE / HRM.
 *
 * 🔴 BẢN TRƯỚC LÀ HAI CỘT Ô TO, VÀ CHÚ THÍCH Ở ĐÂY TỪNG NÓI BA CỘT LÀ SAI. Lý do cũ: *"tên
 *    'Nộp báo cáo POSH' xuống ba dòng"*. Lý do ấy đúng khi mỗi ô còn mang theo một dòng mô tả
 *    và một dòng nhắc — lúc ấy ô to là bắt buộc. Nay mô tả và lời nhắc dời xuống một danh sách
 *    chú thích dưới lưới, ô chỉ còn ICON + TÊN, nên ba cột vừa đủ. Đổi bố cục thì phải đổi cả
 *    thứ nằm trong ô, không thì đúng là hỏng như chú thích cũ cảnh báo.
 *
 * ⚠️ VÙNG CHẠM VẪN PHẢI ≥48px. Icon 52px + nhãn nên mỗi ô cao hơn ngưỡng nhiều; `min-height`
 *    dưới đây là chốt chặn cho ô có nhãn một chữ.
 * ══════════════════════════════════════════════════════════════════════════════════════════ */
.nhom-ung{margin:0 0 18px}
.nhom-ung:last-child{margin-bottom:0}
.nhom-ten{font-size:11px;font-weight:800;letter-spacing:.7px;text-transform:uppercase;
	color:var(--chu-mo);margin:0 0 10px}
.luoi-ung{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px 4px}
.o-ung{display:flex;flex-direction:column;align-items:center;justify-content:flex-start;
	gap:7px;text-align:center;text-decoration:none;color:var(--chu);
	padding:8px 2px;border-radius:var(--bo-o);min-width:0;min-height:48px}
.o-ung:active{background:var(--nen-2)}
/* Ô MỞ MÀN TRONG TRẠM dựng bằng <button>, nên phải gỡ hết nét mặc định của nút — không gỡ thì
   nó có viền, có nền xám và chữ font hệ thống, đứng cạnh mấy ô <a> thành một ô trông hỏng. */
button.o-ung{border:0;background:transparent;font:inherit;color:var(--chu);cursor:pointer;
	-webkit-appearance:none;appearance:none;width:100%}
/* Ô CHƯA ĐƯỢC CẤP. Mờ đủ để đọc ra là "khác", KHÔNG mờ tới mức không đọc nổi: cả điểm của
   việc bày nó ra là để người ta biết thứ ấy tồn tại mà đi xin.
   ⚠️ `cursor:default` + không có :active — ô này không phải thẻ <a> nên vốn đã không bấm được;
      mấy dòng này chỉ để ngón tay chạm vào KHÔNG thấy phản hồi, tức là nói "đây không phải nút"
      trước cả khi người ta đọc chữ. */
.o-ung.o-khoa{opacity:.45;cursor:default}
.o-ung.o-khoa:active{background:transparent}
.o-ung.o-khoa .o-icon{filter:grayscale(1)}
.o-ung b{display:block;font-size:12px;font-weight:600;line-height:1.3;color:var(--chu-dam);
	/* Tên dài ("Gửi đơn đi trễ") phải xuống dòng trong cột, không đẩy toang lưới. */
	overflow-wrap:anywhere}
.o-icon{display:flex;align-items:center;justify-content:center;width:52px;height:52px;
	border-radius:15px;font-size:25px;flex:0 0 52px}
/* ⚠️ CHÚ THÍCH DỜI XUỐNG ĐÂY, KHÔNG BỎ ĐI. Ô nhỏ không chứa nổi câu "Xin quản lý đẩy sang Báo
   cáo cơ sở" — nhưng bỏ hẳn câu ấy thì ô mờ chỉ còn là một ô mờ, và người ta không biết phải
   đi hỏi ai. Một dòng dưới lưới vẫn đọc được, mà không làm phình cái ô. */
.ghi-ung{margin:14px 0 0;padding:12px 0 0;border-top:1px solid var(--vien)}
.ghi-ung p{margin:0 0 6px;font-size:11.5px;line-height:1.45;color:var(--chu-mo);text-align:left}
.ghi-ung p:last-child{margin-bottom:0}
.ghi-ung b{color:var(--chu-dam)}
.o-xanh{background:var(--nhan-nhat)}
.o-vang{background:var(--vang-nhat)}
.o-tim{background:var(--tim-nhat)}
.o-luc{background:var(--luc-nhat)}
.o-cam{background:var(--cam-nhat)}
/* Ô nhập hồ sơ: nhãn nhỏ trên, ô nhập dưới — cùng nhịp với .fld của sáu trang kia. */
.fldx{margin:0 0 var(--d3)}
.fldx label{display:block;font-size:11px;font-weight:700;color:var(--chu-mo);
	text-transform:uppercase;letter-spacing:.4px;margin:0 0 4px}
.fldx input{width:100%}

/* Chữ cái đầu tên, dùng ở tab Tôi. */
#chuCai{flex:0 0 46px;height:46px;border-radius:50%;background:var(--nhan-nhat);color:var(--nhan);
	display:flex;align-items:center;justify-content:center;font-size:16px;font-weight:800}
.thanh button{flex:1;padding:11px 8px;font-size:14px}
/* Chừa chỗ cho thanh dính, không thì nó che mất đầu khối vừa nhảy tới. */
/* ══════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 KHUNG XEM PHẢI LÀ MỘT CÁI HỘP CÓ SẴN KÍCH THƯỚC, KHÔNG PHẢI CÁI HỘP DO ẢNH QUYẾT ĐỊNH.
 *
 * Anh Thắng 20/09/2026, hai ảnh chụp iPhone: *"bấm chụp nó gom ảnh là sao vậy"* — hình trực
 * tiếp thì nhỏ, hẹp, có lề trắng hai bên; bấm Chụp ngay xong thì tấm ảnh nhảy ra to hết chiều
 * ngang và cắt mất trên dưới. Hai khung khác hẳn nhau, nên cái người ta canh KHÔNG PHẢI cái
 * người ta nhận.
 *
 * Nguyên do: bản trước đặt `max-height` thẳng lên `<video>` và `<canvas>` mà không cho chúng
 * một chiều cao. Với thẻ có kích thước gốc (video, canvas, img), khi `max-height` bị chạm thì
 * trình duyệt co luôn CHIỀU NGANG để giữ tỷ lệ gốc — nên hình trực tiếp teo lại thành một dải
 * hẹp giữa hai lề trắng. Còn `<canvas>` thì `width`/`height` đã khai bằng thuộc tính HTML nên
 * nó đi theo nhánh khác của cùng luật ấy: giữ nguyên chiều ngang, để `object-fit:cover` cắt
 * trên dưới. Cùng một dòng CSS, hai kết quả khác nhau — và không ai đoán được điều đó khi đọc.
 *
 * Nay `.khung` tự giữ chiều cao, hai thẻ con phủ kín nó bằng `height:100%`. Không còn chỗ nào
 * cho luật co-theo-tỷ-lệ chen vào, nên hình trực tiếp và ảnh vừa chụp CHẮC CHẮN cùng khung.
 *
 * ⚠️ `contain` CHỨ KHÔNG PHẢI `cover`, và đây là chỗ đáng cân nhắc nhất.
 *    `cover` nhìn đã mắt hơn (đầy khung, không lề đen) nhưng nó CẮT — mà khung xem lại là thứ
 *    người ta dùng để canh mặt mình vào giữa. Cắt phần nhìn trong khi ảnh lưu giữ nguyên cả
 *    khung nghĩa là người ta canh theo một tấm ảnh không tồn tại; ngược lại, cắt cả ảnh lưu
 *    cho khớp thì có ngày cắt mất nửa cái mặt của một phiếu chấm công đang bị tranh cãi.
 *    `contain` chịu hai dải đen hai bên để đổi lấy: cái thấy trên màn ĐÚNG BẰNG cái lưu xuống.
 *
 * ⚠️ 46vh giữ nguyên — anh Thắng 18/09/2026: *"Đẩy màn chụp nhỏ lại 1/2 để cho nút chụp lên
 *    cao. Gọn lại"*. Khung cao hơn là nút "Chụp ngay" rơi khỏi màn trên máy hẹp, và người ta
 *    phải cuộn giữa lúc một tay đang cầm máy tự chụp mặt mình.
 * ══════════════════════════════════════════════════════════════════════════════════════════ */
.khung{position:relative;height:46vh;background:#000;border-radius:var(--bo-the);overflow:hidden}
/* Phủ kín cái hộp ở trên. `height:100%` là thứ chặn luật co-theo-tỷ-lệ — xem khối `.khung`. */
.khung video,.khung canvas.xem{width:100%;height:100%;object-fit:contain;background:#000}
.dem{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;
	pointer-events:none;border-radius:var(--bo-the)}
.dem span{font-size:96px;font-weight:800;color:#fff;line-height:1;
	text-shadow:0 0 22px rgba(0,0,0,.85),0 3px 10px rgba(0,0,0,.9);
	font-variant-numeric:tabular-nums}
.an{display:none!important}

/* ── CHUÔNG THÔNG BÁO ────────────────────────────────────────────────────────────────────
   z-index 7 — THẤP HƠN `.mn` (9) và thấp hơn thanh tab (8). Cao bằng `.mn` thì lúc đang
   chụp ảnh chấm công vẫn thấy cái chuông nổi trên màn tối, bấm trúng là thoát giữa chừng.
   `top` cộng safe-area vì trên iPhone đã thêm vào màn hình chính thì mép trên bị tai che. */
#oChuong{position:fixed;right:10px;top:calc(8px + env(safe-area-inset-top));z-index:7}
#btChuong{position:relative;width:42px;height:42px;padding:0;border:1px solid var(--vien-dam);
	border-radius:50%;background:var(--the);font-size:19px;line-height:1;cursor:pointer;
	box-shadow:0 2px 10px rgba(0,0,0,.35)}
#btChuong:active{transform:scale(.94)}
/* Chấm đỏ đè lên góc chuông. `min-width` + `padding` ngang để '3' tròn còn '99+' thành viên
   thuốc, chứ không phải hai kích thước khác hẳn nhau. */
#demChuong{position:absolute;top:-4px;right:-4px;min-width:18px;height:18px;padding:0 5px;
	border-radius:9px;background:#dc2626;color:#fff;font-size:11px;font-weight:700;
	line-height:18px;font-variant-numeric:tabular-nums}
.tin{display:block;width:100%;text-align:left;padding:11px 12px;margin:0 0 8px;
	border:1px solid var(--vien);border-radius:var(--bo-o);background:var(--the);
	color:var(--chu);font:inherit;font-size:13.5px;cursor:pointer}
/* Chưa đọc: viền trái dày + nền hơi sáng. KHÔNG dùng chữ đậm làm dấu duy nhất — đọc rồi thì
   chữ nhạt đi, và trên màn hình ngoài nắng hai mức đậm nhạt ấy nhìn như nhau. */
.tin.moi{border-left:3px solid var(--nhan);background:var(--nhan-nhat)}
.tin .luc{display:block;margin:4px 0 0;font-size:11px;color:var(--chu-mo)}
/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 NỀN PHỦ LUÔN TỐI, NÊN CHỮ NẰM TRỰC TIẾP TRÊN NÓ PHẢI SÁNG
 * ═══════════════════════════════════════════════════════════════════════════════════════════
 * `.mn` cố định nền `rgba(2,6,23,.94)` — nó là lớp phủ, không đổi theo giao diện. Nhưng chữ
 * bên trong vẫn thừa kế `--chu`, mà từ lúc trang chuyển sang NỀN SÁNG (17/09/2026) `--chu` gần
 * như đen. Hậu quả: TIÊU ĐỀ của mọi màn phủ — Xin bù giờ · Xin nghỉ · Phiếu lương · Khai giờ
 * khác… — là chữ đen trên nền đen. Người ta mở một màn ra và không đọc được tên của chính cái
 * màn mình vừa mở; chỉ mấy thẻ `.the` màu trắng bên trong là còn thấy.
 *
 * Lỗi này sống sót lâu vì nó KHÔNG làm gì hỏng: bấm vẫn chạy, biểu mẫu vẫn gửi được. Nó chỉ
 * âm thầm lấy mất dòng chữ nói cho người dùng biết họ đang ở đâu.
 *
 * ⚠️ ĐẶT MÀU Ở `.mn`, RỒI TRẢ LẠI `--chu` CHO `.the`. Thẻ trắng bên trong phải giữ chữ tối —
 *    đặt màu sáng cho cả cây con là mọi biểu mẫu thành trắng trên trắng, đổi một lỗi lấy một
 *    lỗi to hơn hẳn. Cùng lý do cho `.phu`: nút viền nằm thẳng trên nền phủ.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
.mn{position:fixed;inset:0;background:rgba(2,6,23,.94);z-index:9;overflow:auto;
	padding:14px 14px calc(20px + env(safe-area-inset-bottom));color:#eef2f8}
.mn .bao{padding-top:8px}
/* ══════════════════════════════════════════════════════════════════════════════════════════
 * MÀN CHỤP MANG BẢNG MÀU TỐI RIÊNG — làm nốt phần đã hứa ở khối chú thích đầu tệp, 25/09/2026.
 *
 * Anh Thắng chốt SÁNG TOÀN BỘ ngày 17/09 để trạm đồng nhất với bảy trang còn lại, và chốt ấy
 * giữ nguyên. Nhưng cái giá của nó là có thật: màn hình sáng hắt vào mặt người đang đứng tự
 * chụp, ảnh bệt — mà đúng tấm ảnh đó là thứ quản lý mở ra khi có tranh cãi công ca đêm.
 *
 * Phần bù đã được VIẾT RA trong chú thích từ hôm ấy nhưng CHƯA AI LÀM: cho riêng `#mChup` nền
 * tối. Chỉ năm giây đứng chụp mới có chuyện hắt sáng, nên tối đúng năm giây ấy là đủ — không
 * phải đánh đổi gì với sự đồng nhất của cả trang.
 *
 * ⚠️ ĐÈ TOKEN, KHÔNG ĐÈ TỪNG LUẬT. Khai lại bộ biến ngay trên `#mChup` thì mọi thứ bên trong
 *    (thẻ, nút, ô nhập, nhãn) tự đi theo, kể cả luật viết về sau. Đi sửa tay từng luật là bỏ
 *    sót, và bỏ sót ở đây nghĩa là một mảng trắng loé giữa màn tối.
 * ══════════════════════════════════════════════════════════════════════════════════════════ */
#mChup{--nen:#101828;--the:#1b2436;--nen-2:#243049;
	--vien:#31405e;--vien-dam:#455873;
	--chu:#e7ecf5;--chu-dam:#ffffff;--chu-mo:#93a3bd;
	--nhan:#38bdf8;--nhan-dam:#0ea5e9;--nhan-nhat:#12314a;--do:#f87171;
	background:var(--nen);color:var(--chu)}
#mChup .the{background:var(--the);border-color:var(--vien)}
#mChup label,#mChup .mo,#mChup .ct{color:var(--chu-mo)}
#mChup input,#mChup select{background:var(--nen-2);border-color:var(--vien-dam);color:var(--chu)}
.mn>.bao>.mo,.mn>.bao>.ct{color:#b9c4d4}
.mn>.bao>.phu{color:#dbe3ee;border-color:rgba(255,255,255,.34)}
.mn .the,.mn .the .mo,.mn .the .ct{color:var(--chu)}
.mn .the .mo,.mn .the .ct{color:var(--chu-mo)}
.mmau{width:96px;border-radius:var(--bo-nho);border:1px solid var(--vien-dam);float:right;margin:0 0 var(--d2) 10px}
a{color:var(--nhan)}
.ct{text-align:center;color:var(--chu-mo);font-size:11.5px;margin:var(--d4) 0 0}
/* ══════════════════════════════════════════════════════════════════════════════════════════
 * 🔐 MÀN ĐĂNG NHẬP KIỂU Ô SỐ — anh Thắng 26/09/2026 gửi mẫu (artifact "Verify your number"):
 *    *"tạo mẫu đăng nhập"*. Mỗi chữ số một ô; gửi đi thì hàng ô cuộn thành vòng tròn và quay
 *    trong lúc máy chủ kiểm; sai thì lắc đỏ rồi xoá; đúng thì dấu ✔ xanh rồi vào.
 * ⚠️ Ô `#oPin` THẬT vẫn nằm đó (trong suốt, phủ lên hàng ô) — gõ, dán, bàn phím số của điện
 *    thoại, Enter đều qua nó; mọi đoạn JS cũ đọc `el('oPin').value` vẫn đúng.
 * ⚠️ Chỉ hiện CHẤM, không hiện chữ số — đây là PIN (đứng ở quầy, người sau lưng nhìn thấy),
 *    không phải mã OTP dùng một lần như mẫu.
 * ⚠️ Màu theo bộ áo (`--nhan`, `--do`, `--luc`), không dán hex; không tải font ngoài.
 * ══════════════════════════════════════════════════════════════════════════════════════════ */
.vao-san{position:relative;height:70px;margin:var(--d2) 0 0;touch-action:manipulation;--vo:46px;transition:height .48s cubic-bezier(.2,.8,.2,1)}
.vao-vong{position:absolute;left:50%;top:50%;border-radius:50%;border:1px solid var(--vien-dam);opacity:0;pointer-events:none}
.vao-o{position:absolute;inset:0}
.vao-hop{position:absolute;left:50%;top:50%;width:var(--vo);height:var(--vo);margin:calc(var(--vo) / -2) 0 0 calc(var(--vo) / -2);
	display:grid;place-items:center;border-radius:var(--bo-o);background:var(--nen-2);border:1px solid var(--vien-dam);
	will-change:transform;transition:border-color .2s,background-color .2s,box-shadow .2s}
.vao-hop i{width:12px;height:12px;border-radius:50%;background:var(--chu);transform:scale(0);transition:transform .18s}
.vao-hop.co i{transform:scale(1)}
.vao-hop.lan i{animation:vaoLan .4s cubic-bezier(.2,.8,.2,1)}
@keyframes vaoLan{from{transform:translateY(70%) scale(.3);opacity:0}to{transform:scale(1);opacity:1}}
.vao-hop.dang{border-color:var(--nhan);box-shadow:0 0 0 3px var(--nhan-nhat)}
.vao-o.sai .vao-hop{border-color:var(--do)}
.vao-o.sai .vao-hop i{background:var(--do)}
.vao-o.dung .vao-hop{border-color:var(--luc);background:var(--luc-nhat)}
.vao-o.dung .vao-hop i{background:var(--luc)}
.vao-o.lac{animation:vaoLac .68s cubic-bezier(.36,.07,.19,.97)}
@keyframes vaoLac{15%{transform:translateX(-9px)}30%{transform:translateX(8px)}45%{transform:translateX(-6px)}60%{transform:translateX(5px)}75%{transform:translateX(-2px)}100%{transform:none}}
.vao-o.xoa .vao-hop i{transform:scale(0)}
#oPin.vao-nhap{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);width:320px;max-width:100%;height:var(--vo);
	z-index:3;opacity:0;border:0;background:transparent;color:transparent;caret-color:transparent;font-size:16px;padding:0;margin:0}
.vao-dau{position:absolute;left:50%;top:50%;width:62px;height:62px;margin:-31px 0 0 -31px;display:grid;place-items:center;
	border-radius:18px;border:1.5px solid var(--luc);background:var(--luc-nhat);opacity:0;transform:scale(.4);pointer-events:none;z-index:2}
.vao-dau.hien{opacity:1;transform:scale(1);transition:opacity .4s,transform .75s cubic-bezier(.34,1.56,.64,1)}
.vao-dau path{fill:none;stroke:var(--luc);stroke-width:2.6;stroke-linecap:round;stroke-linejoin:round;stroke-dasharray:20;stroke-dashoffset:20}
.vao-dau.hien path{stroke-dashoffset:0;transition:stroke-dashoffset .6s cubic-bezier(.65,0,.35,1) .3s}
@media (prefers-reduced-motion: reduce){.vao-hop,.vao-hop i,.vao-dau,.vao-dau path,.vao-o{animation:none!important;transition:none!important}}
/* ══════════════════════════════════════════════════════════════════════════════════════════
 * ☀ LỜI CHÀO NGÀY MỚI — anh Thắng 26/09/2026: *"gửi lời chào ngày mới (kiểu trẻ trung năng
 *    lượng)"*, xem mẫu rồi chốt *"làm cả A, B, C luôn"*. Ba kiểu: A Nắng sớm · B Tin nhắn ·
 *    C Chuỗi lửa. Mặc định TỰ ĐỔI kiểu theo ngày; mỗi người chọn được kiểu riêng ở tab Tôi.
 *    Màu thẻ đi theo KHUNG GIỜ (hình ảnh, không phải bộ áo) nên được dán mã màu ở đây.
 * ══════════════════════════════════════════════════════════════════════════════════════════ */
.lc{margin:0 0 var(--d3)}
.lc.k-sang{--g1:#FFB547;--g2:#FF7A59;--gchu:#3b1d00}
.lc.k-trua{--g1:#39C6FF;--g2:#2F6BFF;--gchu:#ffffff}
.lc.k-chieu{--g1:#FFA08F;--g2:#FF5E8A;--gchu:#ffffff}
.lc.k-toi{--g1:#7A6BFF;--g2:#3B2F9E;--gchu:#ffffff}
.lc.k-dem{--g1:#22335F;--g2:#0B1126;--gchu:#e8ecff}
.lc-a{position:relative;border-radius:var(--bo-the);padding:var(--d4) var(--d4) 18px;background:linear-gradient(145deg,var(--g1),var(--g2));color:var(--gchu);overflow:hidden;display:flex;flex-direction:column;gap:6px}
.lc-qua{position:absolute;right:-20px;top:-20px;width:104px;height:104px;border-radius:50%;background:radial-gradient(circle at 35% 35%,rgba(255,255,255,.95),rgba(255,255,255,.35) 60%,transparent 62%);animation:lcTroi 7s ease-in-out infinite;pointer-events:none}
.lc.k-toi .lc-qua,.lc.k-dem .lc-qua{background:radial-gradient(circle at 60% 40%,transparent 38%,rgba(255,255,255,.9) 40%,rgba(255,255,255,.25) 62%,transparent 64%)}
@keyframes lcTroi{0%,100%{transform:translateY(0)}50%{transform:translateY(9px)}}
.lc-ngay{font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;opacity:.82}
.lc-chao{font-size:22px;font-weight:800;line-height:1.2;max-width:86%}
.lc-cau{font-size:14px;line-height:1.45;max-width:92%}
.lc-nho{margin-top:4px;font-size:12px;font-weight:600;background:rgba(255,255,255,.22);border-radius:999px;padding:4px 10px;align-self:flex-start}
.lc-b{display:flex;flex-direction:column;gap:7px}
.lc-bd{display:flex;align-items:center;gap:8px;font-size:12px;color:var(--chu-mo)}
.lc-av{width:30px;height:30px;border-radius:50%;background:linear-gradient(145deg,var(--g1),var(--g2));display:grid;place-items:center;color:#fff;font-weight:800;font-size:10.5px}
.lc-bong{align-self:flex-start;max-width:88%;background:var(--the);border:1px solid var(--vien-dam);border-radius:18px 18px 18px 6px;padding:8px 12px;font-size:14px;line-height:1.4}
.lc-bong.lon{font-weight:700;font-size:16px}
.lc-b.chay .lc-bong{opacity:0;transform:translateY(6px);animation:lcHien .6s cubic-bezier(.2,.8,.2,1) forwards}
.lc-go{align-self:flex-start;display:flex;gap:4px;background:var(--the);border:1px solid var(--vien-dam);border-radius:18px;padding:10px 12px}
.lc-go i{width:6px;height:6px;border-radius:50%;background:var(--chu-mo);animation:lcNhay 1.3s infinite}
.lc-go i:nth-child(2){animation-delay:.15s}.lc-go i:nth-child(3){animation-delay:.3s}
@keyframes lcNhay{0%,60%,100%{opacity:.25;transform:none}30%{opacity:1;transform:translateY(-3px)}}
@keyframes lcHien{to{opacity:1;transform:none}}
.lc-c{display:flex;flex-direction:column;gap:8px}
.lc-hang{display:flex;gap:12px;align-items:center;background:var(--the);border:1px solid var(--vien);border-radius:var(--bo-the);padding:var(--d3)}
.lc-vong{width:64px;height:64px;flex:none;border-radius:50%;display:grid;place-items:center}
.lc-vong span{width:50px;height:50px;border-radius:50%;background:var(--the);display:grid;place-items:center;font-size:12px;font-weight:800;color:var(--chu);font-variant-numeric:tabular-nums;line-height:1.1;text-align:center}
.lc-lua{align-self:flex-start;display:inline-flex;gap:6px;align-items:center;font-size:12.5px;font-weight:700;border-radius:999px;padding:5px 12px;background:linear-gradient(145deg,var(--g1),var(--g2));color:var(--gchu)}
.lc-tin{font-size:12.5px;color:var(--chu-mo);border-left:3px solid var(--g2);padding-left:9px;line-height:1.4}
.lc-kieu{display:flex;flex-wrap:wrap;gap:6px}
.lc-kieu button{font:inherit;font-size:13px;font-weight:600;border:1px solid var(--vien-dam);background:var(--the);color:var(--chu);border-radius:999px;padding:6px 12px;cursor:pointer}
.lc-kieu button.dang{background:var(--nhan);border-color:var(--nhan);color:#fff}
@media (prefers-reduced-motion: reduce){.lc-qua,.lc-go i,.lc-b.chay .lc-bong{animation:none!important;opacity:1!important;transform:none!important}}
</style>
</head>
<body>

<!-- ============ MÀN ĐĂNG NHẬP ============ -->
<div id="mVao" class="bao">
	<h1>Chấm công</h1>
	<p class="mo">Gõ mã PIN của anh/chị để vào.</p>
	<div class="the">
		<label for="oPin">Mã PIN</label>
		<div id="vaoSan" class="vao-san">
			<div id="vaoVong" class="vao-vong" aria-hidden="true"></div>
			<div id="vaoO" class="vao-o" aria-hidden="true"></div>
			<input id="oPin" class="vao-nhap" type="tel" inputmode="numeric" autocomplete="off" maxlength="8"
				placeholder="••••••" enterkeyhint="go" aria-label="Mã PIN">
			<div id="vaoDau" class="vao-dau" aria-hidden="true"><svg viewBox="0 0 24 24" width="28" height="28"><path d="M6 12.5l4 4 8-9"/></svg></div>
		</div>
		<div id="loiVao"></div>
		<p></p>
		<button id="btVao" class="chinh to">VÀO</button>
		<p style="margin:12px 0 0"><button id="btQuen" class="phu" style="width:100%">Quên PIN?</button></p>
	</div>
	<p class="ct">K&amp;H · b<?php echo esc_html( $cfg['ver'] ); ?></p>
</div>

<!-- ============ MÀN QUÊN PIN ============ -->
<div id="mQuen" class="mn an"><div class="bao">
	<h1>Quên PIN</h1>
	<p class="mo">Gõ số căn cước đã khai trong hồ sơ. Không có hồ sơ thì nhờ quản lý cửa hàng.</p>
	<div class="the">
		<label for="oCccd">Số căn cước</label>
		<input id="oCccd" type="tel" inputmode="numeric" maxlength="12" placeholder="0790xxxxxxxx">
		<div id="kqQuen"></div>
		<p></p>
		<div class="hang">
			<button id="btTra" class="chinh">Tra PIN</button>
			<button id="btDongQuen" class="phu">Đóng</button>
		</div>
	</div>
</div></div>

<!-- ============ CHUÔNG THÔNG BÁO ============
     Nằm NGOÀI `#mChinh` và tự ẩn/hiện riêng, vì `#mChinh` là một khối cuộn — đặt chuông
     trong đó thì cuộn xuống bảng công là chuông trôi mất khỏi màn hình. Ngoài ra `#mChinh`
     bị ẩn lúc chưa đăng nhập, mà chuông cũng phải ẩn lúc ấy, nên hai thứ bật tắt cùng nhau
     trong `vaoRoi()`. -->
<div id="oChuong" class="an">
	<button id="btChuong" title="Thông báo" aria-label="Thông báo">🔔<span id="demChuong" class="an"></span></button>
</div>

<!-- ============ MÀN DANH SÁCH THÔNG BÁO ============ -->
<!-- ============ MÀN NHẮN TIN ============
     Anh Thắng 20/09/2026: *"Tạo tính năng mini chat trong app. Chọn thành viên cùng cửa hàng
     và chat"*.

     🔴 MỘT MÀN, HAI LỚP — danh sách cuộc nói chuyện, rồi mới tới khung chat. Nhét cả hai vào
        một lớp (danh sách bên trái, tin nhắn bên phải như máy tính) là thứ không dùng được
        trên màn 390px: mỗi bên còn 195px.
     ⚠️ Phòng CẢ CỬA HÀNG và phòng RIÊNG dùng CHUNG khung chat ở dưới. Tách hai khung là hai
        chỗ phải sửa mỗi lần đổi cách hiện một bong bóng tin. -->
<div id="mChat" class="mn an"><div class="bao">
	<h1>Nhắn tin</h1>

	<div id="chatLop1">
		<p class="mo">Nhắn cho cả cửa hàng, hoặc chọn một người để nhắn riêng.</p>
		<div class="the">
			<label style="margin:0 0 8px">Phòng cửa hàng</label>
			<div id="chatDsPhong"><p class="trong">Đang tải…</p></div>
		</div>
		<div class="the">
			<label style="margin:0 0 8px">Nhắn riêng</label>
			<div id="chatDsRieng"><p class="trong">Chưa có cuộc nào.</p></div>
			<p></p>
			<button id="btChatNguoi" class="phu">+ Chọn người để nhắn</button>
		</div>
		<div class="the an" id="chatOChon">
			<label style="margin:0 0 8px">Người cùng cửa hàng</label>
			<input id="chatTim" type="text" placeholder="Gõ tên để lọc" autocomplete="off">
			<div id="chatDsNguoi"><p class="trong">Đang tải…</p></div>
		</div>
		<button id="btDongChat" class="phu">Đóng</button>
	</div>

	<div id="chatLop2" class="an">
		<div class="the">
			<!-- ⚠️ `.hang` cho mọi nút `flex:1`, nên nút Quay lại nuốt nửa hàng và tên phòng bị
			     ép xuống dòng. Ghim nút lại, nhường chỗ cho tên — tên phòng mới là thứ người ta
			     cần đọc để biết mình đang nhắn vào đâu. -->
			<div class="hang" style="margin:0 0 10px;align-items:center">
				<button id="btChatVe" class="phu" style="flex:0 0 auto;padding:9px 12px">←</button>
				<b id="chatTen" style="flex:1;min-width:0;overflow:hidden;
					text-overflow:ellipsis;white-space:nowrap;font-size:16px">—</b>
			</div>
			<!-- ⚠️ Khung tin phải có CHIỀU CAO CỐ ĐỊNH và tự cuộn. Để nó cao theo nội dung thì
			     ô gõ trôi xuống dưới màn sau vài chục tin, và người ta phải cuộn lên mới gõ
			     được — trên điện thoại thì đó là bỏ cuộc. -->
			<!-- Khung cuộn cao cố định. Để cao theo nội dung thì ô gõ trôi xuống dưới màn sau
			     vài chục tin. `display:flex` + `justify-content:flex-end` dồn tin xuống ĐÁY,
			     nên phòng mới mở (ít tin) không còn một khoảng trắng mênh mông phía trên. -->
			<div id="chatKhung" style="height:46vh;overflow-y:auto;padding:4px 2px;
				display:flex;flex-direction:column;justify-content:flex-end">
				<p class="trong" style="text-align:center">Đang tải…</p>
			</div>
			<div id="chatLoi"></div>
			<div id="chatTepChon" class="an" style="margin:6px 0"></div>
			<p></p>
			<textarea id="chatO" rows="2" placeholder="Gõ tin nhắn…" maxlength="1000"></textarea>
			<!-- ⚠️ Ô chọn tệp ẩn, bấm qua nút 📎. Để `<input type=file>` trần thì mỗi trình
			     duyệt vẽ một kiểu, và trên iPhone nó là một nút xám không ai nhận ra là bấm
			     được. `accept` liệt kê đúng danh sách máy chủ nhận — khai rộng hơn là để người
			     ta chọn xong mới bị chối. -->
			<input id="chatTep" type="file" class="an"
				accept="image/jpeg,image/png,image/gif,image/webp,image/heic,.pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.zip">
			<p></p>
			<div class="hang">
				<button id="btChatDinhKem" class="phu" title="Đính kèm ảnh hoặc tệp"
					style="flex:0 0 68px;font-size:20px;line-height:1">📎</button>
				<button id="btChatGui" class="chinh">Gửi</button>
			</div>
		</div>
	</div>
</div></div>

<!-- ============ MÀN GỌI THOẠI ============
     Anh Thắng 20/09/2026: *"Gọi trong app đi em — tự dựng"*.
     Một màn cho CẢ BA cảnh (đang gọi đi · có người gọi tới · đang nói chuyện): ba màn rời là ba
     chỗ phải nhớ tắt micro, và quên một chỗ là micro mở tiếp sau khi cúp máy. -->
<div id="mGoi" class="mn an"><div class="bao">
	<h1 id="goiTieuDe">Cuộc gọi</h1>
	<div class="the" style="text-align:center">
		<p style="font-size:22px;font-weight:800;margin:6px 0" id="goiTen">—</p>
		<p class="mo" id="goiTrangThai">—</p>
		<!-- Thẻ audio ẩn: chỗ tiếng bên kia phát ra. `playsinline` để iPhone không mở
		     trình phát toàn màn hình. -->
		<audio id="goiTieng" autoplay playsinline></audio>
		<div id="goiLoi"></div>
		<p></p>
		<div class="hang">
			<button id="btGoiNghe" class="chinh an">Nghe</button>
			<button id="btGoiCup" class="phu">Cúp máy</button>
		</div>
	</div>
</div></div>

<div id="mChuong" class="mn an"><div class="bao">
	<h1>Thông báo</h1>
	<p class="mo">Hộp thư chung với trang Nội bộ — đọc ở đây thì bên kia cũng hết đỏ.</p>
	<div class="the">
		<div id="dsTin"><p class="trong">Đang tải…</p></div>
		<p></p>
		<div class="hang">
			<button id="btDocHet" class="phu">Đọc hết</button>
			<button id="btDongChuong" class="phu">Đóng</button>
		</div>
	</div>
</div></div>

<!-- ============ MÀN XIN BÙ GIỜ ============ -->
<div id="mXinBu" class="mn an"><div class="bao">
	<h1>Xin bù giờ</h1>
	<p class="mo">Quên bấm máy hôm nào thì xin bù ở đây. Cửa hàng trưởng duyệt trước, kế toán duyệt sau.</p>
	<div class="the">
		<label for="xbNgay">Ngày</label>
		<input id="xbNgay" type="date">
		<p></p>
		<div class="hang">
			<div style="flex:1"><label for="xbVao">Giờ vào</label>
				<input id="xbVao" type="tel" inputmode="numeric" placeholder="08:00" maxlength="5"></div>
			<div style="flex:1"><label for="xbRa">Giờ ra</label>
				<input id="xbRa" type="tel" inputmode="numeric" placeholder="17:00" maxlength="5"></div>
		</div>
		<div id="xbCa" class="mo" style="margin:6px 0 0;font-size:13px"></div>
		<div id="xbTong" class="mo" style="margin:4px 0 0;font-size:13px"></div>
		<p></p>
		<label for="xbLyDo">Vì sao thiếu giờ hôm ấy</label>
		<input id="xbLyDo" type="text" placeholder="VD: máy hỏng sáng hôm ấy, có camera" maxlength="250">
		<div id="kqXinBu"></div>
		<p></p>
		<div class="hang">
			<button id="btXinBu" class="chinh">Gửi đơn</button>
			<button id="btDongXinBu" class="phu">Đóng</button>
		</div>
		<p class="mo" style="margin:10px 0 0;font-size:12px">Ngày <b>đã có giờ chấm</b> thì không
			xin bù được — giờ sai thì báo cửa hàng trưởng sửa qua bảng công tháng.</p>
	</div>
	<div class="the">
		<label style="margin:0 0 8px">Đơn đã gửi</label>
		<div id="dsXinBu"><p class="trong">Đang tải…</p></div>
	</div>
</div></div>

<!-- ============ MÀN KHAI GIỜ KHÁC ============ -->
<div id="mKhaiGio" class="mn an"><div class="bao">
	<h1>Khai giờ khác</h1>
	<p class="mo">Giờ làm thêm mà máy không ghi được — khai vào đây để cửa hàng theo dõi.</p>
	<div class="the">
		<div class="vang" style="margin:0 0 12px">⚠️ Số này <b>KHÔNG tính vào lương</b>. Nó chỉ
			để cửa hàng biết, và hiện ở một cột riêng trên bảng công. Muốn sửa giờ công thật thì
			báo cửa hàng trưởng.</div>
		<label for="kgNgay">Ngày</label>
		<input id="kgNgay" type="date">
		<p></p>
		<label for="kgGio">Số giờ</label>
		<input id="kgGio" type="tel" inputmode="decimal" placeholder="VD: 2 hoặc 2,5" maxlength="6">
		<p></p>
		<label for="kgViec">Làm việc gì</label>
		<input id="kgViec" type="text" placeholder="VD: dọn kho, chạy sự kiện" maxlength="120">
		<p></p>
		<label for="kgGhi">Ghi chú (không bắt buộc)</label>
		<input id="kgGhi" type="text" placeholder="thêm gì đó cho cửa hàng dễ hiểu" maxlength="250">
		<div id="kqKhai"></div>
		<p></p>
		<div class="hang">
			<button id="btKhai" class="chinh">Lưu</button>
			<button id="btDongKhai" class="phu">Đóng</button>
		</div>
		<p class="mo" style="margin:10px 0 0;font-size:12px">Khai <b>0 giờ</b> cho một ngày = xoá
			dòng đã khai của ngày ấy. Khai lại cùng một ngày thì <b>đè lên</b>, không cộng dồn.</p>
	</div>
	<div class="the">
		<label style="margin:0 0 8px">Đã khai gần đây</label>
		<div id="dsKhai"><p class="trong">Đang tải…</p></div>
	</div>
</div></div>

<!-- ============ MÀN CHÍNH ============ -->
<div id="mChinh" class="bao an">
	<h1 id="tenToi">—</h1>
	<p class="mo"><span id="maToi"></span> · <span id="csToi"></span></p>
	<div id="tinhTrang"></div>

	<!-- ============ TAB 1: CHẤM CÔNG ============
	     Trước đây cả ba khối này nằm chung một trang cuộn dài, với một thanh nhảy nhanh ở
	     đầu. Nhảy neo giải quyết được việc "bấm được dù đang cuộn tới đâu", nhưng không giải
	     quyết được việc người ta phải cuộn qua bảng tháng 30 dòng để về lại nút chấm. -->
	<div id="tChamCong" class="tab-o">
	<!-- ☀ Lời chào ngày mới (xem khối CSS `.lc`) — vẽ bởi `veLoiChao()` sau khi có hồ sơ. -->
	<div id="loiChao" class="lc an" aria-live="polite"></div>

	<!-- ============ THẺ CHẤM CÔNG ============
	     Gộp đồng hồ + trạng thái + nút vào MỘT thẻ. Trước đây là hai thẻ rời: đồng hồ ở trên,
	     nút ở dưới — mà hai thứ ấy là một câu ("bây giờ là mấy giờ, và tôi bấm cái này"), tách
	     ra thì mắt phải đi hai lượt.

	     🔴 THẺ NÀY LÀ MẢNG TỐI DUY NHẤT CÒN LẠI SAU KHI TRANG CHUYỂN SANG NỀN SÁNG, và đó là
	        chủ ý. Nó chứa hai thứ quan trọng nhất màn hình — mốc giờ máy chủ và cái nút ghi
	        công — nên phải tách khỏi phần còn lại bằng một thứ mạnh hơn cái viền. Nền tối cũng
	        là thứ duy nhất trong trang KHÔNG hắt sáng vào mặt lúc người ta đứng chụp ngay sau
	        khi bấm. -->
	<div class="the-cham">
		<p class="cc-ngay" id="ngayMC">—</p>
		<p class="cc-gio" id="gioMC">--:--:--</p>
		<p class="cc-nhan">giờ máy chủ</p>
		<div id="trangThai"></div>
		<div id="baoCham"></div>
		<div id="oKhoiCham">
			<button id="btCham" class="nut-tron">
				<i>📷</i><span>CHẤM CÔNG</span>
			</button>
		</div>
	</div>

	<!-- ============ LƯỢT CHẤM ĐANG GIỮ TRONG MÁY ============
	     Ẩn khi hàng đợi trống, và đó là trạng thái bình thường. Bày một ô "Chờ gửi: 0" suốt
	     ngày là dạy người ta bỏ qua chính cái ô ấy đúng hôm nó có số. -->
	<div class="the an" id="oHangCho">
		<label style="margin:0 0 8px">Chờ gửi lên máy chủ</label>
		<div id="dsHangCho"></div>
		<p class="ct" style="text-align:left;margin:8px 0 10px">Mấy lượt này đã đóng dấu <b>giờ máy
			chủ</b> lúc bấm, nên gửi muộn vẫn vào đúng giờ ấy. <b>Đừng chấm lại</b> — chấm lại là
			hai lượt.</p>
		<button id="btDayHang" class="phu" style="width:100%">Thử gửi ngay</button>
	</div>

	<!-- ============ VỊ TRÍ ĐANG ĐỨNG ============ -->
	<div class="the">
		<label style="margin:0 0 8px">Vị trí đang đứng</label>
		<div id="oViTri"><p class="trong">Đang lấy vị trí…</p></div>
		<p></p>
		<button id="btViTri" class="phu" style="width:100%">Lấy lại vị trí</button>
	</div>

	<!-- ============ CƠ SỞ ĐƯỢC CHẤM ============ -->
	<div class="the">
		<label style="margin:0 0 8px">Cơ sở được chấm công</label>
		<div id="oCoSo"><p class="trong">Đang tải…</p></div>
	</div>

	<div class="the">
		<label style="margin:0 0 8px">Hôm nay</label>
		<p class="ct" style="text-align:left;margin:0 0 8px">Thấy dòng nào sai (nhầm cơ sở, thiếu
			giờ ra)? Báo ở tab <b>👤 Tôi → Báo lượt chấm sai</b>. Đừng chấm lại — chấm lại là thêm
			một lượt nữa, dòng sai vẫn còn.</p>
		<div id="bangHN"><p class="trong">Đang tải…</p></div>
	</div>

	</div><!-- /tChamCong -->

	<!-- ============ TAB 2: CÔNG CỦA TÔI ============ -->
	<div id="tCong" class="tab-o an">

	<!-- ============ LỊCH LÀM CỦA TÔI ============
	     Dùng CHUNG bộ lật tháng với bảng công ngay dưới — một tháng, một cặp nút ‹ ›. Hai bộ
	     lật riêng thì người ta lật cái này quên cái kia, rồi đọc lịch tháng 9 cạnh công tháng
	     8 mà không thấy gì sai.

	     🔴 ĐẶT TRÊN BẢNG CÔNG, không đặt dưới. Lịch nói việc SẮP tới, bảng công nói việc ĐÃ
	        qua; người mở tab này giữa tháng cần cái sắp tới trước. -->
	<div class="the" id="oKhoiLichToi">
		<label style="margin:0 0 8px">Lịch làm của tôi</label>
		<div id="bangLichToi"><p class="trong">—</p></div>
	</div>

	<div class="the" id="oKhoiCong">
		<label style="margin:0 0 8px">Công của tôi</label>
		<div class="hang" style="align-items:center;margin:0 0 10px">
			<button id="btThangTruoc" class="phu" style="flex:0 0 46px">‹</button>
			<b id="nhanThang" style="flex:1;text-align:center;font-variant-numeric:tabular-nums;font-size:16px">—</b>
			<button id="btThangSau" class="phu" style="flex:0 0 46px">›</button>
		</div>
		<div id="tomTat"></div>
		<div id="bangThang"><p class="trong">—</p></div>
		<p class="ct" style="margin:10px 0 0;text-align:left">Số ở đây là <b>giờ có mặt</b> đọc thẳng từ
			bảng chấm công, chưa trừ nghỉ và chưa quy ra công tính lương. Bảng lương do kế toán chốt
			có thể khác — thấy lệch thì báo, đừng tự cộng.</p>
	</div>


	</div><!-- /tCong -->

	<!-- ============ TAB CỬA HÀNG (chỉ cửa hàng trưởng) ============
	     🔴 NÚT TAB ẨN SẴN, MÁY CHỦ MỞ. Trình duyệt không tự quyết ai thấy tab: `?viec=cuahang`
	        trả `duoc:false` cho nhân viên thường, và cả bốn cửa còn lại đều gác lại lần nữa ở
	        máy chủ. Một cái tab hiện nhầm thì chỉ là xấu; một cái cửa mở nhầm mới là hỏng —
	        nên phép gác nằm ở cửa, còn tab chỉ là chuyện bày biện.
	     ⚠️ Ẩn bằng lớp `an` ngay trong HTML, không chờ JS gỡ: chờ JS thì nhân viên thường thấy
	        nút loé lên một nhịp trước khi biến mất, và cái loé ấy đủ để người ta bấm. -->
	<div id="tCuaHang" class="tab-o an">

	<!-- ⚠️ CÂU NHẮC NẰM Ở ĐẦU TAB, TRƯỚC MỌI THỨ KHÁC — anh Thắng 17/09/2026: *"Nên chỗ đầu cửa
	     hàng. Thông báo nội dung hết 24h hôm này không cho phép sửa giờ công. Vui lòng liên hệ
	     kế toán"*. Nhắc ở cuối hay nhắc lúc bị chối thì người ta đã gõ xong mới biết. -->
	<div id="nhacHan" class="an"></div>

	<div class="the">
		<label for="chCoSo" style="margin:0 0 8px">Cơ sở tôi phụ trách</label>
		<select id="chCoSo"></select>
		<!-- ⚠️ Anh Thắng 17/09/2026: *"Cơ sở phụ trách là cơ sở theo dõi nhân sự, thêm nhân sự,
		     chứ không có chấm công trong đó, trừ nó có tên trong chọn cơ sở chấm công"*. Hai
		     danh sách cố ý khác nhau, nên màn phải nói ra — không thì người ta thấy một cái tên
		     ở đây rồi đi tìm nó trong ô chấm công, không có, và tưởng hồ sơ mình bị sót. -->
		<p class="ct" style="text-align:left;margin:8px 0 0">Đây là cơ sở anh/chị <b>theo dõi
			nhân sự</b> — xem công, duyệt đơn, thêm người. Nó <b>không phải</b> nơi anh/chị chấm
			công; ô chấm công ở tab <b>Chấm công</b> là danh sách riêng. Một cơ sở có thể nằm ở
			cả hai, hoặc chỉ một trong hai.</p>
	</div>

	<!-- ĐƠN TỪ NHÂN VIÊN — ba loại gộp một hộp. Người duyệt nghĩ theo "hôm nay còn ai chờ
	     mình", không nghĩ theo loại đơn; ba tab là ba chỗ phải nhớ mở. -->
	<div class="the">
		<label style="margin:0 0 8px">Đơn từ nhân viên</label>
		<div id="bangDonCH"><p class="trong">—</p></div>
	</div>

	<div class="the">
		<div class="hang" style="align-items:center;margin:0 0 10px">
			<button id="chThangTruoc" class="phu" style="flex:0 0 46px">‹</button>
			<b id="chNhanThang" style="flex:1;text-align:center;font-variant-numeric:tabular-nums;font-size:16px">—</b>
			<button id="chThangSau" class="phu" style="flex:0 0 46px">›</button>
		</div>
		<div id="bangCongCH"><p class="trong">—</p></div>
	</div>

	</div><!-- /tCuaHang -->

	<!-- ============ TAB 3: ỨNG DỤNG ============
	     Lưới dựng ở trình duyệt, nhưng phép gác nằm trọn ở máy chủ: `?viec=ung` chỉ trả về
	     những ô người này thật sự vào được, nên trình duyệt chưa bao giờ nhận được ô bị khoá
	     để mà ẩn đi. Xem khối chú thích đầu `class-vhcc-ung.php`. -->
	<div id="tUng" class="tab-o an">
		<div class="the">
			<label style="margin:0 0 10px">Ứng dụng của bạn</label>
			<div id="oUng"><p class="trong">Đang tải…</p></div>
		</div>
	</div><!-- /tUng -->

	<!-- ============ TAB 4: TÔI ============ -->
	<div id="tToi" class="tab-o an">

	<div class="the">
		<label style="margin:0 0 8px">Tài khoản</label>
		<div class="hang" style="align-items:center;gap:12px">
			<div id="chuCai">—</div>
			<div style="flex:1;min-width:0">
				<b id="tenToi2" style="display:block;font-size:16px">—</b>
				<span class="ct" id="moToi2">—</span>
				<span class="ct an" id="oVaiToi">Vai: <b id="vaiToi">—</b></span>
			</div>
		</div>
	</div>

	<div class="the">
		<label style="margin:0 0 8px">Kiểu lời chào</label>
		<div class="lc-kieu" id="kieuChao">
			<button type="button" data-kieu="tu">Tự đổi mỗi ngày</button>
			<button type="button" data-kieu="a">Nắng sớm</button>
			<button type="button" data-kieu="b">Tin nhắn</button>
			<button type="button" data-kieu="c">Chuỗi lửa</button>
		</div>
	</div>

	<!-- ============ HỒ SƠ NHÂN SỰ ============
	     Danh sách ô do MÁY CHỦ quyết (VHCC_HoSoToi::SUA_DUOC), không gõ tay ở đây: gõ hai nơi
	     là sớm muộn màn hình bày một ô mà máy chủ không nhận, người ta gõ xong bấm Lưu rồi
	     thấy nó biến mất. -->
	<div class="the">
		<div class="hang" style="align-items:center;margin:0 0 4px">
			<label style="margin:0;flex:1">Hồ sơ nhân sự</label>
			<span id="nhanThieu" class="nhan an"></span>
		</div>
		<div id="oHoSo"><p class="trong">Đang tải…</p></div>
	</div>

	<!-- ============ BÁO LƯỢT CHẤM SAI ============
	     Tài liệu phát cho cơ sở ghi thẳng: *"Chấm nhầm cơ sở rồi thì tự sửa không được… Báo quản
	     lý sửa ở màn Bảng công, trong ngày."* Câu ấy đúng, nhưng "báo quản lý" không có đường
	     nào trong app — nó là nhắn Zalo, và tin Zalo thì trôi mất giữa hai trăm tin khác trước
	     khi ai kịp mở Bảng công.

	     🔴 Ô NÀY KHÔNG SỬA GIỜ. Nó gắn một cái cờ nằm CẠNH ngày ấy, đúng cơ chế cửa hàng trưởng
	        vẫn đọc hằng ngày. Cho người ta tự sửa giờ của chính mình là bỏ luôn ý nghĩa của việc
	        chấm công. -->
	<div class="the">
		<label style="margin:0 0 8px">Báo lượt chấm sai</label>
		<p class="ct" style="text-align:left;margin:0 0 10px">Chấm nhầm cơ sở, thiếu giờ ra, giờ
			không đúng… Báo ở đây thì cửa hàng trưởng thấy ngay trên bảng công.
			<b>Báo không tự sửa giờ</b> — người có quyền xem rồi mới sửa.</p>
		<label for="bsNgay">Ngày bị sai</label>
		<input id="bsNgay" type="date">
		<label for="bsCoSo">Cơ sở</label>
		<select id="bsCoSo"></select>
		<label for="bsLyDo">Sai chỗ nào</label>
		<input id="bsLyDo" type="text" maxlength="500" placeholder="VD: chấm nhầm sang VP_KH-HCM, đúng ra là SETUP_VP">
		<div id="loiBaoSai"></div>
		<p></p>
		<button id="btBaoSai" class="chinh to">GỬI BÁO SAI</button>
		<div id="bangDaBao" style="margin-top:12px"></div>
	</div>

	<!-- ============ XIN PHÉP ============
	     Đi trễ và đổi lịch (gồm cả xin nghỉ một ngày). Nghiệp vụ nằm nguyên ở VHCC_XinTre và
	     VHCC_Lich; mấy ô này chỉ là cái cửa — xem chú thích khối `xintre` trong class-vhcc-tram.php.

	     🔴 NẰM TRONG TAB "TÔI", KHÔNG PHẢI MỘT TAB THỨ NĂM. Thanh tab dưới đáy đang có bốn ô, và
	        bốn là vừa hết bề ngang một điện thoại hẹp; ô thứ năm là chữ bị cắt cụt ở cả bốn ô
	        kia. Mà đơn xin phép thì cùng họ với hồ sơ và mật khẩu — đều là việc của CHÍNH người
	        đang đăng nhập — nên nó thuộc về đây chứ không đứng riêng.

	     🔴 HAI KHỐI TÁCH RỜI, KHÔNG GỘP THÀNH MỘT Ô XỔ "LOẠI ĐƠN". Hai loại đơn đi về hai bảng
	        khác nhau, hai người duyệt khác nhau, hai bộ hạn nộp khác nhau, và đơn đi trễ thì cơ
	        sở nào cũng nộp được còn đơn đổi lịch chỉ có nghĩa ở cơ sở đã bật phân lịch. Gộp vào
	        một biểu mẫu là phải ẩn/hiện quá nửa số ô theo lựa chọn — và người nộp không bao giờ
	        biết chắc cái ô mình vừa điền có được gửi đi hay không. -->
	<div class="the" id="oKhoiLich">
		<label style="margin:0 0 8px">Xin đổi lịch / xin nghỉ một ngày</label>
		<div id="oLichTat" class="an"><p class="trong">—</p></div>
		<div id="oLichMo" class="an">
			<p class="ct" style="text-align:left;margin:0 0 10px">Đổi việc của một ngày đã xếp lịch, hoặc
				dời sang ngày khác. Duyệt xong là <b>lịch đổi thật</b>, không chỉ đổi trạng thái đơn.</p>
			<label for="xlCoSo">Cơ sở</label>
			<select id="xlCoSo"></select>
			<label for="xlNgay">Ngày cần đổi</label>
			<input id="xlNgay" type="date">
			<label for="xlCa">Ca</label>
			<select id="xlCa"></select>
			<label for="xlViec">Việc mới cho ngày đó</label>
			<select id="xlViec"></select>
			<label for="xlDoiSang">Dời sang ngày khác (để trống nếu chỉ đổi việc)</label>
			<input id="xlDoiSang" type="date">
			<label for="xlLyDo">Lý do</label>
			<input id="xlLyDo" type="text" maxlength="250" placeholder="Người xếp lịch duyệt theo lý do">
			<div id="loiLich"></div>
			<p></p>
			<button id="btGuiLich" class="chinh to">GỬI ĐƠN ĐỔI LỊCH</button>
		</div>
	</div>

	<div class="the">
		<label style="margin:0 0 8px">Đơn của tôi</label>
		<div id="bangDon"><p class="trong">Đang tải…</p></div>
	</div>

	<!-- ============ ĐỔI MẬT KHẨU ============ -->
	<div class="the">
		<label style="margin:0 0 8px">Đổi mật khẩu (PIN)</label>
		<div id="oPin">
			<input id="pinCu"  type="password" inputmode="numeric" autocomplete="off" placeholder="Mật khẩu đang dùng">
			<p></p>
			<input id="pinMoi" type="password" inputmode="numeric" autocomplete="off" placeholder="Mật khẩu mới">
			<p></p>
			<input id="pinLai" type="password" inputmode="numeric" autocomplete="off" placeholder="Nhập lại mật khẩu mới">
			<p></p>
			<button id="btDoiPin" class="phu" style="width:100%">Đổi mật khẩu</button>
			<div id="baoPin"></div>
		</div>
	</div>

	<?php
	/* Ô cài ứng dụng (Android/Chrome). Tự ẩn khi đã cài, và tự ẩn hẳn trên iPhone — iOS không
	   bắn `beforeinstallprompt`, phần chỉ đường cho iPhone nằm ở ô Bật thông báo ngay dưới. */
	VHCC_PWA::nut_cai();

	/* Ô bật thông báo. Tự ẩn khi máy không làm được — xem VHCC_Push::giao_dien(). */
	VHCC_Push::giao_dien();
	?>

	<p style="margin:14px 0 0"><button id="btRa" class="phu" style="width:100%">Thoát</button></p>
	<p class="ct">K&amp;H · b<?php echo esc_html( $cfg['ver'] ); ?></p>

	</div><!-- /tToi -->
</div>

<!-- ============ THANH TAB DƯỚI ĐÁY ============
     z-index 8 — THẤP HƠN `.mn` (9), để màn chụp ảnh và màn chọn cơ sở phủ kín nó. Bằng hoặc
     cao hơn thì lúc đứng chụp vẫn thấy thanh tab ló ra, bấm trúng là thoát giữa chừng. -->
<nav id="thanhTab" class="an">
	<button class="tab-nut dang" data-tab="tChamCong"><span>📷</span>Chấm công</button>
	<button class="tab-nut" data-tab="tCong"><span>📅</span>Công của tôi</button>
	<button class="tab-nut an" id="nutCH" data-tab="tCuaHang"><span>🏪</span>Cửa hàng</button>
	<button class="tab-nut" data-tab="tUng"><span>🧩</span>Ứng dụng</button>
	<button class="tab-nut" data-tab="tToi"><span>👤</span>Tôi</button>
</nav>

<!-- ============ MÀN XIN NGHỈ ============
     🔴 MỘT TÍNH NĂNG RIÊNG — anh Thắng 17/09/2026 khoanh đúng khối này: *"Chuyển này thành 1
        tính năng"*. Cùng lối với Thêm nhân sự · Phiếu lương · Gửi đơn đi trễ.
     ⚠️ Ô QUỸ PHÉP ĐỨNG TRÊN BIỂU MẪU, KHÔNG PHẢI DƯỚI. Người mở màn này ra là để quyết "xin
        mấy ngày" — con số còn lại phải đọc được TRƯỚC khi họ gõ, không phải sau. -->
<div id="mXinNghi" class="mn an"><div class="bao">
	<h1>Xin nghỉ</h1>
	<p class="mo">Cửa hàng trưởng duyệt. <b>Đơn được duyệt không tự cộng hay trừ công</b> — nó
		chỉ trả lời "hôm ấy vắng có phép hay không".</p>

	<div id="oQuyPhep"></div>

	<div class="the">
		<div class="fldx"><label for="xnTu">Nghỉ từ ngày</label>
			<input id="xnTu" type="date"></div>
		<div class="fldx"><label for="xnDen">Đến hết ngày (để trống nếu nghỉ một ngày)</label>
			<input id="xnDen" type="date"></div>
		<div class="fldx"><label for="xnLoai">Loại nghỉ</label>
			<select id="xnLoai"></select></div>
		<div class="fldx"><label for="xnLyDo">Lý do</label>
			<input id="xnLyDo" type="text" maxlength="250" placeholder="Người duyệt quyết theo lý do"></div>
		<div id="loiNghi"></div>
		<p></p>
		<button id="btGuiNghi" class="chinh to">GỬI ĐƠN XIN NGHỈ</button>
		<p class="ct" style="text-align:left;margin:10px 0 0">Đơn đã nộp và kết quả duyệt xem ở
			tab <b>Tôi</b> — ở đó gom cả đơn đi trễ, xin nghỉ và đổi lịch trong một danh sách.</p>
	</div>

	<p></p>
	<button id="btDongNghi" class="phu to">Đóng</button>
</div></div>

<!-- ============ MÀN XIN PHÉP ĐI TRỄ ============
     🔴 MỘT TÍNH NĂNG RIÊNG — anh Thắng 17/09/2026: *"Gửi đơn đi trễ là 1 tính năng"*, khoanh
        đúng khối này ở tab Tôi. Cùng lối với Thêm nhân sự và Phiếu lương: việc thỉnh thoảng
        mới làm thì đừng nằm giữa một trang cuộn dài.
     ⚠️ ĐƠN ĐÃ NỘP VẪN Ở TAB TÔI. Chỗ này là chỗ NỘP; chỗ xem kết quả là danh sách đơn bên
        tab Tôi, và nó gom cả ba loại đơn. Tách danh sách ấy ra theo từng loại là ba chỗ phải
        nhớ mở, mà cái quên mở là cái nằm đó cả tuần. -->
<div id="mXinTre" class="mn an"><div class="bao">
	<h1>Xin phép đi trễ</h1>
	<p class="mo">Cơ sở nào cũng nộp được. Đơn được duyệt thì ô vàng "chấm thiếu giờ" của ngày
		ấy bỏ đi — <b>số giờ trong ô không đổi</b>.</p>

	<div class="the">
		<div class="fldx"><label for="xtNgay">Ngày xin trễ</label>
			<input id="xtNgay" type="date"></div>
		<div class="fldx"><label for="xtPhut">Trễ khoảng bao nhiêu phút</label>
			<input id="xtPhut" type="number" inputmode="numeric" min="1" step="1" placeholder="VD: 20"></div>
		<div class="fldx"><label for="xtLyDo">Lý do</label>
			<input id="xtLyDo" type="text" maxlength="250" placeholder="Cửa hàng trưởng duyệt theo lý do"></div>
		<div id="loiTre"></div>
		<p></p>
		<button id="btGuiTre" class="chinh to">GỬI ĐƠN ĐI TRỄ</button>
		<p class="ct" style="text-align:left;margin:10px 0 0">Đơn đã nộp và kết quả duyệt xem ở
			tab <b>Tôi</b> — ở đó gom cả đơn đi trễ, xin nghỉ và đổi lịch trong một danh sách.</p>
	</div>

	<p></p>
	<button id="btDongTre" class="phu to">Đóng</button>
</div></div>

<!-- ============ MÀN PHIẾU LƯƠNG ============
     🔴 MỘT TÍNH NĂNG RIÊNG, KHÔNG PHẢI KHỐI CUỐI TAB CÔNG. Anh Thắng 17/09/2026 gạch chéo khối
        ấy và chỉ sang ô trống trong lưới: *"Phiếu lương cho vào vị trí này"*.
     🔴 HAI PHẦN, VÀ AI THẤY PHẦN NÀO LÀ DO MÁY CHỦ QUYẾT: *"nhân viên thì 1 phiếu của chính
        mình. Cửa hàng trưởng thì có chính mình và cả cửa hàng"*. Nút "Cả cửa hàng" ẩn sẵn
        trong HTML; `?viec=phieuluong` trả `dsCoSoQl` rỗng cho nhân viên thường nên nó không
        bao giờ hiện — và cửa `phieucs` vẫn gác lại lần nữa ở máy chủ.
     ⚠️ KHÔNG DÙNG CHUNG BỘ LẬT THÁNG cho hai phần. Phần "của tôi" chỉ có tháng ĐÃ CÔNG BỐ;
        phần "cả cửa hàng" có MỌI tháng (quản lý cần thấy cả tháng đang gõ dở). Nối chung một
        ô chọn thì một trong hai bên luôn lật tới chỗ trống. -->
<div id="mPhieu" class="mn an"><div class="bao">
	<h1>Phiếu lương</h1>

	<div class="hang" id="plNut" style="margin:0 0 12px">
		<button id="btPlToi" class="chinh">Của tôi</button>
		<button id="btPlCs" class="phu an">Cả cửa hàng</button>
	</div>

	<div id="plPhanToi">
		<div class="the">
			<label id="plNhanO" for="plThang" class="an" style="margin:0 0 8px">Tháng</label>
			<select id="plThang" class="an"></select>
			<div id="bangPhieu" style="margin-top:10px"><p class="trong">—</p></div>
		</div>
	</div>

	<div id="plPhanCs" class="an">
		<div class="the">
			<label for="plCsCoSo" style="margin:0 0 8px">Cơ sở</label>
			<select id="plCsCoSo"></select>
			<div class="hang" style="align-items:center;margin:10px 0 0">
				<button id="plThangTruoc" class="phu" style="flex:0 0 46px">‹</button>
				<b id="plNhanThang" style="flex:1;text-align:center;font-variant-numeric:tabular-nums;font-size:16px">—</b>
				<button id="plThangSau" class="phu" style="flex:0 0 46px">›</button>
			</div>
			<div id="bangPhieuCs" style="margin-top:10px"><p class="trong">—</p></div>
		</div>
	</div>

	<p></p>
	<button id="btDongPhieu" class="phu to">Đóng</button>
</div></div>

<!-- ============ MÀN NHÂN SỰ (danh sách người của cơ sở) ============
     Anh Thắng 17/09/2026: *"Thêm tab Nhân Sự trong Quản Lý Cửa Hàng"*.
     ⚠️ DANH SÁCH, KHÔNG PHẢI BẢNG. Sáu cột của trang quản trị nhét vào 390px là không đọc nổi.
        Mỗi người một thẻ: tên to, mấy dòng phụ nhỏ — đúng lối app anh gửi ảnh.
     🔴 KHÔNG BAO GIỜ BÀY PIN, chỉ bày CÓ hay CHƯA — cùng luật với trang Nhân sự cửa hàng.
        Biết PIN của một người là đăng nhập thay họ được, mà màn của họ không có gì đổi. -->
<div id="mNhanSu" class="mn an"><div class="bao">
	<h1>Nhân sự</h1>
	<p class="mo" id="nsPhu">—</p>

	<div class="the">
		<div class="fldx"><label for="nsCoSo">Cơ sở</label>
			<select id="nsCoSo"></select></div>
		<div class="fldx"><label for="nsTim">Tìm tên hoặc mã</label>
			<input id="nsTim" type="search" placeholder="gõ rồi bấm Tìm"></div>
		<button id="btNsTim" class="phu">Tìm</button>
	</div>

	<div id="dsNhanSu"><p class="trong">—</p></div>

	<p></p>
	<button id="btDongNs" class="phu to">Đóng</button>
</div></div>

<!-- ============ MÀN GIỜ CÔNG LƯƠNG ============
     Anh Thắng 18/09/2026: *"Nhân viên sẽ thấy giờ làm mình trong ngày hoặc ngày trước và tự bấm
     set loại giờ làm trong những ngày đó và gửi cửa hàng trưởng duyệt"*.

     🔴 HAI ĐƯỜNG KHÁC NHAU, VÀ MÀN NÀY LÀ ĐƯỜNG CHẬM. Lúc KẾT CA thì chọn xong là ghi luôn
        (hộp `mKetCa` bên dưới). Ở đây là sửa NGÀY CŨ, nên mọi dòng đều phải qua cửa hàng trưởng
        duyệt — loại giờ quyết định đơn giá, và sửa được quá khứ mà không ai duyệt thì cuối
        tháng ai cũng đổi ca của mình sang việc có giá cao nhất.

     ⚠️ Ô XỔ DỰNG TỪ DANH SÁCH MÁY CHỦ TRẢ VỀ, không gõ cứng trong HTML. Danh sách ấy chính là
        mấy dòng đơn giá đã khai cho người ấy — gõ cứng là chọn được một việc không có giá. -->
<div id="mGioLuong" class="mn an"><div class="bao">
	<h1>Giờ công lương</h1>
	<p class="mo">Mỗi ngày chọn <b>việc mình đã làm</b> hôm ấy. Đổi ngày cũ thì phải chờ
		<b>cửa hàng trưởng duyệt</b> — chưa duyệt thì bảng lương vẫn giữ cái cũ.</p>

	<div id="lgBao"></div>

	<div class="the">
		<label for="lgLyDo">Lý do đổi <span class="mo">(một lý do cho cả lượt gửi)</span></label>
		<input id="lgLyDo" maxlength="250" placeholder="VD: hôm ấy tôi dẫn chương trình">
	</div>

	<div id="lgDs"><p class="trong">—</p></div>

	<p></p>
	<button id="btLgGui" class="chinh to">Gửi cửa hàng trưởng duyệt</button>
	<p></p>
	<button id="btDongLg" class="phu to">Đóng</button>
</div></div>

<!-- ============ HỘP HỎI LOẠI GIỜ LÚC KẾT CA ============
     Anh Thắng 18/09/2026: *"Khi bấm check in giờ ra nó sẽ hỏi ca1,2,3 bạn làm nhiệm vụ gì. Để
     nhân viên tự set luôn"*.

     🔴 HIỆN SAU KHI GIỜ RA ĐÃ GHI XONG, KHÔNG PHẢI TRƯỚC. Chặn trước là một câu hỏi đứng giữa
        người ta và cái nút họ tới đây để bấm — mạng chậm hay hộp lỗi là mất luôn lượt chấm.
        Ghi giờ trước thì dù họ tắt máy ngay sau đó, công vẫn nguyên; chỉ thiếu mỗi loại giờ,
        mà cái ấy vào tab Giờ công lương khai bù được.

     ⚠️ KHÔNG CÓ NÚT "BỎ QUA" RIÊNG — nút Đóng chính là bỏ qua, và dòng chữ nói rõ bỏ qua thì
        ra sao. Một nút "Bỏ qua" nằm cạnh nút "Lưu" là thứ người ta bấm theo phản xạ. -->
<div id="mKetCa" class="mn an"><div class="bao">
	<h1>Ca vừa xong, bạn làm việc gì?</h1>
	<p class="mo" id="kcMo">Chọn đúng việc thì lương tính đúng đơn giá của việc ấy.</p>
	<div class="the">
		<label for="kcViec">Việc trong ca này</label>
		<select id="kcViec"></select>
	</div>
	<div id="kcBao"></div>
	<p></p>
	<button id="btKcLuu" class="chinh to">Lưu loại giờ</button>
	<p></p>
	<button id="btKcBo" class="phu to">Để sau</button>
	<p class="mo" style="font-size:12px">Để sau thì ca này chưa có loại giờ — vào
		<b>Ứng dụng → Giờ công lương</b> khai bù, nhưng lúc ấy phải chờ cửa hàng trưởng duyệt.</p>
</div></div>

<!-- ============ MÀN THÊM NHÂN SỰ ============
     🔴 MỘT TÍNH NĂNG RIÊNG, KHÔNG PHẢI MỘT KHỐI DƯỚI ĐÁY TAB. Anh Thắng 17/09/2026: *"Chuyển
        sang thêm nhân sự là 1 tính năng"*, kèm ảnh khoanh đúng ô trống trong lưới Ứng dụng.
        Nó là việc làm dăm bữa một lần, còn tab Cửa hàng là thứ mở hằng ngày — để nó nằm cuối
        tab thì mỗi lần xem bảng công lại phải cuộn qua một biểu mẫu trống.
     ⚠️ CÓ Ô CHỌN CƠ SỞ RIÊNG. Trước đây nó mượn ô của tab Cửa hàng; mở từ lưới thì không còn
        ô ấy nữa, mà đoán bừa cơ sở là thêm người vào nhầm cửa hàng. -->
<div id="mThemNv" class="mn an"><div class="bao">
	<h1>Thêm nhân sự</h1>
	<p class="mo">Mở hồ sơ <b>tạm</b> cho người vừa vào làm, để họ chấm công được ngay.</p>

	<div class="the">
		<div class="fldx"><label for="tnCoSo">Thêm vào cơ sở</label>
			<select id="tnCoSo"></select></div>
		<div class="fldx"><label for="tnTen">Họ và tên</label>
			<input id="tnTen" type="text" maxlength="120" placeholder="Nguyễn Văn A"></div>
		<div class="fldx"><label for="tnCccd">Số căn cước</label>
			<input id="tnCccd" type="tel" inputmode="numeric" maxlength="12" placeholder="12 số"></div>
		<div class="fldx"><label for="tnSdt">Điện thoại</label>
			<input id="tnSdt" type="tel" inputmode="numeric" maxlength="15" placeholder="không bắt buộc"></div>
		<div class="fldx"><label for="tnGt">Giới tính</label>
			<select id="tnGt"><option value="">— không khai —</option><option>Nam</option><option>Nữ</option></select></div>
		<div id="loiThem"></div>
		<p></p>
		<button id="btThemNguoi" class="chinh to">THÊM VÀO CƠ SỞ NÀY</button>
		<p class="ct" style="text-align:left;margin:10px 0 0">Hệ cấp <b>mã tạm</b> và
			<b>không phát PIN cho ai cả</b>: người mới tự vào trang chấm công, bấm
			<b>Quên PIN</b>, gõ họ tên + căn cước của chính mình rồi tự đặt PIN. Vì vậy
			<b>căn cước là bắt buộc</b> — thiếu nó thì đường ấy tắc và hồ sơ vừa tạo thành
			hồ sơ chết. Lương, vai trò, mã chuẩn do nhân sự đặt sau.</p>
	</div>

	<p></p>
	<button id="btDongThem" class="phu to">Đóng</button>
</div></div>

<!-- ============ MÀN MỘT NGƯỜI — sửa công & chốt lương ============
     🔴 MÀN PHỦ TOÀN TRANG, KHÔNG PHẢI MỘT KHỐI NHÉT THÊM VÀO TAB. Anh Thắng 17/09/2026:
        *"giao diện dùng như app nhé"*. Hai việc này là việc làm cho MỘT người, cần trọn màn
        hình và một nút Đóng rõ ràng — nhét vào giữa tab Cửa hàng thì nó đẩy bảng công xuống
        dưới và người ta mất chỗ đang đứng.
     ⚠️ Cùng khuôn `.mn` với màn Chụp ảnh / Quên PIN, không dựng kiểu riêng: một khuôn thì
        thói quen bấm (cuộn, nút Đóng ở đáy) giống nhau ở mọi màn. -->
<div id="mNguoi" class="mn an"><div class="bao">
	<h1 id="nguoiTen">—</h1>
	<p class="mo" id="nguoiPhu">—</p>

	<!-- ── SỬA CÔNG TỪNG NGÀY ─────────────────────────────────────────────────────────── -->
	<div class="the">
		<label style="margin:0 0 8px">Công từng ngày</label>
		<div id="dsNgay"><p class="trong">Đang tải…</p></div>
	</div>

	<!-- Ô sửa MỞ RA khi bấm một ngày. Không bày sẵn: bày sẵn thì màn mở lên đã có một biểu
	     mẫu trống, và người ta gõ vào đó trước khi chọn ngày nào. -->
	<div class="the an" id="oSuaNgay">
		<label style="margin:0 0 8px" id="suaTieuDe">Sửa ngày —</label>
		<div class="hang">
			<div class="fldx" style="flex:1"><label for="sgVao">Giờ vào (24h)</label>
				<input id="sgVao" type="text" inputmode="numeric" maxlength="5" placeholder="08:30"></div>
			<div class="fldx" style="flex:1"><label for="sgRa">Giờ ra (24h)</label>
				<input id="sgRa" type="text" inputmode="numeric" maxlength="5" placeholder="17:00"></div>
		</div>
		<label class="tich" for="sgGay"><input id="sgGay" type="checkbox"> Ca gãy — bỏ khúc nghỉ giữa ra khỏi giờ công</label>
		<div class="hang an" id="oNghi">
			<div class="fldx" style="flex:1"><label for="sgNghiTu">Ra ca 1</label>
				<input id="sgNghiTu" type="text" inputmode="numeric" maxlength="5" placeholder="13:00"></div>
			<div class="fldx" style="flex:1"><label for="sgNghiDen">Vào ca 2</label>
				<input id="sgNghiDen" type="text" inputmode="numeric" maxlength="5" placeholder="17:00"></div>
		</div>
		<div class="fldx"><label for="sgLyDo">Vì sao phải sửa (bắt buộc)</label>
			<input id="sgLyDo" type="text" maxlength="200" placeholder="VD: máy lệch đồng hồ 2 tiếng — đối chiếu camera"></div>
		<div id="loiSua"></div>
		<p></p>
		<button id="btLuuGio" class="chinh to">LƯU GIỜ</button>
		<p></p>
		<button id="btXoaGio" class="phu to">Xoá giờ ngày này</button>
		<p></p>
		<button id="btXoaDong" class="nguy to">🗑 Xoá hẳn dòng công</button>
		<p class="ct" style="text-align:left;margin:10px 0 0">Ô để <b>trống</b> nghĩa là
			<b>giữ nguyên</b>, không phải xoá.<br>
			<b>Xoá giờ ngày này</b> — dòng còn, hai ô giờ trống. Lưới vẫn nói "hôm ấy có một
			dòng". Dùng khi giờ sai mà chưa biết giờ đúng.<br>
			<b>Xoá hẳn dòng công</b> — dòng biến mất khỏi lưới, trông như hôm ấy người ta
			<b>không đi làm</b>. Chỉ dùng khi dòng ấy vốn không nên tồn tại (máy chấm nhầm sang
			mã người khác chẳng hạn).<br>
			Cả hai đều <b>vào sổ</b> kèm giờ cũ và lý do, nên sau còn lần lại được.</p>
	</div>

	<!-- ── CHỐT LƯƠNG THEO VIỆC ───────────────────────────────────────────────────────── -->
	<div class="the an" id="oChot">
		<label style="margin:0 0 8px">Chốt lương theo việc</label>
		<div id="chotTom" class="xanh" style="margin:0 0 10px">—</div>

		<div class="fldx"><label for="clViec">Việc chính</label>
			<input id="clViec" type="text" maxlength="60" placeholder="tên việc chính" list="dsViec">
			<datalist id="dsViec"></datalist></div>
		<p class="ct" style="text-align:left;margin:0 0 12px">Chọn một việc là xong —
			<b>cả phần giờ còn lại</b> ăn theo giá của nó. Gõ thêm dòng giờ khác bên dưới thì
			phần này tự co lại.</p>

		<label style="margin:0 0 6px">Giờ ăn đơn giá khác</label>
		<div id="dsDongGio"></div>
		<button id="btThemDong" class="phu">+ Thêm một dòng</button>

		<p></p>
		<label class="tich" for="clThang"><input id="clThang" type="checkbox"> Ăn lương tháng — không tính theo giờ</label>
		<div class="hang an" id="oLuongThang">
			<div class="fldx" style="flex:1"><label for="clLcb">Lương cơ bản (đ/tháng)</label>
				<input id="clLcb" type="tel" inputmode="numeric" maxlength="12" placeholder="4000000"></div>
			<div class="fldx" style="flex:1"><label for="clCongYc">Số công chuẩn</label>
				<input id="clCongYc" type="tel" inputmode="numeric" maxlength="4" placeholder="VD 26"></div>
		</div>

		<label style="margin:14px 0 6px">Các khoản cộng vào lương</label>
		<div id="dsCong"></div>
		<label style="margin:14px 0 6px">Các khoản giảm trừ</label>
		<div id="dsTru"></div>
		<p class="ct" style="text-align:left;margin:8px 0 0">Ô để <b>trống</b> nghĩa là không có
			khoản ấy — khác với gõ số 0. Tờ xuất ra để trống đúng mấy ô ấy.</p>

		<div id="loiChot"></div>
		<p></p>
		<button id="btLuuChot" class="chinh to">LƯU CHỐT LƯƠNG</button>
	</div>

	<p></p>
	<button id="btDongNguoi" class="phu to">Đóng</button>
</div></div>

<!-- ============ MÀN CHỤP ẢNH ============ -->
<div id="mChup" class="mn an"><div class="bao">
	<h1>Chụp ảnh</h1>
	<p class="mo" style="margin:0 0 8px">Đưa mặt vào khung, đủ sáng, rồi bấm <b>Chụp ngay</b>.
		Ảnh được đóng dấu giờ máy chủ.</p>
	<div class="the" id="oMau" style="margin:0 0 8px"></div>
	<div class="the" style="padding:10px;margin:0">
		<div class="khung">
			<video id="vid" playsinline autoplay muted></video>
			<canvas id="xem" class="xem an"></canvas>
			<div id="oDem" class="dem an"><span id="soDem">5</span></div>
		</div>
		<div id="loiChup"></div>
		<div class="hang" id="nhomChup" style="margin-top:10px">
			<button id="btChup" class="chinh">Chụp ngay</button>
			<button id="btHuyChup" class="phu">Huỷ</button>
		</div>
		<div class="hang an" id="nhomXem">
			<button id="btDung" class="chinh">Dùng ảnh này</button>
			<button id="btChupLai" class="phu">Chụp lại</button>
		</div>
	</div>
</div></div>

<!-- ============ MÀN CHỌN CƠ SỞ / NHIỆM VỤ (ĐÚNG LÚC LƯU) ============ -->
<div id="mChon" class="mn an"><div class="bao">
	<h1>Lưu chấm công</h1>
	<p class="mo">Chọn đúng nơi anh/chị đang có mặt <em>lúc này</em>.</p>
	<div class="the">
		<div id="oChonCS"></div>
		<div id="oChonNV"></div>
		<div id="loiChon"></div>
		<p></p>
		<button id="btLuu" class="chinh to">LƯU CHẤM CÔNG</button>
		<p style="margin:10px 0 0"><button id="btHuyChon" class="phu" style="width:100%">Quay lại</button></p>
	</div>
</div></div>


<script>
(function(){
'use strict';
var CFG = <?php echo wp_json_encode( $cfg ); ?>;
/* Khoá phiên RIÊNG, cố ý KHÔNG dùng chung với trang quản lý (`vhcc_token`). Thẻ của trạm mang
   vai 'CC_ONLINE' — hệ quản trị luôn chối nó, và ngược lại. Để chung một khoá thì nhân viên
   đăng nhập trạm trên máy quầy là xoá luôn phiên của quản lý đang mở tab bên cạnh, mà không ai
   hiểu vì sao mình bị đá ra. */
var KHOA_PHIEN = 'cc_session';
var RONG_ANH   = 720;            /* 🔴 ràng buộc 2: thu nhỏ về 720px TRƯỚC khi gửi */

function el(id){ return document.getElementById(id); }

/* ================================================================ BẮT MỌI LỖI, HIỆN LÊN TRANG
 *
 * 🔴 BA LẦN LIỀN TRANG ĐỨNG IM MÀ KHÔNG AI BIẾT VÌ SAO. Người dùng chụp được đúng cái màn hình
 *    im ấy; em nhìn ảnh cũng chỉ đoán. Trên điện thoại thì không có cách nào mở bảng lỗi của
 *    trình duyệt ra xem.
 *
 *    Một lỗi JavaScript ở bất kỳ đâu — gõ nhầm tên biến, trình duyệt cũ thiếu một hàm, một
 *    Promise không ai bắt — đều làm phần còn lại của trang ngừng chạy, LẶNG LẼ. Nên: bắt hết,
 *    in thẳng lên trang. Xấu thì xấu, nhưng nó nói được, còn màn hình im thì không.
 *
 * ⚠️ Gắn TRƯỚC mọi thứ khác, để bắt được cả lỗi của chính đoạn khởi động bên dưới.
 */
function loiToanCuc(chu){
	var o = document.getElementById('loiChet');
	if(!o){
		o = document.createElement('div');
		o.id = 'loiChet';
		o.style.cssText = 'position:fixed;left:0;right:0;top:0;z-index:99;background:#7f1d1d;'
			+ 'color:#fecaca;border-bottom:2px solid #b91c1c;padding:10px 12px;font-size:12.5px;'
			+ 'line-height:1.45;max-height:45vh;overflow:auto;white-space:pre-wrap';
		if(document.body){ document.body.appendChild(o); }
	}
	o.textContent = '⚠ Trang gặp lỗi — chụp màn hình này gửi kỹ thuật:\n' + chu;
}
/* 🔴 08/09/2026 — LỖI CỦA ỨNG DỤNG MỞ TRANG, KHÔNG PHẢI LỖI CỦA TRANG.
   Anh Thắng chụp màn hình: dải đỏ "Trang gặp lỗi" ghi `ReferenceError: Can't find variable:
   zaloJSV2`. `zaloJSV2` KHÔNG có ở đâu trong mã của mình — đó là cầu nối do trình duyệt trong
   Zalo tự chèn vào trang, và chính nó lỗi. Trang mình vẫn chạy bình thường.
   Nhưng bộ bắt lỗi ở trên bắt HẾT, nên nó dựng dải đỏ báo "trang gặp lỗi" cho một lỗi mình
   không gây ra và cũng không sửa được — vừa làm người dùng sợ, vừa CHE mất lỗi thật nếu có lỗi
   thật xảy ra sau đó (dải chỉ hiện một nội dung).
   ⚠️ VẪN IN RA, chỉ hạ xuống một dòng xám và nói đúng nó của ai — cái nếp "bắt hết, in thẳng
      lên trang" là thứ đã cứu ba lần trước, đừng đổi thành im lặng.
   ⚠️ Danh sách hẹp, chỉ mấy cái cầu nối đã gặp thật. Đừng nới thành "mọi ReferenceError": gõ
      nhầm tên biến trong mã mình cũng ra đúng loại lỗi ấy, mà đó là lỗi PHẢI thấy. */
function loiCuaUngDung(chu){
	return /Can't find variable:\s*(zalo|Zalo|fb|FB|messenger|line)[A-Za-z0-9_]*/.test(chu)
		|| /\b(zaloJSV?\d*|ZaloJSV?\d*)\b.*(not defined|undefined)/.test(chu);
}
function ghiChuUngDung(chu){
	var o = document.getElementById('loiUngDung');
	if(!o){
		o = document.createElement('div');
		o.id = 'loiUngDung';
		o.style.cssText = 'position:fixed;left:0;right:0;bottom:0;z-index:98;background:#1e293b;'
			+ 'color:#94a3b8;padding:6px 10px;font-size:11.5px;line-height:1.4;max-height:22vh;'
			+ 'overflow:auto;white-space:pre-wrap';
		if(document.body){ document.body.appendChild(o); }
	}
	o.textContent = 'Ghi chú: ứng dụng đang mở trang này (Zalo/Facebook…) báo lỗi của CHÍNH NÓ — '
		+ 'không phải lỗi trang chấm công, cứ dùng bình thường:\n' + chu;
}
window.addEventListener('error', function(e){
	var chu = (e && e.message ? e.message : 'lỗi không rõ')
		+ (e && e.filename ? '\n' + String(e.filename).split('/').pop() + ':' + e.lineno : '');
	if(loiCuaUngDung(e && e.message ? String(e.message) : '')){ ghiChuUngDung(chu); return; }
	loiToanCuc(chu);
});
window.addEventListener('unhandledrejection', function(e){
	var r = e && e.reason;
	loiToanCuc('Lượt gọi hỏng mà không ai bắt: ' + ( r && r.message ? r.message : String(r) ));
});
function hien(x,co){ el(x).classList[co?'remove':'add']('an'); }
function esc(s){ var d=document.createElement('div'); d.textContent=(s===null||s===undefined)?'':String(s); return d.innerHTML; }
function bao(o,kieu,chu){ el(o).innerHTML = chu ? '<div class="'+kieu+'">'+esc(chu)+'</div>' : ''; }

/* ---------------------------------------------------------------- gọi máy chủ */
/**
 * Gọi máy chủ.
 *
 * 🔴 `r.json()` KHÔNG ĐƯỢC GỌI TRẦN. Khi máy chủ trả lỗi 500, hoặc hosting chèn một trang
 *    chặn, thì thân trả về là HTML — `r.json()` ném một lỗi kiểu "Unexpected token <", và lỗi
 *    ấy trôi vào `.catch` của chỗ gọi rồi bị nuốt. Kết quả đúng như màn hình anh Thắng chụp
 *    lúc 16:44: tên "—", giờ "--:--:--", hai khối "Đang tải…" nằm im mãi mãi. Không có gì đỏ,
 *    không có gì để bấm, và không ai đoán được chuyện gì đang xảy ra.
 *
 *    Nên: đọc thân ra CHỮ trước, tự phân tích, và khi không phải JSON thì ném một lỗi NÓI ĐƯỢC
 *    — mã HTTP là bao nhiêu, máy chủ trả về cái gì. Đó là thứ anh Thắng chụp lại được và em
 *    đọc ra ngay.
 */
var CHO_TOI_DA = 10000;   /* ms — quá lâu thì coi như máy chủ không trả lời.
   Mười giây, không phải mười lăm: người ta đang đứng chờ để vào ca, và mười lăm giây nhìn một
   màn hình không nhúc nhích đủ để họ bỏ đi gọi quản lý. */

/* 🔴 08/09/2026 — LƯỢT CÓ ẢNH PHẢI ĐƯỢC CHỜ LÂU HƠN.
   Anh Thắng chụp màn "Máy chủ không trả lời sau 10 giây" ngay ở nút LƯU CHẤM CÔNG, mở trong
   trình duyệt của Zalo, mạng 5G.
   Mười giây là hạn đặt cho mấy lượt gọi NHẸ (giờ máy chủ, thông tin tôi, phiên) — ở đó người ta
   đứng nhìn một màn hình trống nên phải nói sớm. Nhưng lượt `cham` là lượt DUY NHẤT mang ẢNH:
   720px q0.8 gói base64 ra ~100–200 KB. Đường lên của 4G/5G trong nhà, qua webview của Zalo,
   cộng thêm một host chậm là quá 10 giây rất dễ — mà lúc đó ẢNH ĐÃ ĐI RỒI, chỉ là câu trả lời
   chưa kịp về. Cắt ở 10 giây là báo "hosting quá tải" cho một lượt vẫn đang chạy tử tế, và người
   ta bấm lại lần nữa — hai lượt chấm công cho một lần vào ca.
   ⚠️ KHÔNG nới hạn của mấy lượt nhẹ: chờ 25 giây một cái tên là màn hình đứng im quá lâu, đúng
      thứ mà hạn 10 giây được đặt ra để chặn. */
var CHO_CO_ANH = 25000;

function goi(viec, than, cho){
	var url = CFG.cong + (CFG.cong.indexOf('?')>=0?'&':'?') + 'viec=' + encodeURIComponent(viec);
	var ma  = 0;

	/* 🔴 FETCH PHẢI CÓ THỜI HẠN. Đây là chỗ hổng còn lại sau lần sửa trước: bản ấy đã báo được
	   lỗi khi máy chủ trả về thứ không đọc nổi, nhưng nếu máy chủ NHẬN request rồi không trả
	   lời gì — PHP chạy mãi, tường lửa nuốt gói tin, mạng rớt giữa chừng — thì `fetch` không
	   hỏng mà cũng không xong. Nó treo. Và một Promise treo thì `.then` không chạy, `.catch`
	   cũng không: màn hình đứng ở "Đang tải…" vĩnh viễn, đúng ảnh anh Thắng chụp — lần này
	   KHÔNG có cả dòng lỗi đỏ, vì chẳng có lỗi nào được ném ra cả.

	   Đợi vô hạn không bao giờ là câu trả lời đúng. Mười lăm giây rồi nói thật. */
	var het  = null;
	var han  = cho || CHO_TOI_DA;
	var chan = ( typeof AbortController !== 'undefined' ) ? new AbortController() : null;
	var tuy  = {
		method:'POST', credentials:'same-origin',
		headers:{'Content-Type':'application/json'},
		body: JSON.stringify(than||{})
	};
	if(chan){ tuy.signal = chan.signal; }

	return new Promise(function(xong, hong){
		het = setTimeout(function(){
			if(chan){ try { chan.abort(); } catch(e){} }
			hong(new Error('Máy chủ không trả lời sau ' + Math.round(han/1000)
				+ ' giây. Thường là hosting đang quá tải hoặc chặn đường này — thử lại, '
				+ 'nếu vẫn vậy thì báo quản trị xem nhật ký lỗi. [QUA-HAN: ' + viec + ']'));
		}, han);
		/* 🔴 08/09/2026 — anh Thắng: *"tại báo cáo lỗi không rõ ràng"*.
		   `fetch` hỏng thì ném đúng chữ của trình duyệt: "Failed to fetch" (Chrome),
		   "Load failed" (Safari/webview Zalo), "NetworkError…" (Firefox) — ba câu tiếng Anh,
		   không câu nào nói được phải làm gì. Dịch ra một câu nói được VIỆC PHẢI LÀM, và giữ
		   nguyên chữ gốc trong ngoặc để còn đối chiếu khi anh chụp màn gửi về. */
		fetch(url, tuy).then(xong, function(e){
			hong(new Error('Không gửi được lên máy chủ — mất mạng giữa chừng, hoặc trình duyệt '
				+ 'chặn đường này. Kiểm tra sóng rồi bấm lại. [MAT-MANG: '
				+ ((e && e.message) || 'không rõ') + ']'));
		});
	}).then(function(r){
		clearTimeout(het);
		return r;
	}, function(e){
		clearTimeout(het);
		throw e;
	}).then(function(r){
		ma = r.status;
		return r.text();
	}).then(function(chu){
		var j = null;
		try { j = JSON.parse(chu); }
		catch(e){
			var goi_y = '';
			if(ma === 0)   { goi_y = ' Mất mạng giữa chừng.'; }
			if(ma >= 500)  { goi_y = ' Máy chủ đang lỗi — báo quản trị xem nhật ký lỗi của hosting.'; }
			if(ma === 403) { goi_y = ' Hosting đang chặn đường này (tường lửa).'; }
			if(ma === 404) { goi_y = ' Sai đường dẫn trang — vào Cài đặt bấm Lưu để nạp lại luật đường.'; }
			/* Kèm mấy chữ đầu của thứ nhận được: một trang lỗi PHP hay trang chặn của hosting
			   thường lộ nguyên nhân ngay dòng đầu. */
			var dau = String(chu || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 120);
			throw new Error('Máy chủ trả về nội dung không đọc được (mã ' + ma + ').' + goi_y
				+ (dau ? ' Máy chủ nói: ' + dau : '') + ' [HTTP-' + ma + ': ' + viec + ']');
		}
		if(j && j.ma==='het_phien'){ dangXuat(true); throw new Error(j.error||'Phiên đã hết'); }
		return j;
	});
}

function token(){ try{ return localStorage.getItem(KHOA_PHIEN)||''; }catch(e){ return ''; } }
function datToken(t){ try{ t?localStorage.setItem(KHOA_PHIEN,t):localStorage.removeItem(KHOA_PHIEN); }catch(e){} }

/* ---------------------------------------------------------------- 🔴 ràng buộc 1: GIỜ MÁY CHỦ
   Lấy mốc từ máy chủ MỘT lần rồi để nó tự trôi theo đồng hồ máy. Không bao giờ đọc
   `new Date()` làm giờ hiển thị hay giờ đóng dấu — điện thoại lệch giờ là chuyện thường, và
   một tấm ảnh in sai giờ là bằng chứng nói ngược lại hàng đã ghi. */
var MOC = null;   /* {sec: giây epoch máy chủ, tuLuc: performance.now() lúc nhận, ve: vé đã ký} */

function napGio(){
	/* Gửi kèm thẻ phiên để máy chủ phát VÉ GIỜ (xem VHCC_Tram::ve_gio). Màn đăng nhập cũng gọi
	   hàm này lúc chưa có thẻ — lúc ấy không có vé, và đúng: chưa đăng nhập thì chưa chấm. */
	return goi('gio',{token:token()}).then(function(j){
		if(j && j.ok){ MOC = { sec: Number(j.moc)||0, tuLuc: performance.now(), ve: j.ve || '' }; }
		return j;
	}).catch(function(e){
		/* Đồng hồ đứng ở "--:--:--" là dấu hiệu đầu tiên người ta nhìn thấy khi máy chủ hỏng —
		   nói ngay tại đó, đừng để họ ngồi đợi một cái đồng hồ không bao giờ chạy. */
		el('ngayMC').textContent = 'không lấy được giờ máy chủ';
		bao('trangThai','dong', (e && e.message) || 'Không gọi được máy chủ.');
		return null;
	});
}

/** Giờ máy chủ NGAY BÂY GIỜ. null = chưa lấy được mốc (và lúc đó KHÔNG được đoán). */
function gioMayChu(){
	if(!MOC) return null;
	return new Date((MOC.sec*1000) + (performance.now() - MOC.tuLuc));
}
function hai(n){ return (n<10?'0':'')+n; }
function chuGio(d){ return hai(d.getUTCHours())+':'+hai(d.getUTCMinutes())+':'+hai(d.getUTCSeconds()); }
function chuNgay(d){ return hai(d.getUTCDate())+'/'+hai(d.getUTCMonth()+1)+'/'+d.getUTCFullYear(); }
/* Mốc từ máy chủ là `current_time('timestamp')` — đã CỘNG lệch múi giờ WordPress. Nên đọc bằng
   getUTC* mới ra đúng giờ Việt Nam; đọc bằng getHours() là cộng lệch máy điện thoại lần thứ hai. */

function nhipDongHo(){
	var d = gioMayChu();
	if(!d){ return; }
	el('gioMC').textContent = chuGio(d);
	el('ngayMC').textContent = chuNgay(d);
}
setInterval(nhipDongHo, 1000);

/* ---------------------------------------------------------------- GPS (không chặn)

   🔴 KHÔNG CHẶN CHẤM CÔNG. Vị trí là thứ ghi kèm để đối chiếu, không phải điều kiện để chấm:
      trong nhà kho, dưới hầm gửi xe, máy cũ tắt định vị — thiếu sóng GPS là chuyện thường, mà
      giờ vào thì không đợi được. Không lấy được thì vẫn chấm, phiếu ghi "KHÔNG có GPS".

   ⚠️ NÓI RA TRẠNG THÁI, ĐỪNG IM. Bản trước nuốt lỗi (`GPS = null` rồi thôi) nên người dùng
      không biết phiếu của mình có toạ độ hay không, và cũng không biết vì sao không có. Ba
      nguyên nhân dẫn tới ba cách sửa KHÁC HẲN nhau, nên phải phân biệt:
        · bị TỪ CHỐI QUYỀN  -> vào Cài đặt trình duyệt bật lại (người dùng tự sửa được)
        · KHÔNG BẮT ĐƯỢC SÓNG -> ra chỗ thoáng, bấm lấy lại
        · máy KHÔNG HỖ TRỢ  -> không sửa được, đừng bắt họ thử mãi                            */
var GPS = null;
var GPS_TRANG = 'chua';   /* chua | dangxin | co | choi | hong | khong_ho_tro */

/**
 * Độ chính xác nói lên điều gì.
 *
 * 🔴 ±200000m LÀ VỊ TRÍ THEO ĐỊA CHỈ MẠNG, KHÔNG PHẢI GPS. Trình duyệt vẫn trả về một cặp toạ
 *    độ trông rất thật (10.775500,106.702100 — trung tâm TP.HCM), kèm sai số 200 KILÔMÉT. Ai
 *    đọc phiếu mà chỉ nhìn cặp số ấy sẽ tưởng đã xác nhận được người này đứng ở đâu, trong khi
 *    nó chỉ nói "đâu đó ở miền Nam". Đây là kiểu sai nguy hiểm nhất: có số, trông đúng, và sai.
 *
 * Nên: chia mức, nói thẳng mức nào dùng được vào việc gì.
 */
function mucGps(acc){
	if(acc <= 50)   return 'tot';     /* GPS đã khoá — đủ để nói đứng ở toà nhà nào */
	if(acc <= 200)  return 'tam';     /* GPS yếu hoặc Wi-Fi tốt — đủ để nói đúng khu phố */
	if(acc <= 2000) return 'tho';     /* Wi-Fi / trạm phát sóng — chỉ đúng phường, quận */
	return 'mang';                     /* theo địa chỉ mạng — KHÔNG dùng để xác nhận có mặt */
}

/** 1234 -> "1,2km"; 85 -> "85m". Đọc "±200000m" thì không ai thấy nó to cỡ nào. */
function dai(m){
	m = Math.round(Number(m) || 0);
	if(m < 1000) return m + 'm';
	return (m / 1000).toFixed(m < 10000 ? 1 : 0).replace('.', ',') + 'km';
}

/* ══════════════════════════════════════════════════════════════════════════════════════════
 * Ô BẢN ĐỒ ĐÓNG LÊN ẢNH — anh Thắng 17/09/2026: *"kèm bản đồ được không"*.
 *
 * ══════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 TẢI SẴN, KHÔNG TẢI LÚC BẤM CHỤP
 * ══════════════════════════════════════════════════════════════════════════════════════════
 * Bấm chụp là thao tác KHÔNG ĐƯỢC PHÉP HỎNG. Thêm một lượt tải ảnh từ mạng vào đúng khoảnh
 * khắc ấy là thêm một chỗ treo, mà người ta đang đứng giơ điện thoại. Nên: lấy được GPS thì
 * tải ngay ô bản đồ, cất sẵn trong bộ nhớ; lúc chụp chỉ VẼ cái đã có. Chưa kịp tải thì bỏ qua
 * — ảnh vẫn có dấu giờ và dấu toạ độ như thường.
 *
 * ══════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 LẤY Ô ẢNH QUA `urlO()` — MÁY CHỦ MÌNH, KHÔNG MÓC THẲNG VÀO OSM
 * ══════════════════════════════════════════════════════════════════════════════════════════
 * Bản nháp của tệp này gọi thẳng `tile.openstreetmap.org`. `tools/test/kiem-tram.php` đỏ ngay,
 * và nó đỏ vì KHO ĐÃ TRẢ GIÁ CHO ĐÚNG CHUYỆN NÀY HAI LẦN (xem chú thích ở phép thử ấy):
 *   1. nhúng `<iframe> openstreetmap.org` -> "đã từ chối kết nối", JavaScript không bắt được
 *      lỗi để hiện thứ khác thay;
 *   2. tải thẳng ô ảnh từ `tile.openstreetmap.org` -> ô trắng, dấu hỏi ảnh vỡ. Chính sách của
 *      họ KHÔNG cho một trang bất kỳ móc thẳng vào máy chủ ô ảnh — họ chặn, và họ đúng.
 * Cách đang dùng: MÁY CHỦ MÌNH tải hộ một lần rồi nhớ lại (`?viec=o&z=..`), đúng cái `urlO()`
 * mà bản đồ trên màn hình đã dùng.
 *
 * ⚠️ VÀ NÓ GIẢI QUYẾT LUÔN CHUYỆN CANVAS NHIỄM. Ảnh về từ CÙNG TÊN MIỀN nên không có rào CORS
 *    nào cả — `toDataURL()` chạy bình thường. Nếu lấy từ tên miền khác mà thiếu CORS thì canvas
 *    bị "nhiễm" và `toDataURL()` NÉM LỖI: mất cả tấm ảnh chứ không phải mất mỗi ô bản đồ.
 *    Vẫn giữ `try` quanh chỗ vẽ — rẻ, và là chốt cuối.
 *
 * ══════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 CHỈ TẢI KHI GPS THẬT
 * ══════════════════════════════════════════════════════════════════════════════════════════
 * Sai số ±200km mà vẽ một cái chấm giữa Quận 1 là NÓI DỐI BẰNG HÌNH ẢNH — người xem tin vào
 * cái chấm chứ không đọc dòng ±. Đây đúng là lý do `veViTri()` không vẽ bản đồ ở mức ấy, và ô
 * trên ảnh phải theo cùng luật.
 *
 * ⚠️ GHI NGUỒN LÀ BẮT BUỘC, KHÔNG PHẢI TRANG TRÍ. Bản đồ OpenStreetMap phát hành theo giấy
 *    phép ODbL; dùng mà không ghi "© OpenStreetMap" là vi phạm. Chữ ấy vẽ ngay trên ô, không
 *    được bỏ để "cho gọn".
 * ══════════════════════════════════════════════════════════════════════════════════════════ */
var BANDO = null;        /* Image đã tải xong, kèm vị trí tương đối của điểm trong ô */
var BANDO_KHOA = '';     /* toạ độ đã tải, để đứng yên một chỗ thì không tải lại */

var BANDO_Z = 16;        /* ~2,4 m/điểm ảnh — một ô phủ khoảng 600m, đủ nói "toà nhà nào" */

function taiBanDo(){
	if(GPS_TRANG !== 'co' || !GPS) return;
	if(mucGps(GPS.acc) === 'mang') return;    /* xem khối chú thích trên: không vẽ ở mức này */

	var khoa = GPS.lat.toFixed(4) + ',' + GPS.lng.toFixed(4);
	if(khoa === BANDO_KHOA) return;           /* cùng một chỗ (±11m) — khỏi tải lại */
	BANDO_KHOA = khoa;

	/* Toạ độ → ô bản đồ (Web Mercator). `fx`/`fy` là vị trí LẺ của điểm bên trong ô, dùng để
	   đặt cái ghim đúng chỗ — ranh giới ô là cố định nên điểm hiếm khi nằm giữa. */
	var n = Math.pow(2, BANDO_Z);
	var xf = (GPS.lng + 180) / 360 * n;
	var la = GPS.lat * Math.PI / 180;
	var yf = (1 - Math.log(Math.tan(la) + 1 / Math.cos(la)) / Math.PI) / 2 * n;
	var xt = Math.floor(xf), yt = Math.floor(yf);

	var im = new Image();
	im.onload  = function(){ BANDO = { im: im, fx: xf - xt, fy: yf - yt }; };
	im.onerror = function(){ BANDO = null; }; /* hụt thì thôi, ảnh vẫn chụp được */
	im.src = urlO(BANDO_Z, xt, yt);           /* 🔴 máy chủ MÌNH — xem chú thích trên */
}

function veViTri(){
	var e = el('oViTri');
	if(!e) return;

	if(GPS_TRANG === 'co' && GPS){
		var q  = GPS.lat.toFixed(6) + ',' + GPS.lng.toFixed(6);
		var m  = mucGps(GPS.acc);
		var lk = '<p style="margin:8px 0 0"><a target="_blank" rel="noopener"'
		       + ' href="https://maps.google.com/?q=' + encodeURIComponent(q) + '">Mở Google Maps ↗</a></p>';

		if(m === 'mang'){
			/* KHÔNG vẽ bản đồ ở mức này. Vẽ một chấm đỏ giữa Quận 1 khi sai số là 200km chính
			   là nói dối bằng hình ảnh — người xem tin vào cái chấm chứ không đọc dòng ±. */
			e.innerHTML = '<div class="vang" style="margin:0">📍 <b>Chưa bắt được GPS thật.</b> '
				+ 'Toạ độ đang lấy theo <b>địa chỉ mạng</b>, sai số ±' + dai(GPS.acc) + ' — '
				+ 'chỉ nói được "đâu đó trong vùng này", không xác nhận được anh/chị đứng ở đâu.'
				+ '<br>Bật <b>Dịch vụ định vị</b> trong Cài đặt máy, ra chỗ thoáng, rồi bấm '
				+ '"Lấy lại vị trí". Vẫn chấm công được — phiếu sẽ ghi rõ là vị trí ước lượng.</div>'
				+ '<p class="ct" style="margin:8px 0 0;text-align:left">' + esc(q) + ' (±'
				+ dai(GPS.acc) + ')</p>' + lk;
			return;
		}

		var them = '';
		if(m === 'tam'){ them = '<br><span style="opacity:.85">GPS chưa khoá hẳn — đúng khu phố, '
			+ 'chưa chắc đúng toà nhà. Đợi vài giây hoặc ra chỗ thoáng thì số này nhỏ lại.</span>'; }
		if(m === 'tho'){ them = '<br><span style="opacity:.85">Đang lấy theo Wi-Fi / trạm phát sóng, '
			+ 'chưa phải GPS. Ra chỗ thoáng rồi bấm "Lấy lại vị trí".</span>'; }

		/* Tên lớp viết nội tuyến từ hằng, không đi qua biến: bộ kiểm giao diện canh "mọi thứ
		   ghép vào innerHTML phải là hằng hoặc đã esc()", và nó canh đúng — hôm nay là tên lớp
		   do mình đặt, mai có người sửa thành giá trị lấy từ máy chủ thì chốt ấy phải còn. */
		e.innerHTML = '<div class="' + ( 'tot' === m ? 'xanh' : 'vang' ) + '" style="margin:0">📍 <b>'
			+ esc(q) + '</b>'
			+ ' <span style="opacity:.75">(±' + dai(GPS.acc) + ')</span>'
			+ '<div id="oDiaChi" style="margin-top:4px;opacity:.9"></div>' + them + '</div>'
			+ veBanDo(GPS.lat, GPS.lng, GPS.acc)
			/* Vẫn giữ link ra Google Maps: bản đồ ở đây đủ để thấy "mình đang ở đâu", còn khi
			   cần chỉ đường hay xem ảnh phố thì mở ứng dụng bản đồ thật vẫn hơn. */
			+ lk;
		nghenBanDo();   /* phải gọi SAU khi đã chèn HTML — trước đó chưa có thẻ nào để nghe */
		xinDiaChi();
		return;
	}

	if(GPS_TRANG === 'dangxin'){
		/* Đang chờ GPS khoá: nếu đã có một vị trí thô rồi thì nói ra, đừng để màn hình câm —
		   người ta cần biết máy vẫn đang cố, chứ không phải đã treo. */
		e.innerHTML = '<p class="trong">Đang lấy vị trí…'
			+ (GPS ? ' (hiện ±' + dai(GPS.acc) + ', đang chờ chính xác hơn)' : '') + '</p>';
		return;
	}
	if(GPS_TRANG === 'khong_ho_tro'){
		e.innerHTML = '<div class="vang" style="margin:0">📍 Máy này không hỗ trợ định vị. '
			+ 'Vẫn chấm công được — phiếu sẽ ghi <b>KHÔNG có GPS</b>.</div>';
		return;
	}
	if(GPS_TRANG === 'choi'){
		e.innerHTML = '<div class="vang" style="margin:0">📍 <b>Trình duyệt đang chặn định vị.</b> '
			+ 'Bấm biểu tượng ổ khoá 🔒 cạnh địa chỉ web → cho phép <b>Vị trí</b> → bấm "Lấy lại vị trí". '
			+ 'Vẫn chấm công được — phiếu sẽ ghi <b>KHÔNG có GPS</b>.</div>';
		return;
	}
	if(GPS_TRANG === 'hong'){
		e.innerHTML = '<div class="vang" style="margin:0">📍 Chưa bắt được vị trí (trong nhà hay '
			+ 'dưới hầm hay bị vậy). Ra chỗ thoáng rồi bấm "Lấy lại vị trí". '
			+ 'Vẫn chấm công được — phiếu sẽ ghi <b>KHÔNG có GPS</b>.</div>';
		return;
	}
	e.innerHTML = '<p class="trong">Chưa lấy vị trí.</p>';
}

/**
 * Bản đồ quanh chỗ đang đứng — GHÉP TỪ Ô ẢNH, KHÔNG DÙNG IFRAME.
 *
 * =========================================================================================
 * 🔴 VÌ SAO BỎ IFRAME: "www.openstreetmap.org đã từ chối kết nối"
 * =========================================================================================
 * Bản trước nhúng `openstreetmap.org/export/embed.html` bằng <iframe>. Trên máy anh Thắng nó
 * ra đúng một khung xám với dòng "đã từ chối kết nối" — máy chủ OSM trả tiêu đề chặn nhúng
 * (X-Frame-Options / frame-ancestors), và trình duyệt bỏ luôn khung, không có cách nào bắt
 * lỗi bằng JavaScript để hiện thứ khác thay thế. Nhúng khung của người khác là đặt một mảnh
 * giao diện của mình dưới quyền quyết định của họ.
 *
 * Ô ảnh thì khác: nó chỉ là <img>. Không ai chặn được bằng tiêu đề khung, và nếu tải hỏng thì
 * `onerror` bắt được — bản đồ tự ẩn đi, còn lại dòng toạ độ và link, chứ không để một khung
 * xám báo lỗi giữa trang chấm công.
 *
 * Ghép 3×3 ô 256px quanh điểm cần xem, dịch bằng lề âm cho điểm ấy nằm đúng giữa khung. Toán
 * là phép chiếu Web Mercator chuẩn — cùng công thức mọi thư viện bản đồ dùng, chỉ là mình tự
 * viết mười dòng thay vì kéo về một thư viện 150KB cho một cái bản đồ tĩnh.
 *
 * ⚠️ TẢI Ô ẢNH LÀ GỬI TOẠ ĐỘ RA NGOÀI. Đường dẫn ô ảnh chứa vị trí, nên máy chủ OSM biết
 *    vùng đang xem. Chấp nhận được (phi lợi nhuận, không quảng cáo) nhưng là một lựa chọn.
 *    `referrerpolicy="origin"` gửi mỗi tên miền, không gửi đường dẫn trang.
 *
 * ⚠️ Chỉ vẽ khi độ chính xác đủ tốt — xem `veViTri()`. Chấm đỏ giữa Quận 1 với sai số 200km
 *    là nói dối bằng hình ảnh.
 */
function veBanDo(lat, lng, acc){
	/* Sai số càng lớn thì kéo càng xa: phóng to hết cỡ trong khi máy chỉ biết mình ở đâu đó
	   trong bán kính 500m là vẽ một chấm rất chính xác vào một chỗ rất có thể sai. */
	var z = ( acc <= 60 ) ? 17 : ( acc <= 200 ? 16 : 15 );
	var n  = Math.pow(2, z);
	var xf = (lng + 180) / 360 * n;
	var la = lat * Math.PI / 180;
	var yf = (1 - Math.log(Math.tan(la) + 1 / Math.cos(la)) / Math.PI) / 2 * n;
	var x  = Math.floor(xf), y = Math.floor(yf);
	var px = 256 + Math.round((xf - x) * 256);   /* vị trí điểm trong lưới 3×3 */
	var py = 256 + Math.round((yf - y) * 256);

	var h = '<div class="bando"><div class="luoi" style="margin-left:' + (-px) + 'px;margin-top:'
	      + (-py) + 'px">';
	for(var dy = -1; dy <= 1; dy++){
		for(var dx = -1; dx <= 1; dx++){
			var tx = ((x + dx) % n + n) % n;      /* vòng quanh quả đất theo chiều ngang */
			var ty = y + dy;
			if(ty < 0 || ty >= n){ h += '<i class="o"></i>'; continue; }   /* quá cực, ô trống */
			h += '<img class="o" alt="" loading="lazy" src="' + esc(urlO(z, tx, ty)) + '">';
		}
	}
	h += '</div><b class="cham"></b>'
	   + '<span class="ghi">© OpenStreetMap</span></div>';
	return h;
}

/* Ô ảnh lấy từ MÁY CHỦ MÌNH, không lấy thẳng từ openstreetmap.org.
   Lấy thẳng đã thử và hỏng: ô trắng, một ô hiện dấu hỏi ảnh vỡ. Chính sách dùng ô ảnh của
   OpenStreetMap không cho một trang bất kỳ móc thẳng vào máy chủ ô ảnh của họ — họ chặn, và
   họ đúng. Nay máy chủ mình tải hộ một lần rồi nhớ lại; xem VHCC_BanDo. */
function urlO(z, x, y){
	return CFG.cong + (CFG.cong.indexOf('?') >= 0 ? '&' : '?')
	     + 'viec=o&z=' + z + '&x=' + x + '&y=' + y;
}

/**
 * Ô ảnh tải hỏng -> ẩn cả bản đồ.
 *
 * 🔴 GẮN BẰNG addEventListener, KHÔNG DÙNG onerror="..." TRONG HTML.
 *    Lỗi thật vừa gặp: cả tệp JavaScript này nằm trong một hàm bọc kín `(function(){…})()`,
 *    nên `banDoHong` KHÔNG có mặt ở phạm vi toàn cục. Mà thuộc tính `onerror` trong HTML thì
 *    chạy ở đúng phạm vi toàn cục ấy — nó gọi một cái tên không tồn tại, ném lỗi, và cái việc
 *    cần làm (ẩn bản đồ) không bao giờ chạy. Kết quả trên máy anh Thắng: khung bản đồ nằm đó
 *    với chín ô trắng và một dấu hỏi, trông như trang hỏng.
 *
 *    Đây là loại lỗi im lặng đúng nghĩa: không có gì đỏ, chỉ có một thứ đáng lẽ phải biến mất
 *    thì lại nằm nguyên.
 *
 * ⚠️ Ẩn khi có BẤT KỲ ô nào hỏng, không đợi hỏng hết. Một bản đồ thủng lỗ chỗ còn khó hiểu
 *    hơn là không có bản đồ.
 */
/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * TÊN ĐƯỜNG CỦA CHỖ ĐANG ĐỨNG — anh Thắng 20/09/2026: *"Không thấy địa chỉ"*.
 *
 * Cặp số `10.7755,106.7021` không nói được cho ai điều gì. "12 Nguyễn Huệ, Bến Nghé" thì người
 * đang đứng tự biết mình có ở đúng chỗ hay không, và quản lý đọc bảng cũng vậy.
 *
 * 🔴 HỎI RỒI QUÊN ĐI. Không `await`, không chặn gì, không báo lỗi. Tên đường là thứ ĐỌC CHO
 *    SƯỚNG MẮT, còn cặp số mới là cái đi vào phiếu công. Cho nó chặn được bất cứ thứ gì —
 *    nút chấm công, ô bản đồ, dòng toạ độ — là đánh đổi một tính năng phụ lấy chính việc
 *    người ta cần làm. Máy chủ chưa tra ra thì ô này trống, thế thôi.
 *
 * ⚠️ MỘT LƯỢT HỎI CHO MỖI Ô LƯỚI, nhớ ngay trong trang. `veViTri()` chạy lại mỗi lần GPS nhích
 *    một chút — mà GPS thì nhích liên tục — nên không nhớ là mỗi vài giây một lượt gọi máy chủ
 *    cho cùng một chỗ đứng. Làm tròn 4 chữ số ở đây cho khớp với ô lưới bên máy chủ.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
var DIA_CHI_NHO = {};
function xinDiaChi(){
	if(!GPS || !GPS.lat || !GPS.lng) return;
	var o = GPS.lat.toFixed(4) + ',' + GPS.lng.toFixed(4);
	var e = el('oDiaChi');
	if(!e) return;
	if(DIA_CHI_NHO[o]){ e.textContent = '↳ ' + DIA_CHI_NHO[o]; return; }
	if(DIA_CHI_NHO[o] === ''){ return; }            /* đã hỏi, chưa có — đừng hỏi lại */
	goi('diachi', { token: token(), lat: GPS.lat, lng: GPS.lng }).then(function(j){
		DIA_CHI_NHO[o] = (j && j.ok && j.diaChi) ? j.diaChi : '';
		if(!DIA_CHI_NHO[o]) return;
		/* Phải kiểm lại: người dùng có thể đã đi sang màn khác, hoặc GPS đã nhảy sang ô khác,
		   trong lúc chờ máy chủ. Dán tên đường của chỗ cũ lên toạ độ mới là nói sai. */
		var e2 = el('oDiaChi');
		if(e2 && GPS && (GPS.lat.toFixed(4) + ',' + GPS.lng.toFixed(4)) === o){
			e2.textContent = '↳ ' + DIA_CHI_NHO[o];
		}
	}).catch(function(){ /* im lặng: xem khối chú thích trên */ });
}

function nghenBanDo(){
	var ds = document.querySelectorAll('.bando img.o');
	for(var i = 0; i < ds.length; i++){
		ds[i].addEventListener('error', function(){
			var b = this.parentNode && this.parentNode.parentNode;
			if(b && b.classList && b.classList.contains('bando')){ b.style.display = 'none'; }
		});
	}
}

/**
 * Xin vị trí — CHỜ GPS KHOÁ, không lấy phát đầu rồi thôi.
 *
 * `getCurrentPosition` trả về NGAY cái đang có sẵn: thường là vị trí đoán theo địa chỉ mạng,
 * sai số hàng chục tới hàng trăm kilômét. Chip GPS cần vài giây tới vài chục giây mới bắt đủ
 * vệ tinh. `watchPosition` bắn liên tục và số sai lệch NHỎ DẦN — giữ lấy lần đo tốt nhất, dừng
 * khi đã đủ tốt hoặc hết giờ chờ. `maximumAge: 0` là bắt buộc: để mặc định thì trình duyệt lại
 * đưa đúng cái vị trí cũ theo mạng ra dùng.
 *
 * ⚠️ Vẫn KHÔNG CHẶN chấm công. Hết giờ chờ mà chỉ có vị trí thô thì dùng vị trí thô, có nhãn
 *    đàng hoàng — người ta đang đứng chờ vào ca, không đợi vệ tinh được.
 */
var GPS_THEO = null;     /* id của watchPosition đang chạy */
var GPS_HEN  = null;     /* hẹn giờ dừng chờ */
var GPS_DU   = 50;       /* mét — đủ tốt thì dừng sớm, khỏi hao pin */
var GPS_CHO  = 20000;    /* ms — chờ tối đa */

function thoiTheoGps(){
	if(GPS_THEO !== null && navigator.geolocation){ navigator.geolocation.clearWatch(GPS_THEO); }
	GPS_THEO = null;
	if(GPS_HEN){ clearTimeout(GPS_HEN); GPS_HEN = null; }
}

function xinGps(){
	if(!navigator.geolocation){ GPS = null; GPS_TRANG = 'khong_ho_tro'; veViTri(); return; }
	thoiTheoGps();
	GPS = null;                      /* đo lại từ đầu, không giữ số cũ của lần đứng chỗ khác */
	GPS_TRANG = 'dangxin';
	veViTri();

	GPS_THEO = navigator.geolocation.watchPosition(function(p){
		var moi = { lat:p.coords.latitude, lng:p.coords.longitude, acc:p.coords.accuracy };
		/* Chỉ nhận khi TỐT HƠN cái đang có. Máy có lúc bắn ra một lần đo tệ hơn ở giữa chừng;
		   nhận bừa là số sai lệch nhảy qua nhảy lại trên màn hình. */
		if(!GPS || moi.acc < GPS.acc){ GPS = moi; }
		if(GPS.acc <= GPS_DU){ thoiTheoGps(); GPS_TRANG = 'co'; }
		veViTri();
		taiBanDo();
	}, function(err){
		/* err.code 1 = PERMISSION_DENIED. Hai mã còn lại (2 hết chỗ dò, 3 quá hạn) đều là
		   "không bắt được sóng" với người dùng, nên gộp — họ làm cùng một việc: ra chỗ thoáng. */
		thoiTheoGps();
		if(GPS){ GPS_TRANG = 'co'; }      /* đã đo được lần nào đó rồi thì giữ, đừng vứt */
		else   { GPS = null; GPS_TRANG = ( err && 1 === err.code ) ? 'choi' : 'hong'; }
		veViTri();
	}, { enableHighAccuracy:true, timeout:GPS_CHO, maximumAge:0 });

	GPS_HEN = setTimeout(function(){
		thoiTheoGps();
		/* Hết giờ chờ: có gì dùng nấy, nhưng `veViTri` sẽ dán nhãn đúng mức. Không có gì thì
		   coi như không bắt được sóng — vẫn chấm công được. */
		GPS_TRANG = GPS ? 'co' : 'hong';
		veViTri();
	}, GPS_CHO);
}

el('btViTri').addEventListener('click', xinGps);

/* ---------------------------------------------------------------- đăng nhập */
var TOI = null;   /* thông tin từ viec=toi */

el('btVao').addEventListener('click', vaoHe);
el('oPin').addEventListener('keydown', function(e){ if(e.key==='Enter'){ vaoHe(); } });

/* ══════════════════════════════════════════════════════════════════════════════════════════
 * 🔐 HÀNG Ô PIN — vẽ và hoạt cảnh (xem khối CSS `.vao-*`). Chỉ là LỚP VẼ: giá trị thật luôn
 * nằm ở `#oPin`, và `vaoHe()` vẫn là đường gửi duy nhất.
 *   · 6 ô mặc định; gõ tới 7–8 số thì tự thêm ô (PIN cũ 4–8 số vẫn vào được).
 *   · Gõ ĐỦ 6 số rồi dừng tay một nhịp là tự gửi; PIN 4–5 số thì bấm VÀO / Enter.
 *   · 26/09/2026 — anh Thắng: *"Tốc độ trên app điện thoại hơi nhanh, chậm chút"* — mọi nhịp
 *     hoạt cảnh chậm lại khoảng 1,5 lần.
 * ══════════════════════════════════════════════════════════════════════════════════════════ */
var VAO = (function(){
	var san = el('vaoSan'), oEl = el('vaoO'), inp = el('oPin'), vong = el('vaoVong'), dau = el('vaoDau');
	var RM = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
	var o = [], n = 0, B = 46, P = [], R = 60, A = {c:0,a:0,s:0,rm:1,v:0}, quay = false, ban = false, no = false, hen = 0;
	function doiSo(k){
		while(o.length < k){ var h = document.createElement('div'); h.className = 'vao-hop'; h.innerHTML = '<i></i>'; oEl.appendChild(h); o.push(h); }
		while(o.length > k){ oEl.removeChild(o.pop()); }
		n = k; bo();
	}
	function bo(){
		var w = san.clientWidth || 300;
		B = Math.min(48, Math.floor(w / (n + (n - 1) * .25)));
		var st = B + Math.round(B * .25);
		R = Math.max(st, st / (2 * Math.sin(Math.PI / n)));
		P = o.map(function(_, i){ var x = (i - (n - 1) / 2) * st; return {r0:Math.abs(x), t0:x < 0 ? 180 : 360, t1:180 + (360 / n) * i}; });
		san.style.setProperty('--vo', B + 'px');
		/* Khung chỉ cao bằng hàng ô lúc gõ; NỞ ra đủ chỗ cho vòng tròn khi đang gửi (`no`). */
		san.style.height = Math.round(no ? R * 2 + B + 24 : B + 24) + 'px';
		inp.style.width = (st * n - Math.round(B * .25)) + 'px';
		vong.style.width = vong.style.height = (R * 2) + 'px';
		vong.style.margin = (-R) + 'px 0 0 ' + (-R) + 'px';
		ve();
	}
	function ve(){
		var rad = Math.PI / 180;
		for(var i = 0; i < n; i++){
			var p = P[i], r = (p.r0 + (R - p.r0) * A.c) * A.rm, th = (p.t0 + (p.t1 - p.t0) * A.c + A.a) * rad;
			o[i].style.transform = 'translate(' + (Math.cos(th) * r).toFixed(1) + 'px,' + (Math.sin(th) * r).toFixed(1) + 'px) rotate(' + A.s.toFixed(1) + 'deg)';
			o[i].style.opacity = A.rm < .3 ? (A.rm / .3).toFixed(2) : '1';
		}
		vong.style.opacity = (A.v * A.rm).toFixed(2);
	}
	function tw(den, ms){
		var tu = {}, k; for(k in den){ tu[k] = A[k]; }
		return new Promise(function(xong){
			if(RM){ for(k in den){ A[k] = den[k]; } ve(); xong(); return; }
			var t0 = performance.now();
			(function buoc(t){
				var q = Math.min(1, (t - t0) / ms), e = q < .5 ? 4 * q * q * q : 1 - Math.pow(-2 * q + 2, 3) / 2;
				for(var kk in den){ A[kk] = tu[kk] + (den[kk] - tu[kk]) * e; }
				ve();
				if(q < 1){ requestAnimationFrame(buoc); } else { xong(); }
			})(t0);
		});
	}
	function gan(){
		var v = inp.value.replace(/\D/g, '').slice(0, 8);
		if(v !== inp.value){ inp.value = v; }
		var k = Math.max(6, v.length);
		if(k !== n){ doiSo(k); }
		for(var i = 0; i < n; i++){
			var co = i < v.length, cu = o[i].classList.contains('co');
			o[i].classList.toggle('co', co);
			if(co && !cu){ o[i].classList.remove('lan'); void o[i].offsetWidth; o[i].classList.add('lan'); }
			o[i].classList.toggle('dang', !ban && i === v.length && document.activeElement === inp);
		}
		clearTimeout(hen);
		if(!ban && v.length === 6){ hen = setTimeout(function(){ if(!ban && inp.value.length === 6){ vaoHe(); } }, 650); }
	}
	function ngu(ms){ return new Promise(function(r){ setTimeout(r, ms); }); }
	inp.addEventListener('input', gan);
	inp.addEventListener('focus', function(){ bo(); gan(); });
	inp.addEventListener('blur', gan);
	window.addEventListener('resize', bo);
	san.addEventListener('pointerdown', function(e){ if(!ban && e.target !== inp){ e.preventDefault(); inp.focus(); } });
	doiSo(6);
	return {
		gan: gan, bo: bo,
		/* Bắt đầu gửi: cuộn thành vòng rồi quay tới khi có kết quả. */
		quay: function(){
			ban = true; no = true; clearTimeout(hen); gan(); inp.blur(); bo();
			if(RM){ return; }
			quay = true;
			ngu(RM ? 0 : 320).then(function(){ return tw({c:1, v:1}, 1000); })
				.then(function lap(){ if(quay){ return tw({a:A.a + 360, s:A.s + 360}, 1400).then(lap); } });
		},
		sai: function(){
			quay = false;
			oEl.classList.add('sai');
			var aT = Math.ceil(A.a / 360) * 360, sT = Math.round((A.s + (aT - A.a)) / 360) * 360;
			return tw({c:0, v:0, a:aT, s:sT}, RM ? 1 : 850).then(function(){
				A.a = 0; A.s = 0; ve();
				oEl.classList.add('lac'); return ngu(700);
			}).then(function(){
				oEl.classList.remove('lac'); oEl.classList.add('xoa'); return ngu(340);
			}).then(function(){
				oEl.classList.remove('xoa', 'sai'); inp.value = ''; ban = false; no = false; bo(); gan();
				try { inp.focus({preventScroll:true}); } catch(e){ inp.focus(); }
			});
		},
		dung: function(){
			quay = false;
			oEl.classList.add('dung');
			return ngu(RM ? 60 : 500).then(function(){
				return tw({rm:0, a:A.a + 240, s:A.s + 160}, RM ? 1 : 950);
			}).then(function(){ dau.classList.add('hien'); return ngu(RM ? 60 : 1100); });
		},
		/* Về trạng thái ban đầu (sau khi vào xong, hoặc đăng xuất). */
		lai: function(){
			quay = false; ban = false; no = false;
			A.c = 0; A.a = 0; A.s = 0; A.rm = 1; A.v = 0;
			oEl.classList.remove('sai', 'dung', 'lac', 'xoa'); dau.classList.remove('hien');
			inp.value = ''; gan(); bo();
		}
	};
})();

function vaoHe(){
	var pin = el('oPin').value.trim();
	bao('loiVao','',null);
	var b = el('btVao');
	if(pin === ''){ el('oPin').focus(); return; }
	b.disabled = true; b.textContent = 'Đang vào…';
	VAO.quay();
	goi('vao',{pin:pin}).then(function(j){
		if(!j || !j.ok){ bao('loiVao','dong', (j&&j.error)||'Không vào được.'); return VAO.sai(); }
		datToken(j.token);
		return VAO.dung().then(function(){
			el('oPin').value='';
			VAO.lai();
			moManChinh();
		});
	}).catch(function(e){ bao('loiVao','dong', e.message||'Lỗi mạng.'); return VAO.sai(); })
	.then(function(){ b.disabled=false; b.textContent='VÀO'; });
}

el('btQuen').addEventListener('click', function(){ hien('mQuen',true); });
el('btDongQuen').addEventListener('click', function(){ hien('mQuen',false); });
el('btTra').addEventListener('click', function(){
	var b=el('btTra'); b.disabled=true; b.textContent='Đang tra…';
	bao('kqQuen','',null);
	goi('quenpin',{cccd:el('oCccd').value}).then(function(j){
		if(!j || !j.ok){ bao('kqQuen','dong',(j&&j.error)||'Không tra được.'); return; }
		el('kqQuen').innerHTML = '<div class="xanh"><b>'+esc(j.ten)+'</b><br>PIN: <b style="font-size:19px">'
			+ esc(j.pin) + '</b><br>Cơ sở: ' + esc(j.coSo||'—') + '</div>';
	}).catch(function(e){ bao('kqQuen','dong', e.message||'Lỗi mạng.'); })
	.then(function(){ b.disabled=false; b.textContent='Tra PIN'; });
});

function dangXuat(imLang){
	datToken('');
	TOI = null;
	/* Máy quầy dùng chung: người sau đăng nhập phải thấy lưới CỦA HỌ, không phải lưới của
	   người trước còn nằm trong DOM. */
	DA_NAP_UNG = false;
	DA_NAP_HS = false;
	var _h = el('oHoSo'); if(_h){ _h.innerHTML = '<p class="trong">Đang tải…</p>'; }
	var _u = el('oUng'); if(_u){ _u.innerHTML = '<p class="trong">Đang tải…</p>'; }
	hien('mChinh',false); hien('mChup',false); hien('mChon',false); hien('thanhTab',false);
	hien('mChuong',false); hien('oChuong',false);
	hien('mVao',true);
	VAO.lai();
	if(!imLang){ bao('loiVao','',null); }
	else { bao('loiVao','vang','Phiên đã hết. Đăng nhập lại bằng PIN.'); }
}
el('btRa').addEventListener('click', function(){
	goi('ra',{token:token()}).catch(function(){});
	dangXuat(false);
});

/* ---------------------------------------------------------------- màn chính */
var DEM_GOI = null;

/* Đếm giây trong lúc chờ máy chủ. Không có nó thì "Đang tải…" và "đã treo" nhìn giống hệt
   nhau — người ta không biết nên chờ thêm hay nên bấm lại. */
function dangGoi(bat){
	if(DEM_GOI){ clearInterval(DEM_GOI); DEM_GOI = null; }
	if(!bat){ el('tinhTrang').innerHTML = ''; return; }
	var t0 = 0;
	var ve = function(){
		/* Số giây đi qua esc() như mọi thứ khác ghép vào innerHTML — nó là số do mình đếm, nhưng
		   chốt "không ghép thẳng biến" canh đúng: hôm nay là số, mai có người sửa thành chữ lấy
		   từ máy chủ. */
		el('tinhTrang').innerHTML = '<div class="vang" style="margin:0 0 10px">Đang gọi máy chủ… '
			+ esc(t0) + ' giây</div>';
	};
	ve();
	DEM_GOI = setInterval(function(){ t0++; ve(); }, 1000);
}

function moManChinh(){
	hien('mVao',false); hien('mQuen',false); hien('mChinh',true);
	hien('thanhTab',true);
	/* Mở ra là ở tab Chấm công — đó là lý do 9/10 lần người ta mở trang này. Giữ tab cũ
	   thì ai vừa xem bảng tháng hôm qua, sáng nay mở lên lại thấy bảng tháng. */
	denTab('tChamCong');
	dangGoi(true);
	xinGps();
	/* 🔴 HỎI QUYỀN CỬA HÀNG NGAY LÚC VÀO, không chờ bấm tab. Nút phải hiện sẵn thì người ta
	   mới biết có tab ấy — cái tab chỉ hiện sau khi bấm vào chính nó là cái tab không ai tìm
	   ra. Lượt hỏi này KHÔNG nằm trong `Promise.all` dưới: hỏng nó thì chỉ thiếu một nút, còn
	   `Promise.all` hỏng là màn hình đứng ở "đang gọi máy chủ". */
	doCuaHang();
	/* ⚠️ BẬT SAU KHI ĐĂNG NHẬP, không bật lúc nạp trang. Bật sớm là mỗi 4 giây một lượt gọi bị
	   chối vì chưa có thẻ phiên — và màn đăng nhập thì có người để mở cả buổi. */
	batChuongGoi();
	/* Hỏi cơ sở này có bật khai loại giờ không — cùng lý do với `doCuaHang()` ở trên: hỏng nó
	   thì chỉ thiếu một câu hỏi lúc kết ca, không được kéo cả màn hình đứng lại. */
	napLoaiGio();
	/* Chờ CẢ HAI lượt rồi mới tắt đồng hồ — tắt sớm là màn hình lại trông như đã xong trong
	   khi một nửa vẫn đang treo. */
	Promise.all([ napGio().then(nhipDongHo), napToi() ])
		.then(function(){ dangGoi(false); }, function(){ dangGoi(false); })
		/* Đẩy hàng đợi SAU khi đã có mốc giờ và hồ sơ: lượt gửi lại cần thẻ phiên còn sống, mà
		   thẻ chỉ chắc chắn còn sống sau khi `napToi()` về không lỗi. */
		.then(veHangCho)
		.then(dayHang);
}

function napToi(){
	return goi('toi',{token:token()}).then(function(j){
		if(!j || !j.ok){ bao('trangThai','dong',(j&&j.error)||'Không đọc được hồ sơ.'); return; }
		if(!j.bat){
			TOI = null;
			el('tenToi').textContent = 'Chưa bật chấm công';
			bao('trangThai','vang', j.ghiChu || 'Tài khoản này chưa bật chấm công online.');
			el('btCham').disabled = true;
			return;
		}
		TOI = j;
		if(j.gio){ MOC = { sec: Number(j.gio.moc)||0, tuLuc: performance.now(), ve: j.gio.ve || '' }; nhipDongHo(); }
		el('tenToi').textContent = j.hoTen || '—';
		el('maToi').textContent  = 'Mã ' + (j.maNV || '—');
		el('csToi').textContent  = j.coSoMacDinh || '—';
		/* Tab Tôi nhắc lại đúng ba thứ ấy. Không phải thừa: trên máy quầy dùng chung, người ta
		   vào tab Tôi để kiểm xem mình có đang đứng nhầm phiên của người trước không. */
		el('tenToi2').textContent = j.hoTen || '—';
		el('moToi2').textContent  = 'Mã ' + (j.maNV || '—') + ' · ' + (j.coSoMacDinh || '—');
		el('chuCai').textContent  = chuDau(j.hoTen || '');
		el('btCham').disabled = false;
		veCoSo(j);
		veChuong(j.chuongCo, j.chuongDem);
		/* 🔴 08/09/2026 — CHƯA CÓ CƠ SỞ THÌ KHOÁ NÚT NGAY, đừng để họ chụp ảnh xong mới biết.
		   Hồ sơ vừa lập mà quên tích lưới Cơ sở là `dsCoSo` rỗng: máy chủ vẫn cho đăng nhập
		   (đúng — nói được "thiếu gì" thì hơn là báo PIN sai), nhưng lượt `cham` chắc chắn bị
		   chối. Trước bản này nút vẫn sáng, nên người ta đi hết đường: bấm chấm, chờ camera,
		   chụp, bấm lưu — rồi mới ăn một câu chối. Chối SỚM và nói rõ ai phải sửa. */
		if(!((j.dsCoSo && j.dsCoSo.length) || j.coSoMacDinh)){
			el('btCham').disabled = true;
			bao('trangThai','dong','Hồ sơ của ' + (j.hoTen||'') + ' (mã ' + (j.maNV||'—')
				+ ') chưa tích cơ sở nào, nên chưa chấm công được. Nhờ quản lý mở hồ sơ người này, '
				+ 'tích ít nhất một ô ở lưới "Cơ sở" rồi Lưu — xong thì tải lại trang này.');
		}
		/* ⚠️ `j.qtUrl` KHÔNG CÒN DỰNG LINK Ở ĐÂY — ô "Quản trị chấm công" nay nằm trong lưới
		   tab Ứng dụng, vẽ ở MÁY CHỦ bởi `VHCC_Ung::ve()`.

		   Giữ nguyên tinh thần cũ, chỉ đổi chỗ: trang vẫn KHÔNG tự đoán theo vai trò, vì đoán
		   ở đây là bộ luật quyền thứ hai, và bộ thứ hai bao giờ cũng lệch trước. Khác là phép
		   gác chạy trước cả lúc gửi HTML xuống, chứ không phải ẩn/hiện một khối đã gửi rồi.

		   Vẫn dùng `j.vaiTen` cho tab Tôi để người ta biết mình đang mang vai gì. */
		if(j.vaiTen){ el('vaiToi').textContent = j.vaiTen; el('oVaiToi').classList.remove('an'); }
		else { el('oVaiToi').classList.add('an'); }
		veHomNay(j);
		veLoiChao(j);
		if(!THANG){ var tn = thangNay(); if(tn) veThang(tn); }
	}).catch(function(e){
		/* het_phien tự đá về màn đăng nhập rồi, không báo thêm. Còn lại thì PHẢI nói ra: màn
		   hình đứng im với mấy chữ "Đang tải…" là thứ tệ nhất — người ta không biết nên chờ,
		   nên bấm lại, hay nên gọi ai. */
		if(/Phiên đã hết/.test(e && e.message)) return;
		bao('trangThai','dong', (e && e.message) || 'Không đọc được hồ sơ.');
		el('oCoSo').innerHTML = '<p class="trong">Không tải được.</p>';
		el('bangHN').innerHTML = '<p class="trong">Không tải được.</p>';
	});
}

/* Cơ sở được chấm — khối riêng, không nhét vào dòng chú thích nhỏ ở đầu trang.
   🔴 Người ở nhiều cơ sở phải NHÌN THẤY mình có những cơ sở nào TRƯỚC khi bấm chấm. Ô chọn cơ
      sở chỉ hiện ra lúc lưu (đúng ràng buộc: hỏi đúng lúc lưu, không hỏi từ sáng), nên nếu ở
      đây cũng không hiện thì tới màn chọn họ mới biết mình thiếu một cơ sở — mà lúc ấy tay đã
      cầm ảnh vừa chụp, giờ vào thì đang trôi. */
function veCoSo(j){
	var ds = (j.dsCoSo && j.dsCoSo.length) ? j.dsCoSo : (j.coSoMacDinh ? [j.coSoMacDinh] : []);
	if(!ds.length){
		el('oCoSo').innerHTML = '<div class="vang" style="margin:0">Hồ sơ chưa khai cơ sở nào. '
			+ 'Nhờ quản lý khai ô <b>Cửa hàng</b> trong hồ sơ — chưa có cơ sở thì lượt chấm '
			+ 'không biết ghi vào đâu.</div>';
		return;
	}
	var h = '<table><tbody>';
	for(var i=0;i<ds.length;i++){
		var chinh = (ds[i] === j.coSoMacDinh);
		h += '<tr><td>' + esc(ds[i])
		   + (chinh ? '<span class="nhan">cơ sở chính</span>' : '<span class="nhan">cơ sở phụ</span>')
		   + '</td></tr>';
	}
	h += '</tbody></table>';
	/* 🔴 CƠ SỞ BỊ LOẠI PHẢI NÓI RA, KHÔNG ĐƯỢC BIẾN MẤT LẶNG LẼ. Anh Thắng 09/09/2026 nhờ *"loại
	   ra khỏi bảng chấm công"* mấy cửa hàng chỉ quản lý — nhưng người bị loại là người mở trang
	   này ra, và sáu cơ sở còn hai thì họ tưởng hồ sơ bị sửa mất, hoặc hệ thống hỏng. Kể tên ra,
	   nói rõ vẫn quản lý được, và chỉ chỗ sửa nếu loại nhầm. */
	var dsq = (j.dsCoSoQL || []);
	if(dsq.length){
		h += '<p class="ct" style="margin:8px 0 0;text-align:left">Ngoài ra hồ sơ của anh/chị còn '
		   + '<b>' + dsq.length + ' cơ sở đặt "chỉ quản lý"</b>: ' + esc(dsq.join(' · '))
		   + ' — <b>không chấm công</b> ở đó nên không hiện trong bảng trên, nhưng anh/chị '
		   + '<b>vẫn quản lý nhân viên</b> mấy cơ sở ấy như thường. Loại nhầm thì nhờ quản lý bỏ '
		   + 'ô <b>chỉ QL</b> của cơ sở đó trong hồ sơ.</p>';
	}
	if(ds.length > 1){
		h += '<p class="ct" style="margin:8px 0 0;text-align:left">Lúc lưu, trang sẽ hỏi anh/chị '
		   + '<b>đang có mặt ở cơ sở nào</b> — chọn đúng cơ sở đang đứng, đừng chọn theo thói quen.</p>';
		/* 🔴 NÓI RÕ "CHÍNH" NGHĨA LÀ GÌ. Anh Thắng 09/09/2026: *"làm sao để chuyển đổi cơ sở
		   chính và cơ sở phụ"* — hai cái nhãn kia đọc lên như thứ bậc, nên người ở hai nơi tưởng
		   mình chỉ được tính công ở cơ sở "chính", còn cơ sở "phụ" là hạng hai. Không phải: cả
		   hai được tính đủ, "chính" chỉ là cơ sở CHỌN SẴN trong ô chọn lúc lưu. */
		h += '<p class="ct" style="margin:4px 0 0;text-align:left"><b>"Cơ sở chính"</b> chỉ là cơ '
		   + 'sở được <b>chọn sẵn</b> trong ô ấy — chấm ở cơ sở nào trong bảng trên cũng được '
		   + 'tính công đủ như nhau. Muốn đổi cơ sở chọn sẵn thì nhờ quản lý bấm nút '
		   + '<b>chính</b> ở ô Cơ sở trong hồ sơ.</p>';
	}
	el('oCoSo').innerHTML = h;
}

/* 🔴 ĐI THEO KHOÁ CỦA `homNay`, KHÔNG THEO `dsCoSo`. Từ 3.63.0 hai danh sách ấy KHÁC nhau:
   `dsCoSo` chỉ còn cơ sở CHẤM ĐƯỢC (đã trừ cờ "chỉ quản lý"), còn `homNay` máy chủ vẫn tính đủ
   mọi cơ sở. Duyệt theo `dsCoSo` là lượt đã chấm sáng nay ở một cơ sở vừa bị đặt cờ BIẾN MẤT
   khỏi bảng này — người ta tưởng mất giờ vào rồi bấm lại, mà lượt thứ hai ngay sau giờ vào là
   GIỜ RA. Bảng "Hôm nay" phải in đúng những gì máy chủ gửi về. */
/* ══════════════════════════════════════════════════════════════════════════════════════════
 * ☀ LỜI CHÀO NGÀY MỚI — xem khối CSS `.lc`.
 *   · Câu: mỗi khung giờ một kho; câu CỐ ĐỊNH theo (ngày · khung · mã NV) — mở lại trong cùng
 *     khung vẫn đúng câu ấy, sang ngày mới đổi câu và không trùng câu hôm qua.
 *   · Dịp (thắng câu thường): sinh nhật (ngày sinh trong hồ sơ, máy chủ gửi `chao.ngaySinh`),
 *     thứ Hai, thứ Sáu, Chủ nhật.
 *   · Kiểu: 'tu' (mặc định) đổi A → B → C theo ngày; chọn riêng ở tab Tôi, nhớ trên máy này.
 * ══════════════════════════════════════════════════════════════════════════════════════════ */
var LC = {
	khung: [
		{k:'sang',  tu:5*60,     nhan:'Chào buổi sáng', cau:[
			'Nạp năng lượng xong chưa? Mình quẩy thôi!',
			'Dậy rồi thì chiến thôi — hôm nay chắc chắn là một ngày xịn.',
			'Cà phê vào, năng lượng lên! Chúc {tên} một ngày rực rỡ.',
			'Nắng lên rồi, {tên} cũng lên mood nào!',
			'Một ngày mới, một phiên bản xịn hơn của {tên}.']},
		{k:'trua',  tu:11*60,    nhan:'Chào buổi trưa', cau:[
			'Nửa ngày rồi! Ăn trưa thật no để chiều còn bùng nổ.',
			'Giờ nghỉ trưa — sạc pin 100% nha {tên}.',
			'Làm tốt lắm buổi sáng! Nghỉ ngơi xíu rồi mình đi tiếp.']},
		{k:'chieu', tu:13*60+30, nhan:'Chiều vui nha', cau:[
			'Chiều rồi, cố lên! Về đích đẹp là của mình.',
			'Thêm chút nữa thôi, {tên} làm tốt lắm rồi!',
			'Uống ngụm nước, vươn vai cái — hiệp hai bắt đầu!']},
		{k:'toi',   tu:18*60,    nhan:'Buổi tối vui vẻ', cau:[
			'Ca tối cũng phải thật chill nha {tên}.',
			'Tối rồi mà vẫn cháy — nể {tên} ghê!',
			'Khách đông cũng không sao, có {tên} là yên tâm.']},
		{k:'dem',   tu:22*60,    nhan:'Chào chiến binh ca đêm', cau:[
			'Ca đêm cực thật, nhưng {tên} còn ngầu hơn. Nhớ uống nước nha!',
			'Thành phố ngủ rồi, {tên} vẫn chiến. Cảm ơn bạn nhiều!',
			'Đêm dài nhưng mình có nhau. Giữ sức khoẻ nha {tên}!']}
	],
	dip: {
		sn:  {chao:'Chúc mừng sinh nhật {tên}!', cau:'Cả nhà K&H chúc bạn tuổi mới rực rỡ, niềm vui nhân đôi!'},
		t2:  {chao:'Thứ Hai không đáng sợ khi có {tên}!', cau:'Khởi động tuần mới thật cháy nào.'},
		t6:  {chao:'Thứ Sáu rồi, {tên} ơi!', cau:'Chốt tuần thật đẹp rồi mình xả hơi.'},
		cn:  {chao:'Chủ nhật vẫn đi làm — {tên} đúng là chiến binh!', cau:'Cảm ơn bạn đã có mặt hôm nay.'}
	},
	thu: ['Chủ nhật','Thứ Hai','Thứ Ba','Thứ Tư','Thứ Năm','Thứ Sáu','Thứ Bảy'],
	daChay: {}
};
function lcBam(s){ var h = 2166136261; for(var i=0;i<s.length;i++){ h ^= s.charCodeAt(i); h = Math.imul(h, 16777619) >>> 0; } return h; }
function lcNgay(d){ return d.getFullYear() + '-' + (d.getMonth()+1) + '-' + d.getDate(); }
function lcKhung(phut){ var k = LC.khung[4]; for(var i=0;i<LC.khung.length;i++){ if(phut >= LC.khung[i].tu){ k = LC.khung[i]; } } return phut < 5*60 ? LC.khung[4] : k; }
function lcCauSo(kh, ma, d){
	var n = kh.cau.length, i = lcBam(lcNgay(d) + '|' + kh.k + '|' + ma) % n;
	var hq = new Date(d.getTime() - 86400000), j = lcBam(lcNgay(hq) + '|' + kh.k + '|' + ma) % n;
	return (i === j && n > 1) ? (i + 1) % n : i;
}
function lcKieu(d){
	var k = 'tu'; try { k = localStorage.getItem('vhcc_kieu_chao') || 'tu'; } catch(e){}
	if(k === 'a' || k === 'b' || k === 'c'){ return k; }
	var ngay = Math.floor(Date.UTC(d.getFullYear(), d.getMonth(), d.getDate()) / 86400000);
	return ['a','b','c'][ngay % 3];
}
function lcGioLam(hn, bay){
	var tong = 0, phutBay = bay.getHours()*60 + bay.getMinutes(), k1;
	for(k1 in (hn || {})){
		if(!Object.prototype.hasOwnProperty.call(hn, k1)) continue;
		(hn[k1] || []).forEach(function(x){
			var v = /^(\d{1,2}):(\d{2})/.exec(x.vao || ''); if(!v) return;
			var r = /^(\d{1,2}):(\d{2})/.exec(x.ra || ''), a = (+v[1])*60 + (+v[2]);
			var b = r ? (+r[1])*60 + (+r[2]) : phutBay;
			if(b < a){ b += 1440; }
			tong += Math.max(0, b - a);
		});
	}
	return tong;
}
function lcVaoSom(hn){
	var som = '', k1;
	for(k1 in (hn || {})){
		if(!Object.prototype.hasOwnProperty.call(hn, k1)) continue;
		(hn[k1] || []).forEach(function(x){ if(x.vao && (!som || x.vao < som)){ som = x.vao; } });
	}
	return som;
}
function veLoiChao(j){
	var o = el('loiChao'); if(!o) return;
	if(!j || !j.hoTen){ o.classList.add('an'); return; }
	/* Giờ MÁY CHỦ như mọi chỗ khác của trạm (`gioMayChu`). Chưa có mốc thì lời chào mượn đồng hồ
	   máy — nó chỉ chọn câu chào, không đóng dấu giờ công nào. */
	var d = gioMayChu() || new Date(Date.now()), phut = d.getHours()*60 + d.getMinutes(), kh = lcKhung(phut);
	var ten = String(j.hoTen).trim().split(/\s+/).pop();
	var t = function(s){ return String(s).replace(/\{tên\}/g, ten); };
	var ch = (j.chao || {}), mmdd = ('0' + (d.getMonth()+1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2);
	var dip = (ch.ngaySinh && ch.ngaySinh === mmdd) ? 'sn' : ({1:'t2', 5:'t6', 0:'cn'})[d.getDay()] || '';
	var chao = dip ? t(LC.dip[dip].chao) : kh.nhan + ', ' + ten + '!';
	var cau  = dip ? t(LC.dip[dip].cau) : t(kh.cau[lcCauSo(kh, j.maNV || '', d)]);
	var ngay = LC.thu[d.getDay()] + ', ' + ('0'+d.getDate()).slice(-2) + '/' + ('0'+(d.getMonth()+1)).slice(-2);
	var som = lcVaoSom(j.homNay), chuoi = +(ch.chuoi || 0), gl = lcGioLam(j.homNay, d);
	var nho = som ? 'Đã chấm vào lúc ' + som + ' — chiến tiếp thôi!' : 'Nhớ chấm vào khi bắt đầu ca nha!';
	var kieu = lcKieu(d), h = '';
	o.className = 'lc k-' + kh.k;
	if(kieu === 'a'){
		h = '<div class="lc-a"><div class="lc-qua"></div><div class="lc-ngay">' + esc(ngay) + '</div>'
			+ '<div class="lc-chao">' + esc(chao) + '</div><div class="lc-cau">' + esc(cau) + '</div>'
			+ '<div class="lc-nho">' + esc(nho) + '</div></div>';
	} else if(kieu === 'b'){
		var khoa = lcNgay(d) + kh.k, chay = !LC.daChay[khoa];
		LC.daChay[khoa] = true;
		h = '<div class="lc-b' + (chay ? ' chay' : '') + '"><div class="lc-bd"><span class="lc-av">K&amp;H</span>K&amp;H · ' + esc(ngay) + '</div>'
			+ (chay ? '<div class="lc-go" id="lcGo"><i></i><i></i><i></i></div>' : '')
			+ '<div class="lc-bong lon"' + (chay ? ' style="animation-delay:1.4s"' : '') + '>' + esc(chao) + '</div>'
			+ '<div class="lc-bong"' + (chay ? ' style="animation-delay:2.4s"' : '') + '>' + esc(cau) + '</div>'
			+ '<div class="lc-bong"' + (chay ? ' style="animation-delay:3.4s"' : '') + '>' + esc(nho) + '</div></div>';
		if(chay){ setTimeout(function(){ var g = el('lcGo'); if(g){ g.remove(); } }, 1400); }
	} else {
		var pt = Math.min(100, Math.round(gl / 480 * 100));
		var gio = Math.floor(gl/60) + ':' + ('0' + (gl%60)).slice(-2);
		h = '<div class="lc-c"><div class="lc-hang"><div class="lc-vong" style="background:conic-gradient(var(--g2) ' + pt + '%,var(--vien) 0)">'
			+ '<span>' + esc(gio) + '<br><small>/8:00</small></span></div>'
			+ '<div><div class="lc-chao" style="font-size:17px;max-width:none;color:var(--chu)">' + esc(chao) + '</div>'
			+ '<div class="lc-cau" style="font-size:13px;color:var(--chu-mo)">' + esc(cau) + '</div></div></div>'
			+ (chuoi > 1 ? '<span class="lc-lua">🔥 <b>' + chuoi + '</b> ngày đi làm liên tiếp</span>' : '')
			+ '<div class="lc-tin">' + esc(nho) + (chuoi > 1 ? ' Giữ phong độ để lên ' + (chuoi + 3) + ' ngày nha!' : '') + '</div></div>';
	}
	o.innerHTML = h;
	o.classList.remove('an');
	veKieuChao();
}
function veKieuChao(){
	var k = 'tu'; try { k = localStorage.getItem('vhcc_kieu_chao') || 'tu'; } catch(e){}
	var ds = document.querySelectorAll('#kieuChao button');
	for(var i=0;i<ds.length;i++){ ds[i].classList.toggle('dang', ds[i].getAttribute('data-kieu') === k); }
}
Array.prototype.forEach.call(document.querySelectorAll('#kieuChao button'), function(b){
	b.addEventListener('click', function(){
		try { localStorage.setItem('vhcc_kieu_chao', b.getAttribute('data-kieu')); } catch(er){}
		veKieuChao();
		if(TOI){ veLoiChao(TOI); }
	});
});

function veHomNay(j){
	var hn = (j && j.homNay) || {}, cs = [], k1;
	for(k1 in hn){ if(Object.prototype.hasOwnProperty.call(hn, k1)) cs.push(k1); }
	var co=false, h='<table><thead><tr><th>Cơ sở</th><th>Hàng</th><th>Vào</th><th>Ra</th></tr></thead><tbody>';
	for(var i=0;i<cs.length;i++){
		var ds = hn[cs[i]] || [];
		for(var k=0;k<ds.length;k++){
			co=true;
			h += '<tr><td>'+esc(cs[i])+'</td><td>'+esc(ds[k].hauTo||'chính')+'</td>'
			   + '<td class="g">'+esc(ds[k].vao||'—')+'</td><td class="g">'+esc(ds[k].ra||'—')+'</td></tr>';
		}
	}
	h += '</tbody></table>';
	el('bangHN').innerHTML = co ? h : '<p class="trong">Hôm nay chưa chấm lượt nào.</p>';
	veNutCham(hn, cs);
}

/* ══════════════════════════════════════════════════════════════════════════════════════════
 * CHỮ TRÊN NÚT TRÒN THEO TÌNH TRẠNG HÔM NAY.
 *
 * ⚠️ CHỈ ĐỔI CHỮ VÀ MÀU, KHÔNG ĐỔI VIỆC NÚT LÀM. Cùng một nút gọi cùng một luồng chụp ảnh rồi
 *    `viec=cham`; máy chủ mới là nơi quyết định lượt bấm ấy là VÀO hay RA (xem `cham_cong()`
 *    và `dinh_tuyen()`). Để trình duyệt tự quyết rồi gửi lên là dựng bộ luật thứ hai — mà bộ
 *    thứ hai bao giờ cũng lệch trước, và lệch ở đây nghĩa là ghi nhầm giờ vào thành giờ ra.
 *
 * Nên chữ ở đây là DỰ ĐOÁN để người ta biết mình đang ở đâu, không phải một lựa chọn.
 * ══════════════════════════════════════════════════════════════════════════════════════════ */
function veNutCham(hn, cs){
	var bt = el('btCham'); if(!bt) return;
	var nhan = bt.querySelector('span'), bieu = bt.querySelector('i');
	if(!nhan || !bieu) return;

	/* Có ít nhất một hàng đã vào mà chưa ra -> đang trong ca. */
	var dangLam = false;
	for(var i=0;i<cs.length;i++){
		var ds = hn[cs[i]] || [];
		for(var k=0;k<ds.length;k++){
			if(ds[k].vao && !ds[k].ra){ dangLam = true; break; }
		}
		if(dangLam) break;
	}

	bt.classList.toggle('dang-lam', dangLam);
	nhan.textContent = dangLam ? 'CHẤM RA' : 'CHẤM CÔNG';
	bieu.textContent = dangLam ? '🏁' : '📷';
}

/* ------------------------------------------------------- công của tôi (theo tháng)

   🔴 THÁNG TÍNH TỪ GIỜ MÁY CHỦ, KHÔNG TỪ ĐIỆN THOẠI. Ngày 1 và ngày cuối tháng, một cái điện
      thoại lệch múi giờ mở ra là thấy tháng khác — rồi báo "mất công" trong khi công vẫn còn
      nguyên ở tháng bên cạnh. `TOI.gio.ngay` là chuỗi ngày do máy chủ gửi kèm mọi lượt nạp.

   Cộng trừ tháng bằng CHUỖI chứ không bằng đối tượng Date: Date đọc '2026-08' theo UTC rồi in
   ra theo giờ máy, và ở múi giờ âm thì tháng lùi mất một. */
var THANG = '';

function thangDich(ym, buoc){
	var p = String(ym).split('-'), n = Number(p[0])||2026, t = (Number(p[1])||1) + buoc;
	while(t > 12){ t -= 12; n++; }
	while(t < 1){ t += 12; n--; }
	return n + '-' + hai(t);
}

function thangNay(){
	var ng = (TOI && TOI.gio && TOI.gio.ngay) ? String(TOI.gio.ngay) : '';
	return /^\d{4}-\d{2}/.test(ng) ? ng.slice(0,7) : '';
}

/* Hai chữ cái cuối của tên — "Huỳnh Quang Thắng" -> "QT". Lấy hai từ CUỐI vì người Việt gọi
   nhau bằng tên, không bằng họ: lấy hai từ đầu thì cả phòng họ Nguyễn đều ra "NV". */
function chuDau(ten){
	var t = String(ten||'').trim().split(/\s+/).filter(Boolean);
	if(!t.length) return '—';
	if(t.length === 1) return t[0].slice(0,2).toUpperCase();
	return (t[t.length-2].charAt(0) + t[t.length-1].charAt(0)).toUpperCase();
}

function gioPhut(p){
	if(p === null || p === undefined) return '—';
	var g = Math.floor(p/60), m = p%60;
	return g + 'h' + (m ? hai(m) : '');
}

function veThang(ym){
	THANG = ym;
	el('nhanThang').textContent = 'Tháng ' + ym.slice(5) + '/' + ym.slice(0,4);
	veLichToi(ym);
	/* Không cho đi tới tương lai — tháng sau chắc chắn trống, và một bảng trống làm người ta
	   tưởng mất dữ liệu. */
	el('btThangSau').disabled = ( thangNay() !== '' && ym >= thangNay() );
	el('bangThang').innerHTML = '<p class="trong">Đang tải…</p>';
	el('tomTat').innerHTML = '';
	goi('thang',{token:token(), thang:ym}).then(function(j){
		if(!j || !j.ok){ el('bangThang').innerHTML = '<p class="trong">Không tải được.</p>'; return; }
		var t = j.tong || {}, d = j.dong || [];
		var s = '<div class="xanh" style="margin:0 0 10px">' + (t.ngay||0) + ' ngày · ' + (t.luot||0)
		      + ' lượt · ' + gioPhut(t.phut||0) + ' có mặt</div>';
		if(t.thieuRa){
			s += '<div class="vang" style="margin:0 0 10px">' + t.thieuRa
			   + ' lượt thiếu giờ ra (ô <b>Ra</b> để trống bên dưới). Báo quản lý bổ sung '
			   + '<b>trước khi chốt lương tháng</b> — chốt rồi thì sửa rất phiền.</div>';
		}
		el('tomTat').innerHTML = s;
		if(!d.length){ el('bangThang').innerHTML = '<p class="trong">Tháng này chưa có lượt nào.</p>'; return; }
		var h = '<table><thead><tr><th>Ngày</th><th>Cơ sở</th><th>Vào</th><th>Ra</th><th>Giờ</th></tr></thead><tbody>';
		for(var i=0;i<d.length;i++){
			var thieu = !d[i].ra;
			h += '<tr' + (thieu ? ' style="background:var(--nen-2)"' : '') + '>'
			   + '<td class="g">' + esc(String(d[i].ngay).slice(8)) + '</td>'
			   + '<td>' + esc(d[i].coSo) + (d[i].hauTo ? '<span class="nhan">'+esc(d[i].hauTo)+'</span>' : '') + '</td>'
			   + '<td class="g">' + esc(d[i].vao||'—') + '</td>'
			   + '<td class="g">' + (thieu ? '<b style="color:var(--do)">thiếu</b>' : esc(d[i].ra)) + '</td>'
			   + '<td class="g">' + gioPhut(d[i].phut) + '</td></tr>';
		}
		el('bangThang').innerHTML = h + '</tbody></table>';
	}).catch(function(){ el('bangThang').innerHTML = '<p class="trong">Lỗi mạng.</p>'; });
}

/**
 * Nhảy tới một khối.
 *
 * ⚠️ `scrollIntoView({behavior:'smooth'})` không có ở mọi máy — Safari cũ bỏ qua cả đối tượng
 *    tuỳ chọn và nhảy phắt tới, mà nhảy phắt thì vẫn ĐÚNG VIỆC. Nên gọi trong try/catch rồi
 *    lùi về bản không tham số: thà nhảy giật còn hơn nút bấm không lên.
 */
/* ---------------------------------------------------------------- hồ sơ của tôi

   Nạp khi mở tab Tôi. Danh sách ô lấy TỪ MÁY CHỦ (`VHCC_HoSoToi::SUA_DUOC`) chứ không gõ ở
   đây — gõ hai nơi là sớm muộn màn hình bày một ô mà máy chủ không nhận, người ta gõ xong bấm
   Lưu rồi thấy nó biến mất mà không hiểu vì sao. */
var DA_NAP_HS = false;

function napHoSo(){
	if(DA_NAP_HS) return;
	DA_NAP_HS = true;
	goi('hoso', { token: token() }).then(function(j){
		if(!j || !j.ok){
			el('oHoSo').innerHTML = '<p class="trong">' + esc((j && j.error) || 'Không tải được hồ sơ.') + '</p>';
			return;
		}
		veHoSo(j);
	}).catch(function(){
		DA_NAP_HS = false;
		el('oHoSo').innerHTML = '<p class="trong">Lỗi mạng. Bấm lại tab này để thử.</p>';
	});
}

function veHoSo(j){
	var hs = j.hs || {}, h = '';

	/* Phần CHỈ ĐỌC lên trước: người ta mở tab này phần lớn là để xem mình là ai, chỉ thỉnh
	   thoảng mới sửa. */
	var cd = j.chi_doc || {}, k;
	h += '<table><tbody>';
	for(k in cd){ if(Object.prototype.hasOwnProperty.call(cd,k)){
		h += '<tr><td style="color:var(--chu-mo);width:42%">' + esc(cd[k]) + '</td>'
		  +  '<td><b>' + esc(hs[k] || '—') + '</b></td></tr>';
	}}
	h += '</tbody></table>';
	h += '<p class="ct" style="text-align:left;margin:8px 0 14px">Mấy dòng trên là hồ sơ gốc — '
	  +  'sai thì báo quản lý, không tự sửa được ở đây.</p>';

	/* Phần SỬA ĐƯỢC. */
	var sd = j.sua_duoc || {}, thieu = j.thieu || [];
	for(k in sd){ if(Object.prototype.hasOwnProperty.call(sd,k)){
		var conThieu = thieu.indexOf(k) >= 0;
		var kieu = (k === 'ngay_sinh') ? 'date'
		         : ((k === 'sdt' || k === 'sdt_khan' || k === 'cccd') ? 'tel' : 'text');
		h += '<div class="fldx"><label>' + esc(sd[k])
		  +  (conThieu ? ' <span class="nhan vang">chưa có</span>' : '') + '</label>'
		  +  '<input data-hs="' + esc(k) + '" type="' + kieu + '"'
		  +  (kieu === 'tel' ? ' inputmode="numeric"' : '')
		  +  ' value="' + esc(hs[k] || '') + '"></div>';
	}}
	h += '<p></p><button id="btLuuHS" class="chinh" style="width:100%">Lưu hồ sơ</button>'
	  +  '<div id="baoHS"></div>';

	el('oHoSo').innerHTML = h;
	el('btLuuHS').addEventListener('click', luuHoSo);

	/* Nhãn đếm số ô còn thiếu — để người ta biết có việc phải làm mà không cần cuộn. */
	var n = thieu.length, nh = el('nhanThieu');
	if(n > 0){ nh.textContent = 'thiếu ' + n; nh.classList.remove('an'); }
	else { nh.classList.add('an'); }
}

function luuHoSo(){
	var o = {}, ds = document.querySelectorAll('[data-hs]');
	for(var i=0;i<ds.length;i++){ o[ds[i].getAttribute('data-hs')] = ds[i].value; }

	var bt = el('btLuuHS');
	bt.disabled = true;
	bao('baoHS','','Đang lưu…');
	goi('luu_hoso', { token: token(), hs: o }).then(function(j){
		if(!j || !j.ok){ bao('baoHS','dong',(j && j.error) || 'Không lưu được.'); return; }
		bao('baoHS','xanh', j.message || 'Đã lưu.');
		/* Nạp lại để nhãn "chưa có" và số đếm khớp với thứ vừa ghi — tự sửa ở trình duyệt là
		   hai chỗ tính "thiếu", và chúng sẽ lệch. */
		DA_NAP_HS = false;
		napHoSo();
	}).catch(function(){ bao('baoHS','dong','Lỗi mạng.'); })
	  .then(function(){ bt.disabled = false; });
}

/* ---------------------------------------------------------------- đổi mật khẩu */
el('btDoiPin').addEventListener('click', function(){
	var cu = el('pinCu').value, moi = el('pinMoi').value, lai = el('pinLai').value;
	var bt = this;
	bt.disabled = true;
	bao('baoPin','','Đang đổi…');
	goi('doi_pin', { token: token(), cu: cu, moi: moi, lai: lai }).then(function(j){
		if(!j || !j.ok){ bao('baoPin','dong',(j && j.error) || 'Không đổi được.'); return; }
		bao('baoPin','xanh','Đã đổi mật khẩu. Lần sau đăng nhập bằng mật khẩu mới.');
		el('pinCu').value = ''; el('pinMoi').value = ''; el('pinLai').value = '';
	}).catch(function(){ bao('baoPin','dong','Lỗi mạng.'); })
	  .then(function(){ bt.disabled = false; });
});

/* ---------------------------------------------------------------- lưới ứng dụng
   Nạp MỘT LẦN cho mỗi phiên. Danh sách này đổi khi quản lý cấp quyền — chuyện của tuần, không
   phải của phút — nên gọi lại mỗi lần bấm tab là chín lượt hỏi máy chủ một ngày cho một thứ
   không đổi. Cấp quyền xong thì người ta đăng nhập lại là thấy. */
var DA_NAP_UNG = false;

/* Dòng chỉ đường khi thiếu ô. KHÔNG nói người này bị khoá những gì — chỉ nói CÁI CÔNG TẮC nằm
   ở đâu. Thiếu dòng này thì câu hỏi "sao tôi không thấy app chi phí" phải đi một vòng qua bộ
   phận kỹ thuật, trong khi công tắc nằm đúng ở màn mà quản lý mở hằng tuần. */
var NHAC_UNG = '<p class="ct" style="text-align:left;margin:12px 0 0">Thiếu ứng dụng nào? '
	+ 'Quản lý cấp ở màn <b>Quản lý nhân sự</b> — cột Ghế massage · Vận hành chi phí · '
	+ 'Báo cáo cơ sở. Cấp xong thì đăng xuất rồi vào lại.</p>';

function napUng(){
	if(DA_NAP_UNG) return;
	DA_NAP_UNG = true;
	goi('ung', { token: token() }).then(function(j){
		if(!j || !j.ok || !j.ds || !j.ds.length){
			el('oUng').innerHTML = '<p class="trong">Bạn chưa được cấp ứng dụng nào ngoài chấm công.</p>'
				+ NHAC_UNG;
			return;
		}
		/* Nhóm nào máy chủ không kể tên thì vẫn phải vẽ — thiếu một nhóm là nuốt mất mấy ô
		   người ta đang cần, mà không có dòng nào báo. */
		var nhom = (j.nhom && j.nhom.length) ? j.nhom.slice() : [];
		for(var n=0;n<j.ds.length;n++){
			var tn = j.ds[n].nhom || 'Khác';
			if(nhom.indexOf(tn) < 0){ nhom.push(tn); }
		}

		var h = '', ghi = '';
		for(var g=0; g<nhom.length; g++){
			var oG = '';
			for(var i=0;i<j.ds.length;i++){
				var x = j.ds[i];
				if((x.nhom || 'Khác') !== nhom[g]){ continue; }
				/* 🔴 Ô KHOÁ DỰNG BẰNG <div>, KHÔNG PHẢI <a> KÈM CLASS. Máy chủ đã bỏ hẳn `url`
				   của ô khoá, nên ở đây không có gì để bấm vào — kể cả một dòng CSS sửa nhầm
				   cũng không biến nó thành bấm được. Dùng <a> rồi chặn bằng JS thì chỉ cần một
				   lượt JS hỏng là năm cái ô khoá thành năm cái link sống. */
				/* 🔴 Ô MỞ MÀN TRONG TRẠM CŨNG LÀ Ô MỞ. Anh Thắng 18/09/2026, ảnh chụp tab
				   Ứng dụng của một nhân viên: cả nhóm "CỦA TÔI" (Phiếu lương · Xin bù giờ ·
				   Khai giờ khác · Xin nghỉ · Gửi đơn đi trễ) xám hết, kèm dòng "chưa được cấp"
				   — trong khi máy chủ dựng cả năm ô ấy bằng `o( true, … )`, tức KHÔNG gác gì.
				   Vì sao: chốt này đòi `x.url`, mà năm ô ấy không có `url` — chúng mở một màn
				   NGAY TRONG TRẠM bằng `x.man`. Nên nhánh `if(mo && x.man)` ngay dưới là mã
				   CHẾT, không lượt nào chạy tới, và mọi ô `man` đều rơi xuống nhánh khoá.
				   ⚠️ Vẫn an toàn: `VHCC_Ung::o()` đã `unset()` CẢ `url` LẪN `man` của ô khoá,
				      nên ô khoá thật không có đường nào lọt qua chốt này. */
				var mo = !!x.mo_duoc && (!!x.url || !!x.man);
				var ruot = '<span class="o-icon o-' + thoat(x.mau||'xanh') + '">'
				         + thoat(x.icon||'') + '</span>'
				         + '<b>' + thoat(x.ten||'') + '</b>';

				if(mo && x.man){
					/* 🔴 Ô MỞ MÀN NGAY TRONG TRẠM — dựng bằng <button>, không phải <a href="#">.
					   Thẻ <a> rỗng thì bấm là nhảy lên đầu trang, và trên iOS còn đổi cả địa
					   chỉ — người dùng bấm "Thêm nhân sự" xong thấy trang giật một cái rồi
					   không có gì. <button type="button"> thì không có hành vi mặc định nào. */
					oG += '<button type="button" class="o-ung o-man" data-man="' + thoat(x.man)
					   +  '" title="' + thoat(x.mo||'') + '">' + ruot + '</button>';
				} else if(mo){
					/* `title` mang phần mô tả đã rời khỏi ô — máy tính rê chuột là thấy, điện
					   thoại thì không, nên nó chỉ là phần THÊM, không phải chỗ giấu thông tin
					   cần thiết. Thứ cần thiết nằm ở danh sách chú thích dưới lưới. */
					oG += '<a class="o-ung" href="' + thoat(x.url) + '" title="' + thoat(x.mo||'') + '">'
					   +  ruot + '</a>';
					if(x.ghi_chu){
						ghi += '<p>💡 <b>' + thoat(x.ten||'') + '</b> — ' + thoat(x.ghi_chu) + '</p>';
					}
				} else {
					oG += '<div class="o-ung o-khoa">' + ruot + '</div>';
					ghi += '<p>🔒 <b>' + thoat(x.ten||'') + '</b> — chưa được cấp.'
					    +  (x.xin ? ' ' + thoat(x.xin) : '') + '</p>';
				}
			}
			if(!oG){ continue; }
			h += '<div class="nhom-ung"><p class="nhom-ten">' + thoat(nhom[g]) + '</p>'
			  +  '<div class="luoi-ung">' + oG + '</div></div>';
		}
		el('oUng').innerHTML = h + (ghi ? '<div class="ghi-ung">' + ghi + '</div>' : '') + NHAC_UNG;
		/* Ô mở màn dựng lúc chạy nên gài sự kiện sau mỗi lượt vẽ. */
		var om = el('oUng').querySelectorAll('.o-man');
		for(var q=0;q<om.length;q++){
			om[q].addEventListener('click', function(){ moMan(this.getAttribute('data-man')); });
		}
	}).catch(function(){
		DA_NAP_UNG = false;
	DA_NAP_HS = false;
	var _h = el('oHoSo'); if(_h){ _h.innerHTML = '<p class="trong">Đang tải…</p>'; }   // hỏng thì cho thử lại lần bấm sau
		el('oUng').innerHTML = '<p class="trong">Không tải được danh sách. Bấm lại tab này để thử.</p>';
	});
}

/* Thoát ký tự trước khi ghép vào innerHTML. Tên ứng dụng do máy chủ đặt nên hôm nay lành, nhưng
   một dấu " trong tên là vỡ luôn thẻ a — mà lỗi kiểu đó không kêu, chỉ hiện sai. */
function thoat(v){
	return String(v == null ? '' : v)
		.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
		.replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

/* ---------------------------------------------------------------- tab
   Ba tab thay cho một trang cuộn dài. Đổi tab là đổi `display`, KHÔNG nạp lại dữ liệu:
   `toi()` đã nạp đủ cả ba tab lúc đăng nhập, và đồng hồ chạy bằng `setInterval` chứ không
   bằng vòng lặp gắn vào khối đang hiện — nên tab Chấm công ẩn đi rồi hiện lại vẫn đúng giờ. */
var TAB = 'tChamCong';

function denTab(ten){
	if(!el(ten)) return;
	TAB = ten;
	['tChamCong','tCong','tCuaHang','tUng','tToi'].forEach(function(x){
		var o = el(x); if(o){ o.classList.toggle('an', x !== ten); }
	});
	var ds = document.querySelectorAll('.tab-nut');
	for(var i=0;i<ds.length;i++){ ds[i].classList.toggle('dang', ds[i].getAttribute('data-tab') === ten); }

	if(ten === 'tCuaHang'){ napCuaHang(); }
	if(ten === 'tUng'){ napUng(); }
	if(ten === 'tToi'){ napHoSo(); moManXin(); }
	/* Về đầu trang khi đổi tab. Không có dòng này thì đang cuộn giữa bảng tháng mà bấm sang
	   tab Chấm công là rơi vào khoảng trắng — nút chấm nằm trên đầu, khuất khỏi màn hình. */
	try { window.scrollTo({ top:0, behavior:'instant' }); } catch(e){ window.scrollTo(0,0); }
}

(function(){
	var ds = document.querySelectorAll('.tab-nut');
	for(var i=0;i<ds.length;i++){
		ds[i].addEventListener('click', function(){ denTab(this.getAttribute('data-tab')); });
	}
})();

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * XIN PHÉP — đi trễ và đổi lịch. Cửa, không phải nghiệp vụ: xem class-vhcc-tram.php.
 *
 * 🔴 NẠP LẠI DANH SÁCH ĐƠN MỖI LẦN MỞ MÀN, không nhớ đệm. Trạng thái đơn đổi ở phía cửa hàng
 *    trưởng chứ không ở đây, nên một bản nhớ đệm là màn hình nói "Chờ duyệt" trong khi đơn đã
 *    bị từ chối từ hôm qua — và người ta cứ thế đi trễ.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
var XIN = null;

/* Gọi khi mở tab "Tôi". Không còn màn riêng để mở — xem khối markup ở tab ấy. */
function moManXin(){
	bao('loiTre','',null); bao('loiLich','',null); bao('loiBaoSai','',null);
	napBaoSai();
	napXin();
}

function napXin(){
	return goi('donxin',{token:token()}).then(function(j){
		if(!j || !j.ok){
			el('bangDon').innerHTML = '<p class="trong">' + esc((j&&j.error)||'Không đọc được đơn.') + '</p>';
			return;
		}
		XIN = j;
		/* Ngày mặc định là HÔM NAY THEO MÁY CHỦ, không theo điện thoại. Điện thoại lệch ngày
		   (múi giờ sai, đồng hồ chạy sau nửa đêm) thì đơn rơi vào ngày hôm qua, và cửa hàng
		   trưởng thấy một đơn xin trễ cho ngày đã xong. */
		if(!el('xtNgay').value){ el('xtNgay').value = j.homNay || ''; }
		el('xtPhut').max = j.phutToiDa || 120;
		if(!el('xnTu').value){ el('xnTu').value = j.homNay || ''; }
		if(!el('xnLoai').options.length){
			var dsL = [], k;
			for(k in (j.loaiNghi||{})){ if(Object.prototype.hasOwnProperty.call(j.loaiNghi,k)){ dsL.push(k); } }
			el('xnLoai').innerHTML = xoOptionCap(j.loaiNghi || {}, dsL);
		}
		veQuyPhep(j.quyPhep);
		veKhoiLich(j);
		veBangDon(j);
	});
}

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * QUỸ PHÉP NĂM
 * 🔴 TRẦN = 0 NGHĨA LÀ CÔNG TY KHÔNG THEO DÕI PHÉP NĂM — lúc ấy KHÔNG bày dòng "còn lại", chứ
 *    không bày "còn lại 0". Bày số 0 là nói với cả công ty rằng họ hết phép, trong khi sự thật
 *    là chưa ai đặt con số ấy.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
function veQuyPhep(q){
	if(!q){ el('oQuyPhep').innerHTML = ''; return; }
	if(!q.tran){
		el('oQuyPhep').innerHTML = '<p class="ct" style="text-align:left;margin:0 0 8px">Công ty '
			+ 'chưa đặt số ngày phép năm, nên màn này không tính "còn lại". Đơn vẫn nộp bình thường.</p>';
		return;
	}
	el('oQuyPhep').innerHTML = '<div class="vang">Phép năm ' + esc(q.nam) + ': đã dùng <b>'
		+ esc(q.daDung) + '</b> / ' + esc(q.tran) + ' ngày · còn <b>' + esc(q.conLai)
		+ '</b> ngày.<br><span class="ct">Chỉ tính đơn <b>nghỉ phép năm</b> đã được duyệt.</span></div>';
}

function veKhoiLich(j){
	var bat = j.coSoBatLich || [];
	if(!bat.length){
		hien('oLichMo',false); hien('oLichTat',true);
		el('oLichTat').innerHTML = '<p class="trong">Cơ sở của anh/chị chưa bật phân lịch nên không '
			+ 'có lịch nào để đổi. Xin nghỉ thì báo trực tiếp quản lý.</p>';
		return;
	}
	hien('oLichTat',false); hien('oLichMo',true);
	el('xlCoSo').innerHTML = xoOption(bat, j.coSoMacDinh || bat[0]);
	el('xlCa').innerHTML   = xoOption(j.ca || [], '');
	/* Ô "Việc mới" để TRỐNG được: đổi ca mà giữ nguyên việc là một yêu cầu có thật. Còn XIN
	   NGHỈ thì chọn đúng loại việc mà công ty đã khai cho việc ấy — bộ này cố ý không tự đẻ
	   ra một mục "Nghỉ" không có trong danh mục, vì lúc duyệt nó sẽ được GHI THẲNG vào lịch
	   và một tên việc lạ nằm trong lịch thì không bảng nào tính được. */
	el('xlViec').innerHTML = xoOption(j.loaiViec || [], '', '— giữ nguyên việc —');
	if(!el('xlNgay').value){ el('xlNgay').value = j.homNay || ''; }
}

/* Dựng cả khối <option>. Dòng trống đầu tiên (nếu có) cũng dựng TRONG ĐÂY, không ghép ở nơi
   gọi — ghép ở ngoài là một chuỗi HTML nối với kết quả hàm, và bộ kiểm "không rò HTML" không
   phân biệt nổi chuỗi ấy với một cái tên cơ sở chưa thoát. */
/* Ô xổ dựng từ bản đồ mã -> tên: giá trị gửi lên là MÃ (`phep`), chữ hiện ra là TÊN
   ("Nghỉ phép năm"). Gửi tên lên thì máy chủ phải dịch ngược bằng chuỗi tiếng Việt — đổi một
   chữ ở màn là mọi đơn cũ hoá loại lạ. */
function xoOptionCap(banDo, khoa){
	var h = '';
	for(var i=0;i<khoa.length;i++){
		h += '<option value="' + esc(khoa[i]) + '">' + esc(banDo[khoa[i]]) + '</option>';
	}
	return h;
}

function xoOption(ds, chon, dong_trong){
	var h = dong_trong ? ('<option value="">' + esc(dong_trong) + '</option>') : '';
	for(var i=0;i<ds.length;i++){
		h += '<option value="' + esc(ds[i]) + '"' + (ds[i]===chon ? ' selected' : '') + '>'
			+ esc(ds[i]) + '</option>';
	}
	return h;
}

function veBangDon(j){
	var h = '', i, x;
	var tre = j.donTre || [], lich = j.donLich || [];
	if(!tre.length && !lich.length && !(j.donNghi||[]).length){
		el('bangDon').innerHTML = '<p class="trong">Chưa nộp đơn nào.</p>';
		return;
	}
	h += '<table><thead><tr><th>Ngày</th><th>Đơn</th><th>Trạng thái</th></tr></thead><tbody>';
	for(i=0;i<tre.length;i++){
		x = tre[i];
		h += '<tr><td>' + esc(x.ngay) + '</td><td style="text-align:left">trễ '
			+ esc(x.so_phut) + ' phút · ' + esc(x.ly_do || '') + '</td><td>'
			+ esc(tenTT(x.trang_thai)) + '</td></tr>';
	}
	var nghi = j.donNghi || [];
	for(i=0;i<nghi.length;i++){
		x = nghi[i];
		h += '<tr><td>' + esc(x.tu_ngay) + (x.den_ngay !== x.tu_ngay ? '→' + esc(x.den_ngay) : '')
			+ '</td><td style="text-align:left">nghỉ ' + esc(x.so_ngay) + ' ngày · '
			+ esc((j.loaiNghi && j.loaiNghi[x.loai]) || x.loai) + ' · ' + esc(x.ly_do || '')
			+ '</td><td>' + esc(tenTT(x.trang_thai)) + '</td></tr>';
	}
	for(i=0;i<lich.length;i++){
		x = lich[i];
		h += '<tr><td>' + esc(x.ngay) + '</td><td style="text-align:left">đổi lịch'
			+ (x.ca ? ' · ca ' + esc(x.ca) : '')
			+ (x.viec_moi ? ' · ' + esc(x.viec_moi) : '')
			+ (x.doi_sang_ngay ? ' · dời sang ' + esc(x.doi_sang_ngay) : '')
			+ '</td><td>' + esc(x.trang_thai || '') + '</td></tr>';
	}
	h += '</tbody></table>';
	el('bangDon').innerHTML = h;
}

/* Bảng `xin_tre` giữ mã trạng thái (`cho`/`duyet`/`tu_choi`), bảng `doi_lich_cv` giữ thẳng chữ
   tiếng Việt. Dịch ở đây chứ không sửa một trong hai bảng: đổi giá trị đang nằm trong kho là
   việc của một lượt nâng cấp có kế hoạch, không phải của một màn hình. */
function tenTT(ma){
	if(ma === 'cho')     return 'Chờ duyệt';
	if(ma === 'duyet')   return 'Đã duyệt';
	if(ma === 'tu_choi') return 'Không duyệt';
	return ma || '';
}

/* 🔴 KHOÁ NÚT SAU KHI BẤM — cùng lý do với ràng buộc 4 của nút LƯU CHẤM CÔNG. Mạng chậm, người
   ta bấm ba lần; ba lượt nộp đơn đi trễ liên tiếp thì hai lượt sau ĐÈ lên lượt đầu và kéo đơn
   về "chờ duyệt", kể cả khi cửa hàng trưởng vừa kịp duyệt lượt đầu. */
var DANG_GUI = false;

function guiDon(viec, than, oLoi, nut, chuXong){
	if(DANG_GUI) return;
	DANG_GUI = true;
	var b = el(nut), chuCu = b.textContent;
	b.disabled = true; b.textContent = 'ĐANG GỬI…';
	bao(oLoi,'',null);
	goi(viec, than).then(function(j){
		if(!j || !j.ok){ bao(oLoi,'dong',(j&&j.error)||'Không gửi được.'); return; }
		bao(oLoi,'xanh', chuXong(j));
		return napXin();
	}).catch(function(e){
		bao(oLoi,'dong',(e && e.message) || 'Lỗi mạng — chưa gửi được.');
	}).then(function(){
		DANG_GUI = false;
		b.disabled = false; b.textContent = chuCu;
	});
}

el('btDayHang').addEventListener('click', function(){ dayHang(); });

/* Trình duyệt báo có sóng lại -> thử ngay. `online` không bảo đảm mạng THẬT SỰ đi được (wifi
   của quán không có internet cũng bắn sự kiện này), nên `dayHang()` phải chịu được lượt hỏng —
   nó chịu được: hỏng thì giữ nguyên hàng và không nói gì. */
window.addEventListener('online', function(){ dayHang(); });

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * BÁO LƯỢT CHẤM SAI — cửa, không phải nghiệp vụ. Xem VHCC_Cham::nv_bao_sai.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
function napBaoSai(){
	return goi('dabao', { token: token() }).then(function(j){
		if(!j || !j.ok){ return; }
		/* Ngày mặc định là HÔM NAY THEO MÁY CHỦ — cùng lý do với ô ngày của đơn xin trễ. */
		if(!el('bsNgay').value){ el('bsNgay').value = j.homNay || ''; }
		el('bsNgay').max = j.homNay || '';
		var ds = j.dsCoSo || [];
		if(!el('bsCoSo').options.length){ el('bsCoSo').innerHTML = xoOption(ds, ds[0] || ''); }
		veDaBao(j.dong || []);
	});
}

function veDaBao(ds){
	if(!ds.length){ el('bangDaBao').innerHTML = ''; return; }
	var h = '<label style="margin:0 0 6px">Đã báo</label><table><thead><tr><th>Ngày</th>'
		+ '<th>Nội dung</th><th>Trạng thái</th></tr></thead><tbody>';
	for(var i=0;i<ds.length;i++){
		h += '<tr><td>' + esc(ds[i].ngay) + '</td><td style="text-align:left">'
			+ esc(ds[i].ghi_chu || '') + '</td><td>' + esc(ds[i].trang_thai || '') + '</td></tr>';
	}
	el('bangDaBao').innerHTML = h + '</tbody></table>';
}

el('btBaoSai').addEventListener('click', function(){
	guiDon('baosai', {
		token: token(),
		ngay:  el('bsNgay').value,
		coSo:  el('bsCoSo').value,
		lyDo:  el('bsLyDo').value
	}, 'loiBaoSai', 'btBaoSai', function(j){
		return '✔ Đã báo ngày ' + j.ngay + ' — ' + j.coSo
			+ (j.lai ? ' (đè lên lượt báo trước của ngày này)' : '')
			+ '. Cửa hàng trưởng sẽ thấy trên bảng công. Giờ công CHƯA đổi — chờ người có quyền sửa.';
	});
});

el('btGuiTre').addEventListener('click', function(){
	guiDon('xintre', {
		token: token(),
		ngay:  el('xtNgay').value,
		soPhut: el('xtPhut').value,
		lyDo:  el('xtLyDo').value
	}, 'loiTre', 'btGuiTre', function(j){
		return '✔ Đã gửi đơn xin trễ ' + j.phut + ' phút ngày ' + j.ngay + ' — ' + j.coSo
			+ (j.muon ? ' (nộp muộn, đơn vẫn nhận nhưng có đánh dấu)' : '')
			+ (j.lai ? ' · đè lên đơn cũ của ngày này, đơn quay về CHỜ DUYỆT' : '');
	});
});

el('plThang').addEventListener('change', vePhieu);

el('chCoSo').addEventListener('change', function(){ napDonCH(); napCongCH(); });

/* Mở một màn của trạm từ ô trong lưới. Danh sách trắng, không mở bừa theo chuỗi máy chủ gửi:
   một tên màn lạ thì `el()` trả null và `hien()` nổ, làm chết cả khối JS phía sau. */
/* ── CHUÔNG THÔNG BÁO ───────────────────────────────────────────────────────────────────────
   Hộp thư là CỦA TRANG NỘI BỘ, chuông này chỉ là một cửa sổ nhìn vào. Đọc ở đây thì bên kia
   cũng hết đỏ, và ngược lại — xem khối đầu `class-vhcc-chuong.php`. */

function veChuong(co, dem){
	/* Chưa cài plugin Nội bộ thì GIẤU HẲN cái chuông, không treo một cái rỗng đời đời không
	   bao giờ kêu. Trang Nội bộ cũng làm đúng thế ở `VHNB_Trang`. */
	hien('oChuong', !!co);
	veDemChuong(dem);
}

function veDemChuong(dem){
	var d = el('demChuong');
	var chu = (dem === null || dem === undefined) ? '' : String(dem);
	d.textContent = chu;
	hien('demChuong', '' !== chu);
}

/* ══════════════════════════════════════════════════════════════════════════════════════════
 * NHẮN TIN — anh Thắng 20/09/2026: *"mini chat trong app. Chọn thành viên cùng cửa hàng"*.
 *
 * 🔴 KHOÁ PHÒNG RIÊNG DO MÁY CHỦ DỰNG, KHÔNG DỰNG Ở ĐÂY. Ghép `'@'+coSo+'|'+maToi+'|'+maKia`
 *    ngay trong trình duyệt thì nhanh hơn một lượt gọi — nhưng hai người ghép theo hai thứ tự
 *    khác nhau là hai cái phòng khác nhau, mỗi người thấy một nửa cuộc nói chuyện và cả hai
 *    đều tưởng người kia không trả lời. Máy chủ sắp xếp hai mã rồi mới ghép (`chat_mo`).
 *
 * ⚠️ HỎI TIN MỚI BẰNG `tuId`, KHÔNG TẢI LẠI CẢ PHÒNG. Mỗi 6 giây tải lại tám mươi tin là tốn
 *    băng thông của nhân viên và làm khung tin nhảy về đầu giữa lúc người ta đang đọc.
 * ══════════════════════════════════════════════════════════════════════════════════════════ */
var CHAT_PHONG = '';      /* phòng đang mở: tên cơ sở, hoặc khoá phòng riêng */
var CHAT_TEN = '';
var CHAT_CUOI = 0;        /* id tin cuối đã vẽ */
var CHAT_NHIP = null;

function moChat(){
	hien('mChat', true);
	chatVeLop1();
	napChatPhong();
}

function chatVeLop1(){
	dungNhipChat();
	CHAT_PHONG = '';
	el('chatLop1').classList.remove('an');
	el('chatLop2').classList.add('an');
	el('chatOChon').classList.add('an');
}

function dungNhipChat(){
	if(CHAT_NHIP){ clearInterval(CHAT_NHIP); CHAT_NHIP = null; }
}

function napChatPhong(){
	goi('chat_phong', { token: token() }).then(function(j){
		if(!j || !j.ok) return;
		var h = '';
		for(var i=0;i<(j.phong||[]).length;i++){
			var cs = j.phong[i];
			var n = (j.chuaDoc && j.chuaDoc[cs]) ? j.chuaDoc[cs] : 0;
			h += '<button type="button" class="phu chat-vao" data-phong="' + esc(cs) + '"'
				+ ' data-ten="' + esc('Cả cửa hàng ' + cs) + '" style="width:100%;margin:0 0 6px">'
				+ '🏪 ' + esc(cs) + (n ? ' <b>(' + esc(n) + ' mới)</b>' : '') + '</button>';
		}
		el('chatDsPhong').innerHTML = h || '<p class="trong">Chưa gắn cơ sở nào.</p>';

		var r = '';
		for(var k=0;k<(j.rieng||[]).length;k++){
			var x = j.rieng[k];
			r += '<button type="button" class="phu chat-vao" data-phong="' + esc(x.phong) + '"'
				+ ' data-ten="' + esc(x.tenKia) + '" style="width:100%;margin:0 0 6px;text-align:left">'
				+ '👤 ' + esc(x.tenKia)
				+ (x.chuaDoc ? ' <b>(' + esc(x.chuaDoc) + ' mới)</b>' : '')
				+ '<br><span class="trong">' + esc((x.cuoi || '').slice(0, 60)) + '</span></button>';
		}
		el('chatDsRieng').innerHTML = r || '<p class="trong">Chưa có cuộc nào.</p>';
		nghenChatVao();
	}).catch(function(){});
}

function nghenChatVao(){
	var ds = document.querySelectorAll('.chat-vao');
	for(var i=0;i<ds.length;i++){
		ds[i].onclick = function(){ vaoPhongChat(this.getAttribute('data-phong'), this.getAttribute('data-ten')); };
	}
}

function vaoPhongChat(phong, ten){
	CHAT_PHONG = phong;
	CHAT_TEN = ten || phong;
	CHAT_CUOI = 0;
	el('chatTen').textContent = CHAT_TEN;
	el('chatKhung').innerHTML = '<p class="trong">Đang tải…</p>';
	bao('chatLoi','',null);
	el('chatLop1').classList.add('an');
	el('chatLop2').classList.remove('an');
	napChatTin(true);
	dungNhipChat();
	/* 6 giây một lượt, và CHỈ khi màn chat đang mở — `dungNhipChat()` gọi ở mọi đường thoát. */
	CHAT_NHIP = setInterval(function(){ napChatTin(false); }, 6000);
}

function napChatTin(dau){
	if(!CHAT_PHONG) return;
	goi('chat_ds', { token: token(), coSo: CHAT_PHONG, tuId: CHAT_CUOI }).then(function(j){
		if(!j || !j.ok){ if(dau){ bao('chatLoi','dong',(j&&j.error)||'Không mở được phòng.'); } return; }
		veChatTin(j.ds || [], dau);
	}).catch(function(){ /* mất mạng một nhịp: nhịp sau tự tới, đừng kêu */ });
}

function veChatTin(ds, dau){
	var k = el('chatKhung');
	if(dau){ k.innerHTML = ''; }
	if(dau && !ds.length){
		k.innerHTML = '<p class="trong" id="chatRong" style="text-align:center;margin:auto 0">'
			+ 'Chưa có tin nào. Gõ câu đầu tiên đi.</p>';
	}
	/* 🔴 DỌN CÂU "CHƯA CÓ TIN NÀO" KHI TIN ĐẦU TIÊN TỚI.
	   Bản đầu chỉ đặt câu ấy lúc mở phòng rỗng rồi thôi — tin mới nối vào PHÍA DƯỚI nó, nên
	   màn hình vừa nói "chưa có tin nào" vừa bày một tin ngay bên dưới. Phép thử không bắt
	   được (HTML có đủ cả hai), chỉ lộ ra khi CHỤP MÀN RA NHÌN. */
	if(ds.length){
		var r = el('chatRong');
		if(r && r.parentNode){ r.parentNode.removeChild(r); }
	}
	/* Người đang cuộn lên đọc tin cũ thì ĐỪNG kéo họ xuống đáy — chỉ tự cuộn khi họ vốn đã ở
	   đáy. Kéo bừa là mất chỗ đang đọc mỗi khi có tin mới. */
	var oDay = (k.scrollTop + k.clientHeight >= k.scrollHeight - 40);
	for(var i=0;i<ds.length;i++){
		var x = ds[i];
		if(x.id > CHAT_CUOI){ CHAT_CUOI = x.id; }
		var ben = x.cuaToi ? 'right' : 'left';
		var nen = x.cuaToi ? 'var(--nen-2)' : 'var(--the)';
		var d = document.createElement('div');
		d.style.cssText = 'text-align:' + ben + ';margin:0 0 8px';
		var chu = x.daXoa
			? '<i class="trong">(đã xoá)</i>'
			: esc(x.chu).replace(/\n/g, '<br>');
		if(x.tep){
			/* Đường xem tệp đi qua cổng có gác, KHÔNG trỏ thẳng vào uploads — xem
			   `VHCC_Chat::xem_tep()`. Thẻ phiên đi kèm trong đường dẫn vì <img> không gửi
			   được thân yêu cầu. */
			var dt = CFG.cong + (CFG.cong.indexOf('?')>=0?'&':'?')
				+ 'viec=chat_tep&id=' + encodeURIComponent(x.id)
				+ '&token=' + encodeURIComponent(token());
			chu += (chu ? '<div style="height:6px"></div>' : '')
				+ (x.tep.anh
					? '<a href="' + dt + '" target="_blank" rel="noopener">'
						+ '<img src="' + dt + '" alt="' + esc(x.tep.ten) + '"'
						+ ' style="max-width:100%;border-radius:8px;display:block"></a>'
					: '<a href="' + dt + '" target="_blank" rel="noopener">📎 '
						+ esc(x.tep.ten) + '</a>'
						+ '<div class="trong" style="font-size:11px">'
						+ esc(Math.round(x.tep.co/1024) + ' KB') + '</div>');
		}
		d.innerHTML = '<div style="display:inline-block;max-width:84%;text-align:left;'
			+ 'padding:7px 10px;border-radius:12px;border:1px solid var(--vien);background:' + nen + '">'
			+ (x.cuaToi ? '' : '<b style="font-size:12px">' + esc(x.hoTen) + '</b><br>')
			+ chu
			+ '<div class="trong" style="font-size:11px;margin-top:2px">' + esc((x.luc||'').slice(11,16))
			+ (x.cuaToi && !x.daXoa ? ' · <a href="#" data-xoa="' + esc(x.id) + '">xoá</a>' : '')
			+ '</div></div>';
		k.appendChild(d);
	}
	if(ds.length){ nghenXoaChat(); }
	if(oDay || dau){ k.scrollTop = k.scrollHeight; }
}

function nghenXoaChat(){
	var ds = el('chatKhung').querySelectorAll('[data-xoa]');
	for(var i=0;i<ds.length;i++){
		ds[i].onclick = function(e){
			e.preventDefault();
			var id = this.getAttribute('data-xoa');
			goi('chat_xoa', { token: token(), id: id }).then(function(j){
				if(!j || !j.ok){ bao('chatLoi','dong',(j&&j.error)||'Không xoá được.'); return; }
				/* Vẽ lại cả phòng: tin xoá đổi thành "(đã xoá)" tại chỗ, và tải lại từ đầu là
				   cách duy nhất chắc chắn không lệch với máy chủ. */
				CHAT_CUOI = 0; napChatTin(true);
			}).catch(function(){});
		};
	}
}

/* Tệp đang chờ gửi: { ten, b64 }. Chỉ một tệp một lần — gửi nhiều tệp thì gửi nhiều tin, và
   như thế mỗi tệp có một dòng riêng để xoá, để trả lời. */
var CHAT_TEP = null;

function chonTepChat(){
	var f = el('chatTep').files && el('chatTep').files[0];
	if(!f){ return; }
	/* ⚠️ CHẶN CỠ NGAY Ở ĐÂY, đừng để người ta chờ tải xong 20 MB rồi mới bị chối. Máy chủ vẫn
	   chặn lần nữa — đây chỉ là phép lịch sự, không phải phép gác. */
	if(f.size > 8 * 1024 * 1024){
		bao('chatLoi','dong','Tệp lớn quá 8 MB. Nén lại hoặc gửi qua đơn từ.');
		el('chatTep').value = '';
		return;
	}
	var d = new FileReader();
	d.onload = function(){
		CHAT_TEP = { ten: f.name, b64: String(d.result) };
		el('chatTepChon').classList.remove('an');
		el('chatTepChon').innerHTML = '📎 ' + esc(f.name) + ' <a href="#" id="chatBoTep">bỏ</a>';
		el('chatBoTep').onclick = function(e){ e.preventDefault(); boTepChat(); };
		bao('chatLoi','',null);
	};
	d.onerror = function(){ bao('chatLoi','dong','Không đọc được tệp này.'); };
	d.readAsDataURL(f);
}

function boTepChat(){
	CHAT_TEP = null;
	el('chatTep').value = '';
	el('chatTepChon').classList.add('an');
	el('chatTepChon').innerHTML = '';
}

function guiChat(){
	var chu = el('chatO').value;
	/* Gửi mỗi tệp không kèm chữ là chuyện thường — chỉ chối khi KHÔNG có cả hai. */
	if(!chu.trim() && !CHAT_TEP){ return; }
	var b = el('btChatGui');
	b.disabled = true;
	var goiTin = { token: token(), coSo: CHAT_PHONG, chu: chu };
	if(CHAT_TEP){ goiTin.tep = CHAT_TEP.b64; goiTin.tepTen = CHAT_TEP.ten; }
	goi('chat_gui', goiTin).then(function(j){
		b.disabled = false;
		if(!j || !j.ok){ bao('chatLoi','dong',(j&&j.error)||'Không gửi được.'); return; }
		el('chatO').value = '';
		boTepChat();
		bao('chatLoi','',null);
		napChatTin(false);
	}).catch(function(e){
		b.disabled = false;
		bao('chatLoi','dong','Mất mạng — chưa gửi được. Bấm Gửi lại.');
	});
}

function napChatNguoi(){
	el('chatOChon').classList.remove('an');
	goi('danhba', { token: token(), tim: el('chatTim').value }).then(function(j){
		if(!j || !j.ok){ el('chatDsNguoi').innerHTML = '<p class="trong">Không tải được danh bạ.</p>'; return; }
		var h = '';
		for(var i=0;i<(j.ds||[]).length;i++){
			var x = j.ds[i];
			if(TOI && x.ma_nv === TOI.maNV) continue;   /* không nhắn cho chính mình */
			h += '<div class="hang" style="margin:0 0 6px">'
				+ '<button type="button" class="phu chat-nguoi" data-ma="' + esc(x.ma_nv) + '"'
				+ ' data-ten="' + esc(x.ho_ten) + '" style="text-align:left">'
				+ esc(x.ho_ten) + '<br><span class="trong">' + esc(x.chuc_vu || '') + '</span></button>'
				+ '<button type="button" class="phu chat-goi" data-ma="' + esc(x.ma_nv) + '"'
				+ ' data-ten="' + esc(x.ho_ten) + '" style="flex:0 0 64px">📞</button></div>';
		}
		el('chatDsNguoi').innerHTML = h || '<p class="trong">Không có ai khác ở cơ sở này.</p>';
		var ds = document.querySelectorAll('.chat-nguoi');
		for(var k=0;k<ds.length;k++){
			ds[k].onclick = function(){ moChatRieng(this.getAttribute('data-ma'), this.getAttribute('data-ten')); };
		}
		var dg = document.querySelectorAll('.chat-goi');
		for(var q=0;q<dg.length;q++){
			dg[q].onclick = function(){ batDauGoi(this.getAttribute('data-ma'), this.getAttribute('data-ten')); };
		}
	}).catch(function(){});
}

function moChatRieng(ma, ten){
	/* 🔴 HỎI MÁY CHỦ KHOÁ PHÒNG — xem khối chú thích đầu phần này. */
	goi('chat_mo', { token: token(), maKia: ma }).then(function(j){
		if(!j || !j.ok){ bao('chatLoi','dong',(j&&j.error)||'Không mở được.'); return; }
		vaoPhongChat(j.phong, ten);
	}).catch(function(){});
}

el('btDongChat').addEventListener('click', function(){ dungNhipChat(); hien('mChat', false); });
el('btChatVe').addEventListener('click', function(){ chatVeLop1(); napChatPhong(); });
el('btChatGui').addEventListener('click', guiChat);
el('btChatNguoi').addEventListener('click', napChatNguoi);
el('btChatDinhKem').addEventListener('click', function(){ el('chatTep').click(); });
el('chatTep').addEventListener('change', chonTepChat);
el('chatTim').addEventListener('input', napChatNguoi);

/* ══════════════════════════════════════════════════════════════════════════════════════════
 * GỌI THOẠI — anh Thắng 20/09/2026: *"Gọi trong app đi em — tự dựng"*.
 *
 * 🔴 MÁY CHỦ KHÔNG TRUYỀN TIẾNG NÓI. Âm thanh đi thẳng giữa hai máy (WebRTC); máy chủ chỉ chuyển
 *    giúp mấy mẩu mai mối. Xem khối chú thích đầu `VHCC_Goi`.
 *
 * ⚠️ BA CHỖ PHẢI TẮT MICRO, VÀ QUÊN MỘT CHỖ LÀ MICRO MỞ TIẾP SAU KHI CÚP MÁY:
 *      · mình bấm cúp · bên kia cúp (biết qua lượt hỏi trạng thái) · kết nối đứt giữa chừng.
 *    Cả ba đều đi qua đúng một hàm `dongGoi()`. Đừng viết đường tắt nào khác.
 *
 * ⚠️ HỎI CHUÔNG CHỈ KHI ĐÃ ĐĂNG NHẬP VÀ KHÔNG ĐANG GỌI. Hỏi lúc chưa đăng nhập là mỗi 4 giây
 *    một lượt gọi bị chối; hỏi lúc đang gọi là tự phát hiện chính cuộc của mình.
 * ══════════════════════════════════════════════════════════════════════════════════════════ */
var GOI_ID = 0;           /* cuộc đang mở */
var GOI_PC = null;        /* RTCPeerConnection */
var GOI_LUONG = null;     /* luồng micro của mình */
var GOI_CUOI = 0;         /* id mẩu mai mối cuối đã xử lý */
var GOI_NHIP = null;      /* nhịp hỏi mai mối + trạng thái */
var GOI_CHUONG = null;    /* nhịp hỏi "ai gọi tôi" */
var GOI_LA_NGUOI_GOI = false;
var GOI_VE = null;        /* vé TURN, xin một lần mỗi cuộc */

function batChuongGoi(){
	if(GOI_CHUONG) return;
	GOI_CHUONG = setInterval(function(){
		if(GOI_ID || !token()) return;
		goi('goi_cho', { token: token() }).then(function(j){
			if(!j || !j.ok || !j.cuoc || GOI_ID) return;
			nhanCuocGoi(j.cuoc);
		}).catch(function(){});
	}, 4000);
}

function nhanCuocGoi(c){
	GOI_ID = c.id;
	GOI_LA_NGUOI_GOI = false;
	GOI_CUOI = 0;
	el('goiTieuDe').textContent = 'Có cuộc gọi';
	el('goiTen').textContent = c.tenGoi || c.maGoi;
	el('goiTrangThai').textContent = 'Đang đổ chuông…';
	el('btGoiNghe').classList.remove('an');
	bao('goiLoi','',null);
	hien('mGoi', true);
	nhipGoi();
}

/* Bấm gọi một người — từ danh bạ hoặc từ khung chat riêng. */
function batDauGoi(maKia, tenKia){
	goi('goi_moi', { token: token(), maKia: maKia }).then(function(j){
		if(!j || !j.ok){ bao('chatLoi','dong',(j&&j.error)||'Không gọi được.'); return; }
		GOI_ID = j.id;
		GOI_LA_NGUOI_GOI = true;
		GOI_CUOI = 0;
		el('goiTieuDe').textContent = 'Đang gọi';
		el('goiTen').textContent = tenKia || j.tenKia || maKia;
		el('goiTrangThai').textContent = 'Đang đổ chuông bên kia…';
		el('btGoiNghe').classList.add('an');
		bao('goiLoi','',null);
		hien('mGoi', true);
		nhipGoi();
	}).catch(function(){ bao('chatLoi','dong','Mất mạng — chưa gọi được.'); });
}

function nhipGoi(){
	if(GOI_NHIP) clearInterval(GOI_NHIP);
	/* 1,5 giây: mai mối phải tới nhanh thì cuộc gọi mới nối trong vài giây. Chỉ chạy trong lúc
	   có cuộc — `dongGoi()` tắt nó. */
	GOI_NHIP = setInterval(hoiGoi, 1500);
	hoiGoi();
}

function hoiGoi(){
	if(!GOI_ID) return;
	goi('goi_doc', { token: token(), id: GOI_ID, tuId: GOI_CUOI }).then(function(j){
		if(!j || !j.ok){ dongGoi('Cuộc gọi đã kết thúc.'); return; }
		if('xong' === j.trangThai){ dongGoi('Đã kết thúc.'); return; }
		if('nghe' === j.trangThai && GOI_LA_NGUOI_GOI && !GOI_PC){
			/* Bên kia vừa bấm Nghe -> mình là bên mời, dựng kết nối và gửi offer. */
			el('goiTrangThai').textContent = 'Đang nối…';
			moKetNoi(true);
		}
		for(var i=0;i<(j.ds||[]).length;i++){
			var x = j.ds[i];
			if(x.id > GOI_CUOI){ GOI_CUOI = x.id; }
			nhanMaiMoi(x);
		}
	}).catch(function(){ /* một nhịp mất mạng: nhịp sau tự tới */ });
}

function xinVeGoi(){
	if(GOI_VE) return Promise.resolve(GOI_VE);
	return goi('goi_ve', { token: token() }).then(function(j){
		if(!j || !j.ok) throw new Error((j && j.error) || 'Chưa có máy TURN.');
		GOI_VE = j.may;
		return GOI_VE;
	});
}

function moKetNoi(laBenMoi){
	if(GOI_PC) return Promise.resolve();
	return xinVeGoi().then(function(may){
		return navigator.mediaDevices.getUserMedia({ audio: true, video: false })
			.then(function(luong){
				GOI_LUONG = luong;
				GOI_PC = new RTCPeerConnection({ iceServers: may });
				for(var i=0;i<luong.getTracks().length;i++){
					GOI_PC.addTrack(luong.getTracks()[i], luong);
				}
				GOI_PC.ontrack = function(e){
					el('goiTieng').srcObject = e.streams[0];
					el('goiTrangThai').textContent = 'Đang nói chuyện';
				};
				GOI_PC.onicecandidate = function(e){
					if(e.candidate){ guiMaiMoi('ice', JSON.stringify(e.candidate)); }
				};
				GOI_PC.onconnectionstatechange = function(){
					var t = GOI_PC ? GOI_PC.connectionState : '';
					if('failed' === t){
						/* ⚠️ NÓI ĐÚNG NGUYÊN NHÂN. "Không kết nối được" thì người ta đổ cho sóng
						   yếu; chín phần mười lần này là máy TURN chưa chạy hoặc chặn cổng. */
						dongGoi('Không nối được tiếng. Thường là máy TURN chưa chạy hoặc bị chặn cổng.');
					}
					if('disconnected' === t || 'closed' === t){ dongGoi('Mất kết nối.'); }
				};
				if(laBenMoi){
					return GOI_PC.createOffer().then(function(o){
						return GOI_PC.setLocalDescription(o);
					}).then(function(){
						guiMaiMoi('offer', JSON.stringify(GOI_PC.localDescription));
					});
				}
			});
	}).catch(function(e){
		dongGoi((e && e.message) || 'Không mở được micro.');
	});
}

function nhanMaiMoi(x){
	if('offer' === x.loai){
		/* Bên nhận: có lời mời -> dựng kết nối rồi trả lời. */
		moKetNoi(false).then(function(){
			if(!GOI_PC) return;
			return GOI_PC.setRemoteDescription(JSON.parse(x.noiDung))
				.then(function(){ return GOI_PC.createAnswer(); })
				.then(function(a){ return GOI_PC.setLocalDescription(a); })
				.then(function(){ guiMaiMoi('answer', JSON.stringify(GOI_PC.localDescription)); });
		}).catch(function(){});
		return;
	}
	if(!GOI_PC) return;
	if('answer' === x.loai){
		GOI_PC.setRemoteDescription(JSON.parse(x.noiDung)).catch(function(){});
		return;
	}
	if('ice' === x.loai){
		/* ⚠️ NUỐT LỖI Ở ĐÂY LÀ ĐÚNG. Một ứng viên ICE tới trước khi có mô tả từ xa thì trình
		   duyệt ném lỗi, nhưng mấy ứng viên sau vẫn dùng được — để nó nổ ra ngoài là cả cuộc
		   gọi chết vì một mẩu đến sớm. */
		try { GOI_PC.addIceCandidate(JSON.parse(x.noiDung)).catch(function(){}); } catch(e){}
	}
}

function guiMaiMoi(loai, noiDung){
	if(!GOI_ID) return;
	goi('goi_gui', { token: token(), id: GOI_ID, loai: loai, noiDung: noiDung }).catch(function(){});
}

/* 🔴 ĐƯỜNG DUY NHẤT ĐỂ KẾT THÚC. Mọi nhánh (mình cúp · bên kia cúp · đứt kết nối · lỗi) đều
   phải đi qua đây, vì đây là chỗ TẮT MICRO. Viết một đường tắt nào khác là dựng sẵn cái ngày
   micro còn mở sau khi màn hình đã đóng. */
function dongGoi(chu){
	if(GOI_NHIP){ clearInterval(GOI_NHIP); GOI_NHIP = null; }
	if(GOI_LUONG){
		var tr = GOI_LUONG.getTracks();
		for(var i=0;i<tr.length;i++){ tr[i].stop(); }
		GOI_LUONG = null;
	}
	if(GOI_PC){ try { GOI_PC.close(); } catch(e){} GOI_PC = null; }
	el('goiTieng').srcObject = null;
	GOI_VE = null;
	if(GOI_ID){
		goi('goi_ket', { token: token(), id: GOI_ID }).catch(function(){});
		GOI_ID = 0;
	}
	if(chu){ el('goiTrangThai').textContent = chu; }
	setTimeout(function(){ hien('mGoi', false); }, chu ? 1200 : 0);
}

el('btGoiNghe').addEventListener('click', function(){
	el('btGoiNghe').classList.add('an');
	el('goiTrangThai').textContent = 'Đang nối…';
	goi('goi_tra_loi', { token: token(), id: GOI_ID, dongY: true }).then(function(j){
		if(!j || !j.ok){ dongGoi((j && j.error) || 'Không nghe máy được.'); }
		/* Không dựng kết nối ở đây: bên mời sẽ gửi offer, và `nhanMaiMoi()` lo phần còn lại. */
	}).catch(function(){ dongGoi('Mất mạng.'); });
});

el('btGoiCup').addEventListener('click', function(){ dongGoi(null); });

/* ⚠️ APP XUỐNG NỀN THÌ CÚP. Trình duyệt trong app có thể bị hệ điều hành dừng, và lúc ấy cuộc
   gọi treo ở đầu bên kia mà không ai biết. Thà cúp rõ ràng. */
document.addEventListener('visibilitychange', function(){
	if(document.hidden && GOI_ID && !GOI_PC){ dongGoi(null); }
});

function moChuong(){
	hien('mChuong', true);
	napChuong();
}

function napChuong(){
	el('dsTin').innerHTML = '<p class="trong">Đang tải…</p>';
	goi('chuong', { token: token() })
		.then(function(j){
			if(!j || !j.ok){
				el('dsTin').innerHTML = '<p class="trong">' + esc((j&&j.error)||'Không đọc được.') + '</p>';
				return;
			}
			veDemChuong(j.dem);
			veDsTin(j.ds || []);
		})
		.catch(function(){
			el('dsTin').innerHTML = '<p class="trong">Mất mạng — thử lại sau.</p>';
		});
}

function veDsTin(ds){
	if(!ds.length){
		el('dsTin').innerHTML = '<p class="trong">Chưa có thông báo nào.</p>';
		return;
	}
	var h = '', i;
	for(i = 0; i < ds.length; i++){
		var t = ds[i];
		var chu = t.chu || '';
		/* Gộp khoá: 3 người bình luận cùng một bài thì `soLan` = 3. Nói ra con số chứ không
		   đẻ ba dòng — xem khối "GỘP THEO khoa" ở `class-vhnb-bao.php`. */
		if((t.soLan || 1) > 1){ chu += ' (' + t.soLan + ' lượt)'; }
		h += '<button class="tin' + (t.daDoc ? '' : ' moi') + '" data-id="' + esc(t.id)
			+ '" data-di="' + esc(t.duongDan || '') + '">'
			+ esc(chu)
			+ '<span class="luc">' + esc(gioNgan(t.luc)) + '</span>'
			+ '</button>';
	}
	el('dsTin').innerHTML = h;

	/* Gắn thẳng vào từng nút, đúng lối các bảng khác trong tệp này (xem `bangCongCH`,
	   `dsNgay`). innerHTML thay hẳn nút cũ nên listener cũ đi theo nút cũ, không tồn đọng. */
	var nt = el('dsTin').querySelectorAll('.tin');
	for(i = 0; i < nt.length; i++){
		nt[i].addEventListener('click', function(){
			docTin(this.getAttribute('data-id'), this.getAttribute('data-di'));
		});
	}
}

/* '2026-09-17 14:05:00' -> '17/09 14:05'. Cắt chuỗi chứ KHÔNG `new Date(chuỗi)`: Safari trả
   `Invalid Date` cho dạng có dấu cách, và lỗi ấy chỉ lộ ra trên iPhone. */
function gioNgan(s){
	var t = String(s || '');
	if(t.length < 16){ return t; }
	return t.slice(8,10) + '/' + t.slice(5,7) + ' ' + t.slice(11,16);
}

/* Bấm vào một tin: đánh dấu đã đọc, rồi mở đường dẫn nếu có.
   ⚠️ Đường dẫn của Nội bộ là đường TRONG trang Nội bộ. Mở ở TAB MỚI chứ không điều hướng cả
      trạm sang đó — đang chấm công dở mà bị đá đi là mất lượt chấm, và lúc quay lại phải gõ
      PIN từ đầu. */
function docTin(id, di){
	goi('chuongdoc', { token: token(), id: id })
		.then(function(j){ if(j && j.ok){ veDemChuong(j.dem); } })
		.catch(function(){});
	napChuong();
	if(di){ window.open(di, '_blank', 'noopener'); }
}

el('btChuong').addEventListener('click', moChuong);
el('btDongChuong').addEventListener('click', function(){ hien('mChuong', false); });

el('btDocHet').addEventListener('click', function(){
	goi('chuongdoc', { token: token(), id: 0 })
		.then(function(j){ if(j && j.ok){ veDemChuong(j.dem); } napChuong(); })
		.catch(function(){});
});

/* Quay lại trang sau khi đi đâu đó thì hỏi lại con số. Không có nhịp hỏi định kỳ: trạm mở cả
   ngày trên máy quầy, hỏi mỗi phút là 480 lượt gọi một ngày cho một con số hiếm khi đổi. */
document.addEventListener('visibilitychange', function(){
	if(document.hidden || el('oChuong').classList.contains('an')){ return; }
	goi('chuong', { token: token() })
		.then(function(j){ if(j && j.ok){ veDemChuong(j.dem); } })
		.catch(function(){});
});

function moMan(ten){
	if('mThemNv' === ten){ moThemNv(); }
	if('mPhieu'  === ten){ moPhieu(); }
	if('mXinTre' === ten){ moXinTre(); }
	if('mXinNghi' === ten){ moXinNghi(); }
	if('mNhanSu' === ten){ moNhanSu(); }
	if('mKhaiGio' === ten){ moKhaiGio(); }
	if('mXinBu' === ten){ moXinBu(); }
	if('mGioLuong' === ten){ moGioLuong(); }
	/* ⚠️ THÊM Ô Ở `VHCC_Ung` THÔI LÀ CHƯA ĐỦ — phải thêm một dòng ở đây nữa. Thiếu nó thì ô
	   hiện ra, bấm vào, và KHÔNG CÓ GÌ XẢY RA: `moMan()` không khớp tên nào nên im lặng thoát.
	   Không lỗi, không cảnh báo — đúng kiểu người ta bảo "app hỏng". */
	if('mChat' === ten){ moChat(); }
}

/* ── LOẠI GIỜ LƯƠNG ─────────────────────────────────────────────────────────────────────────
   Hai đường: hộp KẾT CA ghi thẳng, còn màn Giờ công lương gửi cửa hàng trưởng duyệt. Xem khối
   chú thích "HAI CỬA" ở `VHCC_LoaiGio` — chúng cố ý không giống nhau. */

var LG = null;          // { hoi, tab, ds:[{khoa,ten,gia}] } — nạp một lần sau khi đăng nhập
var LG_NGAY = [];       // mấy ngày đang bày trên màn Giờ công lương

function napLoaiGio(){
	return goi('lgviec', { token: token() }).then(function(j){
		LG = (j && j.ok) ? j : null;
	}).catch(function(){ LG = null; });
}

/* Mấy thẻ <option> của danh sách việc — dựng từ danh sách MÁY CHỦ trả về, không gõ cứng trong
   HTML. Trả riêng phần option để dùng được cả cho ô có sẵn (`#kcViec`) lẫn ô sinh ra trong
   danh sách ngày. */
function optViec(dangChon){
	var o = ['<option value="">— chưa chọn —</option>'];
	var ds = (LG && LG.ds) || [];
	for(var i=0;i<ds.length;i++){
		var t = ds[i].ten;
		o.push('<option value="' + esc(t) + '"' + (t === dangChon ? ' selected' : '') + '>'
			+ esc(t) + (ds[i].gia ? ' — ' + Number(ds[i].gia).toLocaleString('vi-VN') + 'đ/h' : '')
			+ '</option>');
	}
	return o.join('');
}

/* ---- hộp hỏi lúc kết ca ---- */
var KC = null;          // { ngay, ma } của lượt giờ ra vừa ghi

function hoiKetCa(ngay, ma){
	if(!LG || !LG.hoi){ return; }
	KC = { ngay: ngay, ma: ma };
	el('kcViec').innerHTML = optViec('');
	el('kcMo').textContent = 'Ca ngày ' + ngay + '. Chọn đúng việc thì lương tính đúng đơn giá của việc ấy.';
	bao('kcBao', '', '');
	hien('mKetCa', true);
}

el('btKcLuu').addEventListener('click', function(){
	var v = el('kcViec').value;
	if(!v){ bao('kcBao','dong','Chọn một việc, hoặc bấm Để sau.'); return; }
	if(!KC){ hien('mKetCa', false); return; }
	var b = this; b.disabled = true;
	goi('lgdat', { token: token(), ngay: KC.ngay, ma: KC.ma, viec: v }).then(function(j){
		if(!j || !j.ok){ bao('kcBao','dong',(j&&j.error)||'Không lưu được.'); return; }
		hien('mKetCa', false);
		el('baoCham').innerHTML += '<div class="xanh">✔ Loại giờ ca này: <b>'
			+ esc(j.viec) + '</b></div>';
	}).catch(function(e){
		bao('kcBao','dong',(e&&e.message)||'Lỗi mạng — chưa lưu được loại giờ.');
	}).then(function(){ b.disabled = false; });
});
el('btKcBo').addEventListener('click', function(){ hien('mKetCa', false); });

/* ---- màn Giờ công lương ---- */
function moGioLuong(){
	hien('mGioLuong', true);
	el('lgDs').innerHTML = '<p class="trong">Đang nạp…</p>';
	goi('lgds', { token: token(), soNgay: 14 }).then(function(j){
		if(!j || !j.ok){ el('lgDs').innerHTML = '<p class="trong">'
			+ esc((j&&j.error)||'Không nạp được.') + '</p>'; return; }
		if(j.viec){ LG = LG || {}; LG.ds = j.viec; }
		LG_NGAY = j.ds || [];
		veGioLuong();
	}).catch(function(e){
		el('lgDs').innerHTML = '<p class="trong">' + esc((e&&e.message)||'Lỗi mạng.') + '</p>';
	});
}

function veGioLuong(){
	if(!LG_NGAY.length){
		el('lgDs').innerHTML = '<p class="trong">Nửa tháng nay chưa có lượt chấm công nào.</p>';
		return;
	}
	var o = [];
	for(var i=0;i<LG_NGAY.length;i++){
		var d = LG_NGAY[i];
		var gio = (d.gio === null || d.gio === undefined) ? '' : (d.gio + 'h');
		o.push('<div class="the"><div class="hang" style="align-items:center;gap:8px">'
			+ '<b style="flex:0 0 auto">' + esc(d.ngay) + (d.hauTo ? ' · ' + esc(d.hauTo) : '') + '</b>'
			+ '<span class="mo" style="flex:1">' + esc(d.vao || '—') + ' → ' + esc(d.ra || '—')
			+ (gio ? ' · ' + gio : '') + '</span></div>');
		/* 🔴 ĐANG CHỜ DUYỆT THÌ KHÔNG MỜI GỬI TIẾP. Bày ô xổ nữa là người ta gửi lần hai cho
		   cùng một ngày, và cửa hàng trưởng thấy hai dòng nói hai điều khác nhau. */
		if(d.dangCho){
			o.push('<p class="vang" style="margin:8px 0 0">⏳ Đang chờ duyệt: <b>'
				+ esc(d.dangCho) + '</b>' + (d.viec ? ' (hiện đang là ' + esc(d.viec) + ')' : '')
				+ '</p></div>');
			continue;
		}
		o.push('<div style="margin:8px 0 0"><select id="lgV' + i + '">'
			+ optViec(d.viec || '') + '</select></div></div>');
	}
	el('lgDs').innerHTML = o.join('');
}

el('btLgGui').addEventListener('click', function(){
	var dong = [];
	for(var i=0;i<LG_NGAY.length;i++){
		var d = LG_NGAY[i];
		if(d.dangCho){ continue; }
		var o = el('lgV' + i);
		if(!o || !o.value || o.value === (d.viec || '')){ continue; }
		dong.push({ ngay: d.ngay, hauTo: d.hauTo, viec: o.value });
	}
	if(!dong.length){ bao('lgBao','dong','Chưa đổi loại giờ của ngày nào.'); return; }
	var b = this; b.disabled = true;
	goi('lggui', { token: token(), dong: dong, lyDo: el('lgLyDo').value }).then(function(j){
		if(!j || !j.ok){ bao('lgBao','dong',(j&&j.error)||'Không gửi được.'); return; }
		bao('lgBao','xanh','✔ Đã gửi ' + j.so + ' ngày cho cửa hàng trưởng duyệt. '
			+ 'Bảng lương chỉ đổi khi họ duyệt.');
		el('lgLyDo').value = '';
		moGioLuong();
	}).catch(function(e){
		bao('lgBao','dong',(e&&e.message)||'Lỗi mạng — chưa gửi được.');
	}).then(function(){ b.disabled = false; });
});
el('btDongLg').addEventListener('click', function(){ hien('mGioLuong', false); });

/* ── XIN BÙ GIỜ ─────────────────────────────────────────────────────────────────────────────
   Hai cấp duyệt: cửa hàng trưởng rồi kế toán. Giờ chỉ vào bảng công sau cấp hai — màn này nói
   rõ điều ấy ở mọi chỗ, vì người gửi hay tưởng gửi xong là xong. */

function moXinBu(){
	hien('mXinBu', true);
	if(!el('xbNgay').value){ el('xbNgay').value = (NG && NG.homNay) ? NG.homNay : ''; }
	napXinBu();
	napXbCa();
}

function napXinBu(){
	el('dsXinBu').innerHTML = '<p class="trong">Đang tải…</p>';
	goi('xinbuds', { token: token() })
		.then(function(j){
			if(!j || !j.ok){ el('dsXinBu').innerHTML = '<p class="trong">Không đọc được.</p>'; return; }
			if(!el('xbNgay').value && j.homNay){ el('xbNgay').value = j.homNay; }
			veDsXinBu(j.ds || [], j.ten || {});
		})
		.catch(function(){ el('dsXinBu').innerHTML = '<p class="trong">Mất mạng — thử lại sau.</p>'; });
}

function veDsXinBu(ds, ten){
	if(!ds.length){
		el('dsXinBu').innerHTML = '<p class="trong">Chưa gửi đơn nào.</p>';
		return;
	}
	var h = '', i;
	for(i = 0; i < ds.length; i++){
		var x = ds[i];
		var tt = ten[x.trang_thai] || x.trang_thai;
		h += '<div class="tin' + ('duyet' === x.trang_thai ? '' : ' moi') + '">'
			+ '<b>' + esc(ngayNgan(x.ngay)) + '</b> · ' + esc(x.vao) + '–' + esc(x.ra)
			+ '<span class="luc">' + esc(tt)
			+ (x.ly_do_choi ? (' — ' + esc(x.ly_do_choi)) : '') + '</span></div>';
	}
	el('dsXinBu').innerHTML = h;
}

function xinBu(){
	if(!el('xbNgay').value){ bao('kqXinBu','dong','Chọn ngày đã.'); return; }
	el('btXinBu').disabled = true;
	goi('xinbu', { token: token(), ngay: el('xbNgay').value, vao: el('xbVao').value,
			ra: el('xbRa').value, lyDo: el('xbLyDo').value })
		.then(function(j){
			el('btXinBu').disabled = false;
			if(!j || !j.ok){ bao('kqXinBu','dong',(j&&j.error)||'Không gửi được.'); return; }
			bao('kqXinBu','xanh','Đã gửi. Chờ cửa hàng trưởng duyệt, rồi kế toán duyệt thì giờ mới vào bảng công.');
			el('xbVao').value = '';
			el('xbRa').value = '';
			el('xbLyDo').value = '';
			napXinBu();
		})
		.catch(function(){
			el('btXinBu').disabled = false;
			bao('kqXinBu','dong','Mất mạng — thử lại.');
		});
}

el('btXinBu').addEventListener('click', xinBu);
el('btDongXinBu').addEventListener('click', function(){ hien('mXinBu', false); });
el('xbNgay').addEventListener('change', function(){ bao('kqXinBu','',null); });
/* ══════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 TỰ CHÈN DẤU HAI CHẤM, VÀ NÓI RA ĐANG XIN MẤY GIỜ.
 *
 * Anh Thắng 18/09/2026 gửi ảnh màn này: ô Giờ vào gõ `1000`, Giờ ra `1700`.
 *
 * Đó là cách gõ TỰ NHIÊN NHẤT trên bàn phím số của điện thoại — ô này là `inputmode=numeric`,
 * tức chính mình mời người ta gõ toàn số. Mà máy chủ đòi đúng `HH:mm`
 * (`VHCC_XinBu::phut()` khớp `/^(\d{2}):(\d{2})$/`), nên `1000` bị chối thẳng.
 *
 * ⚠️ VÀ NÓ CHỐI Ở MỘT CHỖ KHÔNG AI NGỜ. Màn kiểm lý do TRƯỚC, nên người ta thấy "ghi rõ vì sao,
 *    ít nhất 5 chữ", gõ lý do, bấm lại — lúc ấy mới ăn lỗi giờ. Hai lần bị chối cho một lần
 *    điền, và lần thứ hai nói về một ô họ tưởng đã xong.
 *
 * 🔴 SỬA Ở PHÍA GÕ, KHÔNG NỚI Ở MÁY CHỦ. `phut()` chặt là đúng: nó là chỗ cuối cùng trước khi
 *    một con giờ thành công thành tiền. Nới nó ra để nhận `1000` là mở cho cả `10 0`, `1:0:0`
 *    và mọi thứ na ná. Ở đây chuẩn hoá NGAY LÚC GÕ, người dùng thấy `10:00` hiện ra dưới ngón
 *    tay mình — họ biết hệ hiểu đúng, trước cả khi bấm gửi.
 *
 * ⚠️ CHÈN KHI ĐỦ 3 CHỮ SỐ, không chèn ngay ở chữ số thứ 2. Chèn sớm thì người gõ `1` rồi `0`
 *    thấy `10:` nhảy ra giữa chừng và tưởng mình gõ nhầm; chờ tới chữ số thứ ba thì đúng lúc
 *    họ đang chuyển sang phần phút.
 * ⚠️ KHÔNG đụng khi người ta đang XOÁ. Tự chèn lại dấu vừa xoá là ô không xoá nổi.
 * ══════════════════════════════════════════════════════════════════════════════════════════ */
function xbChuanGio(o, xoa){
	var so = (o.value || '').replace(/\D/g, '').slice(0, 4);
	if(xoa){ return; }
	o.value = (so.length >= 3) ? (so.slice(0, 2) + ':' + so.slice(2)) : so;
}

/** Số phút của một ô giờ, hoặc null nếu chưa ra hình HH:mm. Cùng luật với máy chủ. */
function xbPhut(v){
	var m = /^(\d{2}):(\d{2})$/.exec(String(v || '').trim());
	if(!m){ return null; }
	var g = +m[1], p = +m[2];
	if(g > 23 || p > 59){ return null; }
	return g * 60 + p;
}

/* 🔴 NÓI RA ĐANG XIN MẤY GIỜ. Người gửi đơn đang nhớ lại một ngày đã qua; con số giờ là thứ
   cửa hàng trưởng và kế toán sẽ nhìn để duyệt, nên người gửi phải thấy nó TRƯỚC khi gửi.
   Và bắt được ngay hai lỗi hay gặp mà máy chủ chỉ nói bằng một câu cụt: giờ ra sớm hơn giờ
   vào (gõ ngược hai ô), và giờ quá dài (gõ nhầm 0700 thành 1700). */
/* Ca làm của ngày đang chọn — máy chủ trả về, xem cửa `xinbuca`. `null` = chưa hỏi xong. */
var XB_CA = null;

/** Đổi số phút ra "7h" / "7h30" — một chỗ duy nhất, để mọi dòng dưới đây nói cùng một kiểu. */
function xbGioChu(p){
	return Math.floor(p / 60) + 'h' + (p % 60 ? String(p % 60).padStart(2, '0') : '');
}

/* 🔴 BÀY CA LÀM CỦA HÔM ẤY RA NGAY LÚC GÕ.
   Anh Thắng 18/09/2026: *"Hiện giờ thiếu so với ca làm"*.
   Cửa hàng trưởng nhìn một đơn xin 10:00–17:00 thì câu đầu tiên trong đầu họ là "hôm ấy bạn
   này trực ca mấy?". Người gửi không thấy ca của chính mình lúc gõ nên rất hay xin lệch — rồi
   đơn bị chối, gửi lại, hai cấp duyệt lại từ đầu. Bày ra ở đây là cắt trọn vòng ấy. */
function napXbCa(){
	var ng = el('xbNgay').value;
	XB_CA = null;
	el('xbCa').textContent = '';
	if(!ng){ xbHienTong(); return; }
	goi('xinbuca', { token: token(), ngay: ng }).then(function(j){
		if(!j || !j.ok || el('xbNgay').value !== ng){ return; }   // đổi ngày giữa chừng thì bỏ
		XB_CA = j;
		var ds = j.ca || [];
		if(!ds.length){
			/* 🔴 KHÔNG CÓ LỊCH THÌ NÓI LÀ KHÔNG CÓ, ĐỪNG IM. Im thì người dùng tưởng màn hỏng,
			   hoặc tệ hơn, tưởng mình không phải trực hôm ấy. */
			el('xbCa').textContent = 'Hôm ấy không thấy ca nào xếp cho anh/chị — cứ xin theo giờ thật.';
		} else {
			var t = [];
			for(var i = 0; i < ds.length; i++){ t.push(ds[i].ten + ' ' + ds[i].tu + '–' + ds[i].den); }
			el('xbCa').textContent = 'Ca hôm ấy: ' + t.join(' · ') + ' (' + xbGioChu(j.tongPhut) + ')';
		}
		xbHienTong();
	}).catch(function(){ /* mất mạng thì thôi, đơn vẫn gửi được */ });
}

function xbHienTong(){
	var a = xbPhut(el('xbVao').value), b = xbPhut(el('xbRa').value), o = el('xbTong');
	if(null === a || null === b){ o.textContent = ''; o.className = 'mo'; return; }
	if(b <= a){
		o.className = 'mo chu-hong';
		o.textContent = '⚠ Giờ ra phải sau giờ vào — có phải hai ô đang ngược nhau không?';
		return;
	}
	var p = b - a;
	var chu = '= xin bù ' + xbGioChu(p);
	/* 🔴 SO VỚI CA: nói ra THIẾU hay DƯ, và bao nhiêu. Chỉ so khi máy chủ THẬT SỰ có ca —
	   `XB_CA.tongPhut > 0`. Không có lịch mà vẫn so thì mọi đơn đều "dư", và một lời cảnh báo
	   sai thì lần sau người ta không đọc nữa. */
	if(XB_CA && XB_CA.tongPhut > 0){
		var l = p - XB_CA.tongPhut;
		if(0 === l){ chu += ' — vừa đúng ca'; }
		else if(l < 0){ chu += ' — THIẾU ' + xbGioChu(-l) + ' so với ca'; }
		else { chu += ' — DƯ ' + xbGioChu(l) + ' so với ca'; }
	}
	o.className = 'mo';
	o.textContent = chu + (p > 16 * 60 ? ' — dài bất thường, xem lại giúp em' : '');
}

el('xbNgay').addEventListener('change', napXbCa);

[['xbVao'], ['xbRa']].forEach(function(x){
	var o = el(x[0]);
	/* `keydown` chỉ để biết người ta đang XOÁ; việc chuẩn hoá làm ở `input` (bàn phím ảo trên
	   điện thoại không bắn keydown đáng tin cho mọi phím). */
	var dang_xoa = false;
	o.addEventListener('keydown', function(e){
		dang_xoa = ('Backspace' === e.key || 'Delete' === e.key);
	});
	o.addEventListener('input', function(){
		bao('kqXinBu','',null);
		xbChuanGio(o, dang_xoa);
		dang_xoa = false;
		xbHienTong();
	});
	/* Rời ô thì chuẩn hoá lần cuối, kể cả khi vừa xoá — lúc ấy người ta đã gõ xong. */
	o.addEventListener('blur', function(){ xbChuanGio(o, false); xbHienTong(); });
});
el('xbLyDo').addEventListener('input', function(){ bao('kqXinBu','',null); });

/* ── KHAI GIỜ KHÁC ──────────────────────────────────────────────────────────────────────────
   Con số này KHÔNG vào lương. Mọi chỗ bày nó ra đều phải nói thẳng điều ấy — người ta khai
   xong mà tưởng được trả tiền thì đó là một lời hứa không ai hứa. */

function moKhaiGio(){
	hien('mKhaiGio', true);
	if(!el('kgNgay').value){ el('kgNgay').value = (NG && NG.homNay) ? NG.homNay : ''; }
	napKhai();
}

function napKhai(){
	el('dsKhai').innerHTML = '<p class="trong">Đang tải…</p>';
	goi('khaids', { token: token() })
		.then(function(j){
			if(!j || !j.ok){
				el('dsKhai').innerHTML = '<p class="trong">Không đọc được.</p>';
				return;
			}
			if(!el('kgNgay').value && j.homNay){ el('kgNgay').value = j.homNay; }
			veDsKhai(j.ds || []);
		})
		.catch(function(){ el('dsKhai').innerHTML = '<p class="trong">Mất mạng — thử lại sau.</p>'; });
}

function veDsKhai(ds){
	if(!ds.length){
		el('dsKhai').innerHTML = '<p class="trong">Chưa khai ngày nào.</p>';
		return;
	}
	var h = '<table><thead><tr><th>Ngày</th><th>Giờ</th><th>Việc</th></tr></thead><tbody>', i, t = 0;
	for(i = 0; i < ds.length; i++){
		var x = ds[i];
		t += Number(x.so_gio) || 0;
		h += '<tr><td>' + esc(ngayNgan(x.ngay)) + '</td>'
			+ '<td>' + esc(soGio(x.so_gio)) + '</td>'
			+ '<td class="mo">' + esc(x.viec || '—') + '</td></tr>';
	}
	h += '</tbody><tfoot><tr><td><b>Tổng</b></td><td><b>' + esc(soGio(t))
		+ '</b></td><td class="mo">không tính lương</td></tr></tfoot></table>';
	el('dsKhai').innerHTML = h;
}

/* '2026-09-18' -> '18/09'. Cắt chuỗi chứ không new Date — Safari trả Invalid Date. */
function ngayNgan(s){
	var t = String(s || '');
	return (t.length < 10) ? t : (t.slice(8,10) + '/' + t.slice(5,7));
}
function soGio(v){
	var n = Number(v) || 0;
	return n.toFixed(2).replace('.', ',');
}

function khaiGio(){
	var ng = el('kgNgay').value, g = el('kgGio').value;
	if(!ng){ bao('kqKhai','dong','Chọn ngày đã.'); return; }
    if('' === String(g).trim()){ bao('kqKhai','dong','Gõ số giờ đã.'); return; }
	el('btKhai').disabled = true;
	goi('khaigio', { token: token(), ngay: ng, soGio: g,
			viec: el('kgViec').value, ghiChu: el('kgGhi').value })
		.then(function(j){
			el('btKhai').disabled = false;
			if(!j || !j.ok){ bao('kqKhai','dong',(j&&j.error)||'Không lưu được.'); return; }
			if(j.xoa){ bao('kqKhai','vang','Đã xoá dòng khai của ngày ' + ngayNgan(ng) + '.'); }
			else { bao('kqKhai','xanh','Đã lưu ' + soGio(j.soGio) + ' giờ cho ngày '
				+ ngayNgan(ng) + '. Số này KHÔNG tính vào lương.'); }
			el('kgGio').value = '';
			el('kgViec').value = '';
			el('kgGhi').value = '';
			napKhai();
		})
		.catch(function(){
			el('btKhai').disabled = false;
			bao('kqKhai','dong','Mất mạng — thử lại.');
		});
}

el('btKhai').addEventListener('click', khaiGio);
el('btDongKhai').addEventListener('click', function(){ hien('mKhaiGio', false); });
el('kgNgay').addEventListener('change', napKhai);
el('kgGio').addEventListener('input', function(){ bao('kqKhai','',null); });
el('kgViec').addEventListener('input', function(){ bao('kqKhai','',null); });
el('kgGhi').addEventListener('input', function(){ bao('kqKhai','',null); });

/* ── DANH SÁCH NHÂN SỰ CỦA CƠ SỞ ────────────────────────────────────────────────────────── */

function moNhanSu(){
	var ds = (CH && CH.dsCoSo) ? CH.dsCoSo : [];
	if(!ds.length){
		window.alert('Tài khoản của anh/chị chưa được giao cơ sở nào.');
		return;
	}
	if(!el('nsCoSo').options.length){ el('nsCoSo').innerHTML = xoOption(ds, ds[0] || ''); }
	hien('mNhanSu', true);
	napNhanSu();
}

function napNhanSu(){
	el('dsNhanSu').innerHTML = '<p class="trong">Đang tải…</p>';
	goi('chnhansu', { token: token(), coSo: el('nsCoSo').value, tim: el('nsTim').value })
		.then(function(j){
			if(!j || !j.ok){
				el('nsPhu').textContent = '';
				el('dsNhanSu').innerHTML = '<p class="trong">' + esc((j&&j.error)||'Không đọc được.') + '</p>';
				return;
			}
			el('nsPhu').textContent = j.coSo + ' · ' + j.so + ' người';
			var ds = j.nguoi || [];
			if(!ds.length){
				el('dsNhanSu').innerHTML = '<p class="trong">Không có ai khớp.</p>';
				return;
			}
			/* 🔴 NGƯỜI CHƯA CÓ PIN LÀ VIỆC PHẢI LÀM, NÊN NÓI NGAY Ở ĐẦU. Họ chưa đăng nhập được,
			   tức chưa chấm công được — mà nhìn danh sách hai mươi thẻ thì không ai đếm ra. */
			var h = '';
			if(j.chuaPin){
				h += '<div class="vang" style="margin:0 0 10px"><b>' + esc(j.chuaPin)
				  +  '</b> người chưa có PIN nên chưa đăng nhập được. Bảo họ vào trang chấm công, '
				  +  'bấm <b>Quên PIN</b>, gõ họ tên + căn cước của chính mình rồi tự đặt.</div>';
			}
			for(var i=0;i<ds.length;i++){
				var x = ds[i];
				h += '<div class="the" style="margin:0 0 10px;padding:12px">'
				  +  '<b style="display:block;font-size:15px">' + esc(x.hoTen) + '</b>'
				  +  '<span class="ct" style="display:block;margin:2px 0 6px">' + esc(x.maNV)
				  +  (x.chucVu ? ' · ' + esc(x.chucVu) : '')
				  +  (x.sdt ? ' · ' + esc(x.sdt) : '') + '</span>'
				  +  '<span class="ct" style="display:block">'
				  +  (x.coPin ? '✔ có PIN' : '<b>⚠️ chưa có PIN</b>')
				  +  (x.coCccd ? '' : ' · <b>chưa khai căn cước</b> nên chưa tự đặt PIN được')
				  +  (x.trangThai ? ' · ' + esc(x.trangThai) : '') + '</span>'
				  +  '</div>';
			}
			el('dsNhanSu').innerHTML = h;
		}).catch(function(){
			el('dsNhanSu').innerHTML = '<p class="trong">Chưa đọc được — kiểm tra mạng rồi mở lại.</p>';
		});
}

function moXinNghi(){
	bao('loiNghi','',null);
	hien('mXinNghi', true);
	/* `napXin()` là nơi duy nhất biết hôm nay theo MÁY CHỦ là ngày nào, danh sách loại nghỉ và
	   quỹ phép còn lại — gọi lại nó thay vì màn tự đoán. */
	if(!XIN){ napXin(); }
}

/* Màn nộp đơn đi trễ. `napXin()` là nơi duy nhất biết "hôm nay theo MÁY CHỦ là ngày nào" và
   mấy cái trần — nên gọi lại nó thay vì màn tự đoán. */
function moXinTre(){
	bao('loiTre','',null);
	hien('mXinTre', true);
	if(!XIN){ napXin(); }
}

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * MÀN PHIẾU LƯƠNG — hai phần, và ai thấy phần nào là do MÁY CHỦ quyết.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
var PL_CS_THANG = '';

function moPhieu(){
	hien('mPhieu', true);
	phanPhieu(false);
	napPhieu();
}

/* `cs` = true thì mở phần CẢ CỬA HÀNG. Nút nào đang chọn thì mang kiểu `chinh`, nút kia `phu` —
   không có nút nào "đang chọn" thì người ta không biết mình đang xem cái gì.

   🔴 ĐỔI KIỂU BẰNG `classList`, KHÔNG GÁN ĐÈ `className`. Bản trước gán
   `el('btPlCs').className = 'phu'`, và câu ấy XOÁ LUÔN lớp `an` mà markup đặt sẵn để giấu nút
   "Cả cửa hàng". Hậu quả: MỌI nhân viên mở Phiếu lương đều thấy nút ấy hiện ra — `napPhieu()`
   chỉ biết BỎ `an` khi máy chủ gửi danh sách cơ sở quản lý, nó không bao giờ thêm `an` lại.
   Bấm vào thì cửa `phieucs` ở máy chủ chối, nên không lộ dữ liệu của ai; nhưng người dùng nhận
   một nút dẫn tới câu lỗi, và đó là thứ họ sẽ đi hỏi. */
function phanPhieu(cs){
	hien('plPhanToi', !cs);
	hien('plPhanCs', cs);
	kieuNutPl(el('btPlToi'), !cs);
	kieuNutPl(el('btPlCs'),  cs);
	if(cs){ veThangPlCs(); }
}

/* Chỉ đụng vào hai lớp kiểu, mọi lớp khác (`an`…) giữ nguyên. */
function kieuNutPl(o, dang){
	if(!o){ return; }
	o.classList.toggle('chinh', !!dang);
	o.classList.toggle('phu', !dang);
}

function veThangPlCs(){
	el('plNhanThang').textContent = 'Tháng ' + (PL_CS_THANG || '—').replace(/^(\d{4})-(\d{2})$/, '$2/$1');
	napPhieuCs();
}

function doiThangPlCs(b){
	var p = (PL_CS_THANG || '').split('-');
	if(p.length !== 2){ return; }
	var d = new Date(Date.UTC(+p[0], +p[1] - 1 + b, 1));
	PL_CS_THANG = d.getUTCFullYear() + '-' + ('0' + (d.getUTCMonth() + 1)).slice(-2);
	veThangPlCs();
}

function napPhieuCs(){
	el('bangPhieuCs').innerHTML = '<p class="trong">Đang tải…</p>';
	goi('phieucs', { token: token(), coSo: el('plCsCoSo').value, thang: PL_CS_THANG })
		.then(function(j){
			if(!j || !j.ok){
				el('bangPhieuCs').innerHTML = '<p class="trong">' + esc((j&&j.error)||'Không đọc được.') + '</p>';
				return;
			}
			var ds = j.dong || [];
			/* 🔴 NÓI RA NHÂN VIÊN BÊN DƯỚI ĐANG THẤY GÌ. Người quản lý xem được cả tháng kế toán
			   còn đang gõ dở — nhưng nếu không nói rõ tháng ấy chưa công bố thì họ tưởng nhân
			   viên cũng đang nhìn đúng mấy con số này. */
			var h = j.daCongBo
				? '<div class="xanh" style="margin:0 0 10px">✔ Tháng này <b>đã công bố</b> — '
					+ 'nhân viên xem được phiếu của chính họ.</div>'
				: '<div class="vang" style="margin:0 0 10px">Tháng này <b>chưa công bố</b>. '
					+ 'Anh/chị xem được, nhưng nhân viên thì chưa. Kế toán bấm công bố ở trang quản trị.</div>';
			if(!ds.length){
				el('bangPhieuCs').innerHTML = h + '<p class="trong">Tháng này chưa có ai có giờ ở '
					+ esc(j.coSo) + '.</p>';
				return;
			}
			h += '<div class="xanh" style="margin:0 0 10px">' + esc(j.soNguoi) + ' người'
			  +  (j.tong === null ? '' : ' · tổng <b>' + tienVN(j.tong) + '</b>') + '</div>';
			if(!j.daDu){
				h += '<div class="vang" style="margin:0 0 10px">Có dòng <b>chưa khai đơn giá</b> '
				  +  'nên chưa cộng được tổng cả cơ sở.</div>';
			}
			h += '<table><thead><tr><th>Nhân viên</th><th>Giờ</th><th>Tổng</th></tr></thead><tbody>';
			for(var i=0;i<ds.length;i++){
				var d = ds[i];
				h += '<tr' + (d.thieuGia ? ' class="hong"' : '') + '>'
				  +  '<td style="text-align:left">' + esc(d.hoTen)
				  +  (d.thieuGio ? ' <b>· thiếu ' + esc(d.thieuGio) + '</b>' : '') + '</td>'
				  +  '<td>' + esc(d.gio) + '</td>'
				  /* Ai có trừ BHXH thì nói ra ngay dưới con số — bảng này gọn, không có chỗ cho
				     một cột riêng, nhưng một con số nhỏ hơn thật mà không giải thích thì cửa
				     hàng trưởng sẽ đi hỏi kế toán. */
				  +  '<td>' + (d.tong === null ? '<b>chưa đủ giá</b>' : tienVN(d.tong))
				  +  ((+d.bhxh > 0) ? '<div class="ct">đã trừ BHXH ' + tienVN(d.bhxh) + '</div>' : '')
				  +  '</td></tr>';
			}
			h += '</tbody></table>'
			  /* ⚠️ Từ 19/09/2026 BHXH đã vào hệ (kế toán chốt sổ) và đã được trừ ngay trong
			     cột Tổng ở trên — kể nó là "ngoài hệ" là nói dối theo chiều ngược. Chỉ còn
			     lương giờ thêm là thật sự ngoài hệ. */
			  +  '<p class="ct" style="text-align:left;margin:10px 0 0">Đây là số <b>hệ thống tính '
			  +  'được</b>, đã trừ BHXH. <b>Lương giờ thêm</b> kế toán tính ngoài hệ, nên số '
			  +  'chuyển khoản có thể khác.</p>';
			el('bangPhieuCs').innerHTML = h;
		}).catch(function(){
			el('bangPhieuCs').innerHTML = '<p class="trong">Chưa đọc được — kiểm tra mạng rồi lật lại tháng.</p>';
		});
}

function moThemNv(){
	var ds = (CH && CH.dsCoSo) ? CH.dsCoSo : [];
	if(!ds.length){
		window.alert('Tài khoản của anh/chị chưa được giao cơ sở nào, nên chưa thêm người được.');
		return;
	}
	if(!el('tnCoSo').options.length){ el('tnCoSo').innerHTML = xoOption(ds, ds[0] || ''); }
	bao('loiThem','',null);
	hien('mThemNv', true);
}

el('btDongNs').addEventListener('click', function(){ hien('mNhanSu', false); });
el('nsCoSo').addEventListener('change', napNhanSu);
el('btNsTim').addEventListener('click', napNhanSu);
el('btDongNghi').addEventListener('click', function(){ hien('mXinNghi', false); });
el('btDongTre').addEventListener('click', function(){ hien('mXinTre', false); });
el('btDongPhieu').addEventListener('click', function(){ hien('mPhieu', false); });
el('btPlToi').addEventListener('click', function(){ phanPhieu(false); });
el('btPlCs').addEventListener('click', function(){ phanPhieu(true); });
el('plCsCoSo').addEventListener('change', napPhieuCs);
el('plThangTruoc').addEventListener('click', function(){ doiThangPlCs(-1); });
el('plThangSau').addEventListener('click', function(){ doiThangPlCs(1); });
el('btDongThem').addEventListener('click', function(){ hien('mThemNv', false); });
el('btDongNguoi').addEventListener('click', function(){ hien('mNguoi', false); });
el('sgGay').addEventListener('change', function(){ hien('oNghi', this.checked); });
el('clThang').addEventListener('change', function(){ hien('oLuongThang', this.checked); });
el('btThemDong').addEventListener('click', function(){ themDongGio('', ''); });
el('btLuuGio').addEventListener('click', function(){ guiSuaGio(false); });
el('btXoaGio').addEventListener('click', function(){ guiSuaGio(true); });
el('btXoaDong').addEventListener('click', xoaHanDong);
el('btLuuChot').addEventListener('click', guiChot);
el('chThangTruoc').addEventListener('click', function(){ doiThangCH(-1); });
el('chThangSau').addEventListener('click', function(){ doiThangCH(1); });

el('btThemNguoi').addEventListener('click', function(){
	guiDon('chthem', {
		token:    token(),
		coSo:     el('tnCoSo').value,
		hoTen:    el('tnTen').value,
		cccd:     el('tnCccd').value,
		sdt:      el('tnSdt').value,
		gioiTinh: el('tnGt').value
	}, 'loiThem', 'btThemNguoi', function(j){
		el('tnTen').value = ''; el('tnCccd').value = ''; el('tnSdt').value = '';
		/* Nói ra MÃ vừa cấp và BƯỚC KẾ TIẾP. Báo "đã thêm" suông thì cửa hàng trưởng đứng chờ
		   một cái PIN không bao giờ tới — hệ không phát PIN cho ai cả. */
		return '✔ Đã thêm — mã ' + (j.ma_nv || '(tạm)')
			+ '. Bảo người mới vào trang chấm công, bấm "Quên PIN", gõ họ tên + căn cước '
			+ 'của chính họ rồi tự đặt PIN.';
	});
});

el('btGuiNghi').addEventListener('click', function(){
	guiDon('xinnghi', {
		token: token(),
		tu:    el('xnTu').value,
		den:   el('xnDen').value,
		loai:  el('xnLoai').value,
		lyDo:  el('xnLyDo').value
	}, 'loiNghi', 'btGuiNghi', function(j){
		return '✔ Đã gửi đơn nghỉ ' + j.soNgay + ' ngày (' + j.tu
			+ (j.den !== j.tu ? ' → ' + j.den : '') + ') — ' + j.coSo
			+ (j.muon ? ' · nộp muộn, đơn vẫn nhận nhưng người duyệt sẽ thấy' : '')
			+ '. Công KHÔNG đổi vì đơn này.';
	});
});

el('btGuiLich').addEventListener('click', function(){
	guiDon('xinlich', {
		token: token(),
		coSo:  el('xlCoSo').value,
		ngay:  el('xlNgay').value,
		ca:    el('xlCa').value,
		viecMoi: el('xlViec').value,
		doiSangNgay: el('xlDoiSang').value,
		lyDo:  el('xlLyDo').value
	}, 'loiLich', 'btGuiLich', function(j){
		return '✔ Đã gửi yêu cầu đổi lịch — mã ' + j.maYc + '. Người xếp lịch của cơ sở sẽ duyệt.';
	});
});

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * TAB CỬA HÀNG — cửa, không phải nghiệp vụ. Xem class-vhcc-cua-hang.php.
 * ═══════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 NÚT TAB DO MÁY CHỦ MỞ. `?viec=cuahang` trả `duoc:false` cho nhân viên thường và JS không
 *    gỡ lớp `an`. Nhưng đó CHỈ là bày biện: bốn cửa còn lại đều gác lại ở máy chủ, nên một
 *    lượt sửa DOM bằng tay cũng không mở được gì.
 *
 * ⚠️ HỎI MỘT LẦN MỖI PHIÊN, và hỏi NGAY LÚC ĐĂNG NHẬP chứ không chờ bấm tab — nút phải hiện
 *    sẵn thì người ta mới biết có tab ấy. Cái tab chỉ hiện sau khi bấm vào chính nó là cái
 *    tab không ai tìm ra.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
var CH = null;
var CH_THANG = '';

function doCuaHang(){
	return goi('cuahang', { token: token() }).then(function(j){
		if(!j || !j.ok || !j.duoc){ return; }
		CH = j;
		CH_THANG = CH_THANG || j.thang || '';
		var ds = j.dsCoSo || [];
		/* 🔴 KHÔNG CÓ CƠ SỞ NÀO THÌ KHÔNG BÀY TAB. Bày một ô xổ rỗng rồi để mọi lượt bấm trả
		   về "Không có quyền cơ sở này" là đúng cái anh Thắng gặp 17/09 — người ta đọc thành
		   "hệ thống hỏng" chứ không đọc thành "mình chưa được giao cơ sở nào". Máy chủ nay chỉ
		   trả về cơ sở ĐÃ QUA phép gác, nên danh sách rỗng nghĩa là thật sự không có việc gì
		   để làm ở đây. */
		if(!ds.length){ return; }
		el('chCoSo').innerHTML = xoOption(ds, ds[0] || '');
		/* Máy chủ quyết có nhắc hay không — người có `cong_tat_ca` không bị khoá nên không
		   thấy câu này. Bày cho cả những người không bị khoá là dạy họ một luật sai. */
		if(j.nhacHan){
			el('nhacHan').innerHTML = '<div class="vang">⏰ ' + esc(j.nhacHan) + '</div>';
			el('nhacHan').classList.remove('an');
		}
		el('nutCH').classList.remove('an');
		/* Năm ô thì thu chữ — xem khối CSS `#thanhTab.tab5`. Gắn ở ĐÂY, cùng một dòng lệnh với
		   lượt mở nút, để không bao giờ có trạng thái "năm ô mà chưa thu chữ". */
		el('thanhTab').classList.add('tab5');
	}).catch(function(){});
}

function napCuaHang(){
	if(!CH){ return; }
	veThangCH();
	napDonCH();
}

function veThangCH(){
	el('chNhanThang').textContent = 'Tháng ' + (CH_THANG || '—').replace(/^(\d{4})-(\d{2})$/, '$2/$1');
	napCongCH();
}

function doiThangCH(b){
	var p = (CH_THANG || '').split('-');
	if(p.length !== 2){ return; }
	var d = new Date(Date.UTC(+p[0], +p[1] - 1 + b, 1));
	CH_THANG = d.getUTCFullYear() + '-' + ('0' + (d.getUTCMonth() + 1)).slice(-2);
	veThangCH();
}

/* ── đơn từ ─────────────────────────────────────────────────────────────────────────────── */

function napDonCH(){
	el('bangDonCH').innerHTML = '<p class="trong">Đang tải…</p>';
	return goi('chdon', { token: token(), coSo: el('chCoSo').value }).then(function(j){
		if(!j || !j.ok){
			el('bangDonCH').innerHTML = '<p class="trong">' + esc((j&&j.error)||'Không đọc được đơn.') + '</p>';
			return;
		}
		var ds = j.don || [];
		if(!ds.length){
			el('bangDonCH').innerHTML = '<p class="trong">Không có đơn nào chờ anh/chị. '
				+ 'Nhân viên nộp ở tab <b>Tôi</b> thì đơn hiện ra đây.</p>';
			return;
		}
		var h = '';
		for(var i=0;i<ds.length;i++){
			var d = ds[i];
			/* Khoá đơn ghép LOẠI + ID. Ba loại đánh số riêng nhau, nên chỉ mang id là hai đơn
			   khác loại trùng số và bấm duyệt cái này ra cái kia. */
			var k = esc(d.loai) + '|' + esc(d.id);
			h += '<div class="the" style="margin:0 0 10px;padding:12px">'
			  +  '<b style="display:block;font-size:15px">' + esc(d.hoTen || d.maNV) + '</b>'
			  +  '<span class="ct" style="display:block;margin:2px 0 6px">' + esc(d.tenLoai)
			  +  ' · ' + esc(ngayGon(d.ngay)) + ' · ' + esc(d.chiTiet) + '</span>'
			  +  '<div class="vang" style="margin:0 0 10px">' + esc(d.lyDo || '(không ghi lý do)') + '</div>'
			  +  '<div class="hang">'
			  +  '<button class="chinh ch-ok" data-don="' + k + '">Duyệt</button>'
			  +  '<button class="phu ch-no" data-don="' + k + '">Không duyệt</button>'
			  +  '</div></div>';
		}
		el('bangDonCH').innerHTML = h;
		gaiNutDon();
	}).catch(function(){
		el('bangDonCH').innerHTML = '<p class="trong">Chưa đọc được đơn — kiểm tra mạng rồi mở lại tab.</p>';
	});
}

/* Nút dựng lúc chạy nên phải gài sự kiện sau mỗi lượt vẽ. Gài bằng vòng lặp chứ không bằng
   `onclick=` trong chuỗi HTML: lý do trong chính tên hàm `esc()` ở trên — chuỗi ghép vào HTML
   thì một dấu nháy trong dữ liệu là vỡ thẻ, còn addEventListener thì không có chỗ nào để vỡ. */
function gaiNutDon(){
	var ds = el('bangDonCH').querySelectorAll('.ch-ok, .ch-no');
	for(var i=0;i<ds.length;i++){
		ds[i].addEventListener('click', function(){
			quyetDon(this.getAttribute('data-don'), this.classList.contains('ch-ok'), this);
		});
	}
}

function quyetDon(khoa, dongY, nut){
	var p = String(khoa || '').split('|');
	if(p.length < 2){ return; }
	/* ⚠️ KHÔNG DUYỆT là một quyết định, phải hỏi lại — bấm nhầm thì người xin nhận câu từ chối
	   mà không ai cố ý gửi. Duyệt thì không hỏi: nó là việc làm cả ngày. */
	if(!dongY && !window.confirm('Ghi KHÔNG DUYỆT đơn này?')){ return; }
	var ds = el('bangDonCH').querySelectorAll('button');
	for(var i=0;i<ds.length;i++){ ds[i].disabled = true; }
	nut.textContent = 'Đang gửi…';
	goi('chduyet', { token: token(), loai: p[0], id: p.slice(1).join('|'), dongY: dongY ? 1 : 0 })
		.then(function(j){
			if(!j || !j.ok){
				for(var i=0;i<ds.length;i++){ ds[i].disabled = false; }
				window.alert((j && j.error) || 'Không gửi được.');
				return;
			}
			/* Nạp lại cả hộp: đơn vừa quyết phải biến mất, và trong lúc mình bấm có thể có đơn
			   mới nộp vào. Xoá đúng một thẻ trên màn thì hộp nói sai ngay lượt sau. */
			napDonCH();
		}).catch(function(){
			for(var i=0;i<ds.length;i++){ ds[i].disabled = false; }
			window.alert('Mất mạng — đơn CHƯA được quyết. Thử lại.');
		});
}

/* ── bảng công cơ sở ────────────────────────────────────────────────────────────────────── */

function napCongCH(){
	el('bangCongCH').innerHTML = '<p class="trong">Đang tải…</p>';
	return goi('chcong', { token: token(), coSo: el('chCoSo').value, thang: CH_THANG })
		.then(function(j){
			if(!j || !j.ok){
				el('bangCongCH').innerHTML = '<p class="trong">' + esc((j&&j.error)||'Không đọc được bảng công.') + '</p>';
				return;
			}
			var ds = j.dong || [];
			if(!ds.length){
				el('bangCongCH').innerHTML = '<p class="trong">Tháng này chưa có lượt chấm nào ở '
					+ esc(j.coSo) + '.</p>';
				return;
			}
			var h = '<div class="xanh" style="margin:0 0 10px">' + esc(j.soNguoi) + ' người · '
			      + esc(j.tongGio) + ' giờ</div>';
			if(j.tongThieu){
				h += '<div class="vang" style="margin:0 0 10px"><b>' + esc(j.tongThieu)
				  +  '</b> lượt thiếu một đầu giờ — mấy lượt ấy KHÔNG tính phút nào. '
				  +  'Bổ sung trước khi kế toán chốt lương.</div>';
			}
			/* 🔴 CHẠM CẢ DÒNG, KHÔNG PHẢI MỘT NÚT Ở CỘT THỨ TƯ.
			   Bản 4.35 để nút "Mở" trong một cột riêng. Anh Thắng 17/09/2026 chụp màn iPhone:
			   *"trên điện thoại không có nút mở"* — bốn cột trên màn 390px thì cột cuối bị bóp
			   còn vài pixel, và cái nút thực tế biến mất khỏi tầm mắt. Bảng ba cột thì vừa, nên
			   bỏ cột ấy đi và cho CẢ DÒNG làm vùng chạm: vùng chạm to hơn hẳn một cái nút, và
			   không có cột nào để mà bóp nữa.
			   ⚠️ Vẫn phải có DẤU HIỆU nhìn thấy được. Một dòng bấm được mà trông y hệt một dòng
			      chữ thì không ai thử bấm — nên có mũi › ở cuối và một dòng nhắc trên bảng. */
			h += '<p class="ct" style="text-align:left;margin:0 0 8px">Chạm vào một dòng để '
			  +  '<b>sửa công từng ngày</b> hoặc <b>chốt lương theo việc</b>.</p>'
			  +  '<table><thead><tr><th>Nhân viên</th><th>Ngày</th><th>Giờ</th></tr>'
			  +  '</thead><tbody>';
			for(var i=0;i<ds.length;i++){
				var d = ds[i];
				h += '<tr class="hang-mo' + (d.thieu ? ' hong' : '') + '" data-ma="'
				  +  esc(d.maNV) + '">'
				  +  '<td style="text-align:left">' + esc(d.hoTen)
				  +  (d.thieu ? ' <b>· thiếu ' + esc(d.thieu) + '</b>' : '') + '</td>'
				  +  '<td>' + esc(d.soNgay) + '</td>'
				  +  '<td>' + esc(d.gio) + ' <span class="mui">›</span></td>'
				  +  '</tr>';
			}
			h += '</tbody></table>'
			  +  '<p class="ct" style="text-align:left;margin:10px 0 0">Số ở đây là <b>giờ có mặt</b>, '
			  +  'chưa quy ra công tính lương. Cần từng ô từng ngày thì mở trang quản trị trên máy tính.</p>';
			el('bangCongCH').innerHTML = h;
			/* Dòng dựng lúc chạy nên gài sự kiện sau mỗi lượt vẽ — cùng lý do với `gaiNutDon()`:
			   `onclick=` trong chuỗi HTML thì một dấu nháy trong dữ liệu là vỡ thẻ. */
			var nm = el('bangCongCH').querySelectorAll('.hang-mo');
			for(var m=0;m<nm.length;m++){
				nm[m].addEventListener('click', function(){ moNguoi(this.getAttribute('data-ma')); });
			}
		}).catch(function(){
			el('bangCongCH').innerHTML = '<p class="trong">Chưa đọc được bảng công — kiểm tra mạng rồi lật lại tháng.</p>';
		});
}

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * MÀN MỘT NGƯỜI — sửa công từng ngày & chốt lương theo việc.
 * ═══════════════════════════════════════════════════════════════════════════════════════════
 * Anh Thắng 17/09/2026: *"có thêm chức năng sửa công nhân viên và set giờ theo công việc như
 * web luôn được không, mà giao diện dùng như app nhé"*.
 *
 * 🔴 CỬA, KHÔNG PHẢI NGHIỆP VỤ. Mọi luật nằm ở `VHCC_Bu::sua` và `VHCC_ChotLuong::dat` — lý do
 *    bắt buộc ≥5 ký tự, ô trống là GIỮ NGUYÊN chứ không xoá, tổng giờ khác không được vượt giờ
 *    chấm công. Màn này chỉ bày ô và nói lại câu máy chủ trả về.
 *
 * ⚠️ CẢN TRƯỚC Ở MÀN, NHƯNG KHÔNG THAY PHÉP GÁC. Con số "còn lại bao nhiêu giờ" hiện ngay khi
 *    gõ, để người ta thấy mình sắp vượt — nhưng máy chủ vẫn là nơi chối. Cản ở màn mà bỏ chốt
 *    ở máy chủ là mở cửa cho bất kỳ ai gửi thẳng gói dữ liệu.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
var NG = null;        // dữ liệu người đang mở
var NG_NGAY = '';     // ngày đang sửa

function moNguoi(ma){
	NG = null; NG_NGAY = '';
	hien('oSuaNgay', false); hien('oChot', false);
	el('nguoiTen').textContent = '…';
	el('nguoiPhu').textContent = '';
	el('dsNgay').innerHTML = '<p class="trong">Đang tải…</p>';
	hien('mNguoi', true);
	goi('chngay', { token: token(), coSo: el('chCoSo').value, thang: CH_THANG, maNV: ma })
		.then(function(j){
			if(!j || !j.ok){
				el('dsNgay').innerHTML = '<p class="trong">' + esc((j&&j.error)||'Không đọc được.') + '</p>';
				return;
			}
			NG = j;
			el('nguoiTen').textContent = j.hoTen;
			el('nguoiPhu').textContent = j.maNV + ' · ' + j.coSo + ' · tháng ' + j.thang
				+ ' · ' + j.gioThang + ' giờ';
			veDsNgay();
			napChot();
		}).catch(function(){
			el('dsNgay').innerHTML = '<p class="trong">Mất mạng — đóng rồi mở lại.</p>';
		});
}

function veDsNgay(){
	var ds = (NG && NG.ngay) || [];
	if(!ds.length){
		el('dsNgay').innerHTML = '<p class="trong">Tháng này chưa có lượt chấm nào.</p>';
		return;
	}
	if(!NG.duocSua){
		/* Nói TRƯỚC khi người ta gõ xong rồi mới bị chối — và nói ra đường đi tiếp.
		   ⚠️ KHÔNG gài sự kiện, và bỏ luôn mũi ›: dòng chỉ để xem mà vẫn trông bấm được thì
		      người ta bấm mãi không ra gì, tệ hơn là nhìn biết ngay không bấm được. */
		el('dsNgay').innerHTML = '<div class="vang" style="margin:0 0 10px">Tài khoản của anh/chị '
			+ 'chưa được mở quyền <b>sửa giờ</b>, nên bảng dưới chỉ để xem. Thấy giờ sai thì báo '
			+ 'quản lý.</div>' + bangNgay(ds, '').replace(/ class="ng-sua/g, ' class="')
				.replace(/<span class="mui">›<\/span>/g, '');
		return;
	}
	/* 🔴 NGÀY ĐÃ QUÁ HẠN THÌ KHÔNG CHO MỞ Ô SỬA, và nói ra ngay tại chỗ. Cho mở rồi mới chối
	   lúc bấm Lưu là bắt người ta gõ cả giờ lẫn lý do cho một lượt không bao giờ đi được. */
	var chi_nay = !!NG.khoaNgayCu;
	var h = '';
	if(chi_nay && NG.nhacHan){
		h += '<div class="vang" style="margin:0 0 10px">⏰ ' + esc(NG.nhacHan)
		  +  ' Dòng của ngày đã qua chỉ để xem.</div>';
	}
	el('dsNgay').innerHTML = h + bangNgay(ds, chi_nay ? NG.homNay : '');
	var b = el('dsNgay').querySelectorAll('.ng-sua');
	for(var i=0;i<b.length;i++){
		b[i].addEventListener('click', function(){ moSuaNgay(this.getAttribute('data-ngay')); });
	}
}

/* Bốn cột, và CẢ DÒNG là vùng chạm — cùng một luật với bảng công cơ sở, cùng một lý do: cột
   thứ năm chứa nút thì trên màn 390px nó bị bóp mất. Xem chú thích ở `napCongCH()`. */
/* `chi_ngay` khác rỗng = CHỈ ngày ấy còn sửa được (cửa hàng trưởng, sau 24h là khoá). Dòng
   ngoài ngày ấy mất lớp bấm và mất mũi › — nhìn là biết không bấm được, không phải bấm rồi
   mới biết. */
function bangNgay(ds, chi_ngay){
	var h = '<table><thead><tr><th>Ngày</th><th>Vào</th><th>Ra</th><th>Giờ</th></tr></thead><tbody>';
	for(var i=0;i<ds.length;i++){
		var x = ds[i];
		var mo = (!chi_ngay || x.ngay === chi_ngay);
		h += '<tr class="' + (mo ? 'ng-sua' : 'ng-khoa') + (x.thieu ? ' hong' : '')
		  +  '" data-ngay="' + esc(x.ngay) + '">'
		  +  '<td>' + esc(ngayGon(x.ngay)) + (x.hauTo ? ' <b>' + esc(x.hauTo) + '</b>' : '') + '</td>'
		  +  '<td>' + esc(x.vao || '—') + '</td>'
		  +  '<td>' + (x.thieu ? '<b>thiếu</b>' : esc(x.ra || '—')) + '</td>'
		  +  '<td>' + (x.gio === null ? '—' : esc(x.gio))
		  +  (mo ? ' <span class="mui">›</span>' : '') + '</td></tr>';
	}
	return h + '</tbody></table>';
}

function moSuaNgay(ngay){
	var ds = (NG && NG.ngay) || [], x = null;
	for(var i=0;i<ds.length;i++){ if(ds[i].ngay === ngay){ x = ds[i]; } }
	if(!x){ return; }
	NG_NGAY = ngay;
	el('suaTieuDe').textContent = 'Sửa ngày ' + ngayGon(ngay);
	/* 🔴 ĐỔ GIỜ ĐANG CÓ VÀO Ô. Không đổ thì người sửa phải NHỚ giờ cũ, mà nhớ sai một chữ số
	   là ghi đè mất một giờ công thật — và không có gì trên màn mâu thuẫn với con số vừa gõ. */
	el('sgVao').value = x.vao || '';
	el('sgRa').value  = x.ra || '';
	var gay = !!(x.nghiTu && x.nghiDen);
	el('sgGay').checked = gay;
	el('sgNghiTu').value  = x.nghiTu || '';
	el('sgNghiDen').value = x.nghiDen || '';
	hien('oNghi', gay);
	el('sgLyDo').value = '';
	bao('loiSua','',null);
	hien('oSuaNgay', true);
	el('oSuaNgay').scrollIntoView({ block:'start' });
}

/* 🔴 XOÁ HẲN DÒNG PHẢI HỎI BẰNG NGÀY, KHÔNG PHẢI MỘT CÂU "CHẮC CHƯA?".
   Một hộp thoại đồng ý/huỷ thì ngón tay bấm qua được mà mắt chưa kịp đọc — và cái vừa bấm
   qua là một dòng chấm công không dựng lại được. Nhắc đúng NGÀY và đúng TÊN trong câu hỏi
   buộc người ta đối chiếu một lần với thứ mình đang định xoá. */
function xoaHanDong(){
	if(!NG || !NG_NGAY){ return; }
	if(el('sgLyDo').value.trim().length < 5){
		bao('loiSua','loi','Ghi vì sao xoá (ít nhất 5 ký tự) trước đã — sổ cần câu ấy để sau còn lần lại.');
		el('sgLyDo').focus();
		return;
	}
	if(!window.confirm('XOÁ HẲN dòng chấm công ngày ' + ngayGon(NG_NGAY) + ' của '
		+ NG.hoTen + '?\n\nDòng sẽ biến mất khỏi lưới — nhìn vào sẽ tưởng hôm ấy không đi làm. '
		+ 'Giờ cũ và lý do vẫn vào sổ.')){ return; }
	guiDon('chxoadong', {
		token: token(), coSo: el('chCoSo').value, maNV: NG.maNV,
		ngay: NG_NGAY, lyDo: el('sgLyDo').value
	}, 'loiSua', 'btXoaDong', function(j){
		var ma = NG.maNV;
		setTimeout(function(){ moNguoi(ma); napCongCH(); }, 900);
		var d = j.daXoa || {};
		return '✔ Đã xoá hẳn dòng ngày ' + j.ngay + ' (vào ' + (d.vao || '—')
			+ ', ra ' + (d.ra || '—') + '). Sổ đã ghi lại giờ cũ.';
	});
}

function guiSuaGio(xoa){
	if(!NG_NGAY){ return; }
	/* ⚠️ XOÁ GIỜ PHẢI HỎI LẠI. Nó không hỏng gì vĩnh viễn (dòng ở lại, nhật ký ghi giờ cũ),
	   nhưng nó làm một người mất công cả ngày cho tới khi có ai để ý. */
	if(xoa && !window.confirm('Xoá giờ vào và giờ ra của ngày này?')){ return; }
	guiDon('chsua', {
		token:   token(),
		coSo:    el('chCoSo').value,
		maNV:    NG.maNV,
		ngay:    NG_NGAY,
		vao:     xoa ? '' : el('sgVao').value,
		ra:      xoa ? '' : el('sgRa').value,
		xoaVao:  xoa ? 1 : 0,
		xoaRa:   xoa ? 1 : 0,
		gay:     el('sgGay').checked ? 1 : 0,
		nghiTu:  el('sgNghiTu').value,
		nghiDen: el('sgNghiDen').value,
		lyDo:    el('sgLyDo').value
	}, 'loiSua', xoa ? 'btXoaGio' : 'btLuuGio', function(j){
		/* Nạp lại cả màn: sửa một ngày là đổi tổng giờ tháng, mà tổng giờ ấy lại là TRẦN của
		   khối chốt lương ngay dưới. Vá một ô trên màn thì hai chỗ kia nói số cũ. */
		var ma = NG.maNV;
		setTimeout(function(){ moNguoi(ma); napCongCH(); }, 900);
		var d = j.doi || {};
		var noi = [];
		if(d.vao){ noi.push('vào ' + d.vao.cu + ' → ' + d.vao.moi); }
		if(d.ra){  noi.push('ra ' + d.ra.cu + ' → ' + d.ra.moi); }
		return '✔ Đã lưu' + (noi.length ? ' — ' + noi.join(' · ') : ' (không ô nào đổi)');
	});
}

/* ── chốt lương theo việc ───────────────────────────────────────────────────────────────── */

function napChot(){
	goi('chchot', { token: token(), coSo: el('chCoSo').value, thang: CH_THANG, maNV: NG.maNV })
		.then(function(j){
			if(!j || !j.ok){ return; }
			NG.chot = j;
			el('clViec').value = j.vieChinh || '';
			var dl = '';
			for(var i=0;i<(j.tenDaDung||[]).length;i++){
				dl += '<option value="' + esc(j.tenDaDung[i]) + '">';
			}
			el('dsViec').innerHTML = dl;

			el('dsDongGio').innerHTML = '';
			var dg = j.dong || [];
			for(var k=0;k<dg.length;k++){ themDongGio(dg[k].viec, dg[k].gio); }
			themDongGio('', '');

			var lt = j.luongThang;
			el('clThang').checked = !!lt;
			el('clLcb').value    = lt ? lt.lcb : '';
			el('clCongYc').value = (lt && lt.congYc) ? lt.congYc : '';
			hien('oLuongThang', !!lt);

			el('dsCong').innerHTML = oKhoan(j.tenCong, j.cong, 'kc');
			el('dsTru').innerHTML  = oKhoan(j.tenTru,  j.tru,  'kt');
			tomChot();
			hien('oChot', true);
		}).catch(function(){});
}

function oKhoan(ten, gia, tien_to){
	var h = '<div class="luoi-khoan">';
	for(var k in ten){
		if(!Object.prototype.hasOwnProperty.call(ten, k)) continue;
		var v = (gia && gia[k]) ? gia[k] : '';
		h += '<div class="fldx"><label>' + esc(ten[k]) + '</label>'
		  +  '<input type="tel" inputmode="numeric" maxlength="12" data-khoan="'
		  +  esc(tien_to) + ':' + esc(k) + '" value="' + esc(v) + '"></div>';
	}
	return h + '</div>';
}

function themDongGio(viec, gio){
	var d = document.createElement('div');
	d.className = 'dong-gio';
	d.innerHTML = '<input class="viec" type="text" maxlength="60" placeholder="tên việc (VD: MC)" list="dsViec">'
	            + '<input class="gio" type="text" inputmode="decimal" maxlength="7" placeholder="số giờ">'
	            + '<button class="phu bo">✕</button>';
	d.querySelector('.viec').value = viec || '';
	d.querySelector('.gio').value  = (gio === 0 || gio) ? gio : '';
	d.querySelector('.gio').addEventListener('input', tomChot);
	d.querySelector('.bo').addEventListener('click', function(){ d.remove(); tomChot(); });
	el('dsDongGio').appendChild(d);
}

/* Con số "còn lại" cập nhật ngay khi gõ — xem khối chú thích đầu phần này: cản trước ở màn,
   nhưng máy chủ vẫn là nơi chối. */
function tomChot(){
	if(!NG || !NG.chot){ return; }
	var tran = Number(NG.chot.gioCham) || 0, tong = 0;
	var o = el('dsDongGio').querySelectorAll('.gio');
	for(var i=0;i<o.length;i++){
		var v = parseFloat(String(o[i].value).replace(',', '.'));
		if(!isNaN(v) && v > 0){ tong += v; }
	}
	var con = Math.round((tran - tong) * 100) / 100;
	el('chotTom').className = (con < 0) ? 'vang' : 'xanh';
	el('chotTom').innerHTML = 'Chấm công <b>' + esc(tran) + '</b> giờ · giờ khác <b>'
		+ esc(Math.round(tong * 100) / 100) + '</b> · việc chính còn <b>' + esc(con) + '</b> giờ'
		+ (con < 0 ? ' — <b>vượt giờ chấm công</b>, máy chủ sẽ chối.' : '');
}

function guiChot(){
	var dong = [], o = el('dsDongGio').querySelectorAll('.dong-gio');
	for(var i=0;i<o.length;i++){
		dong.push({ viec: o[i].querySelector('.viec').value,
		            gio:  o[i].querySelector('.gio').value });
	}
	var cong = {}, tru = {};
	var ok = el('oChot').querySelectorAll('[data-khoan]');
	for(var k=0;k<ok.length;k++){
		var p = ok[k].getAttribute('data-khoan').split(':');
		if(p[0] === 'kc'){ cong[p[1]] = ok[k].value; } else { tru[p[1]] = ok[k].value; }
	}
	guiDon('chchotluu', {
		token: token(), coSo: el('chCoSo').value, thang: CH_THANG, maNV: NG.maNV,
		viecChinh: el('clViec').value,
		dong: dong,
		anLuongThang: el('clThang').checked ? 1 : 0,
		luongCb: el('clLcb').value,
		congYc:  el('clCongYc').value,
		cong: cong, tru: tru
	}, 'loiChot', 'btLuuChot', function(){
		return '✔ Đã lưu chốt lương tháng ' + CH_THANG + '. Bảng lương bên trang quản trị đổi theo ngay.';
	});
}

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * PHIẾU LƯƠNG CỦA TÔI — cửa, không phải nghiệp vụ. Xem class-vhcc-phieu-luong.php.
 * ═══════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 KHỐI NÀY TỰ ẨN KHI CHƯA CÓ THÁNG NÀO CÔNG BỐ. Bày một ô xổ rỗng kèm chữ "chưa có" thì mỗi
 *    lần mở tab người ta lại đọc lại một câu không giúp được gì, và ô rỗng trên màn lương thì
 *    ai cũng bấm thử vài lần trước khi tin. Chưa có gì để xem thì không bày gì cả.
 *
 * ⚠️ MỌI CON SỐ Ở ĐÂY LÀ SỐ CỦA HỆ, KHÔNG PHẢI SỐ CHUYỂN KHOẢN. Lương giờ thêm kế toán
 *    (BHXH đã vào hệ từ 19/09/2026 — kế toán chốt sổ, và phiếu trừ thẳng)
 *    điền ngoài hệ (xem đầu class-vhcc-bang-luong.php), nên câu ấy phải nằm ngay dưới con số
 *    tổng — không phải ở cuối trang, không phải trong một dấu hỏi phải bấm mới ra.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
var PL_KHOAN = null;

function tienVN(n){
	if(n === null || n === undefined || n === '') return '—';
	var x = Math.round(Number(n));
	if(!isFinite(x)) return '—';
	/* Chấm nghìn bằng vòng lặp, KHÔNG bằng biểu thức chính quy. `toLocaleString` thì phụ thuộc
	   máy người dùng — điện thoại đặt tiếng Anh sẽ ra "1,234,000" giữa một tờ lương tiếng Việt. */
	var am = x < 0; if(am){ x = -x; }
	var t = String(x), r = '';
	while(t.length > 3){ r = '.' + t.slice(-3) + r; t = t.slice(0, -3); }
	return (am ? '-' : '') + t + r + 'đ';
}

/* Ô xổ ẩn khi không có tháng nào để chọn.
   ⚠️ Chỉ đổi lớp, KHÔNG nhận chuỗi rồi tự nhét vào innerHTML: nhận chuỗi là mở một đường cho
      dữ liệu chưa thoát đi vào HTML qua một hàm trông rất vô hại. Chữ do nơi gọi tự đặt, ngay
      tại chỗ, để mắt đọc mã thấy được nó là chữ viết sẵn hay là dữ liệu. */
function phieuHien(){
	el('plThang').classList.add('an');
	/* 🔴 ẨN CẢ CÁI NHÃN, KHÔNG CHỈ Ô XỔ. Anh Thắng 18/09/2026 gửi ảnh màn Phiếu lương: chữ
	   "Tháng" đứng chơ vơ trên một khoảng trắng rồi mới tới câu "chưa được công bố" — trông
	   như ô chọn hỏng chứ không như "chưa có gì để chọn". Nhãn của một ô đã ẩn thì cũng phải
	   ẩn theo; để lại là một lời hứa về một thứ không có. */
	el('plNhanO').classList.add('an');
}

function napPhieu(){
	return goi('phieuluong', { token: token() }).then(function(j){
		if(!j || !j.ok){
			phieuHien();
			el('bangPhieu').innerHTML = '<p class="trong">'
				+ esc((j && j.error) || 'Chưa đọc được phiếu lương.') + '</p>';
			return;
		}
		PL_KHOAN = j.khoan || null;
		/* Phần "cả cửa hàng" chỉ hiện khi MÁY CHỦ gửi danh sách cơ sở — nhân viên thường nhận
		   mảng rỗng, nên nút không bao giờ lộ ra. Cửa `phieucs` vẫn gác lại lần nữa. */
		var dsq = j.dsCoSoQl || [];
		if(dsq.length){
			if(!el('plCsCoSo').options.length){ el('plCsCoSo').innerHTML = xoOption(dsq, dsq[0] || ''); }
			PL_CS_THANG = PL_CS_THANG || j.thangNay || '';
			el('btPlCs').classList.remove('an');
		}
		var ds = j.dsThang || [];
		/* 🔴 KHÔNG ẨN KHỐI — xem khối chú thích ở markup. Nói ra đang chờ ai làm gì, vì người
		   đọc không có cách nào tự đoán rằng có một cái nút bên trang quản trị. */
		if(!ds.length){
			phieuHien();
			el('bangPhieu').innerHTML = '<p class="trong">Phiếu lương <b>chưa được công bố</b>. '
				+ 'Kế toán chốt xong tháng nào thì tháng ấy tự hiện ra ở đây — không phải lỗi, '
				+ 'và anh/chị không phải làm gì cả.</p>';
			return;
		}
		el('plThang').classList.remove('an');
		el('plNhanO').classList.remove('an');
		/* Giá trị của mỗi dòng gói cả cơ sở lẫn tháng: một người làm hai nơi thì tháng 8 có hai
		   phiếu khác nhau, và chỉ mang theo cái tháng thì hai dòng ấy không phân biệt được. */
		var h = '';
		for(var i=0;i<ds.length;i++){
			var v = ds[i].coSo + '|' + ds[i].thang;
			h += '<option value="' + esc(v) + '">Tháng ' + esc(ds[i].thang)
				+ ' — ' + esc(ds[i].coSo) + '</option>';
		}
		el('plThang').innerHTML = h;
		vePhieu();
	}).catch(function(){
		phieuHien();
		el('bangPhieu').innerHTML = '<p class="trong">Chưa đọc được phiếu lương — kiểm tra mạng '
			+ 'rồi mở lại tab.</p>';
	});
}

function vePhieu(){
	var v = (el('plThang').value || '').split('|');
	if(v.length < 2){ return; }
	el('bangPhieu').innerHTML = '<p class="trong">Đang tải…</p>';
	goi('phieu', { token: token(), coSo: v[0], thang: v[1] }).then(function(j){
		if(!j || !j.ok){
			el('bangPhieu').innerHTML = '<p class="trong">' + esc((j&&j.error)||'Không đọc được phiếu.') + '</p>';
			return;
		}
		if(j.trong){
			el('bangPhieu').innerHTML = '<p class="trong">' + esc(j.loi) + '</p>';
			return;
		}
		/* 26/09/2026 — bản in A4 của chính phiếu này (Lưu thành PDF), cùng link gửi qua chuông
		   và email lúc công bố. */
		var h = j.linkIn ? ('<p style="margin:0 0 8px"><a class="nut" target="_blank" rel="noopener" href="'
			+ esc(j.linkIn) + '">🖨 In / Lưu PDF phiếu này</a></p>') : '';
		h += '<table><thead><tr><th>Việc</th><th>Giờ</th><th>Đơn giá</th><th>Thành tiền</th>'
			+ '</tr></thead><tbody>';
		for(var i=0;i<j.dong.length;i++){
			var d = j.dong[i];
			/* Dòng ăn lương tháng không có giờ và không có đơn giá — nó ra tiền bằng
			   lương cơ bản × công thực / công yêu cầu. Bày hai ô trống thì đúng hơn là bày
			   một con số mượn ở đâu đó. */
			var mo = (d.cheDo === 'thang')
				? (tienVN(d.luongCb) + '/tháng × ' + esc(d.congThuc) + '/' + esc(d.congYc || '—') + ' công')
				/* Dòng Ca đêm của cơ sở theo công: số công đêm × giá 1 công đêm, không phải giờ. */
				: (d.cheDo === 'cong')
					? (esc(d.congThuc) + ' công đêm × ' + (d.gia === null ? '<b>chưa khai giá</b>' : tienVN(d.gia)))
					: (d.gia === null ? '<b>chưa khai đơn giá</b>' : tienVN(d.gia) + '/giờ');
			h += '<tr><td style="text-align:left">' + esc(d.cv || '—') + '</td>'
				+ '<td>' + (d.gio === null ? '—' : esc(d.gio)) + '</td>'
				+ '<td>' + mo + '</td>'
				+ '<td>' + (d.luongChinh === null ? '—' : tienVN(d.luongChinh)) + '</td></tr>';
		}
		h += '</tbody></table>';

		h += veKhoanPL(j.dong[0].cong, PL_KHOAN && PL_KHOAN.cong, 'Khoản cộng');
		h += veKhoanPL(j.dong[0].tru,  PL_KHOAN && PL_KHOAN.tru,  'Khoản trừ');

		/* 🔴 THIẾU ĐƠN GIÁ THÌ KHÔNG BÀY TỔNG. Cộng những dòng có giá rồi gọi đó là tổng thì
		   con số ra THẤP HƠN thật mà trông hoàn chỉnh — và người đọc sẽ đi khiếu nại một con
		   số không ai tính ra như thế. Máy chủ đã trả `tong` là null trong trường hợp ấy; ở
		   đây chỉ cần đừng tự cộng lại. */
		if(!j.daDu){
			h += '<div class="vang" style="margin-top:10px">Phiếu này còn <b>' + esc(j.thieuGia)
				+ '</b> dòng chưa khai đơn giá nên chưa cộng được tổng. Báo cửa hàng trưởng để '
				+ 'kế toán khai giá cho việc ấy.</div>';
		} else {
			h += '<div class="the" style="margin-top:10px;padding:10px">'
				+ '<div class="hang" style="justify-content:space-between"><span>Lương chính</span>'
				+ '<b>' + tienVN(j.luongChinh) + '</b></div>'
				+ '<div class="hang" style="justify-content:space-between"><span>Cộng</span>'
				+ '<b>' + tienVN(j.tongCong) + '</b></div>'
				+ '<div class="hang" style="justify-content:space-between"><span>Trừ</span>'
				+ '<b>' + tienVN(j.tongTru) + '</b></div>'
				/* ═══════════════════════════════════════════════════════════════════════════
				 * 🔴 BHXH PHẢI CÓ MỘT DÒNG RIÊNG — anh Thắng 19/09/2026, ảnh chụp phiếu:
				 *    *"Trên app thiếu khoảng trừ như bhxh lại không có"*.
				 *
				 * Lúc ấy phiếu ghi Lương chính 4.000.000 · Cộng 0 · Trừ 0 · **Tổng 3.403.390**.
				 * Tiền đã bị trừ 596.610 thật (đúng sổ BHXH), nhưng KHÔNG dòng nào nói ra — nên
				 * bốn con số trên màn cộng lại không ra con số dưới cùng. Đó là kiểu phiếu tệ
				 * nhất: người đọc tính nhẩm thấy vênh, không biết vênh vì đâu, và họ sẽ nghĩ hệ
				 * thống tính sai chứ không nghĩ tới bảo hiểm.
				 *
				 * ⚠️ KHÔNG GỘP VÀO Ô "TRỪ". Ô ấy là mấy khoản cửa hàng trưởng gõ (phạt, đặt
				 *    cọc); BHXH là khoản của kế toán và có cột riêng bên bảng lương. Gộp lại là
				 *    người ta đi hỏi cửa hàng trưởng về một khoản anh ta không gõ.
				 * ⚠️ KHÔNG ĐÓNG KHAI: `bhxh` bằng 0 thì KHÔNG vẽ dòng nào. Một dòng "BHXH 0đ"
				 *    trên phiếu của người không đóng bảo hiểm là mời họ đi hỏi vì sao có nó.
				 * ═══════════════════════════════════════════════════════════════════════════ */
				+ ( (+j.bhxh > 0)
					? ('<div class="hang" style="justify-content:space-between"><span>BHXH</span>'
						+ '<b>-' + tienVN(j.bhxh) + '</b></div>')
					: '' )
				+ '<div class="hang" style="justify-content:space-between;font-size:17px">'
				+ '<span><b>Tổng</b></span><b>' + tienVN(j.tong) + '</b></div></div>';
		}

		/* Khoản giữ lại, trả sau: số tháng này + tích luỹ còn giữ — không nằm trong Tổng. */
		var giu = j.giu || {}, tl = j.tichLuy || {}, dsg = [], kg;
		var tenCong = (PL_KHOAN && PL_KHOAN.cong) ? PL_KHOAN.cong : {};
		for(kg in tenCong){
			if(!Object.prototype.hasOwnProperty.call(tenCong, kg)) continue;
			var conk = (tl[kg] && +tl[kg].con > 0) ? +tl[kg].con : 0;
			if(!giu[kg] && !conk) continue;
			dsg.push('<div class="hang" style="justify-content:space-between"><span>' + esc(tenCong[kg])
				+ '</span><span>tháng này <b>' + (giu[kg] ? tienVN(giu[kg]) : '—') + '</b> · còn giữ <b>'
				+ (conk ? tienVN(conk) : '—') + '</b></span></div>');
		}
		if(dsg.length){
			h += '<div class="the" style="margin-top:10px;padding:10px"><b>Khoản giữ lại (chưa trả)</b>'
				+ dsg.join('') + '<p class="ct" style="text-align:left;margin:6px 0 0">Không nằm trong Tổng '
				+ 'tháng này — công ty trả vào kỳ quản lý chọn.</p></div>';
		}

		if(j.thieuGio){
			h += '<div class="vang" style="margin-top:10px">Tháng này có <b>' + esc(j.thieuGio)
				+ '</b> lượt thiếu một đầu giờ (quên chấm vào hoặc chấm ra) nên KHÔNG tính phút nào. '
				+ 'Thấy sai thì báo ở tab <b>Tôi</b> → Báo lượt chấm sai.</div>';
		}

		/* ⚠️ Xem khối chú thích đầu hàm: câu này nằm dưới con số tổng, không nằm cuối trang. */
		/* ⚠️ CÂU NÀY PHẢI THEO KỊP THỰC TẾ. Từ 19/09/2026 BHXH đã vào hệ (kế toán chốt sổ), nên
		   kể nó là "ngoài hệ" là nói dối theo chiều ngược: người đọc tưởng còn một khoản trừ
		   chưa tính, trong khi đã trừ rồi — và họ sẽ tự trừ thêm một lần nữa trong đầu.
		   Máy chủ trả `ngoaiHe` để màn khỏi tự đoán; dựng câu từ chính mảng ấy. */
		var ngoai = (j.ngoaiHe && j.ngoaiHe.length) ? j.ngoaiHe : [];
		h += '<p class="ct" style="text-align:left;margin:10px 0 0">Đây là số <b>hệ thống tính '
			+ 'được</b> từ giờ đã chấm và các khoản kế toán đã nhập.'
			+ ( ngoai.length
				? (' <b>' + ngoai.map(esc).join('</b> và <b>') + '</b> kế toán tính ngoài hệ, nên '
					+ 'số chuyển khoản có thể khác.')
				: '' )
			+ ' Lệch thì hỏi cửa hàng trưởng — đừng tự cộng lại.</p>';
		el('bangPhieu').innerHTML = h;
	}).catch(function(){
		el('bangPhieu').innerHTML = '<p class="trong">Chưa đọc được phiếu — kiểm tra mạng rồi chọn lại tháng.</p>';
	});
}

/* Chỉ vẽ những khoản THẬT SỰ CÓ. Liệt kê cả chín khoản với số 0 thì tờ phiếu trông như đã xét
   hết mọi thứ, trong khi thật ra kế toán chưa gõ gì — cùng luật với ô trống của tệp .xlsx. */
function veKhoanPL(d, ten, nhan){
	if(!d || !ten){ return ''; }
	var h = '';
	for(var k in ten){
		if(!Object.prototype.hasOwnProperty.call(ten, k)) continue;
		if(!d[k]) continue;
		h += '<div class="hang" style="justify-content:space-between"><span>' + esc(ten[k])
			+ '</span><b>' + tienVN(d[k]) + '</b></div>';
	}
	if(!h){ return ''; }
	return '<div class="the" style="margin-top:10px;padding:10px"><label style="margin:0 0 6px">'
		+ esc(nhan) + '</label>' + h + '</div>';
}

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * LỊCH LÀM CỦA TÔI
 * ═══════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 CHƯA XẾP LỊCH KHÁC HẲN KHÔNG CÓ LỊCH. Cơ sở chưa bật phân lịch, hoặc bật rồi mà tháng này
 *    người xếp chưa làm — hai chuyện ấy nhìn từ đây giống nhau (danh sách rỗng), nhưng việc
 *    người dùng phải làm thì khác: một bên là không phải chờ gì, một bên là đi hỏi. Nói rõ là
 *    "chưa xếp" chứ đừng để một ô trống, vì ô trống thì ai cũng đọc thành "hệ thống hỏng".
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
function veLichToi(ym){
	el('bangLichToi').innerHTML = '<p class="trong">Đang tải…</p>';
	goi('lichtoi', { token: token(), thang: ym }).then(function(j){
		if(!j || !j.ok){
			el('bangLichToi').innerHTML = '<p class="trong">' + esc((j&&j.error)||'Không đọc được lịch.') + '</p>';
			return;
		}
		var ds = j.dong || [];
		if(!ds.length){
			el('bangLichToi').innerHTML = '<p class="trong">Tháng này chưa xếp lịch cho anh/chị. '
				+ 'Cơ sở nào không dùng phân lịch thì ô này luôn trống — không phải lỗi.</p>';
			return;
		}
		var h = '<table><thead><tr><th>Ngày</th><th>Ca</th><th>Việc</th><th>Cơ sở</th></tr></thead><tbody>';
		for(var i=0;i<ds.length;i++){
			var x = ds[i];
			/* Đánh dấu HÔM NAY. Người mở tab giữa tháng phải tìm được dòng của mình trong ba mươi
			   dòng, và họ tìm bằng mắt chứ không đọc từng ngày. */
			var nay = (x.ngay === j.homNay);
			h += '<tr' + (nay ? ' class="hong"' : '') + '><td>' + esc(ngayGon(x.ngay))
				+ (nay ? ' <b>• hôm nay</b>' : '') + '</td>'
				+ '<td>' + esc(x.ca || '—') + '</td>'
				+ '<td style="text-align:left">' + esc(x.viec || '—') + '</td>'
				+ '<td>' + esc(x.coso || '') + '</td></tr>';
		}
		h += '</tbody></table>';
		el('bangLichToi').innerHTML = h;
	}).catch(function(){
		el('bangLichToi').innerHTML = '<p class="trong">Chưa đọc được lịch — kiểm tra mạng rồi lật lại tháng.</p>';
	});
}

/* '2026-09-17' -> '17/09 T5'. Thứ đọc được ngay là thứ người ta thật sự dùng để nhớ ca. */
function ngayGon(s){
	var p = String(s||'').split('-');
	if(p.length !== 3) return String(s||'');
	var d = new Date(Date.UTC(+p[0], +p[1]-1, +p[2]));
	var tt = ['CN','T2','T3','T4','T5','T6','T7'][d.getUTCDay()];
	return p[2] + '/' + p[1] + ' ' + tt;
}

el('btThangTruoc').addEventListener('click', function(){ if(THANG) veThang(thangDich(THANG,-1)); });
el('btThangSau').addEventListener('click', function(){ if(THANG) veThang(thangDich(THANG,1)); });

/* ---------------------------------------------------------------- chụp ảnh */
var LUONG = null, ANH = null;

el('btCham').addEventListener('click', function(){
	bao('baoCham','',null);
	ANH = null;
	xinGps();
	/* Mốc giờ lấy LẠI ngay trước khi chụp: trang có thể đã mở từ sáng, và mốc cũ trôi theo đồng
	   hồ máy suốt tám tiếng thì đủ lệch để đóng dấu sai phút. */
	napGio().then(nhipDongHo);
	hien('mChup',true);
	veAnhMau();
	moCamera();
});

function veAnhMau(){
	if(el('oMau').getAttribute('data-xong')==='1') return;
	goi('anhmau',{}).then(function(j){
		el('oMau').setAttribute('data-xong','1');
		if(j && j.ok && j.dataUri){
			el('oMau').innerHTML = '<img class="mmau" src="'+esc(j.dataUri)+'" alt="ảnh mẫu">'
				+ '<p style="margin:0;font-size:12.5px;color:var(--chu-mo)">Chụp giống hình mẫu bên cạnh: '
				+ 'thẳng mặt, đủ sáng, không đội mũ.</p>';
		} else {
			el('oMau').innerHTML = '<p style="margin:0;font-size:12.5px;color:var(--chu-mo)">'
				+ 'Chụp thẳng mặt, đủ sáng, không đội mũ.</p>';
		}
	}).catch(function(){ el('oMau').setAttribute('data-xong','1'); });
}

function moCamera(){
	DEM_HUT = 0;
	hien('xem',false); hien('vid',true);
	el('nhomChup').classList.remove('an'); el('nhomXem').classList.add('an');
	bao('loiChup','',null);
	if(!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia){
		bao('loiChup','dong','Trình duyệt này không mở được máy ảnh. Dùng Chrome hoặc Safari.');
		return;
	}
	navigator.mediaDevices.getUserMedia({ video:{ facingMode:'user', width:{ideal:1280} }, audio:false })
	.then(function(s){
		LUONG = s;
		var v = el('vid');
		v.srcObject = s;
		/* Đếm từ lúc CÓ HÌNH, không phải từ lúc bấm nút: máy ảnh trên điện thoại cũ mất một
		   hai giây mới lên hình, đếm sớm là hết 5 giây khi màn hình vẫn còn đen. */
		v.onloadedmetadata = function(){ if(!ANH) batDem(); };
		if(v.videoWidth && !ANH){ batDem(); }
	})
	.catch(function(e){
		bao('loiChup','dong','Không mở được máy ảnh: ' + (e && e.name ? e.name : 'lỗi')
			+ '. Vào Cài đặt trình duyệt cho phép Máy ảnh với trang này.');
	});
}

function dongCamera(){
	if(LUONG){ LUONG.getTracks().forEach(function(t){ t.stop(); }); LUONG=null; }
	el('vid').srcObject = null;
}

el('btHuyChup').addEventListener('click', function(){ dungDem(); dongCamera(); hien('mChup',false); });
el('btChupLai').addEventListener('click', function(){ dungDem(); ANH=null; moCamera(); });

/* ---------------------------------------------------------------- đếm ngược rồi TỰ CHỤP

   Anh Thắng 25/08/2026: *"Trước khi chụp nó sẽ báo 5-4-3-2-1"*. Lý do thật sự đáng làm: chụp
   bằng một tay trong khi tay kia giơ điện thoại thì ngón cái che ống kính hoặc làm rung máy —
   ảnh mờ, mà ảnh mờ thì mất luôn công dụng duy nhất của nó là đối chiếu khi tranh cãi.

   ⚠️ ĐẾM NGƯỢC KHÔNG ĐỤNG TỚI GIỜ ĐÓNG DẤU. Giờ in lên ảnh là giờ máy chủ ở ĐÚNG GIÂY BẤM
      máy, `gioMayChu()` tự trôi theo đồng hồ máy từ mốc đã lấy — nên năm giây đếm ngược không
      làm ảnh ghi sai giờ. Nếu đóng dấu bằng giờ lúc MỞ màn chụp thì mỗi tấm ảnh lệch 5 giây,
      và lệch âm thầm.

   ⚠️ Chưa có mốc giờ máy chủ thì KHÔNG chụp. Đếm lại chứ không chụp bừa — xem `chupNgay()`. */
var DEM = null;          /* id của bộ đếm đang chạy */

/**
 * ⚠️ 0 = KHÔNG TỰ CHỤP, CHỜ NGƯỜI BẤM "Chụp ngay".
 *
 * Ba bản trong một ngày, ghi lại cả ba để người sau thấy đường đi:
 *
 *   · 25/08/2026 — đếm ngược 5 giây. Lý do anh Thắng nêu: chụp một tay trong khi tay kia giơ
 *     điện thoại thì ngón cái dễ che ống kính hoặc làm rung máy; năm giây là thời gian chỉnh
 *     lại tay. Ảnh mờ thì mất luôn công dụng duy nhất của nó — đối chiếu khi tranh cãi.
 *
 *   · 17/09/2026 sáng — anh Thắng: *"cho chụp luôn, không cần đến giây"*. Tôi hiểu thành TỰ
 *     CHỤP NGAY khi camera lên hình, và đó là chỗ SAI: người ta vừa bấm nút CHẤM CÔNG, màn
 *     chụp mở ra, và máy bấm máy trước khi họ kịp đưa mặt vào khung. Ảnh ra là ảnh trần nhà.
 *
 *   · 17/09/2026 — anh Thắng: *"phải bấm chụp sao lại để tự chụp"*. Đúng: nếu đằng nào cũng
 *     phải bấm thì đừng tự chụp. Nay màn chụp chỉ bày hình trực tiếp và đứng chờ; người ta
 *     canh xong thì bấm "Chụp ngay".
 *
 * Cách này giải quyết luôn nỗi lo 25/08 mà không tốn năm giây của ai: người bấm lúc họ SẴN
 * SÀNG, không phải lúc đồng hồ đếm xong.
 *
 * 🔴 GIỮ BỘ ĐẾM LẠI DƯỚI DẠNG MỘT CON SỐ. Muốn quay về tự chụp sau N giây thì sửa đúng số này
 *    thành N — không phải dựng lại cơ chế. Và toàn bộ chốt chống-hụt (`DEM_HUT`, đợi khi chưa
 *    có giờ máy chủ) vẫn nguyên: nó phục vụ cả đường bấm tay.
 */
var DEM_GIAY = 0;
var DEM_HUT = 0;         /* số lần đếm xong mà chụp không được */
var HUT_TOI_DA = 3;

function dungDem(){
	if(DEM){ clearInterval(DEM); DEM = null; }
	el('oDem').classList.add('an');
}

function batDem(){
	dungDem();

	/* 🔴 DEM_GIAY = 0 → KHÔNG TỰ CHỤP. Chỉ bày hình trực tiếp rồi đứng chờ người bấm "Chụp ngay".
	   Không đặt hẹn giờ, không đếm, không thử lại ngầm — mọi lượt chụp đều do người bấm, nên
	   `chupNgay()` chạy đúng lúc họ đã canh xong khung hình.

	   ⚠️ KHÔNG ẨN nút "Chụp ngay" hay đổi nó thành thứ khác: ở chế độ này nó là đường DUY NHẤT
	      để chụp. Bản trước tự chụp nên nút ấy chỉ là lối thoát khi máy hụt; nay nó là nút
	      chính. */
	if(DEM_GIAY <= 0){
		el('oDem').classList.add('an');
		return;
	}

	var con = DEM_GIAY;
	el('soDem').textContent = con;
	el('oDem').classList.remove('an');
	DEM = setInterval(function(){
		con--;
		if(con > 0){ el('soDem').textContent = con; return; }
		dungDem();
		/* Chụp hụt (máy ảnh chưa sẵn sàng, chưa có giờ máy chủ) thì ĐẾM LẠI, đừng đứng im:
		   người ta đang giơ điện thoại chờ, không nhìn vào dòng chữ lỗi nhỏ phía dưới. */
		if(chupNgay()){ DEM_HUT = 0; return; }
		/* Hụt mãi (mất mạng nên không có giờ máy chủ) thì DỪNG, đừng quay vòng vô tận: vòng lặp
		   im lặng làm người ta đứng chờ mà không hiểu, còn nút "Chụp ngay" thì vẫn bấm được. */
		DEM_HUT++;
		if(DEM_HUT >= HUT_TOI_DA){
			DEM_HUT = 0;
			bao('loiChup','dong','Thử tự chụp ' + HUT_TOI_DA + ' lần chưa được — thường là mạng '
				+ 'đang chập chờn nên chưa lấy được giờ máy chủ. Bấm "Chụp ngay" để thử bằng tay.');
			return;
		}
		setTimeout(function(){ if(!ANH) batDem(); }, 1200);
	}, 1000);
}

/* Chạm vào khung hình = "khoan, đếm lại từ đầu". Không thêm nút: màn chụp đã có hai nút, thêm
   nút thứ ba vào chỗ người ta đang giơ điện thoại một tay là mời bấm nhầm.

   ⚠️ CHỈ CÓ NGHĨA Ở CHẾ ĐỘ ĐẾM NGƯỢC (DEM_GIAY > 0). Khi máy chờ người bấm thì không có gì để
      "đếm lại", nên chạm khung KHÔNG làm gì — và cố ý không cho nó chụp luôn: vùng xem hình
      chiếm gần hết màn, người đang giơ điện thoại một tay chạm trúng là mất một tấm ảnh trần
      nhà, rồi phải bấm "Chụp lại". Một đường chụp duy nhất, rõ ràng, là nút "Chụp ngay". */
el('oDem').parentNode.addEventListener('click', function(){
	if(DEM_GIAY <= 0) return;             /* chờ người bấm — không có bộ đếm để khởi động lại */
	if(ANH) return;                       /* đã chụp xong, đang xem lại */
	if(el('vid').classList.contains('an')) return;
	batDem();
});

/**
 * Chụp một tấm. Trả về true nếu chụp được.
 * Dùng chung cho nút "Chụp ngay" và cho bộ đếm — hai đường chụp riêng là hai chỗ đóng dấu giờ,
 * và sớm muộn một chỗ quên mất ràng buộc nào đó.
 */
function chupNgay(){
	if(ANH) return true;                  /* 🔴 ràng buộc 4: đã có ảnh thì không chụp đè */
	var v = el('vid');
	if(!v.videoWidth){ bao('loiChup','dong','Máy ảnh chưa sẵn sàng — chờ một giây rồi bấm lại.'); return false; }
	var d = gioMayChu();
	if(!d){
		/* 🔴 Không có giờ máy chủ thì KHÔNG đóng dấu bừa bằng giờ máy. Thà chối và bảo thử lại. */
		bao('loiChup','dong','Chưa lấy được giờ máy chủ. Kiểm tra mạng rồi bấm Chụp lại.');
		napGio();
		return false;
	}

	/* 🔴 ràng buộc 2: thu nhỏ về 720px NGAY TẠI ĐÂY, trước mọi thứ khác. */
	var ti = v.videoWidth / v.videoHeight;
	var W = Math.min(RONG_ANH, v.videoWidth), H = Math.round(W / ti);
	var c = el('xem');
	c.width = W; c.height = H;
	var g = c.getContext('2d');
	g.drawImage(v, 0, 0, W, H);

	/* 🔴 ràng buộc 1: đóng dấu bằng GIỜ MÁY CHỦ */
	var chu = chuNgay(d) + '  ' + chuGio(d);
	var co  = Math.max(13, Math.round(W/28));
	g.font = '700 ' + co + 'px monospace';
	var rong = g.measureText(chu).width;
	g.fillStyle = 'rgba(0,0,0,.62)';
	g.fillRect(0, H - co - 16, rong + 20, co + 16);
	g.fillStyle = '#fff';
	g.fillText(chu, 10, H - 10);

	/* ══════════════════════════════════════════════════════════════════════════════════════════
	   DẤU VỊ TRÍ — góc PHẢI dưới (anh Thắng 17/09/2026).
	   Dấu giờ ở góc trái, dấu vị trí ở góc phải: hai mốc của cùng một lượt chấm công, đọc được
	   ngay trên ảnh mà không phải mở phiếu ra tra.

	   🔴 ĐÓNG ĐÚNG THỨ ĐANG CÓ, KHÔNG ĐÓNG THỨ MONG MUỐN. Ba trạng thái, ba dòng khác nhau:
	     · GPS thật  -> toạ độ 6 số lẻ kèm ±sai số
	     · vị trí theo địa chỉ mạng (sai số hàng trăm km) -> ghi rõ "≈ theo mạng"
	     · không có  -> "KHÔNG GPS"
	     In toạ độ ±200km như thể nó là GPS chính là nói dối bằng con số — cùng lý do màn hình
	     KHÔNG vẽ bản đồ ở mức ấy (xem `veViTri()`). Ảnh này là bằng chứng đối chiếu khi tranh
	     cãi; một con số trông chính xác mà sai là tệ hơn chữ "KHÔNG GPS".

	   ⚠️ CỠ CHỮ NHỎ HƠN DẤU GIỜ. Toạ độ dài gấp đôi; để cùng cỡ thì trên ảnh dọc 720px nó chạm
	      vào dấu giờ ở giữa cạnh dưới, hai dấu dính nhau thành một vệt đen không đọc được.
	   ══════════════════════════════════════════════════════════════════════════════════════════ */
	var chuVT = '';
	if(GPS_TRANG === 'co' && GPS){
		var uocLuong = (mucGps(GPS.acc) === 'mang');
		/* 🔴 SỐ LẺ KHỚP VỚI SAI SỐ, KHÔNG PHẢI LUÔN 6. Sáu số lẻ là độ phân giải ~11cm — in nó
		   bên cạnh "±217km" là tự mâu thuẫn ngay trong một dòng, và người đọc tin vào con số
		   dài chứ không đọc cái ±. Cùng lý do màn hình không vẽ bản đồ khi sai số hàng trăm km.
		   Tiện thể chuỗi ngắn lại, đỡ đè vào dấu giờ. */
		var le = (GPS.acc <= 50) ? 6 : (GPS.acc <= 200 ? 5 : 3);
		chuVT = (uocLuong ? '≈ ' : '') + GPS.lat.toFixed(le) + ', ' + GPS.lng.toFixed(le)
		      + ' ±' + dai(GPS.acc);
	} else {
		chuVT = 'KHÔNG GPS';
	}
	var coVT = Math.max(11, Math.round(W/40));

	/* ⚠️ CHỐT KHÔNG ĐÈ DẤU GIỜ. Trên ảnh dọc 720px, chuỗi toạ độ theo-mạng từng dài tới mức hộp
	   của nó chạm hộp dấu giờ, hai vệt đen dính thành một mảng không đọc được. Rút số lẻ ở trên
	   đã đủ cho mọi ca hiện tại — chốt này để phòng ca sau (máy ảnh hẹp hơn, chuỗi dài hơn):
	   thu nhỏ dần cho tới khi lọt. Thà chữ nhỏ còn hơn hai dấu chồng nhau. */
	g.font = '700 ' + coVT + 'px monospace';
	var rongVT = g.measureText(chuVT).width;
	while(coVT > 9 && (rong + 20) > (W - rongVT - 20)){
		coVT--;
		g.font = '700 ' + coVT + 'px monospace';
		rongVT = g.measureText(chuVT).width;
	}

	g.fillStyle = 'rgba(0,0,0,.62)';
	g.fillRect(W - rongVT - 20, H - coVT - 14, rongVT + 20, coVT + 14);
	/* Không GPS thì chữ vàng — người soát ảnh nhận ra ngay mà không phải đọc. */
	g.fillStyle = (GPS_TRANG === 'co' && GPS) ? '#fff' : '#fde68a';
	g.fillText(chuVT, W - rongVT - 10, H - 9);

	/* ══════════════════════════════════════════════════════════════════════════════════════════
	   HÌNH DÁNG Ô BẢN ĐỒ TÍNH TRƯỚC — vì dấu ĐỊA CHỈ ở dưới phải biết chừa chỗ cho nó.
	   Tính ở đây, dùng ở hai nơi; tính hai lần là dựng sẵn cái ngày hai chỗ lệch nhau một vài
	   pixel rồi chữ đè lên bản đồ.
	   ══════════════════════════════════════════════════════════════════════════════════════════ */
	var veMap = !!(BANDO && BANDO.im && BANDO.im.complete && BANDO.im.naturalWidth > 0);
	var oB = Math.max(72, Math.round(W / 4));
	var oX = W - oB - 10;
	var oY = H - coVT - 14 - oB - 8;
	/* Ảnh quá thấp (máy ảnh lạ, tỉ lệ dẹt) thì ô tràn lên khỏi mép trên — bỏ ô, giữ dòng toạ độ. */
	if(oY < 8){ veMap = false; }

	/* ══════════════════════════════════════════════════════════════════════════════════════════
	   DẤU ĐỊA CHỈ — anh Thắng 21/09/2026: *"Chèn địa chỉ vào ảnh"*.

	   🔴 ĐỊA CHỈ LÀ THỨ NGƯỜI ĐỌC ĐƯỢC; TOẠ ĐỘ THÌ KHÔNG. Tấm ảnh này là bằng chứng đem ra đối
	      chiếu khi có tranh cãi, mà `10.798747,106.597064` thì phải mở bản đồ ra mới biết là
	      đâu. "Lê Đức Anh, Phường Bình Tân" thì nhìn phát biết ngay. Giữ CẢ HAI: toạ độ để máy
	      tra, địa chỉ để người đọc.

	   ⚠️ CHỪA CHỖ CHO Ô BẢN ĐỒ. Góc dưới phải đã có toạ độ và ô bản đồ chồng lên nhau theo chiều
	      dọc; địa chỉ mà kéo hết chiều ngang là nó chui thẳng vào dưới ô bản đồ. Nên bề ngang
	      tối đa cắt tại mép trái ô ấy.

	   ⚠️ KHÔNG CÓ ĐỊA CHỈ THÌ THÔI, đừng để trống một vệt đen. Máy chủ tra tên đường qua mạng và
	      tra sau khi màn đã vẽ (xem `xinDiaChi()`), nên lúc bấm chụp có thể chưa có. Dòng này
	      là thứ ĐỌC CHO SƯỚNG MẮT — cho nó chặn hay làm hỏng tấm ảnh là đánh đổi sai.
	   ══════════════════════════════════════════════════════════════════════════════════════════ */
	try {
		var dcO = (GPS && GPS.lat && GPS.lng) ? (GPS.lat.toFixed(4) + ',' + GPS.lng.toFixed(4)) : '';
		var dc  = (dcO && DIA_CHI_NHO[dcO]) ? String(DIA_CHI_NHO[dcO]) : '';
		if(dc){
			var coDC   = Math.max(10, Math.round(W / 46));
			var rongTD = (veMap ? oX - 18 : W - 20);
			g.font = '700 ' + coDC + 'px sans-serif';
			var dongDC = catDong(g, dc, rongTD, 2);
			var buocDC = coDC + 4;
			var caoDC  = dongDC.length * buocDC + 8;
			/* Nằm NGAY TRÊN dấu giờ. Dấu giờ bắt đầu ở `H - co - 16`; chừa 6px cho khỏi dính. */
			var yDC = H - co - 16 - caoDC - 6;
			if(yDC >= 4){
				var rongDC = 0;
				for(var i2 = 0; i2 < dongDC.length; i2++){
					rongDC = Math.max(rongDC, g.measureText(dongDC[i2]).width);
				}
				g.fillStyle = 'rgba(0,0,0,.62)';
				g.fillRect(0, yDC, rongDC + 20, caoDC);
				g.fillStyle = '#fff';
				for(var i3 = 0; i3 < dongDC.length; i3++){
					g.fillText(dongDC[i3], 10, yDC + 4 + buocDC * (i3 + 1) - 4);
				}
			}
		}
	} catch(e){ /* Mất dòng địa chỉ còn hơn mất tấm ảnh — cùng lý do với ô bản đồ ở dưới. */ }

	/* ══════════════════════════════════════════════════════════════════════════════════════════
	   Ô BẢN ĐỒ — ngay TRÊN dòng toạ độ, cùng góc phải.

	   🔴 BỌC `try`. Dù đã đặt `crossOrigin` đúng cách, một bản trình duyệt lạ vẫn có thể làm
	      canvas nhiễm — và canvas nhiễm thì `toDataURL()` ở dưới ném lỗi, tức MẤT CẢ TẤM ẢNH.
	      Thà mất ô bản đồ còn hơn mất lượt chấm công. Đây không phải `try` cho có: nó là chốt
	      giữa "thiếu một ô trang trí" và "không ghi được công".
	   ══════════════════════════════════════════════════════════════════════════════════════════ */
	if(veMap){
		try {
			/* `oB` · `oX` · `oY` tính ở khối trên — dấu địa chỉ cần biết trước để chừa chỗ. */
			g.save();
			/* Cắt tròn góc cho ô — và quan trọng hơn: chặn ảnh bản đồ tràn ra ngoài khung. */
			g.beginPath();
			g.rect(oX, oY, oB, oB);
			g.clip();
			g.drawImage(BANDO.im, oX, oY, oB, oB);

			/* Ghim đúng chỗ. Ranh giới ô bản đồ là cố định nên điểm hiếm khi nằm giữa — đặt theo
			   vị trí lẻ đã tính lúc tải, chứ không đặt bừa vào tâm ô. */
			var gx = oX + BANDO.fx * oB;
			var gy = oY + BANDO.fy * oB;
			g.beginPath();
			g.arc(gx, gy, Math.max(4, oB / 18), 0, Math.PI * 2);
			g.fillStyle = '#e7000b';
			g.fill();
			g.lineWidth = 2;
			g.strokeStyle = '#fff';
			g.stroke();

			/* ⚠️ GHI NGUỒN — BẮT BUỘC theo giấy phép ODbL của OpenStreetMap, không phải trang
			   trí. Bỏ dòng này "cho gọn" là dùng dữ liệu của người ta trái phép. */
			var coN = Math.max(8, Math.round(oB / 11));
			g.font = '700 ' + coN + 'px sans-serif';
			var chuN = '© OpenStreetMap';
			var rongN = g.measureText(chuN).width;
			g.fillStyle = 'rgba(0,0,0,.55)';
			g.fillRect(oX, oY + oB - coN - 5, rongN + 8, coN + 5);
			g.fillStyle = '#fff';
			g.fillText(chuN, oX + 4, oY + oB - 4);
			g.restore();

			/* Viền trắng mảnh: tách ô khỏi nền ảnh, nhất là khi chụp trần nhà sáng. */
			g.lineWidth = 2;
			g.strokeStyle = 'rgba(255,255,255,.85)';
			g.strokeRect(oX, oY, oB, oB);
		} catch(e){
			/* Nhiễm canvas hoặc lỗi vẽ: bỏ ô bản đồ, đi tiếp. Không báo gì — người đang chấm
			   công không làm được gì với thông tin này, và dấu giờ/toạ độ vẫn còn nguyên. */
			g.restore();
		}
	}

	ANH = c.toDataURL('image/jpeg', 0.8);
	dungDem();
	hien('vid',false); hien('xem',true);
	el('nhomChup').classList.add('an'); el('nhomXem').classList.remove('an');
	dongCamera();

	/* Ảnh tối thui thì CẢNH BÁO, không chặn. Máy tự bấm nên người chụp không kịp nhìn khung
	   hình — phải nói ra để họ bấm "Chụp lại" thay vì gửi đi một tấm không nhận ra ai. Cố ý
	   không tự chối: thà có ảnh tối còn hơn không có lượt chấm công nào. */
	if(doSang(g, W, H) < 55){
		/* `bao()` thoát HTML (đúng — chữ ở đây có thể tới từ máy chủ), nên viết chữ thuần,
		   đừng nhét thẻ vào rồi ngồi thắc mắc sao màn hình hiện ra "&lt;b&gt;". */
		bao('loiChup','vang','Ảnh hơi tối, khó nhận ra mặt. Ra chỗ sáng hơn rồi bấm "Chụp lại" '
			+ '— hoặc cứ dùng ảnh này nếu anh/chị thấy rõ mặt mình.');
	}
	return true;
}

/**
 * CẮT MỘT CHUỖI DÀI THÀNH TỐI ĐA `nToiDa` DÒNG VỪA BỀ NGANG `rong`.
 *
 * 🔴 CẮT THEO TỪ, KHÔNG CẮT THEO KÝ TỰ. Địa chỉ tiếng Việt toàn từ ngắn ngăn bởi dấu phẩy;
 *    cắt giữa từ ra "Thành ph / ố Hồ Chí Minh" thì đọc còn khó hơn không có.
 *
 * ⚠️ DÒNG CUỐI TRÀN THÌ CẮT BỚT VÀ THÊM "…", đừng để nó chạy ra khỏi hộp đen. Hộp vẽ theo bề
 *    ngang ĐO ĐƯỢC của chữ, nên chữ tràn không phải là chữ thò ra ngoài hộp — nó là hộp phình
 *    to đè lên nửa tấm ảnh.
 *
 * ⚠️ MỘT TỪ DUY NHẤT DÀI HƠN CẢ DÒNG vẫn phải ra được cái gì đó. Vòng `while` cắt dần từng ký
 *    tự có chốt `length > 1` để không quay vô tận trên một ô hẹp bất thường.
 */
function catDong(g, chu, rong, nToiDa){
	var tu = String(chu).split(/\s+/), ds = [], d = '';
	for(var i = 0; i < tu.length; i++){
		var thu = d ? (d + ' ' + tu[i]) : tu[i];
		if(g.measureText(thu).width <= rong || !d){
			d = thu;
		} else {
			ds.push(d);
			d = tu[i];
			if(ds.length === nToiDa - 1) {
				/* Dòng cuối: gom hết phần còn lại rồi cắt cho vừa. */
				d = tu.slice(i).join(' ');
				break;
			}
		}
	}
	if(d) ds.push(d);
	if(ds.length > nToiDa) ds = ds.slice(0, nToiDa);
	var c = ds.length - 1;
	if(c >= 0 && g.measureText(ds[c]).width > rong){
		var t = ds[c];
		while(t.length > 1 && g.measureText(t + '…').width > rong){ t = t.slice(0, -1); }
		ds[c] = t + '…';
	}
	return ds;
}

/** Độ sáng trung bình 0–255. Lấy mẫu thưa: quét đủ 720×540 điểm trên máy cũ là khựng một nhịp. */
function doSang(g, W, H){
	try{
		var d = g.getImageData(0, 0, W, H).data, tong = 0, n = 0;
		for(var i = 0; i < d.length; i += 4 * 40){
			tong += (d[i] * 0.299 + d[i+1] * 0.587 + d[i+2] * 0.114);
			n++;
		}
		return n ? (tong / n) : 255;
	}catch(e){ return 255; }   /* đọc không được thì coi như đủ sáng, đừng doạ nhầm */
}

el('btChup').addEventListener('click', function(){ dungDem(); chupNgay(); });

/* ---------------------------------------------------------------- 🔴 ràng buộc 3:
   hỏi cơ sở / nhiệm vụ ĐÚNG LÚC LƯU, không hỏi lúc mở trang. */
el('btDung').addEventListener('click', function(){
	hien('mChup',false);
	veManChon();
	hien('mChon',true);
});
el('btHuyChon').addEventListener('click', function(){ hien('mChon',false); hien('mChup',true); moCamera(); });

function veManChon(){
	bao('loiChon','',null);
	/* 🔴 Anh Thắng 25/09/2026: *"Bấm check in hoặc out sẽ hỏi cơ sở luôn, cả vào và ra, không mặc
	   định nữa"* — LUÔN dựng ô chọn thật, kể cả khi chỉ có đúng MỘT cơ sở. Trước đây chỉ một cơ
	   sở thì màn này hiện một `<div>` tĩnh và lượt LƯU đọc thẳng `TOI.coSoMacDinh` (xem nhánh dự
	   phòng ở `el('btLuu')`, không có `#oCS` để đọc) — tức lượt chấm công trôi qua mà không ai
	   thật sự BẤM chọn cơ sở nào. Luôn có `<select>` thì nút LƯU luôn đọc từ đây, không còn
	   nhánh nào tự điền im lặng. */
	var cs = (TOI && TOI.dsCoSo) || [];
	if(!cs.length && TOI && TOI.coSoMacDinh){ cs = [TOI.coSoMacDinh]; }
	var h = '<label for="oCS">Cơ sở đang có mặt</label><select id="oCS">';
	for(var i=0;i<cs.length;i++){
		h += '<option value="'+esc(cs[i])+'"'
		   + (cs[i]===TOI.coSoMacDinh?' selected':'') + '>'+esc(cs[i])+'</option>';
	}
	el('oChonCS').innerHTML = h + '</select><p></p>';

	var nv = (TOI && TOI.dsNhiemVu) || [];
	if(nv.length){
		var h2 = '<label for="oNV">Nhiệm vụ</label><select id="oNV"><option value="">— việc chính —</option>';
		for(var k=0;k<nv.length;k++){ h2 += '<option value="'+esc(nv[k])+'">'+esc(nv[k])+'</option>'; }
		el('oChonNV').innerHTML = h2 + '</select>';
	} else {
		el('oChonNV').innerHTML = '';
	}
}

/* 🔴 ràng buộc 4: khoá nút ngay khi bấm, mở lại chỉ khi đã có câu trả lời. */
var DANG_LUU = false;
el('btLuu').addEventListener('click', function(){
	if(DANG_LUU) return;
	if(!ANH){ bao('loiChon','dong','Chưa có ảnh. Quay lại chụp.'); return; }
	DANG_LUU = true;
	var b = el('btLuu');
	b.disabled = true; b.textContent = 'ĐANG LƯU…';
	bao('loiChon','',null);

	var oCS = el('oCS'), oNV = el('oNV');
	var cs = oCS ? oCS.value : (((TOI&&TOI.dsCoSo)||[])[0] || (TOI&&TOI.coSoMacDinh) || '');
	var nv = oNV ? oNV.value : '';

	var anhVuaGui = ANH;   /* giữ lại để đối chiếu mặt SAU KHI giờ đã ghi xong */
	var truocKhiGui = chuoiHomNay(cs);   /* ảnh chụp trạng thái để soát lại nếu lượt gọi hỏng */

	/* 🔴 ĐÓNG BĂNG MỐC GIỜ NGAY TẠI ĐÂY, và gửi kèm ở CẢ lượt online.
	   Lượt gửi lại sau phải mang ĐÚNG TỪNG GIÂY con số này thì máy chủ mới nhận ra nó là lượt
	   trùng (`quyet_dinh_gio` trả 'trung') và bỏ qua. Nếu lượt đầu ghi bằng giờ máy chủ lúc
	   NHẬN còn lượt gửi lại ghi bằng giờ lúc BẤM thì hai con số lệch vài giây, và lượt thứ hai
	   thành GIỜ RA — một ca dài 0 phút, mà bảng công thấy đã đủ cặp nên không báo thiếu. */
	var goiCham = {
		token: token(), anh: ANH, gps: GPS, coSo: cs, nhiemVu: nv,
		veGio: (MOC && MOC.ve) || '',
		troi:  MOC ? Math.max(0, Math.round(performance.now() - MOC.tuLuc)) : 0
	};
	goi('cham', goiCham, CHO_CO_ANH).then(function(j){
		if(!j || !j.ok){ bao('loiChon','dong',(j&&j.error)||'Không lưu được.'); return; }
		ANH = null;
		soiMat(anhVuaGui, j.ngay, j.coSo);
		hien('mChon',false);
		var nhan = (j.loai==='ra') ? 'GIỜ RA' : 'GIỜ VÀO';
		bao('baoCham','xanh', '✔ Đã ghi ' + nhan + ' ' + j.gio + ' — ' + j.coSo
			+ ' (' + j.ngay + ')' + (j.ma!==(TOI&&TOI.maNV) ? ' · hàng ' + j.ma : ''));
		/* 🔴 CƠ SỞ ĐANG GÁC VỊ TRÍ MÀ LƯỢT NÀY BỊ CHẤM Ở NGOÀI VÙNG -> NÓI RA NGAY, ở đây.
		   Lượt vẫn được ghi (mức "Chỉ ghi chú" cố ý không chặn), nhưng dòng ghi chú ấy nằm
		   trong Bảng công — nơi người vừa bấm không mở. Im lặng thì tới cuối tháng quản lý mới
		   hỏi "sao hôm đó chấm cách cửa hàng 3km", mà lúc đó thì không ai còn nhớ nổi hôm ấy
		   đứng ở đâu. Nói ngay lúc còn đứng đó thì họ sửa được ngay, hoặc giải thích được ngay. */
		if(j.viTri && j.viTri.gac && j.viTri.ket === 'ngoai'){
			el('baoCham').innerHTML += '<div class="vang">⚠ Lượt vừa ghi bị đánh dấu <b>NGOÀI '
				+ 'vùng cơ sở</b>. ' + esc(j.viTri.chu||'') + ' Lượt công vẫn được ghi, nhưng quản '
				+ 'lý sẽ thấy dấu này. Chọn nhầm cơ sở thì báo quản lý sửa ngay hôm nay.</div>';
		}
		/* 🔴 TRUY VẾT: TOẠ ĐỘ RƠI VÀO VÙNG MỘT CƠ SỞ KHÁC -> NÓI NGAY VỚI CHÍNH NGƯỜI VỪA BẤM.
		   Anh Thắng 20/09/2026: *"khi nhân viên đi qua cơ sở khác, chấm báo cáo cơ sở"*.

		   ⚠️ CÂU NÀY KHÔNG PHẢI LỜI BUỘC TỘI, và phải viết cho đúng như vậy. Đi hỗ trợ cơ sở bạn
		      là chuyện được phép; hai cửa hàng trong cùng trung tâm thương mại cách nhau 80m
		      cũng là chuyện thường. Thứ duy nhất máy biết chắc là toạ độ, nên nó chỉ được nói
		      đúng bấy nhiêu — và nói NGAY LÚC NÀY, khi người ta còn đứng đó và còn sửa được nếu
		      chỉ là chọn nhầm ô cơ sở. Để tới cuối tháng thì không ai nhớ nổi hôm ấy mình ở đâu. */
		if(j.vet && j.vet.trong && j.vet.coSo){
			el('baoCham').innerHTML += '<div class="vang">📍 Toạ độ lúc bấm nằm trong vùng cơ sở '
				+ '<b>' + esc(j.vet.coSo) + '</b>, còn lượt này ghi về <b>' + esc(j.coSo) + '</b>. '
				+ 'Nếu anh/chị đang làm ở ' + esc(j.vet.coSo) + ' thì chọn lại đúng cơ sở rồi bấm '
				+ 'lại; nếu đang đi hỗ trợ hoặc đứng gần đó thì cứ để nguyên — lượt đã ghi rồi, '
				+ 'chỉ là bảng công có ghi dấu này.</div>';
		}
		/* 🔴 HỎI LOẠI GIỜ CHỈ Ở LƯỢT GIỜ RA, và chỉ SAU khi giờ đã ghi xong. Hỏi lúc vào thì ca
		   còn chưa làm, chưa biết mình sẽ làm gì; hỏi trước khi ghi thì một cái hộp đứng chắn
		   giữa người ta và lượt chấm công. */
		if(j.loai === 'ra'){ hoiKetCa(j.ngay, j.ma); }
		napToi();
	}).catch(function(e){
		/* KHÔNG dừng ở câu lỗi. Xem `soatLaiDaGhi`. */
		return soatLaiDaGhi(cs, truocKhiGui, (e && e.message) || 'Lỗi mạng — chưa lưu được.', goiCham);
	}).then(function(){
		DANG_LUU = false;
		b.disabled = false; b.textContent = 'LƯU CHẤM CÔNG';
	});
});

/* Trạng thái "hôm nay" của MỘT cơ sở, gói thành một chuỗi để so trước/sau. Gồm cả giờ ra, nên
   lượt TAN LÀM cũng so được — chỉ đếm "đã có giờ vào chưa" thì buổi chiều lượt nào cũng ra
   "đã ghi rồi", vì giờ vào buổi sáng vẫn nằm đó. */
function chuoiHomNay(cs){
	var ds = (TOI && TOI.homNay && TOI.homNay[cs]) || [], r = [];
	for(var i=0;i<ds.length;i++){
		r.push((ds[i].hauTo||'') + '|' + (ds[i].vao||'') + '|' + (ds[i].ra||''));
	}
	return r.join(';');
}

function chuHomNay(cs){
	var ds = (TOI && TOI.homNay && TOI.homNay[cs]) || [], r = [];
	for(var i=0;i<ds.length;i++){
		r.push((ds[i].hauTo ? ('hàng ' + ds[i].hauTo + ': ') : '')
			+ 'vào ' + (ds[i].vao || '—') + ', ra ' + (ds[i].ra || '—'));
	}
	return r.join(' · ');
}

/**
 * LƯỢT `cham` HỎNG THÌ HỎI LẠI MÁY CHỦ, ĐỪNG ĐOÁN.
 *
 * 🔴 08/09/2026 — anh Thắng: *"tại báo cáo lỗi không rõ ràng"*, sau khi chụp màn "Máy chủ không
 *    trả lời sau 10 giây" ở nút LƯU CHẤM CÔNG.
 *    Quá hạn KHÔNG có nghĩa là chưa ghi. Ảnh đã đi rồi; thứ chưa về chỉ là câu trả lời. Nên câu
 *    lỗi cũ đặt người đứng đó vào đúng thế không biết đường nào mà lần: bấm lại thì có thể
 *    thành **giờ ra** ngay sau giờ vào (mất cả ca công), không bấm thì có thể **không có giờ
 *    vào nào**. Đoán hộ họ theo kiểu nào cũng sai một nửa số lần.
 *    Máy chủ biết thừa câu trả lời. Chỉ cần hỏi: một lượt `toi` nhẹ (không ảnh), so bảng
 *    "hôm nay" của cơ sở ấy với ảnh chụp lúc trước khi gửi.
 *
 * ⚠️ So CẢ BẢNG chứ không so "có giờ vào chưa" — xem `chuoiHomNay`.
 * ⚠️ Hỏi lại mà cũng hỏng thì NÓI THẲNG LÀ KHÔNG BIẾT, và chỉ việc kiểm tra bằng tay. Bịa ra
 *    một câu chắc chắn ở đây là thứ đắt nhất: nó khiến người ta bấm thêm một lượt nữa.
 */
function soatLaiDaGhi(cs, truoc, loi, goiCham){
	bao('loiChon','vang', loi + ' — đang hỏi lại máy chủ xem giờ có vào được không…');
	return goi('toi',{token:token()}).then(function(j){
		if(!j || !j.ok || !j.bat){ throw new Error('chưa đọc được hồ sơ'); }
		TOI = j; veHomNay(j);
		if(chuoiHomNay(cs) === truoc){
			bao('loiChon','dong', loi + ' Đã hỏi lại máy chủ: ở ' + cs
				+ ' hôm nay KHÔNG có gì mới — giờ CHƯA được ghi. Bấm LƯU CHẤM CÔNG lần nữa.');
			return;
		}
		/* Đã ghi thật -> đóng màn chọn và bỏ ảnh, y như lượt thành công. Để nguyên màn ấy là
		   mời người ta bấm thêm lượt nữa. */
		ANH = null;
		hien('mChon',false);
		bao('loiChon','',null);
		bao('baoCham','xanh','✔ Câu trả lời về chậm, nhưng GIỜ ĐÃ ĐƯỢC GHI. Hôm nay ở ' + cs
			+ ' — ' + chuHomNay(cs) + '. ĐỪNG bấm lưu lại: bấm nữa là ghi thành giờ ra.');
	}).catch(function(){
		/* 🔴 CHƯA BIẾT ĐÃ GHI HAY CHƯA -> XẾP HÀNG ĐỢI, ĐỪNG BẮT NGƯỜI TA TỰ ĐOÁN.
		   Câu cũ ở đây bảo họ "chờ có sóng rồi mở lại trang mà xem" — đúng, nhưng nó đẩy một
		   việc của máy sang cho người đang đứng ngoài cửa hàng với cái điện thoại không sóng.
		   Và xếp hàng đợi AN TOÀN ở đúng chỗ này vì lượt gửi lại mang nguyên vé giờ cũ: ghi
		   được thì trùng từng giây với lượt đầu và máy chủ bỏ qua, chưa ghi thì nó vào. */
		var xep = xepHang(goiCham);
		if(xep.ok){
			ANH = null;
			hien('mChon',false);
			bao('baoCham','vang','⏳ Chưa gửi được lên máy chủ — lượt chấm đã được GIỮ TRONG MÁY '
				+ 'và sẽ tự gửi khi có sóng. ĐỪNG chấm lại: chấm lại là hai lượt. Mở lại trang '
				+ 'này khi có mạng để nó gửi đi, và xem mục "Chờ gửi" ở đầu trang.');
			veHangCho();
			return;
		}
		bao('loiChon','dong', loi + ' Hỏi lại máy chủ cũng không được, và máy cũng KHÔNG giữ được '
			+ 'lượt này (' + xep.viSao + '), nên CHƯA BIẾT giờ đã ghi hay chưa. Chờ có sóng rồi mở '
			+ 'lại trang, xem bảng "Hôm nay" ở đầu trang: đã có giờ thì thôi, chưa có thì chấm lại.');
	});
}

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * HÀNG ĐỢI KHI MẤT MẠNG
 * ═══════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 CHỈ XẾP HÀNG KHI ĐÃ HỎI LẠI MÁY CHỦ MÀ CŨNG KHÔNG ĐƯỢC. Lượt gọi hỏng vì quá hạn thì rất
 *    có thể ảnh đã tới nơi rồi; xếp hàng ngay là mời một lượt ghi thứ hai. `soatLaiDaGhi()`
 *    hỏi lại trước, và chỉ nhánh "hỏi lại cũng hỏng" mới rơi xuống đây.
 *
 * 🔴 LƯU CẢ VÉ GIỜ VÀ ĐỘ TRÔI ĐÃ ĐÓNG BĂNG, KHÔNG ĐO LẠI LÚC GỬI. Đo lại là giờ nhảy tới lúc
 *    có sóng — tức ghi giờ vào là lúc người ta bắt được sóng, không phải lúc tới cửa hàng.
 *    Đóng băng còn là thứ làm lượt gửi lại TRÙNG TỪNG GIÂY với lượt đầu, nên gửi hai lần vô hại.
 *
 * ⚠️ TRẦN BA LƯỢT. Mỗi lượt mang một tấm ảnh base64 cỡ 100–300 KB, mà `localStorage` chỉ có
 *    khoảng 5 MB và dùng chung với mọi thứ khác của tên miền. Đầy kho thì `setItem` NÉM LỖI —
 *    không phải trả về false — và nếu không bắt thì cả khối script chết tại đó, nút bấm không
 *    lên, y hệt trang hỏng. Ba lượt là quá đủ: một ca bình thường có hai lượt.
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
var KHOA_HANG   = 'cc_hang_cho';
var HANG_TOI_DA = 3;
var DANG_DAY    = false;

function docHang(){
	try {
		var d = JSON.parse(localStorage.getItem(KHOA_HANG) || '[]');
		return Object.prototype.toString.call(d) === '[object Array]' ? d : [];
	} catch(e){ return []; }
}

function ghiHang(ds){
	try { localStorage.setItem(KHOA_HANG, JSON.stringify(ds)); return true; }
	catch(e){ return false; }
}

function xepHang(goiCham){
	if(!goiCham || !goiCham.veGio){
		/* Không có vé thì máy chủ sẽ ghi bằng giờ NHẬN, tức giờ lúc có sóng lại. Giữ một lượt
		   như thế là hứa hẹn một con số sai — thà nói thẳng là không giữ được. */
		return { ok:false, viSao:'lượt này không có vé giờ của máy chủ' };
	}
	var ds = docHang();
	if(ds.length >= HANG_TOI_DA){
		return { ok:false, viSao:'trong máy đã có ' + ds.length + ' lượt chờ gửi' };
	}
	ds.push(goiCham);
	if(!ghiHang(ds)){ return { ok:false, viSao:'bộ nhớ của trình duyệt đã đầy' }; }
	return { ok:true };
}

function veHangCho(){
	var ds = docHang();
	if(!ds.length){ hien('oHangCho',false); return; }
	hien('oHangCho',true);
	var h = '';
	for(var i=0;i<ds.length;i++){
		h += '<p class="trong">• ' + esc(ds[i].coSo || '') + (ds[i].nhiemVu ? ' · ' + esc(ds[i].nhiemVu) : '')
			+ ' — chờ gửi</p>';
	}
	el('dsHangCho').innerHTML = h;
}

/**
 * ĐẨY HÀNG ĐỢI ĐI. Gọi được nhiều lần, chạy một lần.
 *
 * 🔴 CHỈ BỎ MỘT LƯỢT KHỎI HÀNG KHI MÁY CHỦ TRẢ LỜI RÕ RÀNG. Mất mạng giữa chừng thì GIỮ LẠI —
 *    bỏ đi là mất công của người ta mà không ai biết. Còn máy chủ CHỐI (vé hết hạn, giữ quá
 *    12 tiếng, ở ngoài vùng cơ sở) thì phải bỏ, và phải NÓI RA: giữ lại một lượt không bao giờ
 *    gửi được là mỗi lần mở trang lại thử lại, mãi mãi, và ô "Chờ gửi" không bao giờ trống.
 */
function dayHang(){
	if(DANG_DAY) return Promise.resolve();
	var ds = docHang();
	if(!ds.length){ hien('oHangCho',false); return Promise.resolve(); }
	DANG_DAY = true;

	var mot = ds[0];
	/* Thẻ phiên có thể đã đổi từ lúc xếp hàng (hết hạn rồi đăng nhập lại). Vé thì buộc vào thẻ
	   CŨ, nên gửi thẻ mới là vé không khớp. Gửi đúng thẻ đã lưu cùng lượt ấy. */
	return goi('cham', mot, CHO_CO_ANH).then(function(j){
		var con = docHang();
		con.shift();
		ghiHang(con);
		if(j && j.ok){
			bao('baoCham','xanh','✔ Đã gửi nốt lượt chấm giữ trong máy: ' + esc(j.coSo) + ' '
				+ esc(j.gio) + ' (' + esc(j.ngay) + ').');
			napToi();
		} else {
			bao('baoCham','dong','Lượt chấm giữ trong máy KHÔNG ghi được, và đã bỏ khỏi hàng chờ: '
				+ esc((j && j.error) || 'máy chủ chối') + ' Nếu hôm nay thiếu giờ thì nhờ quản lý '
				+ 'chấm bù.');
		}
		veHangCho();
	}).catch(function(){
		/* Vẫn chưa có sóng — giữ nguyên hàng, không nói gì. Nói mỗi lần thử là mỗi hai phút một
		   dòng đỏ, và người ta thôi đọc mọi dòng đỏ. */
		veHangCho();
	}).then(function(){
		DANG_DAY = false;
	});
}

/* ================================================================ ĐỐI CHIẾU KHUÔN MẶT

   🔴 CHẠY SAU KHI GIỜ ĐÃ GHI XONG, VÀ KHÔNG AI PHẢI CHỜ NÓ.
      Tính dãy đặc trưng cần tải một model vài megabyte. Nếu việc ấy nằm trên đường đi của
      lượt chấm công thì mỗi lần chấm phải đợi model tải xong mới ghi được giờ — đổi một tiện
      ích lấy chính cái việc mà cả hệ thống sinh ra để làm. Nên: `cham` trả về ok, màn hình
      báo "đã ghi giờ vào", XONG; rồi cái này mới lặng lẽ chạy.

   ⚠️ HỎNG Ở BẤT KỲ ĐÂU CŨNG IM. Thiếu file, model tải dở, ảnh không thấy mặt, mạng chết —
      tất cả đều `return` không nói gì. Người dùng KHÔNG được thấy lỗi của một thứ họ không
      yêu cầu và không sửa được. Cái duy nhất họ cần thấy là dòng "đã ghi giờ vào" ở trên.

   ⚠️ Máy chủ đã tự gác: thiếu thư viện thì `CFG.mat.co` là false ngay từ lúc dựng trang. */
var MAT_TAI = null;      /* Promise nạp thư viện — chỉ nạp MỘT lần cho cả phiên */

function napThuVienMat(){
	if(MAT_TAI) return MAT_TAI;
	MAT_TAI = new Promise(function(xong, hong){
		var s = document.createElement('script');
		s.src = CFG.mat.js;
		s.onload = function(){ xong(); };
		s.onerror = function(){ hong(new Error('không tải được thư viện')); };
		document.head.appendChild(s);
	}).then(function(){
		if(!window.faceapi) throw new Error('thư viện nạp rồi mà không thấy faceapi');
		var m = CFG.mat.mau;
		/* Ba model, tải song song. Bản "tiny" cho bộ dò và bộ điểm mốc — nhẹ hơn nhiều bản
		   đầy đủ và đủ dùng cho ảnh selfie chính diện. Bộ nhận dạng thì không có bản tiny. */
		return Promise.all([
			faceapi.nets.tinyFaceDetector.loadFromUri(m),
			faceapi.nets.faceLandmark68TinyNet.loadFromUri(m),
			faceapi.nets.faceRecognitionNet.loadFromUri(m)
		]);
	});
	MAT_TAI.catch(function(){ /* nuốt, để lần sau còn thử lại được */ MAT_TAI = null; });
	return MAT_TAI;
}

function soiMat(anh, ngay, coSo){
	if(!CFG.mat || !CFG.mat.co || !anh) return;
	napThuVienMat().then(function(){
		return new Promise(function(xong, hong){
			var img = new Image();
			img.onload  = function(){ xong(img); };
			img.onerror = function(){ hong(new Error('ảnh hỏng')); };
			img.src = anh;
		});
	}).then(function(img){
		return faceapi
			.detectSingleFace(img, new faceapi.TinyFaceDetectorOptions({ inputSize: 320 }))
			.withFaceLandmarks(true)
			.withFaceDescriptor();
	}).then(function(kq){
		/* Không thấy mặt trong ảnh: KHÔNG gửi gì cả. Gửi một dãy rỗng lên là máy chủ hoặc lấy
		   nó làm mẫu, hoặc gắn cờ — cả hai đều sai, vì thứ thiếu là tấm ảnh chứ không phải
		   con người. Ảnh không thấy mặt thì quản lý mở ra xem là biết ngay. */
		if(!kq || !kq.descriptor) return;
		var v = [];
		for(var i = 0; i < kq.descriptor.length; i++){ v.push(kq.descriptor[i]); }
		return goi('mat', { token:token(), vector:v, ngay:ngay, coSo:coSo });
	}).catch(function(){ /* im — xem chú thích ở đầu khối */ });
}

/* --------------------------------------------------- vào thẳng khi đã đăng nhập bên quản trị
 *
 * 🔴 Anh Thắng 28/08/2026: *"đăng nhập bên quản trị chấm công, nhưng qua chấm công đăng nhập
 *    online lại bắt đăng nhập lại, tự vào chung luôn"*. Thẻ vốn dùng chung; chỉ là cookie của
 *    trang quản trị thì JavaScript ở đây không đọc được. Nên hỏi máy chủ hộ.
 *
 * ⚠️ HIỆN MÀN PIN TRƯỚC, ĐỪNG ĐỂ TRANG TRẮNG. Người không có phiên sẵn — tức gần như tất cả
 *    nhân viên đứng ở quầy — mà phải nhìn một trang trắng chờ mạng thì tệ hơn hẳn cái phải sửa.
 *
 * ⚠️ ĐANG GÕ PIN DỞ THÌ KHÔNG GIẬT MÀN HÌNH. Lời đáp về muộn mà nhảy màn giữa lúc người ta gõ
 *    là mất mấy chữ vừa gõ, và không ai hiểu vì sao.
 */
function thuPhienSan(){
	hien('mVao',true);
	bao('loiVao','vang','Đang tìm phiên đăng nhập sẵn có…');
	goi('phien',{}).then(function(j){
		if(!j || !j.ok || !j.token){
			bao('loiVao','',null);
			if(j && j.error && j.ma !== 'chua_co'){ bao('loiVao','dong', j.error); }
			el('oPin').focus();
			return;
		}
		if(el('oPin').value.trim() !== ''){ bao('loiVao','',null); return; }
		datToken(j.token);
		bao('loiVao','',null);
		moManChinh();
	}).catch(function(){ bao('loiVao','',null); el('oPin').focus(); });
}

/* ---------------------------------------------------------------- khởi động */
if(token()){ moManChinh(); }
else { thuPhienSan(); }
})();
</script>
</body>
</html>
