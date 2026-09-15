#!/usr/bin/env bash
# ══════════════════════════════════════════════════════════════════════════════════════════════
# SOÁT CÚ PHÁP MỌI KHỐI JS NẰM TRONG HEREDOC PHP.
#
# 🔴 VÌ SAO CẦN (CLAUDE.md §2). PHP KHÔNG kiểm cú pháp bên trong `<<<'JS' … JS;` — một dấu `}`
#    thừa là TRẮNG CẢ TRANG mà `php -l` vẫn báo sạch (đã dính ở 2.20.0, vá ở 2.20.1).
#
# 🔴 VÌ SAO LÀ SCRIPT CHỨ KHÔNG GÕ TAY. Trước đây soát bằng cách đếm tay hai khối của
#    class-vhg-trang.php. Ngày 15/09/2026 tệp ấy có khối THỨ BA (trang /it) — lệnh gõ tay cũ
#    vẫn "xanh" trong khi khối mới chưa hề được kiểm. Script tự tìm nên thêm khối bao nhiêu
#    cũng không sót.
#
# Chạy: bash tools/soat-js-heredoc.sh
# Trả mã thoát khác 0 nếu có khối nào sai cú pháp — dùng được cho CI.
# ══════════════════════════════════════════════════════════════════════════════════════════════
set -u
cd "$(dirname "$0")/.." || exit 2

command -v node >/dev/null 2>&1 || { echo "✗ không có node để kiểm JS"; exit 2; }

TMP=$(mktemp -d) || exit 2
trap 'rm -rf "$TMP"' EXIT
SO=0; HONG=0

while IFS= read -r f; do
  # Mỗi dòng mở `<<<'JS'` là một khối; kết thúc ở dòng chỉ có `JS;`.
  while IFS=: read -r mo _; do
    [ -n "$mo" ] || continue
    dong=$(awk -v s="$mo" 'NR>s && /^[[:space:]]*JS;[[:space:]]*$/ {print NR; exit}' "$f")
    [ -n "$dong" ] || { echo "  ✗ $f:$mo — mở heredoc JS mà không thấy dòng đóng 'JS;'"; HONG=$((HONG+1)); continue; }
    SO=$((SO+1))
    out="$TMP/k$SO.js"
    sed -n "$((mo+1)),$((dong-1))p" "$f" > "$out"
    if err=$(node --check "$out" 2>&1); then
      printf '  ✓ %s dòng %s–%s\n' "$f" "$((mo+1))" "$((dong-1))"
    else
      printf '  ✗ %s dòng %s–%s\n' "$f" "$((mo+1))" "$((dong-1))"
      printf '%s\n' "$err" | head -5 | sed 's/^/      /'
      HONG=$((HONG+1))
    fi
  done < <(grep -n "<<<'JS'" "$f" || true)
done < <(grep -rl "<<<'JS'" --include='*.php' . 2>/dev/null | grep -v '/class-vhg-baocao-v')

echo "───────────────────────────────────────────────────────────────"
if [ "$HONG" -gt 0 ]; then echo "✗ HỎNG $HONG / $SO khối JS"; exit 1; fi
echo "✓ SẠCH — $SO khối JS heredoc đều đúng cú pháp"
