============================================================
 TRANG NẠP FIRMWARE ESP32 QUA WEB (Web Serial / esp-web-tools)
============================================================

MỤC ĐÍCH
  Cắm bo ESP32 bằng cáp USB -> mở trang web (Chrome/Edge máy tính) -> bấm
  "⚡ Nạp firmware". Không cần Arduino IDE. Hợp để nạp MÁY MỚI ở xưởng.

CẤU TRÚC THƯ MỤC (upload nguyên cụm này lên web, CÙNG một chỗ)
  nap-esp32.html
  firmware/
    ghe/        manifest.json  +  firmware-ghe-merged.bin        (Ghế Massage QR)
    cham-cong/  manifest.json  +  firmware-cham-cong-merged.bin  (Máy chấm công)
    tho-nap/    manifest.json  +  firmware-tho-nap-merged.bin    (Thợ nạp OTA)
    may-tram/   manifest.json  +  firmware-may-tram-merged.bin   (Máy thu tiền / chốt ca)
    posh-qr/    manifest.json  +  firmware-posh-qr-merged.bin    (Hộp QR POSH đời trước)

  Trang cho chọn 5 loại máy; mỗi loại đọc manifest.json trong thư mục con của
  nó. Thư mục nào CHƯA có file *-merged.bin thì bấm nạp sẽ báo không tải được
  .bin — chỉ cần bỏ file đúng tên vào là chạy.

⚠️ ĐIỀU KIỆN BẮT BUỘC
  - Trang PHẢI mở qua HTTPS (https://...). Web Serial không chạy trên http
    thường (trừ http://localhost khi test).
  - Chỉ Chrome / Edge trên MÁY TÍNH. Safari, Firefox, điện thoại: không nạp.
  - Cáp USB phải là cáp DỮ LIỆU. Cần driver CP2102 hoặc CH340 tuỳ chip.

============================================================
 CÁCH SINH FILE  *-merged.bin  (người quản trị làm 1 lần mỗi bản)
============================================================
  Trang này nạp 1 file .bin GỘP ở offset 0 (đơn giản, chắc ăn).

CÁCH A — từ Arduino IDE
  1) Sketch > Export Compiled Binary (hoặc Ctrl/Cmd+Alt+S).
     -> sinh ra 4 file trong thư mục build:
        <ten>.ino.bootloader.bin
        <ten>.ino.partitions.bin
        boot_app0.bin   (trong thư mục core esp32/tools/partitions)
        <ten>.ino.bin   (chính là app)
  2) Gộp bằng esptool (cài: pip install esptool):
     esptool.py --chip esp32 merge_bin -o firmware-ghe-merged.bin \
       --flash_mode dio --flash_freq 40m --flash_size 4MB \
       0x1000  <ten>.ino.bootloader.bin \
       0x8000  <ten>.ino.partitions.bin \
       0xe000  boot_app0.bin \
       0x10000 <ten>.ino.bin
  3) Chép firmware-ghe-merged.bin vào firmware/ghe/ (cạnh manifest.json).

CÁCH B — từ arduino-cli
  arduino-cli compile --fqbn esp32:esp32:esp32 --output-dir build esp32_ghe_massage
  Rồi gộp 4 file như CÁCH A bước 2.

LƯU Ý OFFSET
  - Bo 4MB thường: bootloader 0x1000, partitions 0x8000, boot_app0 0xe000,
    app 0x10000. Nếu partition scheme khác thì đổi offset cho khớp.
  - Muốn dùng 4 phần riêng (không gộp) thì sửa manifest.json thành 4 "parts"
    đúng offset trên, trỏ tới 4 file .bin — cũng chạy.

============================================================
 CẬP NHẬT PHIÊN BẢN
============================================================
  - Đổi "version" trong manifest.json cho khớp bản mới (chỉ để hiển thị).
  - Ghi đè file *-merged.bin mới lên chỗ cũ. Trang tự nạp bản mới nhất.

⚠️ BẢO MẬT (repo công khai)
  - .bin để trên SERVER của cửa hàng, KHÔNG commit vào repo công khai.
  - secrets.h nằm trong .bin đã compile (SSID/URL/KEY). Ai tải được .bin là
    đọc được chuỗi trong đó -> chỉ host .bin ở nơi tin cậy / có đăng nhập nếu cần.
    (Mã ghế không nằm cứng: ghế khai MAC, web gán số — 1 .bin cho mọi ghế.)
