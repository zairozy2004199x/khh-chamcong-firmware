# Kiểm thử plugin Nền tảng K&H (`wordpress/khh-platform`)

Hai tầng, cố ý tách riêng vì tầng dưới đã từng che mất lỗi thật (vụ `?rest_route=` hai dấu `?`).

## 1. Chạy không cần WordPress — nhanh, chạy mỗi lần sửa

```bash
cd tools/test/khh-platform
for f in phptest*.php; do php "$f" | tail -1; done          # PHP: eval mã plugin với hàm WordPress giả
npm i playwright && for f in test-*.mjs; do node "$f" | tail -1; done   # trình duyệt: mở build/index.html
```

`build/` là khung giao diện độc lập (cùng mã với `wordpress/khh-platform/assets/`), `tepthu/` là
tệp mẫu cho phép thử đính kèm.

## 2. Chạy trên WordPress THẬT — chạy trước khi bàn giao

Thư mục `wp-that/`. Cần MariaDB và PHP; WordPress lấy từ gói Ubuntu (`apt-get download wordpress`).

```bash
mysql -u root -e "CREATE DATABASE wp_khmatrix; CREATE DATABASE chamcong_cu; ..."   # xem wpinstall.php
php wp-that/wpinstall.php                 # cài WP vào /tmp/wpsite, bật khh-platform
php wp-that/seed-ns.php                   # 10 hồ sơ nhân sự đúng mã như site thật
mysql -u root --default-character-set=utf8mb4 < wp-that/cu.sql          # "phần mềm cũ" dạng bảng MySQL riêng
mysql -u root --default-character-set=utf8mb4 < wp-that/vhcc-seed.sql   # bảng của plugin Chấm Công (K&H)
PHP_CLI_SERVER_WORKERS=6 php -S 127.0.0.1:8899 -t /tmp/wpsite &

php wp-that/phptest-vhcc.php   # nối tự động với vhcp-cham-cong: luật công, mã song song, cơ sở
node wp-that/e2e-vhcc.mjs      # không khai gì → dữ liệu hiện, cơ sở tự chia
node wp-that/e2e-that.mjs      # khai bảng tay (site không có plugin Chấm Công): 3 bước rồi hiện
```

⚠️ Luôn nạp SQL với `--default-character-set=utf8mb4` — CLI mysql mặc định latin1 làm tên có dấu
thành rác, và phép thử tên sẽ "đạt" trên dữ liệu sai.
