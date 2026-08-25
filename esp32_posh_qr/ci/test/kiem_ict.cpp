/* ============================================================================
 *  Bài test cho ict_ghe.h — CHẠY TRÊN MÁY TÍNH, dùng cổng UART GIẢ.
 *
 *  VÌ SAO CẦN: khung byte gửi sang bo ghế là thứ KHÔNG nhìn thấy được. Sai một
 *  byte checksum thì bo im lặng không nhận, mà trên hộp chẳng có dấu hiệu gì
 *  ngoài việc "ghế không chạy" — đúng kiểu lỗi ngồi mò cả buổi.
 *
 *  Bài test này chốt cứng từng byte của khung, để:
 *    - đổi code sau này mà lỡ làm lệch khung thì biết NGAY, không phải mang ra ghế thử;
 *    - và có sẵn một khung ĐÚNG bằng số để đối chiếu với cái bắt được từ bo thật.
 *
 *  Chạy:  bash esp32_posh_qr/ci/kiem-ict.sh
 * ========================================================================== */
#include "../../ict_ghe.h"
#include <cstdio>
#include <string>

static int soLoi = 0, soChay = 0;

static std::string raHex(const std::vector<uint8_t>& d) {
  std::string s;
  char b[4];
  for (size_t i = 0; i < d.size(); i++) { snprintf(b, sizeof(b), "%02X", d[i]); if (i) s += " "; s += b; }
  return s;
}
static void ktHex(const char* ten, const std::vector<uint8_t>& thuc, const char* mongDoi) {
  soChay++;
  std::string t = raHex(thuc);
  if (t == mongDoi) printf("  ✅ %-40s -> %s\n", ten, t.c_str());
  else { printf("  ❌ %-40s\n       gửi ra : %s\n       phải là: %s\n", ten, t.c_str(), mongDoi); soLoi++; }
}
static void ktBang(const char* ten, long thuc, long mongDoi) {
  soChay++;
  if (thuc == mongDoi) printf("  ✅ %-40s -> %ld\n", ten, thuc);
  else { printf("  ❌ %-40s -> %ld, phải là %ld\n", ten, thuc, mongDoi); soLoi++; }
}
static void ktDung(const char* ten, bool thuc, bool mongDoi) {
  soChay++;
  if (thuc == mongDoi) printf("  ✅ %-40s -> %s\n", ten, thuc ? "bo nhận" : "bo không nhận");
  else { printf("  ❌ %-40s -> %s, phải là %s\n", ten, thuc ? "nhận" : "không nhận",
                mongDoi ? "nhận" : "không nhận"); soLoi++; }
}

int main() {
  HardwareSerial cong;
  IctGhe ict;
  ict.amThanh = false;              // đỡ rối màn hình; bật lên khi cần soi

  /* ===== KHUNG NHỊ PHÂN — chốt từng byte ================================= */
  printf("== KHUNG NHỊ PHÂN: byte gửi ra dây ==\n");
  // Bo ghế giả: nhận gì cũng trả ACK.
  cong.boGhe = [](const std::vector<uint8_t>&, HardwareSerial& c) {
    uint8_t tl[6] = { ICT_STX, 0x02, ICT_CMD_MO, ICT_ACK, 0x00, ICT_ETX };
    tl[4] = (uint8_t)(tl[1] ^ tl[2] ^ tl[3]);
    c.traVe(tl, 6);
  };
  ict.batDau(&cong, 16, 17, 9600, ICT_CHE_NHI_PHAN);

  cong.daGui.clear(); ict.moGhe(15);
  // 02  LEN=03 (CMD+2 byte phút)  CMD=31  phút=00 0F  CHK=03^31^00^0F=3D  03
  ktHex("moGhe(15)", cong.daGui, "02 03 31 00 0F 3D 03");

  cong.daGui.clear(); ict.moGhe(240);
  // phút = 00 F0 ; CHK = 03^31^00^F0 = C2
  ktHex("moGhe(240)", cong.daGui, "02 03 31 00 F0 C2 03");

  cong.daGui.clear(); ict.moGhe(300);
  // 300 phút vẫn gửi được về mặt khung (ma_qr.h mới là chỗ chặn > 240):
  // 300 = 0x012C ; CHK = 03^31^01^2C = 1F
  ktHex("moGhe(300) — 2 byte, byte cao trước", cong.daGui, "02 03 31 01 2C 1F 03");

  cong.daGui.clear(); ict.dungGhe();
  // LEN=01 (chỉ CMD) ; CHK = 01^32 = 33
  ktHex("dungGhe()", cong.daGui, "02 01 32 33 03");

  cong.daGui.clear(); ict.pingBo();
  // CHK = 01^33 = 32
  ktHex("pingBo()", cong.daGui, "02 01 33 32 03");

  soChay++;
  if (ict.moGhe(0)) { printf("  ❌ moGhe(0) phải bị chặn ngay, không gửi gì\n"); soLoi++; }
  else printf("  ✅ %-40s -> chặn, không gửi byte nào\n", "moGhe(0)");

  /* ===== ĐỌC TRẢ LỜI ===================================================== */
  printf("\n== ĐỌC TRẢ LỜI CỦA BO ==\n");
  ktDung("bo trả ACK", ict.moGhe(10), true);

  cong.boGhe = [](const std::vector<uint8_t>&, HardwareSerial& c) {
    uint8_t tl[6] = { ICT_STX, 0x02, ICT_CMD_MO, ICT_NAK, 0x00, ICT_ETX };
    tl[4] = (uint8_t)(tl[1] ^ tl[2] ^ tl[3]);
    c.traVe(tl, 6);
  };
  ktDung("bo trả NAK (từ chối)", ict.moGhe(10), false);

  cong.boGhe = nullptr;                       // bo im như thóc
  cong.daGui.clear();
  ktDung("bo im lặng", ict.moGhe(10), false);
  // Im lặng thì phải gửi lại đủ ICT_SO_LAN_GUI lần, mỗi khung 7 byte.
  ktBang("im lặng -> số lần gửi lại", (long)(cong.daGui.size() / 7), ICT_SO_LAN_GUI);

  /* ⚠️ Bẫy thật: byte thừa còn tồn trong bộ đệm từ lần trước. Không xả bộ đệm
     trước khi gửi thì cái "ACK" đọc được là đuôi của lần trước -> hộp tưởng bo
     còn sống trong khi nó đã treo, và ghế thì không chạy. */
  cong.xoaSach();
  cong.traVe((const uint8_t[]){ ICT_ACK, ICT_ACK, ICT_ACK }, 3);   // rác cũ nằm sẵn
  cong.boGhe = nullptr;                                            // bo đã treo, không trả lời
  ktDung("ACK cũ tồn trong bộ đệm KHÔNG được tính", ict.moGhe(10), false);

  /* ===== KHUNG DÒNG CHỮ ================================================== */
  printf("\n== KHUNG DÒNG CHỮ (ASCII) ==\n");
  ict.doiChe(ICT_CHE_DONG_CHU);
  cong.boGhe = [](const std::vector<uint8_t>&, HardwareSerial& c) { c.traVe("OK\r\n"); };

  cong.daGui.clear(); ict.moGhe(15);
  ktHex("moGhe(15) = \"RUN 15\\r\\n\"", cong.daGui, "52 55 4E 20 31 35 0D 0A");
  cong.daGui.clear(); ict.dungGhe();
  ktHex("dungGhe() = \"STOP\\r\\n\"", cong.daGui, "53 54 4F 50 0D 0A");
  cong.daGui.clear(); ict.pingBo();
  ktHex("pingBo() = \"PING\\r\\n\"", cong.daGui, "50 49 4E 47 0D 0A");

  ktDung("bo trả \"OK\"", ict.moGhe(10), true);
  cong.boGhe = [](const std::vector<uint8_t>&, HardwareSerial& c) { c.traVe("ACK\r\n"); };
  ktDung("bo trả \"ACK\"", ict.moGhe(10), true);
  cong.boGhe = [](const std::vector<uint8_t>&, HardwareSerial& c) { c.traVe("ERR 3\r\n"); };
  ktDung("bo trả \"ERR\"", ict.moGhe(10), false);
  cong.boGhe = [](const std::vector<uint8_t>&, HardwareSerial& c) { c.traVe("NAK\r\n"); };
  ktDung("bo trả \"NAK\"", ict.moGhe(10), false);
  /* Bo trả "OK" lẫn "ERR" trong cùng một hơi -> phải hiểu là TỪ CHỐI.
     Đoán bừa là nhận thì hộp báo bán được trong khi ghế đứng im. */
  cong.boGhe = [](const std::vector<uint8_t>&, HardwareSerial& c) { c.traVe("OK\r\nERR 5\r\n"); };
  ktDung("bo trả cả OK lẫn ERR -> coi là từ chối", ict.moGhe(10), false);

  /* ===== BẮN HEX THÔ (lệnh HEX gõ qua cổng USB) ========================== */
  printf("\n== BẮN HEX THÔ ==\n");
  cong.boGhe = nullptr;
  cong.daGui.clear(); ict.banHex("02 03 31 00 0F 3D 03");
  ktHex("banHex có khoảng trắng", cong.daGui, "02 03 31 00 0F 3D 03");
  cong.daGui.clear(); ict.banHex("0203310");
  ktHex("banHex số lẻ ký tự -> bỏ mẩu cụt", cong.daGui, "02 03 31");
  cong.daGui.clear(); ict.banHex("khong-co-hex-nao");
  ktBang("banHex chuỗi vô nghĩa -> không gửi gì", (long)cong.daGui.size(), 0);

  /* ===== DÒ BAUD ========================================================= */
  printf("\n== DÒ BAUD ==\n");
  // Bo giả chỉ "hiểu" khi cổng đang mở ở 19200; các tốc độ khác trả byte rác.
  cong.boGhe = [](const std::vector<uint8_t>&, HardwareSerial& c) {
    if (c.baudDangDung == 19200) c.traVe("OK\r\n");
    else { uint8_t rac[4] = { 0x00, 0xFF, 0x00, 0xFF }; c.traVe(rac, 4); }
  };
  ktBang("dò ra đúng 19200", ict.doBaud(50), 19200);
  ktBang("dò xong TRẢ CỔNG VỀ baud cũ", cong.baudDangDung, 9600);

  cong.boGhe = nullptr;                      // không tốc độ nào nghe ra gì
  ktBang("bo câm hoàn toàn -> trả 0", ict.doBaud(50), 0);

  printf("\n%d bài, %d lỗi\n", soChay, soLoi);
  return soLoi ? 1 : 0;
}
