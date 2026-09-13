#!/usr/bin/env bash
# ══════════════════════════════════════════════════════════════════════════════════════════════
# XEM TRƯỚC MÀN /nhan-su/ MÀ KHÔNG CẦN CÀI LÊN HOSTING.
#
# Dựng trang bằng chính hàm của plugin (`VHCC_TrangNS::phuc_vu()`) với bệ đỡ WordPress giả của
# bộ thử, rồi mở bằng Chromium: chụp ảnh + in ra màn hình đang NÓI GÌ VỀ AI.
#
# 🔴 Bộ thử đếm chuỗi không thay được việc này. 3.69.0 xanh cả 80 phép mà vẫn có một câu nói sai
#    về nhóm người, và một ô xổ cắt cụt đúng phần thông tin. Cả hai chỉ lộ ra khi MỞ RA NHÌN.
#
# Chạy: bash tools/xem/xem-man.sh [thư mục xuất]
# ══════════════════════════════════════════════════════════════════════════════════════════════
set -u
cd "$(dirname "$0")/../.." || exit 2
RA="${1:-${TMPDIR:-/tmp}/vhcc-xem-man}"
mkdir -p "$RA" || exit 2

echo "── Dựng trang bằng hàm thật của plugin ────────────────────────"
TEP="$RA/man-nhan-su.html"
if ! php tools/xem/dung-man.php "$TEP" >/dev/null; then
  echo "  ✗ không dựng được trang"; exit 1
fi
echo "  ✓ $TEP ($(wc -c < "$TEP") byte)"

node tools/xem/chup.js "$TEP" "$RA" || exit 1
