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
