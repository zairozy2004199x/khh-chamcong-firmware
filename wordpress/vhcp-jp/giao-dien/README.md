# GIAO DIỆN JP — chép NGUYÊN VĂN từ bản Apps Script

13 tệp, 13.712 dòng, lấy từ `goc/jp-capsule-v2/`. **Không sửa một dòng nào.**

Chúng chạy được trên WordPress vì `assets/js/gas-shim.js` dựng lại đúng API
`google.script.run` mà chúng gọi. Cả bộ giao diện nói chuyện với máy chủ qua đúng
MỘT chỗ — hàm `srv(fn, ...)` trong `Js01_Core.html` — nên chỉ cần lớp shim ấy.

## Vì sao chép nguyên văn, không "dọn lại cho gọn"

Sửa ở đây là mất khả năng đối chiếu với bản đang chạy thật. Chừng nào hai bản còn
chạy song song để so số, giao diện phải giống hệt — lệch một nút là lệch một thao
tác, và không ai biết số khác nhau vì máy chủ tính khác hay vì người bấm khác.

Khi nào bản trên hosting thay hẳn bản Apps Script thì mới sửa, và lúc đó sửa có chủ ý.

## Cú pháp khuôn

Chỉ có một thứ: `<?!= include('Ten'); ?>` — ghép nguyên tệp `Ten.html` vào chỗ đó.
`VHJP_Trang::ghep()` làm đúng việc ấy. Không còn cú pháp Apps Script nào khác
(đã quét: 13 lời gọi `include`, không có `<?= ?>` hay `<? ?>` nào).
