#!/usr/bin/env bash
# ══════════════════════════════════════════════════════════════════════════════════════════════
# CHẠY HẾT BỘ THỬ CỦA KHO — MỘT LỆNH, MỘT KẾT LUẬN.
#
# 🔴 VÌ SAO CẦN. Kho này có hơn 50 bài thử mà không có lệnh nào chạy hết, nên thực tế mỗi lượt
#    sửa chỉ chạy mấy bài "có vẻ liên quan". Cái giá đã trả bằng tiền thật:
#
#      · 3.66.0 gom mọi phép đọc giờ về `VHCC_DB::gio_24()`, và hàm ấy cắt mất GIÂY. Chấm công
#        bù `08:30:15` lặng lẽ thành `08:30` — mỗi lượt mất tới 59 giây, không dòng đỏ nào.
#        `kiem-cham-bu.php` BẮT ĐƯỢC NGAY từ bản ấy. Không ai chạy, nên lỗi sống tới 3.67.0.
#      · `bam-thu-trang-ghe.js` đòi một tham số đường dẫn chỉ ghi trong chú thích đầu tệp. Gọi
#        thiếu là nó nổ mười dòng, trông y hệt bài kiểm đỏ — nên nó "đỏ sẵn" và không ai chạy,
#        42 phép bấm thật nằm đó không canh gì cả. (Đã cho mặc định ngày 13/09/2026.)
#
#    Bài thử đỏ mà không ai chạy thì cũng như không có. Tệ hơn: nó cho cảm giác AN TOÀN GIẢ.
#
# Chạy: bash tools/test/chay-het.sh
# Trả mã thoát khác 0 nếu có bất kỳ bài nào đỏ — dùng được cho CI.
# ══════════════════════════════════════════════════════════════════════════════════════════════
set -u
cd "$(dirname "$0")/../.." || exit 2

DAT=0; HONG=0; TEN_HONG=()

chay() {   # $1 = lệnh, $2 = tên hiển thị
  if out=$(eval "$1" 2>&1); then
    printf '  ✓ %s\n' "$2"; DAT=$((DAT+1))
  else
    printf '  ✗ %s\n' "$2"; HONG=$((HONG+1)); TEN_HONG+=("$2")
    printf '%s\n' "$out" | tail -14 | sed 's/^/      /'
  fi
}

# ══════════════════════════════════════════════════════════════════════════════════════════════
# SOÁT BẢN CÀI TRONG dist/ TRƯỚC MỌI THỨ KHÁC — NÓ LÀ THỨ THẬT SỰ ĐI TỚI HOSTING.
#
# 🔴 13/09/2026: hai bản 3.69.0 và 3.69.1 đã giao cho anh Thắng với `goc/` NẰM TRONG BẢN CÀI —
#    Code.gs + Index.html, 1,3 MB. Trên hosting chúng nằm dưới wp-content/plugins/… và ĐỌC ĐƯỢC
#    TỪ WEB bằng một địa chỉ đoán ra được, tức công bố cấu trúc bảng và cách tính lương của cả
#    chuỗi để đổi lấy đúng con số không.
#
#    Nguyên nhân: đóng gói bằng `zip -qr` thô thay vì `tools/build-plugin-zip.sh`, rồi soát bằng
#    `diff -rq zip nguồn`. Phép soát ấy BẢO ĐẢM RA SAI: nó đòi bản cài khớp y hệt cây nguồn,
#    trong khi trình đóng gói CỐ Ý loại bớt. Soát càng chặt càng chắc chắn sai.
#
#    `test-cham-cong.php` có canh chuyện này, nhưng nó TỰ ĐÓNG GÓI LẠI rồi mới soát — nên nó
#    canh trình đóng gói, không canh cái tệp đang nằm trong dist/. Hai bản lọt vẫn xanh hết bài.
#    Phép dưới đây soi ĐÚNG tệp sẽ được commit và gửi đi, và chạy TRƯỚC khi có gì kịp dựng lại.
# ══════════════════════════════════════════════════════════════════════════════════════════════
echo "── Bản cài trong dist/ ────────────────────────────────────────"
LOT=0
for z in dist/*.zip; do
  [ -e "$z" ] || continue
  if unzip -Z1 "$z" 2>/dev/null | grep -q '/goc/'; then
    printf '  ✗ %s có goc/ — mã gốc đọc được từ web\n' "$(basename "$z")"; LOT=$((LOT+1))
  fi
done
if [ "$LOT" -gt 0 ]; then
  echo "     Đóng gói lại bằng: bash tools/build-plugin-zip.sh"
  HONG=$((HONG+1)); TEN_HONG+=("bản cài lọt goc/")
else
  echo "  ✓ không bản cài nào lọt goc/"; DAT=$((DAT+1))
fi

echo "── Soát cú pháp PHP ───────────────────────────────────────────"
if find wordpress -name '*.php' -print0 2>/dev/null | xargs -0 -n1 php -l 2>&1 | grep -v 'No syntax errors'; then
  echo "  ✗ có tệp PHP sai cú pháp"; HONG=$((HONG+1)); TEN_HONG+=("cú pháp PHP")
else
  echo "  ✓ mọi tệp PHP đọc được"; DAT=$((DAT+1))
fi

echo "── Bộ thử PHP ─────────────────────────────────────────────────"
for f in tools/test/*.php; do
  [ -e "$f" ] || continue
  # `wp-stub.php` là BỆ ĐỠ dùng chung, không phải một bài thử; chạy thẳng nó chỉ nạp hàm rồi
  # thoát, báo ✓ vô nghĩa. `bench-queries.php` là phép đo tốc độ, không có kết luận đúng/sai.
  case "$(basename "$f")" in wp-stub.php|bench-queries.php) continue;; esac
  chay "php '$f'" "$(basename "$f")"
done

echo "── Bộ thử JavaScript ──────────────────────────────────────────"
# ⚠️ KHÔNG TRUYỀN THAM SỐ Ở ĐÂY. Bài nào cần đường dẫn thì tự có mặc định — để đường mặc định
#    ấy cũng được chạy thật, chứ không phải một nhánh không ai đi tới rồi mục ra lúc nào không
#    hay. Đúng cái đã xảy ra với `bam-thu-trang-ghe.js`.
for f in tools/test/*.js; do
  [ -e "$f" ] || continue
  chay "node '$f'" "$(basename "$f")"
done

echo "── Bộ thử Python ──────────────────────────────────────────────"
for f in tools/test/*.py; do
  [ -e "$f" ] || continue
  chay "python3 '$f'" "$(basename "$f")"
done

echo "───────────────────────────────────────────────────────────────"
if [ "$HONG" -gt 0 ]; then
  echo "✗ HỎNG $HONG / $((DAT+HONG)):"
  for x in "${TEN_HONG[@]}"; do echo "    · $x"; done
  exit 1
fi
echo "✓ SẠCH — $DAT mục đều đạt."
