#!/usr/bin/env bash
#
# Đóng gói plugin WordPress "Đối soát VAT".
#
# Giao diện công cụ nằm ở ../web và dùng chung cho cả bản chạy web tĩnh lẫn bản
# plugin, nên nó được chép vào lúc đóng gói thay vì giữ hai bản trong repo.
#
# Dùng: ./dong-goi.sh [thư mục đích]     (mặc định: thư mục hiện tại)

set -euo pipefail

thu_muc_script="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
goc="$(dirname "$thu_muc_script")"
dich="$(cd "${1:-$PWD}" && pwd)"

ten_plugin="doi-soat-vat"
zip_ra="$dich/$ten_plugin.zip"

if [[ ! -f "$goc/web/index.html" ]]; then
  echo "Không thấy $goc/web/index.html — chạy script từ trong repo." >&2
  exit 1
fi

# Ba chỗ ghi phiên bản phải khớp nhau, nếu không thì trình duyệt sẽ giữ lại giao
# diện cũ trong bộ đệm và người dùng cập nhật xong vẫn thấy như chưa sửa gì.
ver_header="$(sed -n 's/^ \* Version: *\([0-9.]*\).*/\1/p' "$thu_muc_script/$ten_plugin/doi-soat-vat.php" | head -1)"
ver_const="$(sed -n "s/^const DSVAT_VERSION = '\([0-9.]*\)';.*/\1/p" "$thu_muc_script/$ten_plugin/doi-soat-vat.php" | head -1)"
ver_html="$(sed -n 's/.*js\/app\.js?v=\([0-9.]*\)".*/\1/p' "$goc/web/index.html" | head -1)"

if [[ -z "$ver_header" || "$ver_header" != "$ver_const" || "$ver_header" != "$ver_html" ]]; then
  echo "Lệch phiên bản — sửa cho khớp rồi đóng gói lại:" >&2
  echo "  Version ở đầu doi-soat-vat.php : ${ver_header:-(không đọc được)}" >&2
  echo "  DSVAT_VERSION                  : ${ver_const:-(không đọc được)}" >&2
  echo "  ?v= trong web/index.html       : ${ver_html:-(không đọc được)}" >&2
  exit 1
fi
echo "Phiên bản: $ver_header"

tam="$(mktemp -d)"
trap 'rm -rf "$tam"' EXIT

echo "Đang dựng plugin..."
mkdir -p "$tam/$ten_plugin"
cp "$thu_muc_script/$ten_plugin/doi-soat-vat.php" "$tam/$ten_plugin/"
cp "$thu_muc_script/$ten_plugin/readme.txt" "$tam/$ten_plugin/"
cp -R "$goc/web" "$tam/$ten_plugin/web"

# WordPress không cần index.php trong thư mục plugin, nhưng thêm vào thì thư mục
# không bị liệt kê nếu máy chủ bật duyệt thư mục.
for d in "$tam/$ten_plugin" "$tam/$ten_plugin/web" "$tam/$ten_plugin/web/js" "$tam/$ten_plugin/web/vendor"; do
  printf '<?php\n// Im lặng là vàng.\n' > "$d/index.php"
done

find "$tam" -name '.DS_Store' -delete

rm -f "$zip_ra"
( cd "$tam" && zip -qr "$zip_ra" "$ten_plugin" )

echo "Xong: $zip_ra"
unzip -l "$zip_ra" | tail -n +4 | head -n -2
