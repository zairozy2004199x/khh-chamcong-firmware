#!/usr/bin/env bash
# ══════════════════════════════════════════════════════════════════════════════════════════════
# KHAI KHOÁ GITHUB VÀO TRANG — MỘT LỆNH, KHỎI VÀO wp-admin DÁN TAY
#
# Anh Thắng 13/09/2026: *"cho lệnh ... tự add token"*.
#
#   ssh <tài khoản>@<host>
#   cd <thư mục chứa wp-config.php>
#   bash khai-khoa-github.sh
#
# Script hỏi khoá, dán vào, Enter. Xong.
#
# ══════════════════════════════════════════════════════════════════════════════════════════════
# 🔴 KHÔNG ĐƯA KHOÁ VÀO DÒNG LỆNH.
#
#   `bash khai-khoa-github.sh github_pat_xxx` trông tiện hơn, nhưng khoá ấy sẽ nằm lại trong
#   `~/.bash_history` của máy chủ, và hiện ra với bất kỳ ai gõ `ps` đúng lúc lệnh đang chạy.
#   Script này ĐỌC TỪ BÀN PHÍM với `read -s` — gõ xong không hiện, không lưu đâu cả.
#
#   Cùng luật đã đặt cho PIN và khoá máy chấm công: trang chạy ngoài internet, thứ gì lộ được
#   thì sẽ lộ.
#
# ⚠️ CHẠY TRÊN HOSTING, không phải máy anh. Cần `wp` (WP-CLI); gõ `wp --info` để thử.
#    Không có WP-CLI thì vào wp-admin → Vận Hành Chi Phí → Cài đặt → ô "Khoá GitHub".
# ══════════════════════════════════════════════════════════════════════════════════════════════
set -uo pipefail

# `vhcp_gh_token` dùng chung cho tám plugin trên cùng một site.
# `vhcphn_gh_token` là của BẢN VÙNG Hà Nội — nó chạy site riêng, khoá riêng, xem
# docs/CAP-NHAT-TU-GITHUB.md mục 3.
O_KHOA="${1:-vhcp_gh_token}"

if ! command -v wp >/dev/null 2>&1; then
  echo "🔴 Không thấy lệnh 'wp' (WP-CLI) trên máy này."
  echo "   Khai tay: wp-admin → Vận Hành Chi Phí → Cài đặt → ô 'Khoá GitHub'."
  exit 1
fi
if ! wp core is-installed >/dev/null 2>&1; then
  echo "🔴 Thư mục này không phải gốc WordPress."
  echo "   cd tới thư mục có wp-config.php rồi chạy lại."
  exit 1
fi

cu="$(wp option get "$O_KHOA" 2>/dev/null || true)"
if [ -n "$cu" ]; then
  echo "Ô '$O_KHOA' ĐANG CÓ khoá (${#cu} ký tự)."
  echo "Dán khoá mới để thay, hoặc bỏ trống rồi Enter để giữ nguyên."
else
  echo "Ô '$O_KHOA' chưa khai."
fi
echo
echo "Tạo khoá ở: GitHub → Settings → Developer settings → Fine-grained tokens"
echo "            Only select repositories → khh-chamcong-firmware"
echo "            Permissions → Contents → Read-only   ← đúng một mục này"
echo

# 🔴 `-s`: gõ vào KHÔNG hiện lên màn hình. Một ảnh chụp màn hình lúc này là mất khoá.
printf 'Dán khoá rồi Enter: '
read -r -s khoa
echo

khoa="$(printf '%s' "$khoa" | tr -d '[:space:]')"
if [ -z "$khoa" ]; then
  echo "Bỏ trống — giữ nguyên khoá cũ, không đổi gì."
  exit 0
fi

# ⚠️ Soát hình dạng TRƯỚC KHI GHI. Dán nhầm nửa chuỗi hay dán nhầm mật khẩu khác thì ghi vào
#    cũng chẳng ai biết, và lần cập nhật sau im lặng không thấy bản mới — một lỗi không có
#    câu báo nào. Khoá GitHub bắt đầu bằng 'github_pat_' (fine-grained) hoặc 'ghp_' (cổ điển).
case "$khoa" in
  github_pat_*|ghp_*|gho_*) : ;;
  *)
    echo "🔴 Chuỗi vừa dán không giống khoá GitHub (phải bắt đầu bằng 'github_pat_' hoặc 'ghp_')."
    echo "   Không ghi gì cả. Xem lại rồi chạy lại."
    exit 1 ;;
esac
if [ "${#khoa}" -lt 30 ]; then
  echo "🔴 Chuỗi quá ngắn (${#khoa} ký tự) — nhiều khả năng dán thiếu. Không ghi gì cả."
  exit 1
fi

wp option update "$O_KHOA" "$khoa" --quiet
# Bỏ bộ nhớ tạm để lượt hỏi GitHub kế tiếp đi thật ngay, khỏi chờ hết sáu giờ.
for t in vhcp vhcc vhg vhnb vhtc vhd vhda vhcphn; do
  wp transient delete "${t}_gh_ban_moi" >/dev/null 2>&1 || true
done
wp transient delete update_plugins --network >/dev/null 2>&1 || true
wp transient delete update_plugins >/dev/null 2>&1 || true

# 🔴 CHỈ NÓI CÓ/KHÔNG, KHÔNG IN LẠI KHOÁ.
moi="$(wp option get "$O_KHOA" 2>/dev/null || true)"
if [ "${#moi}" = "${#khoa}" ]; then
  echo "✓ Đã khai khoá vào '$O_KHOA' (${#moi} ký tự)."
  echo
  echo "Thử ngay: vào wp-admin → Plugin. Có bản mới thì hiện dòng 'Có phiên bản mới'."
  echo "Chưa thấy gì cũng bình thường — nghĩa là đang chạy bản mới nhất."
else
  echo "🔴 Ghi xong nhưng đọc lại không khớp. Khai tay ở wp-admin → Cài đặt."
  exit 1
fi
