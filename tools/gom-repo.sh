#!/usr/bin/env bash
# ══════════════════════════════════════════════════════════════════════════════════════════════
# GOM MỘT REPO KHÁC VỀ ĐÂY — GIỮ NGUYÊN LỊCH SỬ
#
# Anh Thắng 13/09/2026: *"anh muốn các bản đó về cùng 1 link, thì cho anh lệnh để anh đẩy các
# repo đó về chung 1 nơi trên github"*.
#
#   bash tools/gom-repo.sh <chủ/repo-nguồn> <tên-thư-mục-đích> [nhánh-nguồn] [thư-mục-con-nguồn]
#
# Ví dụ:
#   bash tools/gom-repo.sh zairozy2004199x/vhcp-kho vhcp-kho
#   bash tools/gom-repo.sh zairozy2004199x/Claude   vhcp-abc  main  wordpress/vhcp-abc
#
# ══════════════════════════════════════════════════════════════════════════════════════════════
# 🔴 DÙNG `git subtree`, KHÔNG CHÉP TAY.
#
#   Chép tay thì mã về đây nhưng LỊCH SỬ ở lại repo cũ. Sáu tháng sau hỏi "dòng này sửa hôm nào,
#   vì sao" thì không còn chỗ nào trả lời — mà chính những câu ấy mới là thứ giữ cho hệ này chạy
#   được. `git subtree` mang cả lịch sử sang, gắn vào đúng thư mục con.
#
# 🔴 KHÔNG DÙNG `git merge --allow-unrelated-histories` TRẦN.
#   Nó trộn cây thư mục hai bên vào nhau: `README.md` của repo kia đè lên `README.md` ở đây,
#   `.gitignore` cũng thế. Xong rồi mới phát hiện thì đã lẫn, và gỡ ra rất khó.
#
# ⚠️ CHẠY TRÊN MÁY ANH, không phải trên hosting. Cần `git` và quyền đọc repo nguồn.
# ⚠️ LÀM TRÊN MỘT NHÁNH RIÊNG. Script tự tạo nhánh `gom-<tên>`; xem xong ưng thì mới gộp vào
#    nhánh chính. Không bao giờ gom thẳng vào nhánh đang chạy.
# ══════════════════════════════════════════════════════════════════════════════════════════════
set -euo pipefail

NGUON="${1:-}"; DICH="${2:-}"; NHANH="${3:-main}"; THUMUC_CON="${4:-}"

if [ -z "$NGUON" ] || [ -z "$DICH" ]; then
  sed -n '2,30p' "$0" | sed 's/^# \{0,1\}//'
  exit 1
fi

GOC="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$GOC"

# ── Chốt trước khi đụng vào gì ───────────────────────────────────────────────────────────────
if [ -n "$(git status --porcelain)" ]; then
  echo "🔴 Cây làm việc đang có thay đổi chưa lưu."
  echo "   Gom repo là thao tác lớn — phải bắt đầu từ một cây SẠCH, không thì lỗi xảy ra"
  echo "   giữa chừng là không biết chỗ nào của ai. Chạy 'git status' xem rồi commit hoặc bỏ."
  exit 1
fi

DEST="wordpress/$DICH"
if [ -e "$DEST" ]; then
  echo "🔴 '$DEST' ĐÃ CÓ trong repo này."
  echo "   Gom đè lên là mất mã đang có mà không ai báo. Đổi tên thư mục đích, hoặc xoá cái cũ"
  echo "   bằng một lượt commit riêng trước (để còn lần ngược lại được)."
  exit 1
fi

NHANH_GOM="gom-$DICH"
REMOTE="nguon-$DICH"
URL="https://github.com/$NGUON.git"

echo "▸ Repo nguồn : $URL  (nhánh $NHANH)"
[ -n "$THUMUC_CON" ] && echo "▸ Chỉ lấy    : $THUMUC_CON"
echo "▸ Về thư mục : $DEST"
echo "▸ Trên nhánh : $NHANH_GOM"
echo
read -r -p "Đúng chưa? [y/N] " tl
[ "$tl" = "y" ] || [ "$tl" = "Y" ] || { echo "Dừng."; exit 0; }

git checkout -b "$NHANH_GOM"
git remote remove "$REMOTE" 2>/dev/null || true
git remote add "$REMOTE" "$URL"
git fetch "$REMOTE" "$NHANH"

if [ -n "$THUMUC_CON" ]; then
  # ⚠️ Chỉ lấy MỘT THƯ MỤC CON của repo nguồn: tách nó ra thành một nhánh riêng trước, rồi mới
  #    ghép. Không có bước này thì cả repo nguồn (README, .github, tools…) đổ vào đây.
  echo "▸ Tách '$THUMUC_CON' khỏi repo nguồn…"
  git subtree split --prefix="$THUMUC_CON" "$REMOTE/$NHANH" -b "tam-$DICH"
  git subtree add --prefix="$DEST" "tam-$DICH" --squash
  git branch -D "tam-$DICH"
else
  git subtree add --prefix="$DEST" "$REMOTE" "$NHANH" --squash
fi

git remote remove "$REMOTE"

echo
echo "✓ Đã gom vào $DEST trên nhánh $NHANH_GOM."
echo
echo "CÒN BA VIỆC — gom xong mới là nửa đường:"
echo
echo "  1. Nếu đó là một plugin WordPress, nối bộ tự cập nhật cho nó:"
echo "       xem docs/CAP-NHAT-TU-GITHUB.md mục 6 (bốn việc)"
echo "       chép class-*-tu-cap-nhat.php, đổi TIEN_TO thành '$DICH-v'"
echo "       khai khoá '$DICH' vào tools/build-plugin-zip.sh"
echo
echo "  2. Chạy bộ thử — kiem-tu-cap-nhat.php quét cả thư mục wordpress/, nên"
echo "     plugin mới chưa nối là nó ĐỎ ngay:"
echo "       bash tools/test/chay-het.sh"
echo
echo "  3. Xem lại rồi mới gộp vào nhánh chính:"
echo "       git log --oneline -5"
echo "       git diff --stat main...$NHANH_GOM | tail -5"
echo
echo "🔴 CHƯA XONG BƯỚC 2 THÌ ĐỪNG GỘP. Gom một plugin vào mà quên nối tự cập nhật thì nó nằm"
echo "   đây im lặng, không bao giờ hiện bản mới, và nửa năm sau không ai nhớ vì sao."
