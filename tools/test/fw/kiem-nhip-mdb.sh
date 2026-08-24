#!/usr/bin/env bash
# Kiểm phép TÍNH MỐC BIT của bản giả lập MDB bằng g++ — không cần ESP32, không cần bo ghế.
#
# Vì sao cần: ở 9600 baud một bit rộng 104,1667 µs — KHÔNG tròn. Nếu tính mốc bằng cách
# cộng dồn 104 µs mười một lần thì tới bit stop đã lệch 1,8 µs; cộng dồn qua vài khung là
# lấy mẫu trượt sang bit bên cạnh, đọc ra byte sai mà nhìn dạng sóng vẫn thấy "đẹp".
# Nên mã dùng phân số BIT_x100 và mốc TUYỆT ĐỐI. Phép thử này canh đúng chỗ đó.
#
# Trích thẳng từ .ino chứ không chép lại — chép là sớm muộn lệch với firmware.
#
# Chạy: bash tools/test/fw/kiem-nhip-mdb.sh
set -euo pipefail
cd "$(dirname "$0")/../../.."
INO=esp32_ghe_gia_lap_mdb/esp32_ghe_gia_lap_mdb.ino
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

python3 - "$INO" "$TMP/trich.inc" <<'PY'
import io, re, sys
s = io.open(sys.argv[1], encoding='utf-8').read()
lay = []
m = re.search(r'static const uint32_t BIT_x100\s*=\s*\d+;', s)
if not m: sys.exit('KHÔNG thấy BIT_x100 trong .ino')
lay.append(m.group(0))
for ten in ('mocDau', 'mocGiua'):
    m = re.search(r'static inline uint32_t ' + ten + r'\(uint8_t k\)\s*\{[^}]*\}', s)
    if not m: sys.exit('KHÔNG thấy hàm %s trong .ino' % ten)
    lay.append(m.group(0))
io.open(sys.argv[2], 'w', encoding='utf-8').write('\n'.join(lay) + '\n')
PY

cat > "$TMP/t.cpp" <<'CPP'
#include <cstdint>
#include <cstdio>
#include <cmath>
#include "trich.inc"

static int hong = 0;
static void can(const char* ten, double thuc, double mong, double dung_sai) {
  double lech = std::fabs(thuc - mong);
  if (lech > dung_sai) {
    std::printf("  HỎNG %-28s được %.2f, mong %.2f (lệch %.2f > %.2f)\n",
                ten, thuc, mong, lech, dung_sai);
    hong++;
  }
}

int main() {
  const double BIT = 1e6 / 9600.0;          // 104,1667 µs

  // 1) mốc đầu mỗi bit phải bám sát bội số thật, kể cả ở bit cuối khung
  for (int k = 0; k <= 11; k++) {
    char t[64]; std::snprintf(t, sizeof t, "mocDau(%d)", k);
    can(t, mocDau(k), BIT * k, 1.0);
  }

  // 2) mốc lấy mẫu phải nằm GIỮA bit, không được trôi ra mép
  for (int k = 0; k <= 10; k++) {
    char t[64]; std::snprintf(t, sizeof t, "mocGiua(%d)", k);
    can(t, mocGiua(k), BIT * (k + 0.5), 1.0);
    double cach_mep = std::fabs(mocGiua(k) - BIT * k);
    if (cach_mep < BIT * 0.35 || cach_mep > BIT * 0.65) {
      std::printf("  HỎNG mocGiua(%d) cách mép %.1f µs — phải quanh giữa bit (%.1f)\n",
                  k, cach_mep, BIT / 2);
      hong++;
    }
  }

  // 3) chỗ dễ sai nhất: cộng dồn thay vì mốc tuyệt đối.
  //    Nếu ai đó đổi mocDau thành cộng dồn 104 µs thì bit stop lệch ~1,8 µs — phép trên bắt được.
  //    Ở đây canh thêm: sai số KHÔNG được lớn dần theo k.
  double lech_dau = std::fabs(mocDau(1) - BIT * 1);
  double lech_cuoi = std::fabs(mocDau(11) - BIT * 11);
  if (lech_cuoi > lech_dau + 1.0) {
    std::printf("  HỎNG sai số dồn theo khung: bit 1 lệch %.2f, bit 11 lệch %.2f\n",
                lech_dau, lech_cuoi);
    hong++;
  }

  if (hong == 0) { std::printf("  nhịp bit MDB: SẠCH (12 mốc đầu + 11 mốc giữa + phép dồn sai số)\n"); }
  return hong ? 1 : 0;
}
CPP

cp "$TMP/trich.inc" "$TMP/trich.inc.bak"
g++ -std=c++17 -I"$TMP" -o "$TMP/t" "$TMP/t.cpp"
"$TMP/t"

# ——— thử ngược: phá mã cho HỎNG, phép thử phải bắt được ———
sed 's/static const uint32_t BIT_x100 = 10417;/static const uint32_t BIT_x100 = 10400;/' \
    "$TMP/trich.inc.bak" > "$TMP/trich.inc"
g++ -std=c++17 -I"$TMP" -o "$TMP/t2" "$TMP/t.cpp"
if "$TMP/t2" >/dev/null 2>&1; then
  echo "  ⚠ PHÉP THỬ VÔ DỤNG: bẻ BIT_x100 thành 10400 mà vẫn báo sạch"
  exit 1
else
  echo "  thử ngược: bẻ nhịp thành 104,00 µs → phép thử BẮT ĐƯỢC, tốt"
fi

# ——— chân nghe phải KHỚP giữa ba nơi, không thì đấu dây một kiểu chạy một kiểu ———
n_giaLap=$(grep -oP '#define CHAN_NGHE\s+\K\d+' esp32_ghe_gia_lap_mdb/esp32_ghe_gia_lap_mdb.ino)
n_nghe=$(grep -oP '#define CHAN_NGHE\s+\K\d+'    esp32_ghe_nghe_bo/esp32_ghe_nghe_bo.ino)
n_chinh=$(grep -oP '#define MDB_RX_PIN\s+\K\d+'  esp32_ghe_massage/esp32_ghe_massage.ino)
if [ "$n_giaLap" = "$n_nghe" ] && [ "$n_nghe" = "$n_chinh" ]; then
  echo "  chân nghe khớp cả ba nơi: GPIO $n_giaLap"
else
  echo "  ⚠ CHÂN NGHE LỆCH — giả lập:$n_giaLap  dò:$n_nghe  firmware ghế:$n_chinh"
  exit 1
fi
