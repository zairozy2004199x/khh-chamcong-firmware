/* Bài test cho mdb.h — giải mã khung MDB 9 bit. Chạy g++ thuần, không cần chip.
 * Phần giải mã là chỗ dễ sai nhất (bit thứ 9, bit thấp trước, bit stop), nên chốt
 * bằng dữ liệu dựng sẵn trước khi tin nó ngoài hiện trường. */
#include "../../mdb.h"
#include <cstdio>
#include <vector>

static int soChay = 0, soLoi = 0;
static void ok(const char* ten, bool dat, const char* them="") {
  soChay++;
  if (dat) printf("  ✅ %-46s %s\n", ten, them);
  else { printf("  ❌ %-46s %s\n", ten, them); soLoi++; }
}

/* Dựng chuỗi mẫu cho một byte MDB: start(0) + 9 bit (thấp trước) + stop(1),
   mỗi bit lặp mauMoiBit mẫu. Trước và sau chèn mức nghỉ (1). */
static void themByte(std::vector<uint8_t>& s, uint16_t v9, int mauMoiBit) {
  auto bit = [&](int m){ for (int i=0;i<mauMoiBit;i++) s.push_back((uint8_t)m); };
  bit(0);                              // start
  for (int b=0;b<9;b++) bit((v9>>b)&1);// 9 bit, thấp trước
  bit(1);                              // stop
}

int main() {
  const int OV = 8;                    // 8 mẫu mỗi bit
  MdbByte ra[32];

  printf("== GIẢI MÃ BYTE ĐỊA CHỈ VÀ BYTE DỮ LIỆU ==\n");
  {
    std::vector<uint8_t> s(OV*4, 1);   // nghỉ đầu
    themByte(s, 0x130, OV);            // 0x30 + mode=1 (0x100) -> byte địa chỉ 0x30
    themByte(s, 0x055, OV);            // 0x55, mode=0 -> byte dữ liệu
    for (int i=0;i<OV*4;i++) s.push_back(1);
    int n = mdbGiaiMa(s.data(), (int)s.size(), OV, ra, 32);
    ok("giải được 2 byte", n==2, "");
    if (n>=1) {
      char m[64]; snprintf(m,sizeof(m),"0x%02X mode=%d", ra[0].giaTri, ra[0].mode);
      ok("byte 1 = 0x30, mode=1 (địa chỉ)", ra[0].giaTri==0x30 && ra[0].mode==1 && !ra[0].khungLoi, m);
    }
    if (n>=2) {
      char m[64]; snprintf(m,sizeof(m),"0x%02X mode=%d", ra[1].giaTri, ra[1].mode);
      ok("byte 2 = 0x55, mode=0 (dữ liệu)", ra[1].giaTri==0x55 && ra[1].mode==0 && !ra[1].khungLoi, m);
    }
  }

  printf("\n== BIT THẤP TRƯỚC (dễ đảo nhầm thành cao trước) ==\n");
  {
    std::vector<uint8_t> s(OV*4, 1);
    themByte(s, 0x001, OV);            // chỉ bit 0 -> giá trị 0x01, KHÔNG phải 0x80
    for (int i=0;i<OV*4;i++) s.push_back(1);
    int n = mdbGiaiMa(s.data(), (int)s.size(), OV, ra, 32);
    char m[32]; snprintf(m,sizeof(m),"0x%02X", n? ra[0].giaTri : 0);
    ok("bit 0 bật -> 0x01 (không phải 0x80)", n==1 && ra[0].giaTri==0x01, m);
  }
  {
    std::vector<uint8_t> s(OV*4, 1);
    themByte(s, 0x080, OV);            // chỉ bit 7
    for (int i=0;i<OV*4;i++) s.push_back(1);
    int n = mdbGiaiMa(s.data(), (int)s.size(), OV, ra, 32);
    char m[32]; snprintf(m,sizeof(m),"0x%02X", n? ra[0].giaTri : 0);
    ok("bit 7 bật -> 0x80", n==1 && ra[0].giaTri==0x80, m);
  }

  printf("\n== BIT STOP SAI -> BÁO KHUNG LỖI ==\n");
  {
    std::vector<uint8_t> s(OV*4, 1);
    // dựng tay: start + 9 bit + stop=0 (sai)
    auto bit=[&](int mm){for(int i=0;i<OV;i++)s.push_back((uint8_t)mm);};
    bit(0); for(int b=0;b<9;b++)bit(0); bit(0);   // stop = 0 -> lỗi
    for (int i=0;i<OV*4;i++) s.push_back(1);
    int n = mdbGiaiMa(s.data(), (int)s.size(), OV, ra, 32);
    bool coLoi=false; for(int i=0;i<n;i++) if(ra[i].khungLoi) coLoi=true;
    ok("khung stop=0 bị đánh dấu lỗi", coLoi, "");
  }

  printf("\n== CHUỖI LỆNH THẬT: POLL rồi trả lời ==\n");
  {
    // chủ gửi địa chỉ 0x33 (bill validator + POLL), tớ trả 2 byte dữ liệu + checksum
    std::vector<uint8_t> s(OV*6, 1);
    themByte(s, 0x133, OV);           // địa chỉ 0x33, mode=1
    themByte(s, 0x000, OV);           // dữ liệu 0x00
    themByte(s, 0x011, OV);           // dữ liệu 0x11
    for (int i=0;i<OV*6;i++) s.push_back(1);
    int n = mdbGiaiMa(s.data(), (int)s.size(), OV, ra, 32);
    ok("giải đúng 3 byte", n==3, "");
    ok("byte đầu là ĐỊA CHỈ (mode=1)", n>=1 && ra[0].mode==1, mdbTenDiaChi(ra[0].giaTri));
    ok("hai byte sau là DỮ LIỆU (mode=0)", n>=3 && ra[1].mode==0 && ra[2].mode==0, "");
  }

  printf("\n%d bài, %d lỗi\n", soChay, soLoi);
  return soLoi ? 1 : 0;
}
