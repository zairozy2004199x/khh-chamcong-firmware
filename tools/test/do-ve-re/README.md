# Kiểm thử plugin Dò vé rẻ (`wordpress/do-ve-re`)

`bash tools/test/do-ve-re/run.sh` — dựng máy chủ giả `mockdvr.php` (chạy đúng mã PHP của plugin,
lưu đơn vào `dvr-orders.json`) trên cổng 8098, rồi `test-dvr.mjs` mở bằng Chromium thật:
khách xem, quản trị đặt/sửa/xoá đơn, phân quyền, giao diện hẹp/rộng.
