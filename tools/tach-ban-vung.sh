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
#      · trùng KHOÁ ĐĂNG KÝ TOÀN CỤC của WordPress -> bản nạp SAU ĐÈ bản nạp trước
#    Đổi tên bằng tay thì chắc chắn sót một chỗ, và chỗ sót ấy lộ ra vào lúc tệ nhất.
#
# 🔴 CẮN THẬT 08/09/2026 — vì sao có luật "chuỗi trần" bên dưới.
#    Bản đầu của script này chỉ đổi `VHCP_` và `vhcp_` (CÓ gạch dưới). Mấy khoá toàn cục lại
#    viết KHÔNG gạch dưới: `register_rest_route( 'vhcp/v1', … )`, `add_menu_page( …, 'vhcp', … )`,
#    `$_GET['vhcp']`, thư mục tải ảnh `ROOT = 'vhcp'`. Chúng thoát hết.
#    WordPress cho đường REST đăng ký SAU đè lên đường trước, mà plugin nạp theo thứ tự chữ cái
#    nên `vhcp-chi-phi-hn` nạp sau `vhcp-chi-phi` -> BẢN HN CHIẾM ĐƯỜNG `vhcp/v1/call`.
#    Kết quả: anh Thắng mở /chi-phi/ ra thấy TRỐNG TRƠN — trang HCM vẫn đúng, nhưng mọi lượt hỏi
#    dữ liệu của nó rơi vào bảng rỗng của HN. Dữ liệu không mất, mà nhìn y như đã mất sạch.
#    Bài kiểm `tools/test/kiem-tach-ban-vung.php` nay chốt: KHÔNG chuỗi nào dùng chung.
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

# ── Đổi tên: MỘT LƯỢT DUY NHẤT, QUÉT SẠCH, CHỪA ĐÚNG TÊN TỆP ────────────────────────────────
# 🔴 GỘP HẾT VÀO MỘT `s///` CHỨ KHÔNG CHẠY NỐI TIẾP NHIỀU LƯỢT. Perl quét trái sang phải và
#    KHÔNG soi lại phần vừa thay, nên mỗi chỗ bị đổi đúng một lần. Chạy nối tiếp thì lượt sau
#    ăn vào kết quả lượt trước: 'vhcp_slug' -> 'vhcphn_slug' -> 'vhcphnhn_slug'. Đã cắn thật
#    08/09/2026, hỏng 143 chỗ trong một lượt sinh.
#
# 🔴 ĐỔI MỌI CHỮ `vhcp`, KHÔNG CHỈ `vhcp_`. Bản đầu chỉ đổi chuỗi CÓ gạch dưới, và mấy khoá
#    toàn cục viết không gạch dưới thoát hết:
#      · register_rest_route( 'vhcp/v1', … )   -> bản nạp SAU ĐÈ đường REST của bản nạp trước
#      · add_menu_page( …, 'vhcp', … )          -> chung menu wp-admin, chung cả màn Cài đặt
#      · admin.php?page=vhcp                    -> form của vùng POST sang màn của bản gốc
#      · $_GET['vhcp'], ROOT = 'vhcp'           -> chung khoá URL, chung thư mục ảnh tải lên
#      · 'X-VHCP-Token'                         -> chung tên tiêu đề mang thẻ phiên
#      · LIKE '_transient_vhcp\_fail\_%'        -> vùng dọn transient là XOÁ CỦA BẢN GỐC
#    Ngày 08/09/2026 chỗ REST làm anh Thắng mở /chi-phi/ ra thấy TRỐNG TRƠN: trang HCM vẫn
#    đúng, nhưng mọi lượt hỏi dữ liệu của nó rơi vào bảng rỗng của HN. Dữ liệu còn nguyên mà
#    nhìn y như mất sạch. Nên luật bây giờ là QUÉT SẠCH rồi chừa ra, chứ không phải liệt kê ra
#    rồi đổi — liệt kê thì mỗi khoá thêm về sau lại lọt, mà lọt thì im lặng.
#
# THỨ TỰ TRONG DẤU | CÓ NGHĨA — perl thử từ trái sang, khớp cái nào thì dừng ở đó:
#   1. class-vhcp-    GIỮ NGUYÊN. Tên tệp, nằm trong thư mục riêng của từng bản nên trùng
#                     nhau vô hại; đổi thì `require_once` trỏ vào tệp không tồn tại.
#   2. vhcp.css       GIỮ NGUYÊN. Cùng lý do — xem class-vhcp-app.php nạp nó theo tên.
#   3. vhcp-chi-phi   tên thư mục và tên tệp gốc plugin -> thêm đuôi mã vùng
#   4. VHCP           mọi chữ HOA còn lại: tên lớp, hằng, 'X-VHCP-Token'
#   5. vhcp           mọi chữ thường còn lại: khoá option, tên bảng, namespace REST, slug menu,
#                     nhóm bộ nhớ đệm, thư mục ảnh, tên hàm gọi qua cầu, cả trong chú thích
export MA MA_HOA
find "$DICH" -type f \( -name '*.php' -o -name '*.html' -o -name '*.js' -o -name '*.md' \) -print0 \
  | xargs -0 perl -pi -e '
      my $m = $ENV{MA}; my $M = $ENV{MA_HOA};
      s{ class-vhcp- | vhcp\.css | vhcp-chi-phi | VHCP | vhcp }{
            $& eq "class-vhcp-" ? $&
          : $& eq "vhcp.css"    ? $&
          : $& eq "vhcp-chi-phi" ? "vhcp-chi-phi-$m"
          : $& eq "VHCP"        ? "VHCP$M"
          :                       "vhcp$m"
      }gex;
    '


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
echo "  · REST      vhcp${MA}/v1/call   (bản gốc giữ vhcp/v1/call)"
echo "  · menu      wp-admin ?page=vhcp${MA}"
echo
echo "Bước tiếp:"
echo "  1. bash tools/build-plugin-zip.sh chi-phi-$MA"
echo "  2. Nạp .zip lên WordPress, kích hoạt — chạy song song bản cũ"
echo "  3. Khai lại danh mục, tài khoản cho vùng '$MA' (hai bản KHÔNG dùng chung dữ liệu)"
