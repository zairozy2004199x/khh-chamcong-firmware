#!/usr/bin/env bash
# ══════════════════════════════════════════════════════════════════════════════════════════════
# KHÔNG TỆP NÀO TRONG BỘ CÀI ĐƯỢC MANG TÊN LÀ MỘT MẨU MÃ NGUỒN.
#
# 🔴 LỖI CÂM PHÁT HIỆN 22/09/2026 — VÀ NÓ ĐÃ NẰM TRONG BẢN CÀI SUỐT BA BẢN.
#    Trong `wordpress/vhcp-chi-phi/` có SÁU tệp mang tên kiểu:
#        <tab><tab><tab><tab>'cot'     => 'giai_doan',
#    Chúng là bản sao y hệt của `includes/class-vhcp-truc.php`, đẻ ra từ một lượt gõ lệnh có dấu
#    `>` KHÔNG ĐƯỢC BỌC NHÁY khi làm bản 1.284.0: shell đọc `=>` thành lệnh ghi tệp, và tên tệp
#    chính là phần còn lại của mẩu mã. Rồi một lượt `git add` rộng tay đưa cả sáu vào kho.
#
#    Từ đó `tach-ban-vung.sh` chép nguyên `cp -r` sang cả ba bản vùng (6 × 4 = 24 tệp), và
#    `build-plugin-zip.sh` đóng luôn vào .zip. Anh Thắng cài lên WordPress là chúng nằm trong
#    thư mục plugin thật.
#
# 🔴 VÌ SAO KHÔNG AI THẤY. Chúng không phải `.php` nên WordPress không nạp; không phải `.php`
#    nên `php -l` của `chay-het.sh` bỏ qua; tên có tab nên `ls` in ra trông như mấy dòng mã lạc
#    giữa danh sách thư mục, và mắt người đọc thành "à, chú thích". Không một bài kiểm nào hỏi
#    "thư mục này có tệp nào KHÔNG NÊN CÓ không" — mọi bài đều hỏi "tệp cần có, có chưa".
#
# ⚠️ CANH BẰNG LUẬT VỀ TÊN, KHÔNG PHẢI DANH SÁCH SÁU CÁI TÊN ẤY. Lần sau dấu `>` lọt ở một lệnh
#    khác thì tên rác sẽ khác hẳn; điều KHÔNG đổi là nó chứa thứ không bao giờ có trong một tên
#    tệp thật: ký tự xuống dòng, ký tự tab, hoặc `=>`.
#
# Chạy: bash tools/test/kiem-ten-tep-sach.sh
# ══════════════════════════════════════════════════════════════════════════════════════════════
set -u
cd "$(dirname "$0")/../.." || exit 2

# Soát mọi thứ sẽ đi vào bản cài. `goc/` bị loại khỏi .zip nên không tính; `.git` cũng vậy.
RAC=$(find wordpress apps-script -depth \
        \( -name '.git' -o -name 'goc' -o -name 'node_modules' \) -prune -o \
        -print 2>/dev/null \
      | grep -P '/[^/]*(\t|=>)' || true)

if [ -n "$RAC" ]; then
  echo "✗ CÓ TỆP MANG TÊN LÀ MỘT MẨU MÃ NGUỒN — gần như chắc chắn do dấu '>' lọt ra ngoài nháy:"
  printf '%s\n' "$RAC" | cat -A | sed 's/^/    · /'
  echo
  echo "  Xoá chúng đi, rồi tìm lại lệnh đã đẻ ra chúng và bọc nháy cho tham số có dấu '>'."
  echo "  ⚠️ Nhớ xoá ở CẢ bản gốc lẫn ba bản vùng — 'tach-ban-vung.sh' chép bằng 'cp -r'."
  exit 1
fi

# ⚠️ Và một phép ĐỐI CHỨNG: bài này phải thật sự soi thấy tệp. Không có nó thì một câu `find`
#    gõ sai đường dẫn cũng "sạch", và bài kiểm thành một dòng chữ xanh vô nghĩa.
DEM=$(find wordpress apps-script -type f 2>/dev/null | wc -l)
if [ "$DEM" -lt 100 ]; then
  echo "✗ ĐỐI CHỨNG TRƯỢT: chỉ soi thấy $DEM tệp — câu 'find' đang trỏ sai chỗ, phép trên xanh rỗng."
  exit 1
fi

echo "✓ SẠCH — $DEM tệp, không tệp nào mang tên là một mẩu mã nguồn."
