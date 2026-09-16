#!/usr/bin/env bash
# ══════════════════════════════════════════════════════════════════════════════════════════════
# KHOÁ MÀ LUỒNG PHÁT HÀNH TRUYỀN VÀO PHẢI TRA RA ĐƯỢC — CHO MỌI BỘ.
#
# 🔴 LỖI CÂM PHÁT HIỆN 16/09/2026: `khh-doanh-thu` CHƯA TỪNG PHÁT HÀNH ĐƯỢC LẦN NÀO.
#
#    `.github/workflows/phat-hanh.yml` gọi `build-plugin-zip.sh "${thumuc#vhcp-}"`. Bộ nào tên thư
#    mục mang tiền tố `vhcp-` thì cắt ra đúng khoá gõ tay; còn `khh-doanh-thu` KHÔNG mang tiền tố
#    ấy nên nó truyền nguyên `khh-doanh-thu`, mà khoá gõ tay lại là `doanh-thu`.
#
#    Rơi vào nhánh "không hiểu tham số" -> workflow ghi một dòng "chưa khai khoá, bỏ qua" giữa log
#    rồi ĐI TIẾP và vẫn xanh -> bộ ấy không có lấy một tag nào trên Releases, và bộ tự cập nhật
#    của nó (đọc Releases) chưa bao giờ thấy bản mới. Không ai phát hiện suốt từ ngày nó ra đời,
#    vì luồng "bỏ qua" chứ không "hỏng".
#
# ⚠️ CHỐT CHỐNG SÓT trong build-plugin-zip.sh KHÔNG bắt được ca này: nó soát "thư mục có được
#    đóng gói ở đâu đó không", còn đây là "khoá workflow truyền vào có tra ra không" — hai câu
#    hỏi khác nhau, và ca này lọt đúng khe giữa chúng.
#
# ⚠️ CHẠY SCRIPT THẬT, không mô phỏng lại luật tra khoá. Chép luật sang đây là bài thử canh chính
#    nó. Ghi ra thư mục tạm qua VHCP_DIST_DIR nên không đụng bản cài trong dist/.
#
# Chạy: bash tools/test/kiem-khoa-dong-goi.sh
# ══════════════════════════════════════════════════════════════════════════════════════════════
set -u
cd "$(dirname "$0")/../.." || exit 2

# Bộ cố ý KHÔNG đóng gói — giữ đồng bộ với KHONG_DONG_GOI trong build-plugin-zip.sh.
BO_QUA="vhcp-cong"

TMP=$(mktemp -d) || exit 2
trap 'rm -rf "$TMP"' EXIT
DAT=0; HONG=0; TEN_HONG=()

for d in wordpress/*/; do
  ten="$(basename "$d")"
  [ -f "$d/$ten.php" ] || continue
  case " $BO_QUA " in *" $ten "*) continue ;; esac
  khoa="${ten#vhcp-}"                      # ĐÚNG phép cắt mà workflow đang dùng
  if VHCP_DIST_DIR="$TMP" bash tools/build-plugin-zip.sh "$khoa" >/dev/null 2>&1; then
    if [ -f "$TMP/$ten.zip" ]; then
      printf '  ✓ %-22s khoá "%s"\n' "$ten" "$khoa"; DAT=$((DAT+1))
    else
      printf '  ✗ %-22s khoá "%s" chạy xong mà KHÔNG ra %s.zip\n' "$ten" "$khoa" "$ten"
      HONG=$((HONG+1)); TEN_HONG+=("$ten")
    fi
  else
    printf '  ✗ %-22s khoá "%s" KHÔNG tra ra được — workflow sẽ lặng lẽ bỏ qua bộ này\n' "$ten" "$khoa"
    HONG=$((HONG+1)); TEN_HONG+=("$ten")
  fi
done

echo "───────────────────────────────────────────────────────────────"
if [ "$HONG" -gt 0 ]; then
  echo "✗ HỎNG $HONG / $((DAT+HONG)):"
  for x in "${TEN_HONG[@]}"; do echo "    · $x"; done
  exit 1
fi
echo "✓ SẠCH — $DAT bộ, khoá của luồng phát hành đều tra ra được."
