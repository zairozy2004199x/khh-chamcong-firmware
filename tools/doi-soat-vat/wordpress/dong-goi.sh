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
