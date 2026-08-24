#!/usr/bin/env bash
# Kiểm bộ ĐỌC KHUNG BÁO TIỀN của ICT, bằng g++ — không cần ESP32, không cần máy thật.
#
# GIAO THỨC (dò 24/08/2026 bằng tờ tiền thật — xem docs/GIAO-THUC-BO-GHE.md)
#     81  (0x40+kênh)  10     NHẬN được    kênh 1=10k · 2=20k · 3=50k · 4=100k
#     81  (0x40+kênh)  29     đút vướng, ICT nhả ra — KHÔNG tiền
#     29  2F                  khách giựt lại / nhét giấy — KHÔNG tiền, KHÔNG có byte mở đầu
#     25                      kẹt tờ
#     2F                      sẵn sàng lại
#
# VÌ SAO CẦN
#   Hàm này quyết định TIỀN VÀO SỔ. Sai một nhánh là ghi sai doanh thu, mà sai kiểu này không
#   ai phát hiện ra cho tới lúc đối soát cuối tháng — lúc đó không truy lại được nữa.
#
#   Bẫy lớn nhất: hai byte ĐẦU của ca nhận và ca nhả giống HỆT nhau. Cộng tiền ngay khi thấy
#   81 + mã kênh là cộng cho cả tờ ICT vừa nhả ra — khách không mất đồng nào mà ghế vẫn chạy.
#
# Trích hàm TỪ CHÍNH .ino chứ không chép lại.
#
# Chạy: bash tools/test/fw/kiem-ict-khung.sh
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
    if not m: sys.exit('KHÔNG thấy #define %s' % ten)
    lay.append(m.group(0).split('//')[0].rstrip())

m = re.search(r'^enum \{ ICT_KQ_KHONG.*?\};', s, re.M)
if not m: sys.exit('KHÔNG thấy enum ICT_KQ_*')
lay.append(m.group(0))
m = re.search(r'^struct IctKhung \{.*?\};', s, re.M)
if not m: sys.exit('KHÔNG thấy struct IctKhung')
lay.append(m.group(0))
m = re.search(r'^struct IctMenhGia \{.*?\};', s, re.M)
if not m: sys.exit('KHÔNG thấy struct IctMenhGia')
lay.append(m.group(0))
m = re.search(r'\n(static const IctMenhGia ICT_BANG_TIEN\[\].*?\n\};)', s, re.S)
if not m: sys.exit('KHÔNG thấy bảng ICT_BANG_TIEN')
lay.append(m.group(1))
for pat in (r'\n(static long ictTienCuaKenh\([^)]*\)\s*\{.*?\n\})',
            r'\n(int ictNapByte\([^)]*\)\s*\{.*?\n\})'):
    m = re.search(pat, s, re.S)
    if not m: sys.exit('KHÔNG thấy hàm khớp %s' % pat)
    lay.append(m.group(1))
io.open(sys.argv[2], 'w', encoding='utf-8').write('\n\n'.join(lay) + '\n')
PY

cat > "$TMP/t.cpp" <<'CPP'
#include <cstdint>
#include <cstdio>
#include <cstring>
#include "trich.inc"

static int hong = 0;

/* Nạp cả một chuỗi byte, trả kết quả CUỐI CÙNG khác KHÔNG, và tổng tiền cộng được.
   Đúng cách firmware dùng: byte về lần lượt, trạng thái giữ giữa các lần. */
static int chay(const uint8_t* ds, int n, long* tong) {
  IctKhung k = { 0, 0 };
  int cuoi = ICT_KQ_KHONG;
  *tong = 0;
  for (int i = 0; i < n; i++) {
    long t = 0;
    int kq = ictNapByte(&k, ds[i], &t);
    if (kq != ICT_KQ_KHONG) cuoi = kq;
    *tong += t;
  }
  return cuoi;
}

static void ca(const char* ten, const uint8_t* ds, int n, int kqMong, long tienMong) {
  long tong = 0;
  int kq = chay(ds, n, &tong);
  if (kq != kqMong || tong != tienMong) {
    std::printf("  HỎNG %-44s mong kq=%d tien=%ld · được kq=%d tien=%ld\n",
                ten, kqMong, tienMong, kq, tong);
    hong++;
  }
}

int main() {
  // ---- bốn mệnh giá, đều đo bằng tờ thật ----
  { uint8_t d[] = {0x81,0x41,0x10}; ca("10.000đ", d, 3, ICT_KQ_TIEN,  10000); }
  { uint8_t d[] = {0x81,0x42,0x10}; ca("20.000đ", d, 3, ICT_KQ_TIEN,  20000); }
  { uint8_t d[] = {0x81,0x43,0x10}; ca("50.000đ", d, 3, ICT_KQ_TIEN,  50000); }
  { uint8_t d[] = {0x81,0x44,0x10}; ca("100.000đ", d, 3, ICT_KQ_TIEN, 100000); }

  // ---- 🔴 ca NHẢ RA: hai byte đầu GIỐNG HỆT ca nhận, chỉ byte cuối khác ----
  { uint8_t d[] = {0x81,0x41,0x29}; ca("đút vướng 10k, nhả ra: KHÔNG tiền", d, 3, ICT_KQ_HUY, 0); }
  { uint8_t d[] = {0x81,0x43,0x29}; ca("đút vướng 50k, nhả ra: KHÔNG tiền", d, 3, ICT_KQ_HUY, 0); }
  { uint8_t d[] = {0x81,0x44,0x29,0x2F}; ca("nhả 100k rồi sẵn sàng lại", d, 4, ICT_KQ_HET, 0); }

  // ---- khách giựt lại / nhét giấy: KHÔNG có byte mở đầu ----
  { uint8_t d[] = {0x29,0x2F}; ca("giựt tiền lại / nhét giấy", d, 2, ICT_KQ_HET, 0); }
  { uint8_t d[] = {0x29};      ca("chỉ 0x29 đứng một mình",    d, 1, ICT_KQ_HUY, 0); }

  // ---- kẹt / hết kẹt ----
  { uint8_t d[] = {0x25};      ca("kẹt tờ",              d, 1, ICT_KQ_KET, 0); }
  { uint8_t d[] = {0x25,0x2F}; ca("kẹt rồi gỡ xong",     d, 2, ICT_KQ_HET, 0); }
  { uint8_t d[] = {0x2F};      ca("sẵn sàng lại",        d, 1, ICT_KQ_HET, 0); }

  // ---- khung dở bị KẸT chen ngang: không được cộng tiền ----
  { uint8_t d[] = {0x81,0x41,0x25,0x10};
    ca("81 41 rồi KẸT chen vào, 10 lạc lõng", d, 4, ICT_KQ_KET, 0); }

  // ---- gai nhiễu ----
  { uint8_t d[] = {0xFF,0x81,0x41,0x10}; ca("gai FF trước khung thật", d, 4, ICT_KQ_TIEN, 10000); }
  { uint8_t d[] = {0x81,0xFF,0x10};      ca("gai FF thay mã kênh: bỏ khung", d, 3, ICT_KQ_KHONG, 0); }
  { uint8_t d[] = {0xFF,0xFE,0xF8,0x00}; ca("toàn gai: không gì cả", d, 4, ICT_KQ_KHONG, 0); }

  // ---- hai tờ liên tiếp ----
  { uint8_t d[] = {0x81,0x41,0x10,0x81,0x43,0x10};
    ca("10k rồi 50k liền nhau", d, 6, ICT_KQ_TIEN, 60000); }

  // ---- kênh ngoài bảng: nhận ra khung nhưng KHÔNG đoán mệnh giá ----
  { uint8_t d[] = {0x81,0x47,0x10}; ca("kênh 7 lạ: không đoán bừa", d, 3, ICT_KQ_TIEN, 0); }

  /* 81 lạc không được nuốt mất tờ tiền THẬT ngay sau nó. Firmware có thêm hạn giờ bỏ khung dở,
     nhưng ngay cả khi hai cái sát nhau thì tờ sau vẫn phải vào. */
  { uint8_t d[] = {0x81, 0x81,0x41,0x10};
    ca("81 lạc rồi tới khung thật", d, 4, ICT_KQ_TIEN, 10000); }

  // ---- bảng mệnh giá phải đủ bốn và đúng số ----
  if (ictTienCuaKenh(1) != 10000 || ictTienCuaKenh(2) != 20000 ||
      ictTienCuaKenh(3) != 50000 || ictTienCuaKenh(4) != 100000) {
    std::printf("  HỎNG bảng mệnh giá lệch so với tờ tiền đã đo\n"); hong++;
  }
  if (ictTienCuaKenh(0) != 0 || ictTienCuaKenh(9) != 0) {
    std::printf("  HỎNG kênh ngoài bảng phải trả 0, không được đoán\n"); hong++;
  }

  if (!hong) std::printf("  đọc khung ICT: SẠCH (20 phép)\n");
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

thu_nguoc "cộng tiền ngay khi thấy mã kênh (bỏ qua byte cuối)" \
  's/if(b == ICT_KET_THUC){ \*tien = ictTienCuaKenh(k->kenh); return ICT_KQ_TIEN; }/*tien = ictTienCuaKenh(k->kenh); return ICT_KQ_TIEN;/'
# Phép phá phải nằm gọn TRÊN MỘT DÒNG: sed không khớp qua dấu xuống dòng, nên mẫu nhiều dòng
# lặng lẽ không thay gì cả, bản "đã phá" y hệt bản gốc, và phép thử tự báo mình vô dụng.
thu_nguoc "coi byte NHẢ RA (0x29) là đã nhận tiền" \
  's/if(b == ICT_KET_THUC){ \*tien = ictTienCuaKenh(k->kenh); return ICT_KQ_TIEN; }/if(b == ICT_KET_THUC || b == ICT_NHA){ *tien = ictTienCuaKenh(k->kenh); return ICT_KQ_TIEN; }/'
thu_nguoc "kênh lạ tự suy thành 10.000đ" \
  's/return 0;   \/\/ kênh lạ/return 10000; \/\/ kênh lạ/'
thu_nguoc "kẹt tờ không xoá khung đang dở" \
  's/if(b == ICT_KET){ k->buoc = 0; return ICT_KQ_KET; }/if(b == ICT_KET){ return ICT_KQ_KET; }/'
