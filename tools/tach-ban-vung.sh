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

# ── TÊN HIỆN TRÊN ĐẦU TRANG ────────────────────────────────────────────────────────────────
# Anh Thắng 14/09/2026: *"đổi tên trang máy tự động trước nhé"*. Viết tắt MTD/VP là mã nội bộ —
# nhân viên lên đơn không đọc ra mảng nào. Tên đầy đủ để ở tiêu đề trang; NHÃN MENU wp-admin
# vẫn giữ bản ngắn (menu bên trái hẹp, xem lý do ở khối nhãn menu bên dưới).
#
# Truyền tham số thứ 3 để đặt tên khác: bash tools/tach-ban-vung.sh vp "Chi Phí VP" "Chi Phí Kho"
case "$MA" in
  mtd) TEN_TRANG="Chi Phí Máy Tự Động" ;;
  vp)  TEN_TRANG="Chi Phí Văn Phòng" ;;
  *)   TEN_TRANG="Chi Phí $MA_HOA" ;;
esac
TEN_TRANG="${3:-$TEN_TRANG}"
export TEN_TRANG

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


# ── Đường dẫn trang ────────────────────────────────────────────────────────────────────────
# 🔴 PHẢI ĐỔI **CẢ HAI** HẰNG SLUG, VÀ ĐÂY LÀ CHỖ NGUY NHẤT CỦA CẢ SCRIPT.
#    Từ 14/09/2026 bản gốc mang hai hằng: `SLUG_MAC_DINH = 'chi-phi-kvc'` (đường của mảng Khu
#    Vui Chơi) và `SLUG_CU = 'chi-phi'` (đường đời đầu, giữ sống cho link cũ khỏi chết).
#    · Quên đổi SLUG_MAC_DINH -> bản mới mặc định mở ở /chi-phi-kvc, tức GIÀNH ĐƯỜNG của bản
#      đang chở sổ tiền thật.
#    · Quên đổi SLUG_CU -> bản mới cũng đăng ký /chi-phi, và WordPress cho luật khai SAU đè
#      luật trước: /chi-phi rơi vào bảng RỖNG của bản mới. Đúng cái đã cắn 08/09/2026 với
#      đường REST — trang mở ra trống trơn, dữ liệu còn nguyên mà nhìn y như mất sạch.
#    Nên sau lượt sed có một CHỐT: không còn chuỗi 'chi-phi' hay 'chi-phi-kvc' trần nào sót lại.
sed -i \
  -e "s#'chi-phi-kvc'#'chi-phi-$MA'#g" \
  -e "s#'chi-phi'#'chi-phi-$MA'#g" \
  -e "s#/chi-phi/#/chi-phi-$MA/#g" \
  "$DICH/includes/class-vhcp-app.php"
if grep -qE "'chi-phi'|'chi-phi-kvc'" "$DICH/includes/class-vhcp-app.php"; then
  echo "✗ CÒN SÓT đường dẫn của bản gốc trong $DICH/includes/class-vhcp-app.php"
  grep -nE "'chi-phi'|'chi-phi-kvc'" "$DICH/includes/class-vhcp-app.php"
  echo "  Bản này sẽ GIÀNH đường của bản đang chạy — dừng, không giao bản hỏng."
  exit 3
fi

# ── Nhãn menu trong wp-admin ───────────────────────────────────────────────────────────────
# 🔴 NHÃN MENU KHÔNG CHỨA CHỮ `vhcp`, NÊN LƯỢT ĐỔI TIỀN TỐ Ở TRÊN KHÔNG ĐỤNG TỚI NÓ.
#    Anh Thắng 14/09/2026 gửi ảnh wp-admin: bốn dòng «Vận Hành Chi Phí» y hệt nhau trong menu
#    bên trái, không biết dòng nào là mảng nào. Bốn bản cài chung một site thì mỗi bản có menu
#    riêng (slug đã khác), nhưng NHÃN thì vẫn là cái chuỗi chép từ bản gốc.
#
# ⚠️ Cái hại không phải xấu mắt: bấm nhầm dòng là mở màn Cấu hình của MẢNG KHÁC, rồi sửa danh
#    mục hay phân quyền ở đó mà tưởng đang sửa mảng mình. Không có gì báo, vì màn nào cũng
#    giống hệt màn nào.
#
# ⚠️ NHÃN PHẢI NGẮN. Menu bên trái hẹp; nhét cả «Chi Phí — Máy Tự Động (MTD)» vào là nó xuống
#    dòng hoặc bị cắt. Dùng «Chi Phí <MÃ HOA>», còn tên đầy đủ để ở tiêu đề trang.
#
# ⚠️ THAY BẤT KỲ NHÃN NÀO ĐANG CÓ, đừng tìm đúng một chuỗi. Bản đầu của khối này tìm nguyên văn
#    'Vận Hành Chi Phí' — rồi bản gốc đổi nhãn thành 'Chi Phí KVC' và lượt thay lặng lẽ trượt,
#    nên bản mới mang luôn nhãn 'Chi Phí KVC'. Sót kiểu ấy không kêu: nó chỉ hiện ra dưới dạng
#    hai dòng menu giống nhau, đúng cái đang đi sửa.
NHAN_MENU="Chi Phí $MA_HOA"
export NHAN_MENU
perl -pi -e '
  s{add_menu_page\(\s*(["\x27])(?:(?!\1).)*\1\s*,\s*(["\x27])(?:(?!\2).)*\2}
   {add_menu_page( "$ENV{NHAN_MENU}", "$ENV{NHAN_MENU}"}g;
' "$DICH/includes/class-vhcp-admin.php"
if ! grep -q "add_menu_page( \"Chi Phí $MA_HOA\", \"Chi Phí $MA_HOA\"" "$DICH/includes/class-vhcp-admin.php"; then
  echo "✗ Nhãn menu chưa đổi — bản này sẽ trùng dòng menu với bản khác."
  grep -n "add_menu_page(" "$DICH/includes/class-vhcp-admin.php" | head -3
  exit 4
fi

# ── Tên hiện trên đầu TRANG ────────────────────────────────────────────────────────────────
# 🔴 CÙNG BỆNH VỚI NHÃN MENU: chuỗi tiếng Việt nên lượt đổi tiền tố không chạm tới.
#    Anh Thắng 14/09/2026: *"Đổi tên trang chi phí"* — bốn bản cài chung một site đều mở ra với
#    đúng một dòng «Vận Hành Chi Phí»; mở hai tab cạnh nhau thì không biết tab nào là mảng nào.
#
# ⚠️ ĐÂY CHỈ LÀ TÊN MẶC ĐỊNH. Người dùng đổi được ngay trên màn Cài đặt (khoá `vhcp<mã>_ten_trang`)
#    — đổi tên là việc của họ, không phải việc phải sửa mã rồi cài lại.
perl -pi -e '
  s{const TEN_MAC_DINH = .[^\x27"]*.;}{const TEN_MAC_DINH = "$ENV{TEN_TRANG}";}g;
' "$DICH/includes/class-vhcp-app.php"
if ! grep -q "TEN_MAC_DINH = \"$TEN_TRANG\"" "$DICH/includes/class-vhcp-app.php"; then
  echo "✗ Tên trang chưa đổi — bản này sẽ trùng tiêu đề với bản gốc."
  grep -n "TEN_MAC_DINH" "$DICH/includes/class-vhcp-app.php" | head -3
  exit 5
fi

# ── KHỐI của bản này (cột `khoi` trong mọi bảng) ───────────────────────────────────────────
#
# 🔴 ANH THẮNG 19/09/2026 CHỐT GỘP BA MẢNG LÀM MỘT APP. Bước 1 mở cột `khoi` trong sơ đồ bảng
#    của bản gốc, mặc định 'kvc'. Chuỗi 'kvc' là chữ thường không dấu, nên lượt đổi TIỀN TỐ ở
#    trên KHÔNG chạm tới nó — chép sang bản Máy Tự Động thì mọi dòng họ nhập vẫn đóng dấu 'kvc'.
#
#    Nhãn sai ấy không kêu lúc nhập. Nó chỉ lộ ra ở bước DỜI DỮ LIỆU: đơn của Máy Tự Động mang
#    dấu 'kvc' sẽ chảy vào tab Khu Vui Chơi, lẫn vào sổ của mảng khác — mà lúc đó thì không còn
#    đường nào tách chúng ra nữa, vì chính cái cột dùng để tách đã sai.
#
# ⚠️ CÙNG KHUÔN VỚI `TEN_MAC_DINH` NGAY TRÊN: thay xong thì SOÁT LẠI và dừng hẳn nếu trượt.
#    Một lượt thay lặng lẽ không khớp còn tệ hơn không thay, vì nó trông y như đã xong.
perl -pi -e '
  s{const KHOI = .[^\x27"]*.;}{const KHOI = \x27$ENV{MA}\x27;}g;
' "$DICH/includes/class-vhcp-db.php"
if ! grep -q "const KHOI = '$MA';" "$DICH/includes/class-vhcp-db.php"; then
  echo "✗ Khối chưa đổi — dữ liệu bản này sẽ đóng dấu 'kvc' và lẫn vào sổ khu vui chơi."
  grep -n "const KHOI" "$DICH/includes/class-vhcp-db.php" | head -3
  exit 6
fi

# ── Danh mục gieo sẵn: BẢN MẢNG RIÊNG KHÔNG ĐẺ DANH MỤC CỦA KHU VUI CHƠI ──────────────────
#
# 🔴 ANH THẮNG 14/09/2026: *"rõ ràng các trang chi phí là không dùng dữ liệu của nhau, nhỉ là
#    đẩy sang chi phí tổng thôi"* — đúng, bảng của bốn bản tách hoàn toàn. Nhưng anh mở
#    /chi-phi-vp ra vẫn thấy 14 gian hàng khu vui chơi (FUNZONE, TÀU TÂN PHÚ, VR SORA…) và
#    tưởng hai bên đang xài chung sổ.
#
#    Chúng KHÔNG kéo từ bản kia sang. Chúng là HẠT GIỐNG GÕ CỨNG trong `default_coso()` —
#    danh sách của khu vui chơi, chép sang bản nào thì bản ấy tự đẻ ra y hệt. Cùng lý do với
#    `default_nhom()`: "SP Đồ uống - NCC", "Nuôi thú"… là nhóm mặt hàng của khu vui chơi, ở
#    Văn phòng hay Máy tự động thì vô nghĩa — mà bảng Loại chi phí lại dựng từ nhóm, nên một
#    hạt giống sai đẻ ra hai bảng sai.
#
# ⚠️ Hạt giống là MỒI CHO NGƯỜI DÙNG ĐẦU TIÊN, không phải dữ liệu. Mồi sai thì người ta phải
#    ngồi xoá từng dòng trước khi làm được việc — mà xoá tay 14 dòng rồi bấm Lưu là đúng thao
#    tác đã hỏng suốt hai ngày qua. Bản mảng riêng mở ra với danh mục TRẮNG, khai theo mảng
#    của mình; bản gốc khu vui chơi giữ nguyên hạt giống của nó.
perl -0777 -pi -e '
  s/(function default_coso\(\) \{).*?(\n\t\})/$1\n\t\treturn array();   \/\/ bản mảng riêng: danh mục trắng, khai theo mảng của mình$2/s;
  s/(function default_nhom\(\) \{).*?(\n\t\})/$1\n\t\treturn array();   \/\/ nt — bảng Loại chi phí dựng từ đây nên cũng trắng theo$2/s;
' "$DICH/includes/class-vhcp-cfg.php"
for H in default_coso default_nhom; do
  if ! grep -A 1 "function $H() {" "$DICH/includes/class-vhcp-cfg.php" | grep -q "return array();"; then
    echo "✗ $H() chưa được dọn trắng — bản '$MA' sẽ đẻ lại danh mục khu vui chơi."
    grep -n "function $H" -A 3 "$DICH/includes/class-vhcp-cfg.php" | head -6
    exit 6
  fi
done
php -l "$DICH/includes/class-vhcp-cfg.php" >/dev/null || { echo "✗ Dọn danh mục xong thì tệp cfg gãy cú pháp."; exit 6; }

# ── Không tạm ứng thì mỗi dòng chi một cơ sở ───────────────────────────────────────────────
#
# 🔴 ANH THẮNG 14/09/2026: *"Đối với bộ phận văn phòng và máy tự động — nếu nhập tạm ứng thì nó
#    sẽ khóa theo cơ sở chọn tạm ứng; còn nếu không nhập tạm ứng mà nhập chi phí bình thường thì
#    cho cơ chế mỗi chi phí sẽ 1 cơ sở, nên không khóa cơ sở đó lại"*.
#
#    CÓ TẠM ỨNG thì tiền đã giao cho một người ở một gian, đối chiếu thừa/thiếu theo chính gian
#    ấy — xin ứng gian này mà chi gian khác là sổ không khớp, khoá là đúng. KHÔNG TẠM ỨNG thì
#    người ta tiêu tiền túi hoặc trả thẳng nhà cung cấp rồi gom một đợt: một đợt rải qua nhiều
#    gian, mỗi dòng một gian. Khoá cả đơn theo dòng đầu là ép họ lập năm đơn cho một đợt chi.
#
# ⚠️ BẢN GỐC KHU VUI CHƠI GIỮ NGUYÊN `false` — luật một-đơn-một-gian bên ấy có lý do riêng, và
#    anh Thắng đã dặn đừng can thiệp phần chi phí khu vui chơi.
perl -0777 -pi -e '
  s/(const MO_KHI_KHONG_TAM_UNG = )false;/${1}true;/;
' "$DICH/includes/class-vhcp-donvi.php"
if ! grep -q "const MO_KHI_KHONG_TAM_UNG = true;" "$DICH/includes/class-vhcp-donvi.php"; then
  echo "✗ Luật 'không tạm ứng thì nhiều cơ sở' chưa bật — bản '$MA' sẽ khoá cơ sở như khu vui chơi."
  grep -n "MO_KHI_KHONG_TAM_UNG" "$DICH/includes/class-vhcp-donvi.php" | head -3
  exit 7
fi

# ── Cơ sở hút từ bên Ghế thuộc nhà nào ─────────────────────────────────────────────────────
#
# 🔴 ANH THẮNG 14/09/2026: *"chi phí [máy] tự động lấy cơ sở từ ghế, còn chi phí văn phòng lấy từ
#    đó, chỉnh lại"*.
#
#    Bản gốc gắn cứng 'POSH' — đúng cho khu vui chơi, vì gian ghế bên ấy là của nhà POSH. Nhưng
#    bản Máy tự động và bản Văn phòng có nhà riêng; cơ sở hút về mà mang 'POSH' thì người dùng
#    nhà mặc định của bản ấy KHÔNG NHÌN THẤY nó (danh mục lọc theo đơn vị) — mở hộp chọn cơ sở ra
#    thấy trống trơn dù danh mục đầy, và báo cáo theo nhà hụt đúng phần tiền của những gian này.
#
# ⚠️ RỖNG = NHÀ MẶC ĐỊNH CỦA CHÍNH BẢN NÀY (`VHCP_DonVi::chuan()` lo phần ấy), không phải "không
#    có nhà".
perl -0777 -pi -e "s/const DON_VI_GHE = 'POSH';/const DON_VI_GHE = '';/" "$DICH/includes/class-vhcp-cfg.php"
if ! grep -q "const DON_VI_GHE = '';" "$DICH/includes/class-vhcp-cfg.php"; then
  echo "✗ Đơn vị cho cơ sở hút từ Ghế chưa dọn — bản '$MA' sẽ gắn cơ sở vào nhà POSH."
  grep -n "DON_VI_GHE = " "$DICH/includes/class-vhcp-cfg.php" | head -3
  exit 8
fi

# 🔴 MẢNG NÀO CÓ GHẾ — anh Thắng 14/09/2026: *"VP không dùng cơ sở ghế, ghế chỉ mỗi MTD thôi"*.
#
#    Máy tự động CHÍNH LÀ mảng ghế massage nên danh mục gian của nó đúng bằng danh mục bên Ghế.
#    Mảng khác thì không: gian ở đó là chỗ làm việc hay khu vui chơi. Hút sang là mỗi lần bên Ghế
#    mở thêm một điểm đặt máy, danh mục bản kia lại dài thêm một dòng lạ — rồi người nhập chọn nhầm,
#    và tiền của mảng này rơi vào một gian ghế.
#
# 🔴 MẶC ĐỊNH LÀ TẮT, ĐỔI TỪ 14/09/2026. Trước đó mặc định là BẬT và bản gốc (khu vui chơi)
#    chịu đúng cái giá của nếp ấy: 67 điểm đặt ghế (AEON MALL, CGV, Bệnh viện 175…) nằm lẫn
#    trong danh mục cơ sở của họ, xóa bao nhiêu lần cũng quay về — vì lượt hút tự động chạy lại
#    mỗi khi đổi phiên bản plugin. Anh Thắng: *"tại sao xóa không được"*, *"nó thuộc bộ phận
#    khác"*, *"bỏ vào đây là người khác khai sai"*.
#
#    Hai kiểu hỏng không bằng nhau, nên mặc định phải ngả về phía hỏng TO TIẾNG:
#      · quên BẬT  -> danh mục cơ sở của mảng ấy trống, người dùng thấy ngay, bấm nút
#                     "🪑 Hút cơ sở từ Ghế" là xong.
#      · quên TẮT  -> 67 dòng lạ lặng lẽ chảy vào, không ai biết, và xóa thì nó mọc lại.
case "$MA" in
  mtd) GHE=true  ;;
  *)   GHE=false ;;
esac
# 🔴 BẢN GỐC NAY ĐÃ BẬT SẮN (21/09/2026, anh Thắng: *"đẩy cơ sở bên ghế sang nhé"* — ba khối
#    nay chung một bản cài, gian ghế rơi vào đúng khối Máy tự động của nó). Nên chiều lật đảo lại:
#    trước là "bật cho mtd", nay là "TẮT cho bản nào không dùng ghế" — tức vp.
#    ⚠️ Quên tắt cho vp là danh mục Văn phòng dài thêm mỗi lần bên Ghế mở một điểm đặt máy, rồi
#       người nhập chọn nhầm và tiền văn phòng rơi vào một gian ghế.
if [ "$GHE" = "true" ]; then
  if ! grep -q "const LAY_COSO_GHE = true;" "$DICH/includes/class-vhcp-cfg.php"; then
    echo "✗ Bản '$MA' phải lấy cơ sở từ Ghế mà hằng LAY_COSO_GHE đang tắt."
    grep -n "LAY_COSO_GHE" "$DICH/includes/class-vhcp-cfg.php" | head -3
    exit 9
  fi
else
  perl -0777 -pi -e "s/const LAY_COSO_GHE = true;/const LAY_COSO_GHE = false;/" "$DICH/includes/class-vhcp-cfg.php"
  if ! grep -q "const LAY_COSO_GHE = false;" "$DICH/includes/class-vhcp-cfg.php"; then
    echo "✗ Chưa tắt được đường lấy cơ sở từ Ghế cho bản '$MA'."
    grep -n "LAY_COSO_GHE" "$DICH/includes/class-vhcp-cfg.php" | head -3
    exit 9
  fi
fi
# ⚠️ ĐẦU PHÁT NGƯỢC (chi phí → ghế) TẮT Ở MỌI BẢN — anh Thắng chỉ xin *"1 chiều từ ghế sang"*.
#    Chốt lại ở đây để ai bật thì lỗi nổ ngay lúc dựng bản, không phải sau vài tuần ở dữ liệu
#    của một hệ khác.
if ! grep -q "const BAO_COSO_GHE = false;" "$DICH/includes/class-vhcp-cfg.php"; then
  echo "✗ Bản '$MA' đang bật đầu phát ngược sang Ghế (BAO_COSO_GHE)."
  grep -n "BAO_COSO_GHE" "$DICH/includes/class-vhcp-cfg.php" | head -3
  exit 9
fi

# ── Tên plugin ─────────────────────────────────────────────────────────────────────────────
# ⚠️ TÊN PHẢI MANG MÃ BẢN. Trong danh sách Plugin của wp-admin bốn bản trông na ná nhau; thiếu
#    mã thì gỡ nhầm hay cập nhật nhầm là chuyện sớm muộn, mà gỡ nhầm một bản chi phí là mất
#    đường vào sổ tiền của cả một mảng. Tên truyền vào mà không có mã thì tự chèn vào cuối.
case "$TEN" in
  *"$MA"*|*"$MA_HOA"*) ;;
  *) TEN="$TEN ($MA_HOA)" ;;
esac
sed -i "s/^ \* Plugin Name:.*/ * Plugin Name:       $TEN/" "$DICH/vhcp-chi-phi-$MA.php"

echo "✓ Đã sinh $DICH"
echo "  · tên lớp   VHCP${MA_HOA}_*      (không đụng VHCP_* của bản đang chạy)"
echo "  · bảng      wp_vhcp${MA}_*"
echo "  · trang     /chi-phi-$MA"
echo "  · REST      vhcp${MA}/v1/call   (bản gốc giữ vhcp/v1/call)"
echo "  · menu      wp-admin ?page=vhcp${MA}"
echo "  · tên trang $TEN_TRANG   (đổi được ở wp-admin -> Cài đặt, khỏi sửa mã)"
echo "  · cơ sở     không tạm ứng -> mỗi dòng chi một cơ sở; có tạm ứng -> khoá theo gian ấy"
echo "  · lấy từ Ghế $GHE"
echo
echo "Bước tiếp:"
echo "  1. bash tools/build-plugin-zip.sh chi-phi-$MA"
echo "  2. Nạp .zip lên WordPress, kích hoạt — chạy song song bản cũ"
echo "  3. Khai danh mục, tài khoản cho vùng '$MA' — bản này mở ra TRẮNG, không mang"
echo "     danh mục của khu vui chơi sang (hai bản KHÔNG dùng chung dữ liệu)"
