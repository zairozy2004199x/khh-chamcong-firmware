/* Bài test cho doc_hex.h — chạy trên máy tính bằng g++ thuần, không cần chip.
 * Đây là phần đáng kiểm nhất của cả bộ nạp: sai ở đây thì chip nhận đúng dữ liệu
 * vào SAI CHỖ, mà máy vẫn báo "nạp thành công" và chương trình vẫn chạy tới hết. */
#include "../../doc_hex.h"
#include <cstdio>
#include <cstring>

static int soChay = 0, soLoi = 0;

static void ok(const char* ten, bool dat, const char* them = "") {
  soChay++;
  if (dat) printf("  ✅ %-52s %s\n", ten, them);
  else { printf("  ❌ %-52s %s\n", ten, them); soLoi++; }
}

/* Dựng một dòng .hex hợp lệ và tự tính ô kiểm — để bài test không phụ thuộc vào
   việc mình gõ tay checksum đúng hay sai. */
static void dungDong(char* ra, uint16_t offset, uint8_t kieu, const uint8_t* d, uint8_t n) {
  uint8_t b[300]; int k = 0;
  b[k++] = n; b[k++] = (uint8_t)(offset >> 8); b[k++] = (uint8_t)offset; b[k++] = kieu;
  for (uint8_t i = 0; i < n; i++) b[k++] = d[i];
  uint8_t t = 0; for (int i = 0; i < k; i++) t = (uint8_t)(t + b[i]);
  b[k++] = (uint8_t)(-(int)t);
  char* p = ra; *p++ = ':';
  for (int i = 0; i < k; i++) { sprintf(p, "%02X", b[i]); p += 2; }
  *p = 0;
}

int main() {
  char dong[600];
  TuChuongTrinh ra[16];

  printf("== HAI BẪY CỦA FILE .hex dsPIC ==\n");
  {
    DocHex d; d.batDau();
    // hai lệnh: 0x123456 và 0xABCDEF, mỗi lệnh 4 byte kèm byte ma 0x00
    uint8_t x[8] = { 0x56,0x34,0x12,0x00,  0xEF,0xCD,0xAB,0x00 };
    dungDong(dong, 0x0000, HEX_DU_LIEU, x, 8);
    int n = d.nap(dong, ra, 16);
    ok("giải mã được 2 từ", n == 2, n < 0 ? d.loi() : "");
    if (n == 2) {
      char m[80];
      snprintf(m, sizeof(m), "0x%06X", (unsigned)ra[0].giaTri);
      ok("ghép 24 bit theo thứ tự byte thấp trước", ra[0].giaTri == 0x123456, m);
      ok("vứt byte ma, không nạp vào chip", ra[1].giaTri == 0xABCDEF);
      snprintf(m, sizeof(m), "hex 0x000000 -> ct 0x%06X", (unsigned)ra[0].diaChi);
      ok("địa chỉ chia 2: từ đầu ở 0x000000", ra[0].diaChi == 0x000000, m);
      snprintf(m, sizeof(m), "hex 0x000004 -> ct 0x%06X", (unsigned)ra[1].diaChi);
      ok("địa chỉ chia 2: từ sau ở 0x000002 CHỨ KHÔNG PHẢI 0x000004",
         ra[1].diaChi == 0x000002, m);
    }
  }
  {
    DocHex d; d.batDau();
    uint8_t x[4] = { 0x56,0x34,0x12,0x01 };            // byte ma khác 0
    dungDong(dong, 0x0000, HEX_DU_LIEU, x, 4);
    int n = d.nap(dong, ra, 16);
    ok("byte thứ tư khác 0 -> từ chối", n < 0, n < 0 ? d.loi() : "LỌT!");
  }

  printf("\n== ĐỊA CHỈ CAO (bản ghi kiểu 04) ==\n");
  {
    DocHex d; d.batDau();
    uint8_t hi[2] = { 0x01, 0xF0 };                     // nền = 0x01F00000
    dungDong(dong, 0x0000, HEX_DIA_CHI_CAO, hi, 2);
    ok("nhận bản ghi kiểu 04", d.nap(dong, ra, 16) == 0, d.loi());
    uint8_t x[4] = { 0x33,0x22,0x11,0x00 };
    dungDong(dong, 0x0000, HEX_DU_LIEU, x, 4);
    int n = d.nap(dong, ra, 16);
    char m[80]; snprintf(m, sizeof(m), "-> ct 0x%06X", n == 1 ? (unsigned)ra[0].diaChi : 0);
    // hex 0x01F00000 / 2 = 0x00F80000 — đúng vùng config của dsPIC33F
    ok("hex 0x01F00000 -> vùng config 0x00F80000", n == 1 && ra[0].diaChi == 0x00F80000, m);
  }

  printf("\n== TỪ 4 BYTE NẰM VẮT QUA HAI DÒNG ==\n");
  {
    DocHex d; d.batDau();
    uint8_t a[2] = { 0x78, 0x56 };
    dungDong(dong, 0x0000, HEX_DU_LIEU, a, 2);
    ok("nửa đầu chưa ra từ nào", d.nap(dong, ra, 16) == 0, d.loi());
    uint8_t b[2] = { 0x34, 0x00 };
    dungDong(dong, 0x0002, HEX_DU_LIEU, b, 2);
    int n = d.nap(dong, ra, 16);
    ok("nối lại thành 0x345678", n == 1 && ra[0].giaTri == 0x345678, n < 0 ? d.loi() : "");
  }
  {
    DocHex d; d.batDau();
    uint8_t a[2] = { 0x78, 0x56 };
    dungDong(dong, 0x0000, HEX_DU_LIEU, a, 2);
    d.nap(dong, ra, 16);
    uint8_t b[2] = { 0x34, 0x00 };
    dungDong(dong, 0x0100, HEX_DU_LIEU, b, 2);          // nhảy cóc giữa một từ
    int n = d.nap(dong, ra, 16);
    ok("nhảy cóc giữa một từ -> từ chối", n < 0, n < 0 ? d.loi() : "LỌT!");
  }

  printf("\n== DÒNG HỎNG, PHẢI TỪ CHỐI ==\n");
  {
    DocHex d; d.batDau();
    uint8_t x[4] = { 0x56,0x34,0x12,0x00 };
    dungDong(dong, 0x0000, HEX_DU_LIEU, x, 4);
    dong[strlen(dong) - 1] = (dong[strlen(dong) - 1] == 'A' ? 'B' : 'A');   // hỏng ô kiểm
    int n = d.nap(dong, ra, 16);
    ok("sai ô kiểm -> từ chối", n < 0, n < 0 ? d.loi() : "LỌT!");
  }
  {
    DocHex d; d.batDau();
    ok("dòng không có dấu ':' -> từ chối", d.nap("00000001FF", ra, 16) < 0, d.loi());
  }
  {
    DocHex d; d.batDau();
    ok("dòng có ký tự lạ -> từ chối", d.nap(":04000000ZZ3412000A", ra, 16) < 0, d.loi());
  }
  {
    DocHex d; d.batDau();
    ok("số byte khai báo không khớp -> từ chối", d.nap(":10000000563412000A", ra, 16) < 0, d.loi());
  }
  {
    DocHex d; d.batDau();
    uint8_t x[4] = { 0x56,0x34,0x12,0x00 };
    dungDong(dong, 0x0002, HEX_DU_LIEU, x, 4);          // không thẳng hàng 4
    ok("địa chỉ không thẳng hàng 4 byte -> từ chối", d.nap(dong, ra, 16) < 0, d.loi());
  }
  {
    DocHex d; d.batDau();
    ok("bản ghi kiểu 02 (thời DOS) -> từ chối", d.nap(":020000021000EC", ra, 16) < 0, d.loi());
  }

  printf("\n== KẾT THÚC FILE ==\n");
  {
    DocHex d; d.batDau();
    int n = d.nap(":00000001FF", ra, 16);
    ok("nhận bản ghi kết thúc", n == 0 && d.xong(), d.loi());
    /* Dòng này phải TRƯỢT VÌ "sau bản ghi kết thúc", chứ không phải vì sai ô kiểm —
       nên dựng bằng dungDong() cho ô kiểm đúng, không gõ tay. Bài test đạt vì lý do
       sai thì còn tệ hơn không có bài test. */
    uint8_t x[4] = { 0x56,0x34,0x12,0x00 };
    dungDong(dong, 0x0000, HEX_DU_LIEU, x, 4);
    int n2 = d.nap(dong, ra, 16);
    ok("còn dữ liệu sau kết thúc -> từ chối", n2 < 0, d.loi());
    ok("  ... và trượt ĐÚNG vì lý do đó", strstr(d.loi(), "sau bản ghi kết thúc") != nullptr, d.loi());
  }
  {
    DocHex d; d.batDau();
    uint8_t a[2] = { 0x78, 0x56 };
    dungDong(dong, 0x0000, HEX_DU_LIEU, a, 2);
    d.nap(dong, ra, 16);
    ok("hết file giữa chừng một từ -> từ chối", d.nap(":00000001FF", ra, 16) < 0, d.loi());
  }
  {
    DocHex d; d.batDau();
    ok("dòng trắng -> bỏ qua, không tính là lỗi", d.nap("", ra, 16) == 0);
    ok("dòng chỉ có CR/LF -> bỏ qua", d.nap("\r\n", ra, 16) == 0);
  }

  printf("\n== ĐỊA CHỈ PHẢI TĂNG DẦN (bộ nạp ghi theo hàng, đi tới đâu ghi tới đó) ==\n");
  {
    DocHex d; d.batDau();
    uint8_t x[4] = { 0x01,0x00,0x00,0x00 };
    dungDong(dong, 0x0010, HEX_DU_LIEU, x, 4); d.nap(dong, ra, 16);
    dungDong(dong, 0x0000, HEX_DU_LIEU, x, 4);          // nhảy lùi
    ok("file xáo thứ tự -> từ chối", d.nap(dong, ra, 16) < 0, d.loi());
  }

  printf("\n%d bài, %d lỗi\n", soChay, soLoi);
  return soLoi ? 1 : 0;
}
