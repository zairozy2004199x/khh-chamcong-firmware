# ⚠️ THƯ MỤC NÀY LÀ BẢN CHỤP CŨ — ĐỪNG ĐÓNG GÓI ĐEM CÀI

Mã `vhcp-chi-phi-hn` **không** được nuôi ở kho này. Kho này nuôi đúng một plugin:
`vhcp-cham-cong`. Mọi thư mục khác chỉ là bản chụp, phần lớn từ lần gói chung
`1c781a5` (Chấm Công 4.16.0), và **đứng im từ đó** — trong khi bản chạy thật
ngoài host đã đi xa hơn nhiều chục phiên bản.

Số bản trong thư mục này: **1.101.0**. Đừng tin nó là bản mới nhất.

## Chuyện đã xảy ra, 19/09/2026

Em vá đường nối danh tính (vé `ccve`) lên bốn thư mục `vhcp-chi-phi*` rồi đóng
gói gửi anh Thắng như một bản cài. Anh mở lên thì WordPress báo:

    Hiện tại 1.212.0    ·    Đã tải lên 1.188.0

Bấm "Thay thế" là **lùi plugin về mã của mấy tháng trước** — mất trắng mọi thứ
làm giữa chừng. Anh Thắng nhìn ra kịp, em thì không.

Không có gì trong kho nói ra điều đó: số bản trong thư mục vẫn "hợp lệ" (header
khớp hằng số) nên `kiem-phien-ban.py` xanh; `tools/build-plugin-zip.sh` vẫn gói
được, không hỏi một câu; và bản gói ra trông y như một bản cài thật.

## Muốn sửa plugin này thì làm thế nào

1. Xin **tệp .zip đang chạy thật** (hoặc kho nguồn thật) của đúng bản ấy.
2. Vá lên **nguồn thật**, không vá lên đây.
3. Nâng số bản **từ số của nguồn thật** trở lên — không phải từ số trong kho này.
4. Gửi bản gói ra, và nói rõ số bản trước / sau để người cài đối chiếu.

Mã trong thư mục này chỉ dùng để **tra cứu và dựng bản vá mẫu**.
