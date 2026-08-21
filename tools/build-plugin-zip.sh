#!/usr/bin/env bash
# Đóng gói plugin thành file .zip cài được qua wp-admin (Plugin → Cài mới → Tải plugin lên).
#
#   bash tools/build-plugin-zip.sh            -> đóng gói CẢ HAI plugin
#   bash tools/build-plugin-zip.sh chi-phi    -> chỉ Vận Hành Chi Phí
#   bash tools/build-plugin-zip.sh hop-dong   -> chỉ Thư Viện Hợp Đồng
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUT="$ROOT/dist"
CHON="${1:-tatca}"

dong_goi() {
  local ten="$1" thumuc="$2"
  local SRC="$ROOT/wordpress/$thumuc"
  local ZIP="$OUT/$thumuc.zip"

  [ -f "$SRC/$thumuc.php" ] || { echo "Không thấy $SRC/$thumuc.php"; exit 1; }

  # Kiểm cú pháp PHP trước khi đóng gói — thà báo lỗi ở đây hơn là trên hosting.
  if command -v php >/dev/null 2>&1; then
    while IFS= read -r f; do php -l "$f" >/dev/null || { echo "Lỗi cú pháp: $f"; exit 1; }; done \
      < <(find "$SRC" -name '*.php')
  fi

  mkdir -p "$OUT"
  rm -f "$ZIP"
  ( cd "$(dirname "$SRC")" && zip -qr "$ZIP" "$(basename "$SRC")" \
      -x '*/.git/*' -x '*/.DS_Store' -x '*/node_modules/*' )

  echo "Đã tạo: $ZIP  ($ten)"
  if command -v du >/dev/null 2>&1; then du -h "$ZIP"; fi
}

case "$CHON" in
  chi-phi)  dong_goi "Vận Hành Chi Phí" vhcp-chi-phi ;;
  hop-dong) dong_goi "Thư Viện Hợp Đồng" vhcp-hop-dong ;;
  tatca)
    dong_goi "Vận Hành Chi Phí" vhcp-chi-phi
    dong_goi "Thư Viện Hợp Đồng" vhcp-hop-dong
    ;;
  *) echo "Tham số không hiểu: $CHON (chi-phi | hop-dong | tatca)"; exit 1 ;;
esac

echo
echo "Cài lên hosting: wp-admin → Plugin → Cài mới → Tải plugin lên → chọn file → Cài đặt → Kích hoạt."
echo "Hai plugin cài độc lập, cài cái nào trước cũng được."
