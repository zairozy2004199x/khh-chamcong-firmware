/* Công cụ kiểm file .hex TRÊN MÁY TÍNH trước khi mang thẻ ra hiện trường.
 * Dùng ĐÚNG bộ đọc doc_hex.h mà firmware dùng — nên máy tính nói được thì máy nạp
 * cũng đọc được, không có chuyện "ở nhà chạy, ra hiện trường mới hỏng".
 *   Chạy:  bash esp32_nap_pic/ci/xem-hex.sh <file.hex>  */
#include "../../doc_hex.h"
#include <cstdio>
#include <cstring>

int main(int argc, char** argv) {
  if (argc < 2) { printf("dung: xem_hex <file.hex>\n"); return 2; }
  FILE* f = fopen(argv[1], "rb");
  if (!f) { printf("khong mo duoc %s\n", argv[1]); return 2; }

  DocHex d; d.batDau();
  TuChuongTrinh ra[8];
  char dong[1024]; int k = 0;
  uint32_t dau = 0xFFFFFFFF, cuoi = 0, soVung = 0, truoc = 0; bool coTruoc = false;
  bool hong = false;

  for (;;) {
    int c = fgetc(f);
    if (c != EOF && c != '\n') { if (k < (int)sizeof(dong) - 1) dong[k++] = (char)c; continue; }
    dong[k] = 0; k = 0;
    int n = d.nap(dong, ra, 8);
    if (n < 0) { printf("🔴 %s\n", d.loi()); hong = true; break; }
    for (int i = 0; i < n; i++) {
      if (ra[i].diaChi < dau)  dau = ra[i].diaChi;
      if (ra[i].diaChi > cuoi) cuoi = ra[i].diaChi;
      if (!coTruoc || ra[i].diaChi != truoc + 2) soVung++;
      truoc = ra[i].diaChi; coTruoc = true;
    }
    if (c == EOF) break;
  }
  fclose(f);
  if (hong) return 1;

  printf("file        : %s\n", argv[1]);
  printf("số dòng     : %lu\n", (unsigned long)d.soDong());
  printf("số lệnh     : %lu\n", (unsigned long)d.soTu());
  printf("địa chỉ ct  : %06lX .. %06lX\n", (unsigned long)dau, (unsigned long)cuoi);
  printf("số vùng rời : %lu\n", (unsigned long)soVung);
  printf("bản ghi kết thúc: %s\n", d.xong() ? "có" : "🔴 KHÔNG CÓ — file có thể bị cụt");
  if (cuoi >= 0x00F80000UL)
    printf("⚠️  file CÓ ghi vùng cấu hình (0x%06lX trở lên)\n", (unsigned long)0x00F80000UL);
  printf("%s\n", d.xong() ? "✅ đọc trọn file, không lỗi" : "🔴 chưa trọn");
  return d.xong() ? 0 : 1;
}
