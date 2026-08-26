#!/usr/bin/env bash
# Đóng gói plugin thành file .zip cài được qua wp-admin (Plugin → Cài mới → Tải plugin lên).
#
#   bash tools/build-plugin-zip.sh            -> đóng gói CẢ BA plugin
#   bash tools/build-plugin-zip.sh chi-phi    -> chỉ Vận Hành Chi Phí
#   bash tools/build-plugin-zip.sh hop-dong   -> chỉ Thư Viện Hợp Đồng
#   bash tools/build-plugin-zip.sh cham-cong  -> chỉ Chấm Công
#   bash tools/build-plugin-zip.sh ghe        -> chỉ Ghế Massage
#   bash tools/build-plugin-zip.sh noi-bo     -> chỉ Nội Bộ K&H
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
  # ⚠️ BỎ `goc/` ra khỏi bản cài — bản gốc Code.gs + Index.html giữ để lập bản đồ nghiệp vụ
  #    (1,3 MB), KHÔNG chạy gì trên hosting. Để nó trong plugin là nó nằm dưới
  #    wp-content/plugins/… và ĐỌC ĐƯỢC TỪ WEB bằng một địa chỉ đoán ra được — tức công bố toàn
  #    bộ cấu trúc bảng, danh sách PIN bị chặn và cách tính lương của cả chuỗi, để đổi lấy đúng
  #    con số không. Ai cần thì lấy trong repo.
  #
  # 🔴 `apps-script/` thì PHẢI GIỮ, dù nó cũng chỉ để dán tay. Bản đầu bỏ luôn cả nó, và đó là
  #    lỗi: `VHCC_Trang::ds_ham()` ĐỌC `apps-script/cau-noi.gs` lúc chạy để lấy danh sách hàm
  #    giao diện, còn màn Cài đặt hiện nội dung file đó cho anh Thắng copy. Thiếu file thì trang
  #    chấm công báo "CC_CHO_PHEP còn RỖNG" — một câu chỉ sai hướng hoàn toàn, vì bên Apps
  #    Script danh sách vẫn đủ 23 hàm. Mấy tệp .gs này không chứa bí mật nào (khoá nằm trong
  #    Script Properties và wp-config.php), nên đọc được từ web cũng không mất gì.
  #    Mục 37 của bộ thử canh: mọi đường `VHCC_DIR . '…'` mã có đọc thì phải CÓ TRONG ZIP.
  ( cd "$(dirname "$SRC")" && zip -qr "$ZIP" "$(basename "$SRC")" \
      -x '*/.git/*' -x '*/.DS_Store' -x '*/node_modules/*' \
      -x "$(basename "$SRC")/goc/*" )

  echo "Đã tạo: $ZIP  ($ten)"
  if command -v du >/dev/null 2>&1; then du -h "$ZIP"; fi
}

case "$CHON" in
  trang-chu) dong_goi "Trang Vận Hành K&H" vhcp-trang-chu ;;
  chi-phi)  dong_goi "Vận Hành Chi Phí" vhcp-chi-phi ;;
  hop-dong) dong_goi "Thư Viện Hợp Đồng" vhcp-hop-dong ;;
  cham-cong) dong_goi "Chấm Công" vhcp-cham-cong ;;
  ghe)       dong_goi "Ghế Massage" vhcp-ghe ;;
  noi-bo)    dong_goi "Nội Bộ K&H" vhcp-noi-bo ;;
  tatca)
    dong_goi "Trang Vận Hành K&H" vhcp-trang-chu
    dong_goi "Vận Hành Chi Phí" vhcp-chi-phi
    dong_goi "Thư Viện Hợp Đồng" vhcp-hop-dong
    dong_goi "Chấm Công" vhcp-cham-cong
    dong_goi "Ghế Massage" vhcp-ghe
    dong_goi "Nội Bộ K&H" vhcp-noi-bo
    ;;
  *) echo "Tham số không hiểu: $CHON (trang-chu | chi-phi | hop-dong | cham-cong | ghe | noi-bo | tatca)"; exit 1 ;;
esac

# 🔴 CHỐT CHỐNG SÓT: thư mục plugin nào có trong cây mã mà không nằm trong danh sách trên thì
# báo ra. Danh sách gõ tay ở đây đứng im trong khi cây mã đi tiếp — `vhcp-noi-bo` vừa suýt bị
# bỏ quên đúng kiểu ấy. Cố ý KHÔNG đóng gói thì khai vào KHONG_DONG_GOI, để chỗ bỏ qua là một
# quyết định có ghi lại, chứ không phải một chỗ sót.
KHONG_DONG_GOI="vhcp-cong"   # bản viết lại 25/08 đã dừng — hệ đang chạy là vhcp-cham-cong
if [ "$CHON" = "tatca" ]; then
  for d in "$ROOT"/wordpress/*/; do
    ten="$(basename "$d")"
    [ -f "$d/$ten.php" ] || continue
    case " $KHONG_DONG_GOI " in *" $ten "*) continue ;; esac
    grep -q "dong_goi \".*\" $ten\b" "${BASH_SOURCE[0]}" || echo "⚠️  CHƯA ĐÓNG GÓI: $ten (thêm vào tools/build-plugin-zip.sh, hoặc khai vào KHONG_DONG_GOI)"
  done
fi

echo
echo "Cài lên hosting: wp-admin → Plugin → Cài mới → Tải plugin lên → chọn file → Cài đặt → Kích hoạt."
echo "Các plugin cài độc lập, cài cái nào trước cũng được."
echo
echo "Bản cài KHÔNG có goc/ (mã gốc để tra cứu, không chạy gì)."
echo "apps-script/ thì CÓ, và phải có: plugin đọc cau-noi.gs lúc chạy để biết cầu nối cho gọi hàm nào."
echo "Bỏ nó ra khỏi bản cài là trang chấm công báo \"CC_CHO_PHEP còn RỖNG\" — đã xảy ra một lần." 
