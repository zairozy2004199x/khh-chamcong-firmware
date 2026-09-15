#!/usr/bin/env bash
# ══════════════════════════════════════════════════════════════════════════════════════════════
# BUILD vhcp-ghe → dist/vhcp-ghe.zip, KÈM BẢN SAO LỚP BÁO CÁO MANG SỐ BẢN.
#
# 🔴 VÌ SAO CÓ BẢN SAO (15/09/2026). Trên host thật, sáu lần cài zip liên tiếp: vhcp-ghe.php và
#    class-vhg-trang.php đổi mới, riêng includes/class-vhg-baocao.php thì KHÔNG — tệp ấy kẹt quyền
#    trên đĩa, WordPress không ghi đè được mà vẫn báo cài thành công. vhcp-ghe.php vì thế nạp lớp
#    báo cáo qua một bản sao mang số bản (includes/class-vhg-baocao-v<VER>.php): tên mới thì luôn
#    ghi được và opcache luôn biên dịch tươi. Script này tạo bản sao ấy ĐÚNG MỘT bản, đúng số bản.
#
# Chạy ở gốc repo:  bash tools/build-ghe.sh
# Nguồn sửa vẫn là includes/class-vhg-baocao.php — KHÔNG sửa tay bản sao; build xoá và chép lại.
# ══════════════════════════════════════════════════════════════════════════════════════════════
set -euo pipefail
cd "$(dirname "$0")/.."

VER=$(sed -n "s/.*define( 'VHG_VERSION', '\([0-9][0-9.]*\)' ).*/\1/p" vhcp-ghe/vhcp-ghe.php | head -1)
[ -n "$VER" ] || { echo "✗ không đọc được VHG_VERSION trong vhcp-ghe/vhcp-ghe.php"; exit 1; }

# Đúng MỘT bản sao: xoá mọi bản sao cũ trước, rồi chép bản hiện tại dưới tên mới.
rm -f vhcp-ghe/includes/class-vhg-baocao-v*.php
cp vhcp-ghe/includes/class-vhg-baocao.php "vhcp-ghe/includes/class-vhg-baocao-v$VER.php"

rm -f dist/vhcp-ghe.zip
zip -qr dist/vhcp-ghe.zip vhcp-ghe -x '*.DS_Store'

# Zip phải đúng bản đang có trên cây nguồn (quy ước §1 CLAUDE.md).
T=$(mktemp -d)
unzip -q dist/vhcp-ghe.zip -d "$T"
diff -rq "$T/vhcp-ghe" vhcp-ghe -x '*.DS_Store'
rm -rf "$T"

echo "✓ vhcp-ghe $VER → dist/vhcp-ghe.zip (kèm includes/class-vhg-baocao-v$VER.php)"
