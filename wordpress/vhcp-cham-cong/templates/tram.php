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
input,select{width:100%;padding:12px 13px;font-size:16px;border-radius:var(--bo-o);
	border:1px solid var(--vien-dam);background:var(--nen);color:var(--chu);font-family:inherit}
input:focus,select:focus{outline:none;border-color:var(--nhan);
	box-shadow:0 0 0 3px rgba(56,189,248,.22)}
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
.tab-nut.dang{color:var(--nhan)}
.tab-o{animation:hienTab .18s ease-out}
@keyframes hienTab{from{opacity:0;transform:translateY(4px)}to{opacity:1;transform:none}}
/* ══════════════════════════════════════════════════════════════════════════════════════════
 * LƯỚI ỨNG DỤNG
 *
 * Hai cột, không phải ba. Ba cột ở bề rộng 520px là mỗi ô còn ~150px: tên "Nộp báo cáo POSH"
 * xuống ba dòng, và vùng chạm tụt xuống dưới ngưỡng ngón cái. Lưới ba cột của mấy app lớn
 * chạy được vì nhãn của họ một hai chữ.
 * ══════════════════════════════════════════════════════════════════════════════════════════ */
.luoi-ung{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:var(--d3)}
.o-ung{display:block;text-decoration:none;color:var(--chu);background:var(--nen-2);
	border:1px solid var(--vien);border-radius:var(--bo-o);padding:var(--d3);
	/* minmax(0,1fr) ở trên + min-width:0 ở đây: thiếu một trong hai là tên dài đẩy toang cột. */
	min-width:0}
.o-ung:active{background:var(--vien)}
/* Ô CHƯA ĐƯỢC CẤP. Mờ đủ để đọc ra là "khác", KHÔNG mờ tới mức không đọc nổi: cả điểm của
   việc bày nó ra là để người ta biết thứ ấy tồn tại mà đi xin.
   ⚠️ `cursor:default` + không có :active — ô này không phải thẻ <a> nên vốn đã không bấm được;
      mấy dòng này chỉ để ngón tay chạm vào KHÔNG thấy phản hồi, tức là nói "đây không phải nút"
      trước cả khi người ta đọc chữ. */
.o-ung.o-khoa{opacity:.55;cursor:default;background:var(--nen-2);border-style:dashed}
.o-ung.o-khoa .o-icon{filter:grayscale(1)}
.o-xin{display:block;font-size:11px;color:var(--chu-mo);margin-top:8px;line-height:1.4}
.o-ung b{display:block;font-size:14px;margin:9px 0 2px;color:var(--chu-dam)}
.o-mo{display:block;font-size:11.5px;color:var(--chu-mo);line-height:1.4}
.o-nhac{display:block;font-size:11px;color:var(--vang-dam);margin-top:6px;line-height:1.35}
.o-icon{display:flex;align-items:center;justify-content:center;width:42px;height:42px;
	border-radius:12px;font-size:21px}
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
.khung{position:relative}
.dem{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;
	pointer-events:none;border-radius:var(--bo-the)}
.dem span{font-size:96px;font-weight:800;color:#fff;line-height:1;
	text-shadow:0 0 22px rgba(0,0,0,.85),0 3px 10px rgba(0,0,0,.9);
	font-variant-numeric:tabular-nums}
.an{display:none!important}
.mn{position:fixed;inset:0;background:rgba(2,6,23,.94);z-index:9;overflow:auto;
	padding:14px 14px calc(20px + env(safe-area-inset-bottom))}
.mn .bao{padding-top:8px}
.mmau{width:96px;border-radius:var(--bo-nho);border:1px solid var(--vien-dam);float:right;margin:0 0 var(--d2) 10px}
a{color:var(--nhan)}
.ct{text-align:center;color:var(--chu-mo);font-size:11.5px;margin:var(--d4) 0 0}
</style>
</head>
<body>

<!-- ============ MÀN ĐĂNG NHẬP ============ -->
<div id="mVao" class="bao">
	<h1>Chấm công</h1>
	<p class="mo">Gõ mã PIN của anh/chị để vào.</p>
	<div class="the">
		<label for="oPin">Mã PIN</label>
		<input id="oPin" type="tel" inputmode="numeric" autocomplete="off" maxlength="8"
			placeholder="••••••" enterkeyhint="go">
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


	<!-- ============ PHIẾU LƯƠNG CỦA TÔI ============
	     🔴 KHÔNG DÙNG CHUNG BỘ LẬT THÁNG với bảng công ngay trên. Bảng công có MỌI tháng, còn
	        phiếu lương chỉ có tháng kế toán ĐÃ CÔNG BỐ — nối chung một bộ lật thì bảy tháng
	        trong mười hai lật tới chỗ trống, và chỗ trống ấy đọc y như "tháng đó anh không có
	        lương". Ô xổ chỉ liệt kê tháng thật sự mở được, nên không có cái lật nào hụt.
	     ⚠️ Ô xổ trống = chưa công bố tháng nào; khối tự ẩn đi thay vì bày một ô rỗng. -->
	<div class="the an" id="oKhoiPhieu">
		<label style="margin:0 0 8px">Phiếu lương của tôi</label>
		<select id="plThang"></select>
		<div id="bangPhieu" style="margin-top:10px"><p class="trong">—</p></div>
	</div>

	</div><!-- /tCong -->

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
	<div class="the">
		<label style="margin:0 0 8px">Xin phép đi trễ</label>
		<p class="ct" style="text-align:left;margin:0 0 10px">Cơ sở nào cũng nộp được. Đơn được duyệt
			thì ô vàng "chấm thiếu giờ" của ngày ấy bỏ đi — <b>số giờ trong ô không đổi</b>.</p>
		<label for="xtNgay">Ngày xin trễ</label>
		<input id="xtNgay" type="date">
		<label for="xtPhut">Trễ khoảng bao nhiêu phút</label>
		<input id="xtPhut" type="number" inputmode="numeric" min="1" step="1" placeholder="VD: 20">
		<label for="xtLyDo">Lý do</label>
		<input id="xtLyDo" type="text" maxlength="250" placeholder="Cửa hàng trưởng duyệt theo lý do">
		<div id="loiTre"></div>
		<p></p>
		<button id="btGuiTre" class="chinh to">GỬI ĐƠN ĐI TRỄ</button>
	</div>

	<div class="the">
		<label style="margin:0 0 8px">Xin nghỉ</label>
		<div id="oQuyPhep"></div>
		<p class="ct" style="text-align:left;margin:0 0 10px">Cửa hàng trưởng duyệt. <b>Đơn được
			duyệt không tự cộng hay trừ công</b> — nó chỉ trả lời "hôm ấy vắng có phép hay không".</p>
		<label for="xnTu">Nghỉ từ ngày</label>
		<input id="xnTu" type="date">
		<label for="xnDen">Đến hết ngày (để trống nếu nghỉ một ngày)</label>
		<input id="xnDen" type="date">
		<label for="xnLoai">Loại nghỉ</label>
		<select id="xnLoai"></select>
		<label for="xnLyDo">Lý do</label>
		<input id="xnLyDo" type="text" maxlength="250" placeholder="Người duyệt quyết theo lý do">
		<div id="loiNghi"></div>
		<p></p>
		<button id="btGuiNghi" class="chinh to">GỬI ĐƠN XIN NGHỈ</button>
	</div>

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
	<button class="tab-nut" data-tab="tUng"><span>🧩</span>Ứng dụng</button>
	<button class="tab-nut" data-tab="tToi"><span>👤</span>Tôi</button>
</nav>

<!-- ============ MÀN CHỤP ẢNH ============ -->
<div id="mChup" class="mn an"><div class="bao">
	<h1>Chụp ảnh</h1>
	<p class="mo">Đưa mặt vào khung, đủ sáng, rồi bấm <b>Chụp ngay</b>. Ảnh được đóng dấu giờ máy chủ.</p>
	<div class="the" id="oMau"></div>
	<div class="the" style="padding:10px">
		<div class="khung">
			<video id="vid" playsinline autoplay muted></video>
			<canvas id="xem" class="xem an"></canvas>
			<div id="oDem" class="dem an"><span id="soDem">5</span></div>
		</div>
		<div id="loiChup"></div>
		<p></p>
		<div class="hang" id="nhomChup">
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
			+ ' <span style="opacity:.75">(±' + dai(GPS.acc) + ')</span>' + them + '</div>'
			+ veBanDo(GPS.lat, GPS.lng, GPS.acc)
			/* Vẫn giữ link ra Google Maps: bản đồ ở đây đủ để thấy "mình đang ở đâu", còn khi
			   cần chỉ đường hay xem ảnh phố thì mở ứng dụng bản đồ thật vẫn hơn. */
			+ lk;
		nghenBanDo();   /* phải gọi SAU khi đã chèn HTML — trước đó chưa có thẻ nào để nghe */
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

function vaoHe(){
	var pin = el('oPin').value.trim();
	bao('loiVao','',null);
	var b = el('btVao');
	b.disabled = true; b.textContent = 'Đang vào…';
	goi('vao',{pin:pin}).then(function(j){
		if(!j || !j.ok){ bao('loiVao','dong', (j&&j.error)||'Không vào được.'); return; }
		datToken(j.token);
		el('oPin').value='';
		moManChinh();
	}).catch(function(e){ bao('loiVao','dong', e.message||'Lỗi mạng.'); })
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
	hien('mVao',true);
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
		var h = '<div class="luoi-ung">';
		for(var i=0;i<j.ds.length;i++){
			var x = j.ds[i];
			/* 🔴 Ô KHOÁ DỰNG BẰNG <div>, KHÔNG PHẢI <a> KÈM CLASS. Máy chủ đã bỏ hẳn `url` của
			   ô khoá, nên ở đây không có gì để bấm vào — kể cả một dòng CSS sửa nhầm cũng
			   không biến nó thành bấm được. Dùng <a> rồi chặn bằng JS thì chỉ cần một lượt
			   JS hỏng là năm cái ô khoá thành năm cái link sống. */
			var mo = !!x.mo_duoc && !!x.url;
			var ruot = '<span class="o-icon o-' + thoat(x.mau||'xanh') + '">' + thoat(x.icon||'') + '</span>'
			         + '<b>' + thoat(x.ten||'') + '</b>'
			         + '<span class="o-mo">' + thoat(x.mo||'') + '</span>';

			if(mo){
				h += '<a class="o-ung" href="' + thoat(x.url) + '">' + ruot
				  +  (x.ghi_chu ? '<span class="o-nhac">' + thoat(x.ghi_chu) + '</span>' : '')
				  +  '</a>';
			} else {
				h += '<div class="o-ung o-khoa">' + ruot
				  +  '<span class="o-xin">🔒 Chưa được cấp'
				  +  (x.xin ? '<br>' + thoat(x.xin) : '') + '</span>'
				  +  '</div>';
			}
		}
		el('oUng').innerHTML = h + '</div>' + NHAC_UNG;
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
	['tChamCong','tCong','tUng','tToi'].forEach(function(x){
		var o = el(x); if(o){ o.classList.toggle('an', x !== ten); }
	});
	var ds = document.querySelectorAll('.tab-nut');
	for(var i=0;i<ds.length;i++){ ds[i].classList.toggle('dang', ds[i].getAttribute('data-tab') === ten); }
	if(ten === 'tCong'){ napPhieu(); }
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
 * PHIẾU LƯƠNG CỦA TÔI — cửa, không phải nghiệp vụ. Xem class-vhcc-phieu-luong.php.
 * ═══════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 KHỐI NÀY TỰ ẨN KHI CHƯA CÓ THÁNG NÀO CÔNG BỐ. Bày một ô xổ rỗng kèm chữ "chưa có" thì mỗi
 *    lần mở tab người ta lại đọc lại một câu không giúp được gì, và ô rỗng trên màn lương thì
 *    ai cũng bấm thử vài lần trước khi tin. Chưa có gì để xem thì không bày gì cả.
 *
 * ⚠️ MỌI CON SỐ Ở ĐÂY LÀ SỐ CỦA HỆ, KHÔNG PHẢI SỐ CHUYỂN KHOẢN. BHXH và lương giờ thêm kế toán
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

function napPhieu(){
	return goi('phieuluong', { token: token() }).then(function(j){
		if(!j || !j.ok){ return; }
		PL_KHOAN = j.khoan || null;
		var ds = j.dsThang || [];
		el('oKhoiPhieu').classList.toggle('an', !ds.length);
		if(!ds.length){ return; }
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
		var h = '<table><thead><tr><th>Việc</th><th>Giờ</th><th>Đơn giá</th><th>Thành tiền</th>'
			+ '</tr></thead><tbody>';
		for(var i=0;i<j.dong.length;i++){
			var d = j.dong[i];
			/* Dòng ăn lương tháng không có giờ và không có đơn giá — nó ra tiền bằng
			   lương cơ bản × công thực / công yêu cầu. Bày hai ô trống thì đúng hơn là bày
			   một con số mượn ở đâu đó. */
			var mo = (d.cheDo === 'thang')
				? (tienVN(d.luongCb) + '/tháng × ' + esc(d.congThuc) + '/' + esc(d.congYc || '—') + ' công')
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
				+ '<div class="hang" style="justify-content:space-between;font-size:17px">'
				+ '<span><b>Tổng</b></span><b>' + tienVN(j.tong) + '</b></div></div>';
		}

		if(j.thieuGio){
			h += '<div class="vang" style="margin-top:10px">Tháng này có <b>' + esc(j.thieuGio)
				+ '</b> lượt thiếu một đầu giờ (quên chấm vào hoặc chấm ra) nên KHÔNG tính phút nào. '
				+ 'Thấy sai thì báo ở tab <b>Tôi</b> → Báo lượt chấm sai.</div>';
		}

		/* ⚠️ Xem khối chú thích đầu hàm: câu này nằm dưới con số tổng, không nằm cuối trang. */
		h += '<p class="ct" style="text-align:left;margin:10px 0 0">Đây là số <b>hệ thống tính '
			+ 'được</b> từ giờ đã chấm và các khoản kế toán đã nhập. <b>BHXH</b> và <b>lương giờ '
			+ 'thêm</b> kế toán tính ngoài hệ, nên số chuyển khoản có thể khác. Lệch thì hỏi cửa '
			+ 'hàng trưởng — đừng tự cộng lại.</p>';
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
	   Ô BẢN ĐỒ — ngay TRÊN dòng toạ độ, cùng góc phải.

	   🔴 BỌC `try`. Dù đã đặt `crossOrigin` đúng cách, một bản trình duyệt lạ vẫn có thể làm
	      canvas nhiễm — và canvas nhiễm thì `toDataURL()` ở dưới ném lỗi, tức MẤT CẢ TẤM ẢNH.
	      Thà mất ô bản đồ còn hơn mất lượt chấm công. Đây không phải `try` cho có: nó là chốt
	      giữa "thiếu một ô trang trí" và "không ghi được công".
	   ══════════════════════════════════════════════════════════════════════════════════════════ */
	if(BANDO && BANDO.im && BANDO.im.complete && BANDO.im.naturalWidth > 0){
		try {
			var oB = Math.max(72, Math.round(W / 4));          /* cạnh ô vuông */
			var oX = W - oB - 10;
			var oY = H - coVT - 14 - oB - 8;                   /* nằm trên dòng toạ độ, chừa 8px */

			/* ⚠️ Ảnh quá thấp (máy ảnh lạ, tỉ lệ dẹt) thì ô tràn lên khỏi mép trên — bỏ ô, giữ
			   dòng toạ độ. Một ô bản đồ cụt đầu còn khó đọc hơn không có. */
			if(oY < 8){ throw new Error('anh qua thap'); }

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
	var cs = (TOI && TOI.dsCoSo) || [];
	if(cs.length > 1){
		var h = '<label for="oCS">Cơ sở đang có mặt</label><select id="oCS">';
		for(var i=0;i<cs.length;i++){
			h += '<option value="'+esc(cs[i])+'"'
			   + (cs[i]===TOI.coSoMacDinh?' selected':'') + '>'+esc(cs[i])+'</option>';
		}
		el('oChonCS').innerHTML = h + '</select><p></p>';
	} else {
		el('oChonCS').innerHTML = '<label>Cơ sở</label><div class="vang" style="margin:0">'
			+ esc(cs[0] || (TOI && TOI.coSoMacDinh) || '—') + '</div><p></p>';
	}

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
