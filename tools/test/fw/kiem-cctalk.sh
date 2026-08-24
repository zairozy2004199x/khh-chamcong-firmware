#!/usr/bin/env bash
# Canh phép NHẬN DẠNG ccTalk của bản dò — bằng g++, không cần ESP32.
#
# Vì sao có tệp này: 24/08/2026 bản dò báo "ccTalk — kết luận CHẮC" trong khi thật ra chân
# đọc đang kẹt mức thấp và cả 401 byte đều là 00. ccTalk kiểm bằng tổng byte chia hết 256;
# tổng của một đống 00 là 0, mà 0 thì chia hết 256 — nên MỌI đoạn rỗng đều "khớp checksum".
# Dương tính giả này suýt dẫn cả buổi đi sai hướng.
#
# Trích thẳng từ .ino chứ không chép lại.
#
# Chạy: bash tools/test/fw/kiem-cctalk.sh
set -euo pipefail
cd "$(dirname "$0")/../../.."
INO=esp32_ghe_nghe_bo/esp32_ghe_nghe_bo.ino
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

python3 - "$INO" "$TMP/trich.inc" <<'PY'
import io, re, sys
s = io.open(sys.argv[1], encoding='utf-8').read()
lay = []
for ten in ('doCcTalk', 'moiByteGiongNhau'):
    m = re.search(r'\n(?:int|bool) ' + ten + r'\(\)\{.*?\n\}\n', s, re.S)
    if not m:
        sys.exit('KHÔNG tìm thấy hàm %s trong .ino — đổi tên thì sửa luôn phép thử này.' % ten)
    lay.append(m.group(0))
io.open(sys.argv[2], 'w', encoding='utf-8').write(''.join(lay))
PY

cat > "$TMP/t.cpp" <<'CPP'
#include <cstdint>
#include <cstdio>
#include <cstring>
#include <vector>

static const int BO_DEM = 512;
static uint8_t  g_bo[BO_DEM];
static uint16_t g_soBo = 0;

#include "trich.inc"

static int hong = 0;
static void nap(const std::vector<uint8_t>& v) {
  g_soBo = 0;
  for (uint8_t b : v) { if (g_soBo < BO_DEM) g_bo[g_soBo++] = b; }
}
static void can(const char* ten, int duoc, int mong) {
  if (duoc != mong) { std::printf("  HỎNG %-42s được %d, mong %d\n", ten, duoc, mong); hong++; }
}

// dựng một khung ccTalk thật: [đích][số data][nguồn][mã lệnh][data…][checksum]
static std::vector<uint8_t> khung(uint8_t dich, uint8_t nguon, uint8_t lenh,
                                  std::vector<uint8_t> data) {
  std::vector<uint8_t> k{dich, (uint8_t)data.size(), nguon, lenh};
  for (uint8_t d : data) k.push_back(d);
  uint8_t t = 0;
  for (uint8_t b : k) t += b;
  k.push_back((uint8_t)(256 - t));      // byte cuối kéo tổng về 0
  return k;
}

int main() {
  // 1) chuỗi toàn 00 — ĐÂY LÀ CA ĐÃ LỪA ĐƯỢC BẢN CŨ. Phải trả về 0.
  nap(std::vector<uint8_t>(64, 0x00));
  can("chuỗi toàn 00 không được tính là ccTalk", doCcTalk(), 0);
  can("chuỗi toàn 00 bị bắt là kẹt mức", moiByteGiongNhau() ? 1 : 0, 1);

  // 2) chuỗi toàn FF cũng vậy — cùng bệnh, khác mức
  nap(std::vector<uint8_t>(64, 0xFF));
  can("chuỗi toàn FF bị bắt là kẹt mức", moiByteGiongNhau() ? 1 : 0, 1);

  // 3) khung ccTalk THẬT phải được nhận
  auto k1 = khung(2, 1, 254, {});             // "đầu dò còn sống?"
  nap(k1);
  can("một khung ccTalk thật", doCcTalk(), 1);
  can("khung thật không bị coi là kẹt mức", moiByteGiongNhau() ? 1 : 0, 0);

  // 4) ba khung liên tiếp
  auto k2 = khung(2, 1, 245, {0x11});
  auto k3 = khung(1, 2, 0, {0x22, 0x33});
  std::vector<uint8_t> ba;
  for (auto* k : {&k1, &k2, &k3}) ba.insert(ba.end(), k->begin(), k->end());
  nap(ba);
  can("ba khung ccTalk liên tiếp", doCcTalk(), 3);

  // 5) khung bị sai một byte thì không được khớp nữa
  ba[2] ^= 0x01;
  nap(ba);
  if (doCcTalk() >= 3) { std::printf("  HỎNG bẻ một byte mà vẫn khớp đủ 3 khung\n"); hong++; }

  // 6) dữ liệu ngẫu nhiên đều đặn: thi thoảng khớp là bình thường, nhưng không được khớp dày
  std::vector<uint8_t> ngau;
  uint32_t x = 12345;
  for (int i = 0; i < 256; i++) { x = x * 1103515245u + 12345u; ngau.push_back((x >> 16) & 0xFF); }
  nap(ngau);
  int kn = doCcTalk();
  if (kn > 8) { std::printf("  HỎNG dữ liệu ngẫu nhiên khớp tới %d khung — ngưỡng quá lỏng\n", kn); hong++; }

  if (hong == 0) { std::printf("  nhận dạng ccTalk: SẠCH (9 phép, có cả ca chuỗi rỗng)\n"); }
  return hong ? 1 : 0;
}
CPP

cp "$TMP/trich.inc" "$TMP/trich.inc.bak"
g++ -std=c++17 -I"$TMP" -o "$TMP/t" "$TMP/t.cpp"
"$TMP/t"

# ——— thử ngược: gỡ đúng cái chốt vừa thêm, phép thử phải bắt được ———
sed 's/if(tong == 0 \&\& co_khac){/if(tong == 0){/' "$TMP/trich.inc.bak" > "$TMP/trich.inc"
g++ -std=c++17 -I"$TMP" -o "$TMP/t2" "$TMP/t.cpp"
if "$TMP/t2" >/dev/null 2>&1; then
  echo "  ⚠ PHÉP THỬ VÔ DỤNG: gỡ chốt co_khac mà vẫn báo sạch"
  exit 1
else
  echo "  thử ngược: gỡ chốt 'byte phải khác nhau' → phép thử BẮT ĐƯỢC, tốt"
fi
