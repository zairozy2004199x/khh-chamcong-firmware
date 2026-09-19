# ⚠️ THƯ MỤC NÀY LÀ BẢN CHỤP CŨ — ĐỪNG ĐÓNG GÓI ĐEM CÀI

Mã `vhcp-chi-phi-hn` **không** được nuôi ở kho này. Nó nằm đây từ lần gói chung
`1c781a5` (Chấm Công 4.16.0) và **đứng im từ đó**, trong khi bản chạy thật
ngoài host đã đi xa hơn nhiều chục phiên bản.

## Chuyện đã xảy ra, 19/09/2026

Em vá đường nối danh tính (vé `ccve`) lên bốn thư mục này rồi đóng gói gửi anh
Thắng. Anh mở lên cài thì WordPress báo:

    Hiện tại 1.212.0    ·    Đã tải lên 1.188.0

Bấm "Thay thế" là **lùi plugin về mã của mấy tháng trước** — mất trắng mọi thứ
làm giữa chừng. Không có gì trong kho này nói cho biết điều đó; số phiên bản
trong thư mục thì vẫn "hợp lệ" (header khớp hằng số), nên phép thử phiên bản
cũng xanh.

## Muốn sửa mấy plugin này thì làm thế nào

1. Xin **tệp .zip đang chạy thật** (hoặc kho nguồn thật) của đúng bản ấy.
2. Vá lên **nguồn thật**, không vá lên đây.
3. Nâng số bản **từ số của nguồn thật** trở lên — không phải từ số trong kho này.

Mã trong thư mục này giữ lại làm **bản tham chiếu**: nó cho thấy bản vá trông
như thế nào. Xem `includes/class-vhcp-app.php` (`ve_cham_cong()`) và
`assets/js/gas-shim.js` (khối `CFG.ssoToken`).
