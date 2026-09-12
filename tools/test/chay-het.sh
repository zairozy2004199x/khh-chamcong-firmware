#!/usr/bin/env bash
# ══════════════════════════════════════════════════════════════════════════════════════════════
# CHẠY HẾT BỘ THỬ CỦA KHO — MỘT LỆNH, MỘT KẾT LUẬN.
#
# 🔴 VÌ SAO CẦN. Trước tệp này, muốn chạy phải gõ tay từng bài, và HAI bài lại đòi một tham số
#    đường dẫn mà chỉ có trong chú thích đầu tệp. Hậu quả thật, 08/09/2026:
#      · `kiem-tran-ngay-sau.js` ĐỎ suốt từ bản 2.9.0 tới 2.13.2 — bốn bản liền — vì một phép
#        ghim nguyên văn thứ tự khoá. Không ai chạy nên không ai thấy.
#      · `kiem-duyet-bao-cao.js` chạy thiếu tham số thì NỔ mười dòng, trông y hệt bài kiểm đỏ;
#        mất một lượt đi tìm lỗi không có thật.
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
    printf '%s\n' "$out" | tail -12 | sed 's/^/      /'
  fi
}

echo "── Soát cú pháp PHP ───────────────────────────────────────────"
# ⚠️ `vhcp-saoke` từng KHÔNG nằm trong dòng này — plugin sao kê sửa bao nhiêu lượt cũng không ai
#    soát nổi một dấu chấm phẩy. Thêm vào 12/09/2026, cùng lượt có bộ thử đầu tiên cho nó.
if find vhcp-ghe vhcp-ve vhcp-saoke -name '*.php' -print0 2>/dev/null | xargs -0 -n1 php -l 2>&1 | grep -v 'No syntax errors'; then
  echo "  ✗ có tệp PHP sai cú pháp"; HONG=$((HONG+1)); TEN_HONG+=("cú pháp PHP")
else
  echo "  ✓ mọi tệp PHP đọc được"; DAT=$((DAT+1))
fi

echo "── Bộ thử PHP ─────────────────────────────────────────────────"
for f in tools/test/*.php; do
  [ -e "$f" ] || continue
  chay "php '$f'" "$(basename "$f")"
done

echo "── Bộ thử JavaScript ──────────────────────────────────────────"
# ⚠️ KHÔNG TRUYỀN THAM SỐ Ở ĐÂY. Bài nào cần đường dẫn thì tự có mặc định — để đường mặc định
#    ấy cũng được chạy thật, chứ không phải một nhánh không ai đi tới rồi mục ra lúc nào không
#    hay. Đúng cái đã xảy ra với `kiem-duyet-bao-cao.js`.
for f in tools/test/*.js; do
  [ -e "$f" ] || continue
  chay "node '$f'" "$(basename "$f")"
done

echo "───────────────────────────────────────────────────────────────"
if [ "$HONG" -gt 0 ]; then
  echo "✗ HỎNG $HONG / $((DAT+HONG)):"
  for x in "${TEN_HONG[@]}"; do echo "    · $x"; done
  exit 1
fi
echo "✓ SẠCH — $DAT mục đều đạt."
