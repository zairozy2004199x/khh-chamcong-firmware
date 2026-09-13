#!/usr/bin/env bash
# ══════════════════════════════════════════════════════════════════════════════════════════════
# SOÁT: TRANG ĐANG CHẠY CÓ GÌ MÀ REPO CHƯA CÓ
#
# Anh Thắng 13/09/2026: *"anh thấy còn nhiều trang chưa thấy trong này"*.
#
# Trước khi gom bất cứ thứ gì, phải biết CHẮC còn sót cái gì — chứ không đoán. Lệnh này liệt kê
# mọi plugin đang cài trên hosting rồi đối chiếu với thư mục `wordpress/` của repo, và chỉ ra
# đúng ba nhóm:
#
#   · CHỈ CÓ TRÊN HOST   -> plugin đang chạy thật mà repo không giữ mã. Mất máy chủ là mất luôn.
#   · CHỈ CÓ TRONG REPO  -> đã viết nhưng chưa cài, hoặc đã gỡ khỏi trang.
#   · CÓ CẢ HAI          -> yên tâm, kèm số phiên bản hai bên để thấy bên nào cũ hơn.
#
# ⚠️ CHẠY TRÊN HOSTING, không phải trên máy anh. Cần `wp` (WP-CLI) — phần lớn hosting có sẵn;
#    gõ `wp --info` để thử. Không có thì xem cách thủ công ở cuối tệp này.
#
#   ssh <tài khoản>@<host>
#   cd <thư mục chứa wp-config.php>
#   bash soat-plugin-tren-host.sh > danh-sach.txt
#
# Rồi gửi `danh-sach.txt` về — đối chiếu xong mới biết phải gom cái gì.
# ══════════════════════════════════════════════════════════════════════════════════════════════
set -uo pipefail

if ! command -v wp >/dev/null 2>&1; then
  echo "Không thấy lệnh 'wp' (WP-CLI) trên máy này."
  echo
  echo "Cách thủ công thay thế — liệt kê thư mục plugin rồi đọc số phiên bản:"
  echo "  ls -1 wp-content/plugins/"
  echo "  grep -m1 -r 'Version:' wp-content/plugins/*/[a-z]*.php"
  exit 1
fi

echo "# PLUGIN ĐANG CÀI TRÊN HOST  ($(date '+%d/%m/%Y %H:%M'))"
echo "# cột: tên thư mục | trạng thái | phiên bản"
echo
wp plugin list --fields=name,status,version --format=table 2>/dev/null

echo
echo "# THƯ MỤC THẬT TRONG wp-content/plugins/"
echo "# (plugin cài tay, chưa kích hoạt, hoặc WP-CLI không nhận ra vẫn hiện ở đây)"
echo
ls -1 wp-content/plugins/ 2>/dev/null || ls -1 ../wp-content/plugins/ 2>/dev/null || echo "(không tìm thấy thư mục plugins — chạy lệnh này ở thư mục có wp-config.php)"

echo
echo "# KHOÁ GITHUB ĐÃ KHAI CHƯA (chỉ nói CÓ/KHÔNG, không in khoá ra)"
for o in vhcp_gh_token vhcphn_gh_token; do
  v="$(wp option get "$o" 2>/dev/null || true)"
  if [ -n "$v" ]; then echo "  $o : ĐÃ KHAI (${#v} ký tự)"; else echo "  $o : chưa khai"; fi
done
