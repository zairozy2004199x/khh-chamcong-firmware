#!/usr/bin/env bash
# DỊCH THỬ bản .ino bằng g++ trên máy, với bộ khung giả của Arduino/ESP32.
#
# VÌ SAO CẦN
#   Các phép thử khác trích RIÊNG từng hàm ra kiểm — nhanh và chính xác cho phần lô-gic, nhưng
#   không bao giờ dịch cả tệp, nên mọi lỗi kiểu ở lời gọi thư viện đều lọt. Ngày 24/08/2026 một
#   lời gọi uart_set_line_inverse(1, ...) đã lọt hết mọi phép thử rồi chết ngay ở Arduino IDE:
#   "invalid conversion from 'int' to 'uart_port_t'". Tham số đó kiểu uart_port_t, phải truyền
#   UART_NUM_1. Người ngồi nạp mới là người nhận lỗi — không chấp nhận được.
#
#   Khung giả giữ ĐÚNG kiểu của các API dùng tới, nên lỗi kiểu bị bắt ngay trên máy này.
#
# KHÔNG LÀM ĐƯỢC GÌ
#   Không chạy, không mô phỏng phần cứng, không thay được việc nạp thử. Chỉ trả lời đúng một
#   câu: "tệp này có dịch được không".
#
# Chạy: bash tools/test/fw/kiem-dich.sh [tệp.ino ...]
set -euo pipefail
cd "$(dirname "$0")/../../.."
KHUNG="tools/test/fw/khung-gia"
DS=("$@")
if [ ${#DS[@]} -eq 0 ]; then DS=(esp32_ghe_bom_tien/esp32_ghe_bom_tien.ino); fi

TMP="$(mktemp -d)"; trap 'rm -rf "$TMP"' EXIT
hong=0
for ino in "${DS[@]}"; do
  ten="$(basename "$ino")"
  # .ino không phải C++ hợp lệ: thiếu #include và Arduino IDE tự sinh prototype. Dựng lại đủ.
  {
    echo '#include "Arduino.h"'
    echo 'SerialGia Serial;'
    cat "$ino"
    echo 'int main(){ setup(); loop(); return 0; }'
  } > "$TMP/$ten.cpp"

  if g++ -std=c++17 -fsyntax-only -I"$KHUNG" "$TMP/$ten.cpp" 2> "$TMP/loi.txt"; then
    echo "  ✓ $ten dịch được"
  else
    echo "  ✗ $ten KHÔNG dịch được:"
    sed 's/^/      /' "$TMP/loi.txt" | head -30
    hong=1
  fi
done

if [ $hong -eq 0 ]; then
  # Phép phá ngược: cố tình truyền số trần, phải bị bắt — không thì khung giả vô dụng.
  sed 's/uart_set_line_inverse(UART_NUM_1,/uart_set_line_inverse(1,/' "$TMP"/*.cpp > "$TMP/pha.cpp" 2>/dev/null || true
  if [ -s "$TMP/pha.cpp" ] && grep -q 'uart_set_line_inverse(1,' "$TMP/pha.cpp"; then
    if g++ -std=c++17 -fsyntax-only -I"$KHUNG" "$TMP/pha.cpp" 2>/dev/null; then
      echo "  ⚠ KHUNG GIẢ VÔ DỤNG: truyền số trần vào uart_port_t mà vẫn dịch được"; exit 1
    else
      echo "  thử ngược: truyền số trần vào uart_port_t → BẮT ĐƯỢC, tốt"
    fi
  fi
fi
exit $hong
