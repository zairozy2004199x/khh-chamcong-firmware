#!/usr/bin/env bash
# Đóng gói plugin thành file .zip cài được qua wp-admin (Plugin → Cài mới → Tải plugin lên).
#
#   bash tools/build-plugin-zip.sh          -> dist/vhcp-chi-phi.zip
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SRC="$ROOT/wordpress/vhcp-chi-phi"
OUT="$ROOT/dist"
ZIP="$OUT/vhcp-chi-phi.zip"

[ -f "$SRC/vhcp-chi-phi.php" ] || { echo "Không thấy $SRC/vhcp-chi-phi.php"; exit 1; }

# Kiểm cú pháp PHP trước khi đóng gói — thà báo lỗi ở đây hơn là trên hosting.
if command -v php >/dev/null 2>&1; then
  while IFS= read -r f; do php -l "$f" >/dev/null || { echo "Lỗi cú pháp: $f"; exit 1; }; done \
    < <(find "$SRC" -name '*.php')
fi

mkdir -p "$OUT"
rm -f "$ZIP"
( cd "$(dirname "$SRC")" && zip -qr "$ZIP" "$(basename "$SRC")" \
    -x '*/.git/*' -x '*/.DS_Store' -x '*/node_modules/*' )

echo "Đã tạo: $ZIP"
if command -v du >/dev/null 2>&1; then du -h "$ZIP"; fi
echo
echo "Cài lên hosting: wp-admin → Plugin → Cài mới → Tải plugin lên → chọn file này → Cài đặt → Kích hoạt."
