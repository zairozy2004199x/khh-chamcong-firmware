#!/usr/bin/env bash
# ══════════════════════════════════════════════════════════════════════════════════════════════
# XEM TRƯỚC GIAO DIỆN TRẠM — và đây cũng chính là giao diện app Android.
#
# 🔴 APP ANDROID KHÔNG CÓ GIAO DIỆN RIÊNG. Nó mở đúng trang này trong một WebView (xem
#    `android_cham_cong/README.md`). Nên chụp trang này LÀ chụp app, trừ hai màn phụ do Kotlin
#    vẽ (nhập địa chỉ máy chủ, báo mất mạng) — chỉ máy Android thật mới dựng được.
#
# 🔴 VÌ SAO PHẢI DỰNG MÁY CHỦ, không dựng ra một tệp HTML rồi chụp như `xem-man.sh`.
#    Màn nhân sự dựng xong là xong: máy chủ in ra cả cái bảng. Trạm thì in ra một cái vỏ rỗng,
#    rồi JavaScript gọi `?viec=toi`, `?viec=ung` mới có nội dung — mở `file://` thì mọi lời gọi
#    ấy trượt, và ảnh chụp được là một trang trắng có mấy cái tab. Thẻ phiên lại nằm trong
#    `localStorage` chứ không phải cookie, nên cũng không "đăng nhập sẵn" rồi dựng HTML tĩnh
#    được. Phải đi đúng đường của trình duyệt thật: gõ PIN, nhận thẻ.
#
# Được thêm một thứ đáng giá hơn cả ảnh: mọi lời gọi đều qua ĐÚNG mã của cổng
# (`VHCC_Tram::cong()`), nên cửa nào hỏng thì hỏng ở đây chứ không hỏng trên máy nhân viên.
#
# ⚠️ CHỈ CHẠY Ở MÁY MÌNH. Bệ đỡ WordPress giả, không có bảo mật thật, dữ liệu là giả.
#
# Chạy: bash tools/xem/xem-tram.sh [thư mục xuất]
# ══════════════════════════════════════════════════════════════════════════════════════════════
set -u
cd "$(dirname "$0")/../.." || exit 2

RA="${1:-${TMPDIR:-/tmp}/vhcc-xem-tram}"
CONG="${VHCC_XEM_CONG:-8899}"
SO="${TMPDIR:-/tmp}/vhcc-xem-tram.sqlite"
mkdir -p "$RA" || exit 2
rm -rf "${RA:?}"/*.png
rm -f "$SO"

echo "── Dựng máy chủ xem trước (cổng $CONG) ────────────────────────"
# 🔴 NHIỀU LUỒNG. Máy chủ sẵn có của PHP mặc định xử ĐÚNG MỘT lượt một lúc — và trang trạm thì
# vừa hỏi tin chat mỗi 6 giây, vừa tải ảnh đính kèm, vừa hỏi chuông. Lượt này chờ lượt kia, và
# thẻ <img> bỏ cuộc: ảnh hiện ra một ô vỡ. Đã mất một lượt chụp để tìm ra, và suýt đi sửa nhầm
# phần phục vụ tệp — vốn chạy đúng (curl thẳng vào nó trả 200 kèm image/png).
PHP_CLI_SERVER_WORKERS=4 VHCC_STUB_DB="$SO" VHCC_STUB_HOME="http://127.0.0.1:$CONG" \
  php -S "127.0.0.1:$CONG" tools/xem/may-chu-tram.php >"${TMPDIR:-/tmp}/vhcc-xem-tram.log" 2>&1 &
PID=$!
# Dừng máy chủ dù thoát kiểu gì — kể cả khi Chromium chết giữa chừng. Bỏ dòng này là để lại
# một tiến trình PHP giữ cổng, và lần chạy sau báo "Address already in use".
trap 'kill "$PID" 2>/dev/null' EXIT

for _ in $(seq 1 30); do
  curl -sS -o /dev/null "http://127.0.0.1:$CONG/" 2>/dev/null && break
  sleep 1
done
if ! curl -sS -o /dev/null "http://127.0.0.1:$CONG/" 2>/dev/null; then
  echo "  ✗ máy chủ không lên — xem ${TMPDIR:-/tmp}/vhcc-xem-tram.log"; exit 1
fi
echo "  ✓ đã lên"

node tools/xem/chup-tram.js "http://127.0.0.1:$CONG/" "$RA" || exit 1
