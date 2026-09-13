#!/usr/bin/env bash
# Cài / cập nhật plugin thẳng trên hosting, không phải tải zip rồi bấm qua wp-admin.
#
# Chạy Ở TRÊN HOST (SSH vào hosting), từ thư mục chứa wp-config.php:
#
#   cd ~/public_html
#   curl -fsSL -o tren-host.sh https://raw.githubusercontent.com/zairozy2004199x/khh-chamcong-firmware/claude/chao-em-iiyx5i/tools/tren-host.sh
#   bash tren-host.sh soat
#
# Lệnh con:
#   soat   — Đối soát thu hộ & lập danh sách xuất hoá đơn VAT  (doi-soat-vat)
#
# Chạy lại lệnh trên là cập nhật lên bản mới nhất. Bản đang chạy được giữ lại một
# bản lưu, hỏng thì lùi về ngay bằng câu lệnh script in ra ở cuối.
set -euo pipefail

REPO="zairozy2004199x/khh-chamcong-firmware"
NHANH="${TREN_HOST_NHANH:-claude/chao-em-iiyx5i}"

CHON="${1:-}"
case "$CHON" in
  soat) SLUG="doi-soat-vat"
        TEN="Đối soát thu hộ & lập danh sách xuất hoá đơn VAT"
        DUONG_DAN="tools/doi-soat-vat/wordpress/doi-soat-vat"
        # Giao diện nằm riêng ở tools/doi-soat-vat/web và dùng chung cho cả bản
        # web tĩnh lẫn bản plugin, nên phải chép vào lúc dựng — hệt như
        # wordpress/dong-goi.sh vẫn làm. Thiếu nó thì plugin cài xong mở ra trắng.
        KEM_WEB="tools/doi-soat-vat/web" ;;
  "")   echo "Thiếu tên plugin. Ví dụ:  bash tren-host.sh soat"; exit 2 ;;
  *)    echo "Không biết plugin '$CHON'. Hiện có: soat"; exit 2 ;;
esac

# ── tìm thư mục WordPress ────────────────────────────────────────────────────
# Đi ngược lên vài cấp: người dùng hay đứng sẵn trong public_html, nhưng cũng hay
# đứng trong một thư mục con. Tìm hộ còn hơn bắt gõ lại đường dẫn.
WP=""
thu="$PWD"
for _ in 1 2 3 4; do
  [ -f "$thu/wp-config.php" ] && { WP="$thu"; break; }
  thu="$(dirname "$thu")"
done
if [ -z "$WP" ]; then
  echo "✗ Không thấy wp-config.php ở đây hay các thư mục cha."
  echo "  Vào đúng thư mục WordPress rồi chạy lại, ví dụ:  cd ~/public_html"
  exit 1
fi

PLUGINS="$WP/wp-content/plugins"
[ -d "$PLUGINS" ] || { echo "✗ Không thấy $PLUGINS"; exit 1; }
[ -w "$PLUGINS" ] || { echo "✗ Không có quyền ghi vào $PLUGINS"; exit 1; }

DICH="$PLUGINS/$SLUG"
echo "WordPress : $WP"
echo "Plugin    : $TEN"
echo "Nhánh     : $NHANH"
echo

# ── tải mã nguồn ────────────────────────────────────────────────────────────
command -v curl >/dev/null 2>&1 || { echo "✗ Hosting không có curl."; exit 1; }
command -v tar  >/dev/null 2>&1 || { echo "✗ Hosting không có tar."; exit 1; }

TAM="$(mktemp -d)"
# Dọn thư mục tạm dù script dừng giữa chừng, kể cả khi bị Ctrl-C.
trap 'rm -rf "$TAM"' EXIT INT TERM

# Nhánh có dấu "/" nên phải mã hoá khi ghép vào URL.
NHANH_URL="${NHANH//\//%2F}"
echo "→ Đang tải mã nguồn..."
# Nuốt lời than của curl: ngay dưới đã có câu tiếng Việt nói rõ phải làm gì.
if ! curl -fsSL "https://codeload.github.com/$REPO/tar.gz/refs/heads/$NHANH_URL" \
     -o "$TAM/nguon.tgz" 2>/dev/null; then
  echo "✗ Tải không được. Kiểm lại tên nhánh '$NHANH', hoặc hosting chặn ra ngoài."
  exit 1
fi
tar -xzf "$TAM/nguon.tgz" -C "$TAM"

GOC="$(find "$TAM" -maxdepth 1 -type d -name "$(basename "$REPO")-*" | head -1)"

# ── tự cập nhật chính mình ──────────────────────────────────────────────────
# raw.githubusercontent.com cache 5 phút và bỏ qua mọi tham số phá cache, nên
# curl lại script ngay sau khi nó vừa đổi là lấy đúng bản cũ. Tarball thì không
# cache, mà trong đó đã có sẵn bản mới của chính script này — dùng luôn.
TOI="$(cd "$(dirname "$0")" && pwd)/$(basename "$0")"
MOI="$GOC/tools/$(basename "$0")"
if [ "${TREN_HOST_DA_TU_CAP_NHAT:-}" != "1" ] && [ -f "$MOI" ] && ! cmp -s "$MOI" "$TOI"; then
  echo "→ Có bản script mới, đang cập nhật rồi chạy lại..."
  cp "$MOI" "$TOI"
  TREN_HOST_DA_TU_CAP_NHAT=1 exec bash "$TOI" "$@"
fi
NGUON="$GOC/$DUONG_DAN"
if [ ! -f "$NGUON/$SLUG.php" ]; then
  echo "✗ Trong nhánh '$NHANH' không có $DUONG_DAN/$SLUG.php"
  exit 1
fi

PHIEN_BAN="$(sed -n 's/^ \* Version:[[:space:]]*//p' "$NGUON/$SLUG.php" | head -1)"

# ── dựng đúng bộ file của plugin ────────────────────────────────────────────
DUNG="$TAM/$SLUG"
mkdir -p "$DUNG"
cp "$NGUON"/*.php "$NGUON"/*.txt "$DUNG"/ 2>/dev/null || cp "$NGUON"/*.php "$DUNG"/
if [ -n "${KEM_WEB:-}" ]; then
  [ -d "$GOC/$KEM_WEB" ] || { echo "✗ Nhánh '$NHANH' thiếu $KEM_WEB"; exit 1; }
  cp -R "$GOC/$KEM_WEB" "$DUNG/web"

  # Ba chỗ ghi phiên bản phải khớp nhau, nếu không trình duyệt giữ lại giao diện
  # cũ trong bộ đệm và cập nhật xong vẫn thấy y như chưa sửa gì.
  V_HTML="$(sed -n 's/.*js\/app\.js?v=\([0-9.]*\)".*/\1/p' "$DUNG/web/index.html" | head -1)"
  V_CONST="$(sed -n "s/^const DSVAT_VERSION = '\([0-9.]*\)';.*/\1/p" "$DUNG/$SLUG.php" | head -1)"
  if [ "$PHIEN_BAN" != "$V_HTML" ] || [ "$PHIEN_BAN" != "$V_CONST" ]; then
    echo "✗ Lệch phiên bản trong nhánh — không cài để khỏi ra bản nửa vời:"
    echo "    Version ở đầu $SLUG.php : ${PHIEN_BAN:-?}"
    echo "    DSVAT_VERSION           : ${V_CONST:-?}"
    echo "    ?v= trong web/index.html: ${V_HTML:-?}"
    exit 1
  fi

  # WordPress không cần index.php trong thư mục plugin, nhưng thêm vào thì thư
  # mục không bị liệt kê nếu máy chủ bật duyệt thư mục.
  for d in "$DUNG" "$DUNG/web" "$DUNG/web/js" "$DUNG/web/vendor"; do
    [ -d "$d" ] && printf '<?php\n// Im lặng là vàng.\n' > "$d/index.php"
  done
fi
find "$DUNG" -name '.DS_Store' -delete 2>/dev/null || true
SO_FILE="$(find "$DUNG" -type f | wc -l | tr -d ' ')"
CU=""
if [ -f "$DICH/$SLUG.php" ]; then
  CU="$(sed -n 's/^ \* Version:[[:space:]]*//p' "$DICH/$SLUG.php" | head -1)"
fi
echo "→ Bản trên host: ${CU:-chưa cài}   →   bản sắp cài: ${PHIEN_BAN:-?}"

# ── cài ─────────────────────────────────────────────────────────────────────
# Giữ bản cũ lại trước khi thay. Cập nhật giữa giờ làm mà hỏng thì phải lùi được
# ngay, không phải ngồi chờ tải lại.
LUU=""
if [ -d "$DICH" ]; then
  LUU="$PLUGINS/.$SLUG.luu-$(date +%Y%m%d-%H%M%S)"
  mv "$DICH" "$LUU"
fi
cp -R "$DUNG" "$DICH"
find "$DICH" -type d -exec chmod 755 {} + 2>/dev/null || true
find "$DICH" -type f -exec chmod 644 {} + 2>/dev/null || true

echo "✓ Đã cài $TEN ${PHIEN_BAN:+($PHIEN_BAN)} — $SO_FILE file vào $DICH"

# ── kích hoạt nếu hosting có wp-cli ─────────────────────────────────────────
if command -v wp >/dev/null 2>&1; then
  ( cd "$WP" && wp plugin activate "$SLUG" --quiet 2>/dev/null ) \
    && echo "✓ Đã kích hoạt plugin." || echo "  (chưa kích hoạt được — vào wp-admin bật tay)"
  # Trang chạy bằng rewrite rule nên phải nạp lại, không thì địa chỉ trả 404.
  ( cd "$WP" && wp rewrite flush --quiet 2>/dev/null ) \
    && echo "✓ Đã nạp lại đường dẫn." || true
else
  echo "  Hosting không có wp-cli. Vào wp-admin → Plugin, bật '$TEN'."
  echo "  Nếu trang báo 404: Cài đặt → Đường dẫn tĩnh → bấm Lưu một lần."
fi

echo
echo "Mở trang:  Cài đặt → Đối soát VAT (trong wp-admin) để lấy địa chỉ."
echo "Kiểm bản:  chân trang phải ghi 'Phiên bản ${PHIEN_BAN:-?}'."
if [ -n "$LUU" ]; then
  echo
  echo "Bản cũ giữ ở: $LUU"
  echo "Muốn lùi lại:  rm -rf '$DICH' && mv '$LUU' '$DICH'"
fi
