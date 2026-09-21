#!/usr/bin/env bash
# Đóng gói plugin "Tài Chính K&H" thành .zip cài được qua wp-admin.
#   ./dong-goi.sh [thư mục đích]     (mặc định: thư mục hiện tại)
set -euo pipefail

thu_muc="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
dich="$(cd "${1:-$PWD}" && pwd)"
ten="kh-tai-chinh"
zip_ra="$dich/$ten.zip"

goc="$thu_muc/wordpress/$ten"
[ -f "$goc/$ten.php" ] || { echo "Không thấy $goc/$ten.php" >&2; exit 1; }

# Cú pháp hỏng thì thà dừng ở đây còn hơn để WordPress trắng màn hình.
while IFS= read -r f; do
  php -l "$f" >/dev/null || { echo "Lỗi cú pháp: $f" >&2; exit 1; }
done < <(find "$goc" -name '*.php')

# Hai chỗ ghi phiên bản phải khớp, không thì bản cài lên không nâng cấp bảng.
v_header="$(sed -n 's/^ \* Version: *\([0-9.]*\).*/\1/p' "$goc/$ten.php" | head -1)"
v_const="$(sed -n "s/^define( 'KHTC_VERSION', '\([0-9.]*\)'.*/\1/p" "$goc/$ten.php" | head -1)"
if [ -z "$v_header" ] || [ "$v_header" != "$v_const" ]; then
  echo "Lệch phiên bản: header=$v_header KHTC_VERSION=$v_const" >&2
  exit 1
fi
echo "Phiên bản: $v_header"

rm -f "$zip_ra"
( cd "$thu_muc/wordpress" && zip -qr "$zip_ra" "$ten" -x '*.DS_Store' )
echo "Xong: $zip_ra"
unzip -l "$zip_ra" | tail -n +4 | head -n -2
