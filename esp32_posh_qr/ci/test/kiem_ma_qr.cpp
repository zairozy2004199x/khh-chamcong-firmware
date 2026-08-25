/* ============================================================================
 *  Bài test cho ma_qr.h — CHẠY TRÊN MÁY TÍNH, không cần chip.
 *
 *  VÌ SAO: chỗ kiểm chữ ký là chỗ giữ tiền. Sai một ly ở đây thì hoặc khách đúng
 *  mã vẫn bị từ chối (mất khách), hoặc mã giả vẫn mở được ghế (mất tiền) — cả hai
 *  đều IM LẶNG, nạp firmware lên ghế rồi mới phát hiện thì đã muộn.
 *
 *  Chạy:  bash esp32_posh_qr/ci/kiem-ma-qr.sh
 * ========================================================================== */
#include "../../ma_qr.h"
#include <cstdio>
#include <cstdlib>
#include <string>
#include <openssl/hmac.h>

static const char* KHOA = "khoa-ky-thu-nghiem-0123456789abcdef";
static int soLoi = 0, soChay = 0;

/* Ký y như máy chủ bán vé sẽ làm — cố ý viết ĐỘC LẬP với ma_qr.h (dùng thẳng
   OpenSSL) để nếu ma_qr.h ký sai thì test bắt được, chứ không cùng sai một kiểu. */
static std::string kyThat(const std::string& noiDung) {
  unsigned char h[32]; unsigned n = 0;
  HMAC(EVP_sha256(), KHOA, (int)strlen(KHOA),
       (const unsigned char*)noiDung.data(), noiDung.size(), h, &n);
  char b[17];
  for (int i = 0; i < 8; i++) snprintf(b + i * 2, 3, "%02x", h[i]);
  return std::string(b, 16);
}
static std::string maMoGhe(const char* may, const char* phut, const char* han, const char* ma) {
  std::string than = std::string("POSH1|") + may + "|" + phut + "|" + han + "|" + ma;
  return than + "|" + kyThat(than);
}
static void ktNhan(const char* ten, const std::string& tho) {
  soChay++;
  MaQR m = qrDoc(String(tho.c_str()), String(KHOA));
  if (m.loai == MA_HONG) { printf("  ❌ %-46s -> BỊ TỪ CHỐI: %s\n", ten, m.loi.c_str()); soLoi++; }
  else                   printf("  ✅ %-46s -> nhận\n", ten);
}
static void ktTuChoi(const char* ten, const std::string& tho) {
  soChay++;
  MaQR m = qrDoc(String(tho.c_str()), String(KHOA));
  if (m.loai != MA_HONG) { printf("  ❌ %-46s -> LỌT! đáng lẽ phải từ chối\n", ten); soLoi++; }
  else                   printf("  ✅ %-46s -> từ chối (%s)\n", ten, m.loi.c_str());
}

int main(int argc, char** argv) {
  /* Chạy kèm tham số = chế độ "kiểm một chuỗi": dùng để đối chiếu với công cụ
     tao-ma-qr.py, chắc chắn máy chủ và chip hiểu mã y hệt nhau. */
  if (argc >= 3) {
    MaQR m = qrDoc(String(argv[2]), String(argv[1]));
    if (m.loai == MA_MO_GHE) {
      printf("MO_GHE may=%s phut=%u hethan=%u ma=%s\n",
             m.may.c_str(), (unsigned)m.phut, (unsigned)m.hetHan, m.maLuot.c_str());
      return 0;
    }
    if (m.loai == MA_DAT_GIO) { printf("DAT_GIO gio=%u\n", (unsigned)m.gioDat); return 0; }
    printf("HONG %s\n", m.loi.c_str());
    return 1;
  }

  printf("== MÃ ĐÚNG, PHẢI NHẬN ==\n");
  ktNhan("bình thường",              maMoGhe("GHE-01", "15", "1786000000", "abc123"));
  ktNhan("mã dùng cho mọi ghế (*)",  maMoGhe("*",      "30", "1786000000", "xyz789"));
  ktNhan("không đặt hạn (han=0)",    maMoGhe("GHE-01", "1",  "0",          "n1"));
  ktNhan("số phút lớn nhất (240)",   maMoGhe("GHE-01", "240","0",          "n2"));
  ktNhan("kèm CR/LF của module quét", maMoGhe("GHE-01","15", "0",          "n3") + "\r\n");
  ktNhan("kèm BOM ở đầu chuỗi",      std::string("\xEF\xBB\xBF") + maMoGhe("GHE-01", "15", "0", "n4"));
  ktNhan("chữ ký viết HOA",          [] { std::string s = maMoGhe("GHE-01","15","0","n5");
                                          for (size_t i = s.size() - 16; i < s.size(); i++) s[i] = toupper(s[i]);
                                          return s; }());
  {
    std::string than = "POSHT|1786000000";
    ktNhan("mã đặt giờ POSHT",       than + "|" + kyThat(than));
  }

  printf("\n== MÃ HỎNG/GIẢ, PHẢI TỪ CHỐI ==\n");
  {
    std::string s = maMoGhe("GHE-01", "15", "1786000000", "abc123");
    std::string x = s; x[x.size() - 1] = (x[x.size() - 1] == 'a' ? 'b' : 'a');
    ktTuChoi("đổi 1 ký tự trong chữ ký", x);
    std::string y = s; size_t v = y.find("|15|"); y.replace(v, 4, "|60|");
    ktTuChoi("sửa số phút, giữ chữ ký cũ", y);
    std::string z = s; v = z.find("GHE-01"); z.replace(v, 6, "GHE-02");
    ktTuChoi("sửa mã ghế, giữ chữ ký cũ", z);
    std::string w = s; v = w.find("1786000000"); w.replace(v, 10, "1999999999");
    ktTuChoi("nới hạn dùng, giữ chữ ký cũ", w);
  }
  ktTuChoi("chuỗi rỗng",                  "");
  ktTuChoi("chỉ có khoảng trắng",         "   ");
  ktTuChoi("mã ngân hàng, không phải POSH","00020101021138540010A00000072701");
  ktTuChoi("thiếu ô (5 phần)",            "POSH1|GHE-01|15|0|abc");
  ktTuChoi("thừa ô (7 phần)",             maMoGhe("GHE-01","15","0","n9") + "|thua");
  ktTuChoi("phút = 0",                    maMoGhe("GHE-01", "0",   "0", "n10"));
  ktTuChoi("phút vượt 240",               maMoGhe("GHE-01", "241", "0", "n11"));
  ktTuChoi("phút không phải số",          maMoGhe("GHE-01", "muoi","0", "n12"));
  ktTuChoi("mã lượt rỗng",                maMoGhe("GHE-01", "15",  "0", ""));
  ktTuChoi("mã lượt có ký tự lạ",         maMoGhe("GHE-01", "15",  "0", "a b"));
  ktTuChoi("chữ ký cụt (8 ký tự)",        std::string("POSH1|GHE-01|15|0|n13|3f9c1d0a"));
  ktTuChoi("mã đặt giờ, chữ ký sai",      "POSHT|1786000000|0000000000000000");
  ktTuChoi("mã đặt giờ, giờ quá xa xưa",  "POSHT|100|0000000000000000");
  {
    soChay++;
    MaQR m = qrDoc(String(maMoGhe("GHE-01","15","0","n14").c_str()), String(""));
    if (m.loai != MA_HONG) { printf("  ❌ %-46s -> LỌT!\n", "hộp chưa khai khoá ký"); soLoi++; }
    else printf("  ✅ %-46s -> từ chối (%s)\n", "hộp chưa khai khoá ký", m.loi.c_str());
  }
  {
    soChay++;
    MaQR m = qrDoc(String(maMoGhe("GHE-01","15","0","n15").c_str()), String("khoa-khac"));
    if (m.loai != MA_HONG) { printf("  ❌ %-46s -> LỌT!\n", "hộp khai NHẦM khoá ký"); soLoi++; }
    else printf("  ✅ %-46s -> từ chối (%s)\n", "hộp khai NHẦM khoá ký", m.loi.c_str());
  }

  printf("\n== TUYỆT ĐỐI KHÔNG ĐƯỢC LỘ CHỮ KÝ ĐÚNG RA CÂU BÁO LỖI ==\n");
  {
    soChay++;
    std::string s = maMoGhe("GHE-01", "15", "0", "n16");
    std::string chuKyDung = s.substr(s.size() - 16);
    std::string gia = s.substr(0, s.size() - 16) + "0000000000000000";
    MaQR m = qrDoc(String(gia.c_str()), String(KHOA));
    if (m.loi.indexOf(chuKyDung.c_str()) >= 0) {
      printf("  ❌ câu báo lỗi CÓ chữ ký đúng — chỉ luôn cho người ta cách làm mã giả\n"); soLoi++;
    } else printf("  ✅ câu báo lỗi không chứa chữ ký đúng\n");
  }

  printf("\n%d bài, %d lỗi\n", soChay, soLoi);
  return soLoi ? 1 : 0;
}
