#!/usr/bin/env bash
# Kiểm phép ĐỌC BYTE CỦA ICT trong firmware ghế, bằng g++ — không cần ESP32, không cần máy thật.
#
# GIAO THỨC (dò 24/08/2026 bằng cách nạp tờ thật rồi đọc thẳng cổng ICT — xem docs/GIAO-THUC-BO-GHE.md)
#     81  (0x40+kênh)  10     NHẬN được    kênh 1=10k · 2=20k · 3=50k · 4=100k
#     81  (0x40+kênh)  29     NHẢ ra       khách đút vướng — KHÔNG có tiền
#     29  2F                  khách giựt tiền lại, hoặc nhét giấy — KHÔNG có byte mở đầu
#     25                      kẹt tờ
#     2F                      sẵn sàng lại
#
# VÌ SAO CẦN PHÉP THỬ NÀY
#   · 0x25 và 0x2F chỉ khác nhau vài bit; đảo nhầm là web báo kẹt khi máy đang chạy tốt, hoặc
#     tệ hơn, im lặng khi máy đang nuốt tiền của khách.
#   · 0x29 (nhả ra) rất dễ bị coi là "xong một lượt bình thường". Hai byte ĐẦU của ca nhận và
#     ca nhả giống hệt nhau — chỉ byte cuối phân biệt.
#   · 0x29 còn đến MỘT MÌNH, không có 81 mở đầu, khi khách giựt tờ lại hoặc nhét giấy. Lúc đó
#     máy không kẹt và cũng không có tiền — nhận nhầm thành kẹt là web treo cảnh báo oan.
#
# Trích hàm TỪ CHÍNH .ino chứ không chép lại — chép là sớm muộn lệch với firmware đang chạy.
#
# Chạy: bash tools/test/fw/kiem-ket-tien.sh
set -euo pipefail
cd "$(dirname "$0")/../../.."
INO=esp32_ghe_massage/esp32_ghe_massage.ino
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

python3 - "$INO" "$TMP/trich.inc" <<'PY'
import io, re, sys
s = io.open(sys.argv[1], encoding='utf-8').read()
lay = []
for ten in ('ICT_KET', 'ICT_HET', 'ICT_NHA', 'ICT_MO_DAU', 'ICT_KET_THUC'):
    m = re.search(r'^#define\s+' + ten + r'\s+\S+', s, re.M)
    if not m: sys.exit('KHÔNG thấy #define %s trong .ino' % ten)
    lay.append(m.group(0).split('//')[0].rstrip())
m = re.search(r'\n(int ictXetByte\([^)]*\)\s*\{.*?\n\})', s, re.S)
if not m: sys.exit('KHÔNG thấy hàm ictXetByte trong .ino — đổi tên thì sửa luôn phép thử này.')
lay.append(m.group(1))
io.open(sys.argv[2], 'w', encoding='utf-8').write('\n'.join(lay) + '\n')
PY

cat > "$TMP/t.cpp" <<'CPP'
#include <cstdint>
#include <cstdio>
#include "trich.inc"

static int hong = 0;
static void teq(const char* ten, int mong, int nhan) {
  if (mong != nhan) { std::printf("  HỎNG %-46s mong %d, được %d\n", ten, mong, nhan); hong++; }
}

int main() {
  //  1 = vừa KẸT · -1 = vừa HẾT kẹt · 0 = byte khác
  teq("0x25 = kẹt tờ",                     1, ictXetByte(ICT_KET));
  teq("0x2F = sẵn sàng lại",              -1, ictXetByte(ICT_HET));
  teq("0x10 = nuốt xong -> máy vẫn chạy", -1, ictXetByte(ICT_KET_THUC));
  teq("0x29 = nhả ra  -> máy vẫn chạy",   -1, ictXetByte(ICT_NHA));

  // byte mở đầu và mã mệnh giá KHÔNG được đụng tới cờ kẹt
  teq("0x81 mở đầu: không đổi gì",         0, ictXetByte(ICT_MO_DAU));
  teq("0x41 (10k):  không đổi gì",         0, ictXetByte(0x41));
  teq("0x42 (20k):  không đổi gì",         0, ictXetByte(0x42));
  teq("0x43 (50k):  không đổi gì",         0, ictXetByte(0x43));
  teq("0x44 (100k): không đổi gì",         0, ictXetByte(0x44));

  // gai nhiễu hay ra dạng toàn bit 1 — không được nhận nhầm thành lệnh nào
  teq("gai 0xFF: không đổi gì",            0, ictXetByte(0xFF));
  teq("gai 0xFE: không đổi gì",            0, ictXetByte(0xFE));
  teq("gai 0xF8: không đổi gì",            0, ictXetByte(0xF8));
  teq("0x00: không đổi gì",                0, ictXetByte(0x00));

  // khách giựt tiền lại / nhét giấy: 29 rồi 2F, KHÔNG có 81 mở đầu. Đo được cả hai tình
  // huống, cùng một dấu vết. Máy không kẹt -> tuyệt đối không được trả 1.
  teq("giựt lại: 0x29 đứng một mình",     -1, ictXetByte(0x29));
  teq("giựt lại: 0x2F theo sau",          -1, ictXetByte(0x2F));

  // 0x25 và 0x2F chỉ khác vài bit — canh đúng chiều, đảo nhầm là báo ngược
  if (ICT_KET == ICT_HET) { std::printf("  HỎNG kẹt và hết kẹt trùng mã\n"); hong++; }
  teq("kẹt rồi hết: cờ đảo đúng chiều",   -1, ictXetByte(ICT_HET));

  if (!hong) { std::printf("  đọc byte ICT: SẠCH (17 phép)\n"); }
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

thu_nguoc "đảo kẹt/hết kẹt"          's/if(b == ICT_KET) return 1;/if(b == ICT_KET) return -1;/'
thu_nguoc "coi 0x29 là không có gì"  's/if(b == ICT_KET_THUC || b == ICT_NHA) return -1;/if(b == ICT_KET_THUC) return -1;/'
thu_nguoc "nhận nhầm gai 0xFF"       's/if(b == ICT_KET) return 1;/if(b == ICT_KET || b == 0xFF) return 1;/'
