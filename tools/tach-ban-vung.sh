#!/usr/bin/env bash
# ══════════════════════════════════════════════════════════════════════════════════════════════
# SINH MỘT BẢN CHI PHÍ RIÊNG CHO MỘT VÙNG — chạy song song, không đụng bản đang chạy.
#
# Anh Thắng 08/09/2026: *"khu vực hn muốn dùng hệ thống vận hành chi phí … build 1 web riêng để
# tránh sai dữ liệu"*, rồi chốt *"tạo riêng, tức hn họ có quy trình khác thì đổi lại để không
# ảnh hưởng hcm"*.
#
# 🔴 VÌ SAO PHẢI ĐỔI TÊN CHỨ KHÔNG CHỈ CHÉP THƯ MỤC. Hai bản cùng cài trên MỘT WordPress thì:
#      · trùng tên lớp  -> PHP chết ngay lúc nạp ("Cannot redeclare class")
#      · trùng tên bảng -> hai bên ghi đè dữ liệu của nhau, im lặng
#      · trùng khoá cấu hình (option) -> đổi cài đặt bên này là đổi luôn bên kia
#      · trùng đường dẫn trang -> chỉ một bản mở được
#    Đổi tên bằng tay thì chắc chắn sót một chỗ, và chỗ sót ấy lộ ra vào lúc tệ nhất.
#
# 🔴 CHẠY MỘT LẦN, RỒI HAI BÊN ĐI ĐƯỜNG RIÊNG. Đây là bản TÁCH, không phải bản đồng bộ: chạy lại
#    là ĐÈ SẠCH mọi thứ vùng ấy đã sửa riêng. Script hỏi lại trước khi đè.
#
# Dùng:  bash tools/tach-ban-vung.sh hn "Vận Hành Chi Phí (HN)"
#        bash tools/tach-ban-vung.sh <mã-vùng> [tên hiển thị]
#
# Ra:    wordpress/vhcp-chi-phi-<mã>/   — plugin độc lập, cài chung site được
#        trang /chi-phi-<mã>
# ══════════════════════════════════════════════════════════════════════════════════════════════
set -euo pipefail
cd "$(dirname "$0")/.."

MA="${1:-}"
if [ -z "$MA" ]; then echo "✗ Thiếu mã vùng. VD: bash tools/tach-ban-vung.sh hn"; exit 2; fi
# Mã vùng đi vào TÊN LỚP PHP, TÊN BẢNG và ĐƯỜNG DẪN — chỉ cho chữ thường và số, không dấu.
if ! printf '%s' "$MA" | grep -qE '^[a-z][a-z0-9]{0,7}$'; then
  echo "✗ Mã vùng phải là 1–8 ký tự chữ thường/số, bắt đầu bằng chữ (vd: hn, dn, hcm)."; exit 2
fi
TEN="${2:-Vận Hành Chi Phí ($(printf '%s' "$MA" | tr '[:lower:]' '[:upper:]'))}"

GOC="wordpress/vhcp-chi-phi"
DICH="wordpress/vhcp-chi-phi-$MA"
MA_HOA="$(printf '%s' "$MA" | tr '[:lower:]' '[:upper:]')"

[ -d "$GOC" ] || { echo "✗ Không thấy $GOC — chạy từ gốc kho."; exit 2; }

if [ -d "$DICH" ]; then
  echo "⚠️  $DICH ĐÃ CÓ."
  echo "    Chạy tiếp là ĐÈ SẠCH mọi thứ vùng '$MA' đã sửa riêng — không lấy lại được."
  printf "    Gõ đúng chữ  DONG-Y  để đè: "
  read -r tra
  [ "$tra" = "DONG-Y" ] || { echo "→ Dừng, không đụng gì."; exit 1; }
  rm -rf "$DICH"
fi

cp -r "$GOC" "$DICH"
# goc/ là mã gốc để tra cứu, không chạy gì — bản vùng không cần.
rm -rf "$DICH/goc"
mv "$DICH/vhcp-chi-phi.php" "$DICH/vhcp-chi-phi-$MA.php"

# ── Đổi tên: THỨ TỰ CÓ NGHĨA ──────────────────────────────────────────────────────────────────
# Chữ HOA trước (VHCP_ → VHCPHN_), rồi chữ thường (vhcp_ → vhcphn_). Làm ngược lại thì lượt sau
# đụng vào kết quả của lượt trước và sinh ra VHCPHN_HN_.
find "$DICH" -type f \( -name '*.php' -o -name '*.html' -o -name '*.js' -o -name '*.md' \) -print0 \
  | xargs -0 sed -i \
      -e "s/VHCP_/VHCP${MA_HOA}_/g" \
      -e "s/vhcp_/vhcp${MA}_/g" \
      -e "s/vhcp-chi-phi/vhcp-chi-phi-$MA/g"

# Đường dẫn trang mặc định + tên plugin — sửa RIÊNG, sau lượt đổi tiền tố.
sed -i \
  -e "s#'chi-phi'#'chi-phi-$MA'#g" \
  -e "s#/chi-phi/#/chi-phi-$MA/#g" \
  "$DICH/includes/class-vhcp-app.php"
sed -i "s/^ \* Plugin Name:.*/ * Plugin Name:       $TEN/" "$DICH/vhcp-chi-phi-$MA.php"

echo "✓ Đã sinh $DICH"
echo "  · tên lớp   VHCP${MA_HOA}_*      (không đụng VHCP_* của bản đang chạy)"
echo "  · bảng      wp_vhcp${MA}_*"
echo "  · trang     /chi-phi-$MA"
echo
echo "Bước tiếp:"
echo "  1. bash tools/build-plugin-zip.sh chi-phi-$MA"
echo "  2. Nạp .zip lên WordPress, kích hoạt — chạy song song bản cũ"
echo "  3. Khai lại danh mục, tài khoản cho vùng '$MA' (hai bản KHÔNG dùng chung dữ liệu)"
