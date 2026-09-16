#!/usr/bin/env bash
# ══════════════════════════════════════════════════════════════════════════════════════════════
# BUILD vhcp-cham-cong → dist/vhcp-cham-cong.zip
#
# Không có trò bản-sao-mang-số-bản như tools/build-ghe.sh: chuyện "tệp kẹt quyền trên đĩa" mới chỉ
# xảy ra với includes/class-vhg-baocao.php của plugin Ghế. Thêm cơ chế ấy ở đây khi chưa có triệu
# chứng là nhân đôi số tệp phải đọc để đổi lấy một thứ chưa ai cần.
#
# ⚠️ ĐỪNG GÓI MODEL KHUÔN MẶT VÀO ZIP. Bảy megabyte model nằm ở wp-content/uploads/vhcc-mat/ là CỐ Ý
#    — cài đè plugin bằng zip thì WordPress xoá sạch thư mục plugin cũ, model nằm trong plugin là
#    mỗi lượt cập nhật bay một lần. Xem vhcp-cham-cong/assets/mat/DOC-TRUOC.txt.
#
# Chạy ở gốc repo:  bash tools/build-chamcong.sh
# ══════════════════════════════════════════════════════════════════════════════════════════════
set -euo pipefail
cd "$(dirname "$0")/.."

VER=$(sed -n "s/.*define( 'VHCC_VERSION', '\([0-9][0-9.]*\)' ).*/\1/p" vhcp-cham-cong/vhcp-cham-cong.php | head -1)
[ -n "$VER" ] || { echo "✗ không đọc được VHCC_VERSION trong vhcp-cham-cong/vhcp-cham-cong.php"; exit 1; }

rm -f dist/vhcp-cham-cong.zip
zip -qr dist/vhcp-cham-cong.zip vhcp-cham-cong -x '*.DS_Store'

# Zip phải đúng bản đang có trên cây nguồn (quy ước §1 CLAUDE.md).
T=$(mktemp -d)
unzip -q dist/vhcp-cham-cong.zip -d "$T"
diff -rq "$T/vhcp-cham-cong" vhcp-cham-cong -x '*.DS_Store'
rm -rf "$T"

echo "✓ vhcp-cham-cong $VER → dist/vhcp-cham-cong.zip"
