#!/usr/bin/env bash
# Đóng gói plugin thành file .zip cài được qua wp-admin (Plugin → Cài mới → Tải plugin lên).
#
#   bash tools/build-plugin-zip.sh            -> đóng gói CẢ BA plugin
#   bash tools/build-plugin-zip.sh chi-phi    -> chỉ Vận Hành Chi Phí
#   bash tools/build-plugin-zip.sh hop-dong   -> chỉ Thư Viện Hợp Đồng
#   bash tools/build-plugin-zip.sh cham-cong  -> chỉ Chấm Công
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
  # ⚠️ BỎ `goc/` và `apps-script/` ra khỏi bản cài. Hai thư mục đó KHÔNG chạy gì trên hosting:
  #    `goc/` là bản gốc Code.gs + Index.html giữ để lập bản đồ nghiệp vụ (1,3 MB), `apps-script/`
  #    là mấy tệp .gs để dán tay vào Apps Script cùng hai bản kê.
  #    Để chúng trong plugin là chúng nằm dưới wp-content/plugins/… và ĐỌC ĐƯỢC TỪ WEB bằng một
  #    địa chỉ đoán ra được — tức công bố toàn bộ cấu trúc bảng, danh sách PIN bị chặn và cách
  #    tính lương của cả chuỗi, để đổi lấy đúng con số không. Ai cần thì lấy trong repo.
  ( cd "$(dirname "$SRC")" && zip -qr "$ZIP" "$(basename "$SRC")" \
      -x '*/.git/*' -x '*/.DS_Store' -x '*/node_modules/*' \
      -x "$(basename "$SRC")/goc/*" -x "$(basename "$SRC")/apps-script/*" )

  echo "Đã tạo: $ZIP  ($ten)"
  if command -v du >/dev/null 2>&1; then du -h "$ZIP"; fi
}

case "$CHON" in
  chi-phi)  dong_goi "Vận Hành Chi Phí" vhcp-chi-phi ;;
  hop-dong) dong_goi "Thư Viện Hợp Đồng" vhcp-hop-dong ;;
  cham-cong) dong_goi "Chấm Công" vhcp-cham-cong ;;
  tatca)
    dong_goi "Vận Hành Chi Phí" vhcp-chi-phi
    dong_goi "Thư Viện Hợp Đồng" vhcp-hop-dong
    dong_goi "Chấm Công" vhcp-cham-cong
    ;;
  *) echo "Tham số không hiểu: $CHON (chi-phi | hop-dong | cham-cong | tatca)"; exit 1 ;;
esac

echo
echo "Cài lên hosting: wp-admin → Plugin → Cài mới → Tải plugin lên → chọn file → Cài đặt → Kích hoạt."
echo "Ba plugin cài độc lập, cài cái nào trước cũng được."
echo
echo "Trong bản cài KHÔNG có goc/ và apps-script/ — hai thư mục đó không chạy gì mà lại đọc được"
echo "từ web. Mấy tệp .gs cần dán vào Apps Script thì lấy trong repo (hoặc bản gửi kèm)." 
