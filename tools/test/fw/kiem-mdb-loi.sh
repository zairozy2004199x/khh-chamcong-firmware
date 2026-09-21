#!/usr/bin/env bash
# Kiểm phép ĐỔI BYTE TRẠNG THÁI MDB THÀNH MÃ LỖI, bằng g++ — không cần ESP32, không cần máy thật.
#
# VÌ SAO CẦN
#   Đổi từ đường xung sang MDB chính là để BÁO ĐƯỢC LỖI. Đường xung chỉ đếm tiền; kẹt tờ, mô-tơ
#   chết, hộp tiền rơi đều im lặng cho tới lúc khách phàn nàn. Nếu bảng đổi mã này sai thì đổi
#   sang MDB xong vẫn không được gì — mất công mà không đạt mục đích.
#
#   Hai chiều sai đều tệ, theo hai kiểu khác nhau:
#     · bỏ sót lỗi thật  -> máy hỏng mà web im, đúng cảnh đang muốn thoát khỏi
#     · báo cả thứ vặt   -> chuông reo suốt ngày rồi không ai thèm nhìn, tệ hơn không báo
#
# Trích hàm TỪ CHÍNH .ino chứ không chép lại.
#
# Chạy: bash tools/test/fw/kiem-mdb-loi.sh
set -euo pipefail
cd "$(dirname "$0")/../../.."
INO=esp32_ghe_massage/esp32_ghe_massage.ino
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

python3 - "$INO" "$TMP/trich.inc" <<'PY'
import io, re, sys
s = io.open(sys.argv[1], encoding='utf-8').read()
lay = []
for ten in ('mdbMaLoi', 'mdbLaBinhThuong'):
    m = re.search(r'\n((?:const char\*|bool) ' + ten + r'\([^)]*\)\s*\{.*?\n\})', s, re.S)
    if not m: sys.exit('KHÔNG thấy hàm %s trong .ino — đổi tên thì sửa luôn phép thử này.' % ten)
    lay.append(m.group(1))
io.open(sys.argv[2], 'w', encoding='utf-8').write('\n\n'.join(lay) + '\n')
PY

cat > "$TMP/t.cpp" <<'CPP'
#include <cstdint>
#include <cstdio>
#include <cstring>
#include "trich.inc"

static int hong = 0;
static void la(const char* ten, uint8_t z, const char* mong) {
  const char* duoc = mdbMaLoi(z);
  bool ok = mong ? (duoc && !std::strcmp(duoc, mong)) : (duoc == NULL);
  if (!ok) {
    std::printf("  HỎNG %-42s mong %-8s được %s\n", ten, mong ? mong : "(không)",
                duoc ? duoc : "(không)");
    hong++;
  }
}
static void binhThuong(const char* ten, uint8_t z, bool mong) {
  if (mdbLaBinhThuong(z) != mong) { std::printf("  HỎNG %-42s\n", ten); hong++; }
}

int main() {
  // ---- lỗi THẬT: phải báo về web ----
  la("0x01 mô-tơ hỏng",              0x01, "motor");
  la("0x02 cảm biến hỏng",           0x02, "cambien");
  la("0x04 lỗi bộ nhớ",              0x04, "rom");
  la("0x05 KẸT TỜ",                  0x05, "ket");
  la("0x08 hộp tiền rơi ra",         0x08, "hopmat");
  la("0x09 máy đang bị khoá",        0x09, "khoa");

  // ---- chuyện thường ngày: KHÔNG được báo ----
  la("0x03 máy đang bận",            0x03, NULL);
  la("0x06 vừa reset",               0x06, NULL);
  la("0x07 khách rút tờ ra",         0x07, NULL);
  la("0x0A xin escrow sai",          0x0A, NULL);
  la("0x0B từ chối tờ tiền",         0x0B, NULL);
  la("0x0C nghi rút tờ đã cộng",     0x0C, NULL);
  la("0x00 không có gì",             0x00, NULL);

  // ---- mã lạ ngoài bảng: im lặng, đừng đoán bừa ----
  la("0x33 mã lạ",                   0x33, NULL);
  la("0x7F mã lạ",                   0x7F, NULL);

  /* 0x05 PHẢI trùng mã với đường đếm xung. Web chỉ biết chuỗi "ket"; đặt tên khác là màn đối
     soát hiện hai loại lỗi cho cùng một sự cố, và bộ lọc theo mã sót mất một nửa. */
  {
    /* Kiểm NULL trước khi strcmp: bỏ sót 0x05 thì mdbMaLoi trả NULL, và strcmp(NULL,...) làm
       sập chương trình. Sập vẫn tính là "bắt được", nhưng người đọc chỉ thấy Segmentation
       fault chứ không thấy hỏng cái gì — phép thử phải nói ra được điều nó vừa bắt. */
    const char* m = mdbMaLoi(0x05);
    if (!m) {
      std::printf("  HỎNG kẹt tờ 0x05 KHÔNG được nhận ra là lỗi\n"); hong++;
    } else if (std::strcmp(m, "ket") != 0) {
      std::printf("  HỎNG kẹt tờ MDB dùng mã \"%s\", phải dùng chung \"ket\" với đường xung\n", m);
      hong++;
    }
  }

  // ---- trạng thái nào chứng tỏ máy còn cử động -> xoá cờ lỗi ----
  binhThuong("0x03 bận  -> còn cử động",        0x03, true);
  binhThuong("0x06 reset -> còn cử động",       0x06, true);
  binhThuong("0x07 rút tờ -> còn cử động",      0x07, true);
  binhThuong("0x0B từ chối -> còn cử động",     0x0B, true);
  binhThuong("0x05 kẹt   -> KHÔNG phải bình thường", 0x05, false);
  binhThuong("0x01 mô-tơ -> KHÔNG phải bình thường", 0x01, false);

  /* Không byte nào được vừa là lỗi vừa là bình thường — hai nhánh trong mã gọi liên tiếp nhau,
     trùng là cờ lỗi bật rồi tắt ngay trong cùng một vòng, web nhấp nháy vô nghĩa. */
  for (int z = 0; z < 256; z++) {
    if (mdbMaLoi((uint8_t)z) && mdbLaBinhThuong((uint8_t)z)) {
      std::printf("  HỎNG 0x%02X vừa là lỗi vừa là bình thường\n", z); hong++;
    }
  }

  if (!hong) { std::printf("  đổi mã lỗi MDB: SẠCH (21 phép + quét đủ 256 byte)\n"); }
  return hong ? 1 : 0;
}
CPP

cp "$TMP/trich.inc" "$TMP/trich.inc.bak"
g++ -std=c++17 -I"$TMP" -o "$TMP/t" "$TMP/t.cpp"
"$TMP/t"

thu_nguoc() {
  local ten="$1" sed_lenh="$2"
  sed "$sed_lenh" "$TMP/trich.inc.bak" > "$TMP/trich.inc"
  g++ -std=c++17 -I"$TMP" -o "$TMP/tx" "$TMP/t.cpp" 2>/dev/null || { echo "  thử ngược ($ten): không dịch được -> coi như bắt được"; return; }
  if "$TMP/tx" >/dev/null 2>&1; then
    echo "  ⚠ PHÉP THỬ VÔ DỤNG: $ten mà vẫn báo sạch"; exit 1
  else
    echo "  thử ngược: $ten → BẮT ĐƯỢC, tốt"
  fi
}

thu_nguoc "kẹt tờ đặt tên riêng thay vì dùng chung \"ket\"" 's/case 0x05: return "ket";/case 0x05: return "mdbket";/'
thu_nguoc "báo cả tờ bị từ chối (0x0B) thành lỗi"          's/case 0x09: return "khoa";/case 0x09: return "khoa";\n    case 0x0B: return "tuchoi";/'
thu_nguoc "bỏ sót kẹt tờ"                                  's/case 0x05: return "ket";//'
