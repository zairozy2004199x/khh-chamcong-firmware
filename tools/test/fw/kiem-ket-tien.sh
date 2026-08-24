#!/usr/bin/env bash
# Kiểm phép GOM 0xF8 -> báo kẹt tiền, bằng g++ — không cần ESP32, không cần bo ghế.
#
# VÌ SAO CẦN
# 0xF8 là bo ghế báo kẹt tờ (dò và xác nhận 24/08/2026). Nhưng nó nằm cùng họ với 0xFF/0xFE mà
# gai nhiễu hay sinh ra, nên KHÔNG được báo ngay lần đầu: phải thấy đủ số lần trong một cửa sổ.
# Hai chỗ dễ sai và đều im lặng:
#   - cộng dồn thay vì cửa sổ TRƯỢT: hai gai cách nhau nửa tiếng cũng thành "kẹt"
#   - quên xoá cờ khi bo ghế im lại: web báo kẹt mãi dù tờ đã gỡ
# Báo động giả thì người ta học cách bỏ qua cảnh báo — tệ hơn không báo.
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
for ten in ('BO_GHE_KET', 'BO_GHE_KET_LAN', 'BO_GHE_KET_CUA_MS', 'BO_GHE_HET_MS'):
    m = re.search(r'^#define\s+' + ten + r'\s+\S+', s, re.M)
    if not m: sys.exit('KHÔNG thấy #define %s trong .ino' % ten)
    lay.append(m.group(0).split('//')[0].rstrip())
for ten in ('g_ketLan', 'g_ketDau', 'g_ketCuoi'):
    # kiểu có thể nhiều từ ("unsigned long"), nên không dùng \w+ đơn
    m = re.search(r'^static\s+[A-Za-z_][\w\s]*?\b' + ten + r'\s*=\s*[^;]*;', s, re.M)
    if not m: sys.exit('KHÔNG thấy biến %s trong .ino' % ten)
    lay.append(m.group(0))
for ten in ('boGheDemKet', 'boGheHetKet'):
    m = re.search(r'\n(bool ' + ten + r'\([^)]*\)\s*\{.*?\n\})', s, re.S)
    if not m: sys.exit('KHÔNG thấy hàm %s trong .ino — đổi tên thì sửa luôn phép thử này.' % ten)
    lay.append(m.group(1))
io.open(sys.argv[2], 'w', encoding='utf-8').write('\n'.join(lay) + '\n')
PY

cat > "$TMP/t.cpp" <<'CPP'
#include <cstdint>
#include <cstdio>
#include "trich.inc"

static int hong = 0;
static void t(const char* ten, bool dieu) {
  if (!dieu) { std::printf("  HỎNG %s\n", ten); hong++; }
}
static void datLai() { g_ketLan = 0; g_ketDau = 0; g_ketCuoi = 0; }

int main() {
  const uint8_t KET = BO_GHE_KET, THUONG = 0x00, GAI = 0xFF;

  // 1) MỘT lần 0xF8 thì CHƯA báo — có thể chỉ là gai
  datLai();
  t("một lần 0xF8 chưa báo", !boGheDemKet(KET, 1000));

  // 2) đủ số lần trong cửa sổ thì BÁO
  datLai();
  boGheDemKet(KET, 1000);
  t("đủ số lần trong cửa sổ thì báo", boGheDemKet(KET, 20000));

  // 3) byte khác không tính
  datLai();
  t("byte thường không tính", !boGheDemKet(THUONG, 1000));
  t("gai 0xFF không tính", !boGheDemKet(GAI, 2000));
  t("hai byte lạ rồi một 0xF8 vẫn chưa đủ", !boGheDemKet(KET, 3000));

  // 4) CỬA SỔ TRƯỢT: hai lần cách nhau QUÁ XA thì không được cộng dồn
  datLai();
  boGheDemKet(KET, 1000);
  t("hai lần cách quá cửa sổ thì KHÔNG báo",
    !boGheDemKet(KET, 1000 + BO_GHE_KET_CUA_MS + 1));
  // và lần đó phải mở cửa sổ MỚI, nên thêm một lần nữa trong cửa sổ mới là báo
  t("nhưng mở cửa sổ mới, lần kế tiếp thì báo",
    boGheDemKet(KET, 1000 + BO_GHE_KET_CUA_MS + 2));

  // 5) HẾT KẸT: im đủ lâu thì trả true đúng MỘT lần
  datLai();
  boGheDemKet(KET, 1000);
  boGheDemKet(KET, 2000);
  t("chưa im đủ lâu thì chưa coi là hết", !boGheHetKet(2000 + BO_GHE_HET_MS));
  t("im đủ lâu thì báo hết", boGheHetKet(2000 + BO_GHE_HET_MS + 1));
  t("và chỉ báo hết ĐÚNG MỘT LẦN", !boGheHetKet(2000 + BO_GHE_HET_MS + 2));

  // 6) sau khi hết kẹt, bộ đếm phải sạch — không thì một 0xF8 lẻ sau đó báo kẹt ngay
  t("sau khi hết, một lần 0xF8 chưa báo lại", !boGheDemKet(KET, 999999));

  // 7) chưa từng thấy 0xF8 thì không bao giờ "hết kẹt"
  datLai();
  t("chưa kẹt thì không báo hết", !boGheHetKet(999999));

  if (!hong) { std::printf("  gom 0xF8 -> kẹt tiền: SẠCH (11 phép)\n"); }
  return hong ? 1 : 0;
}
CPP

cp "$TMP/trich.inc" "$TMP/trich.inc.bak"
g++ -std=c++17 -I"$TMP" -o "$TMP/t" "$TMP/t.cpp"
"$TMP/t"

# ——— thử ngược: bẻ cửa sổ trượt thành cộng dồn, phép thử phải bắt được ———
sed 's/if(g_ketLan == 0 || nay - g_ketDau > BO_GHE_KET_CUA_MS){ g_ketDau = nay; g_ketLan = 0; }//' \
    "$TMP/trich.inc.bak" > "$TMP/trich.inc"
g++ -std=c++17 -I"$TMP" -o "$TMP/t2" "$TMP/t.cpp"
if "$TMP/t2" >/dev/null 2>&1; then
  echo "  ⚠ PHÉP THỬ VÔ DỤNG: bỏ cửa sổ trượt mà vẫn báo sạch"; exit 1
else
  echo "  thử ngược: bỏ cửa sổ trượt → phép thử BẮT ĐƯỢC, tốt"
fi

# ——— thử ngược 2: hạ ngưỡng xuống 1 lần (báo ngay từ gai đầu tiên) ———
sed 's/#define BO_GHE_KET_LAN    2/#define BO_GHE_KET_LAN    1/' \
    "$TMP/trich.inc.bak" > "$TMP/trich.inc"
g++ -std=c++17 -I"$TMP" -o "$TMP/t3" "$TMP/t.cpp"
if "$TMP/t3" >/dev/null 2>&1; then
  echo "  ⚠ PHÉP THỬ VÔ DỤNG: hạ nguong ve 1 ma van bao sach"; exit 1
else
  echo "  thử ngược: hạ ngưỡng về 1 lần → phép thử BẮT ĐƯỢC, tốt"
fi
