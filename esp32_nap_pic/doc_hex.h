/* ============================================================================
 *  doc_hex.h — ĐỌC FILE .hex CỦA dsPIC (Intel HEX), ĐỌC TỚI ĐÂU TRẢ TỚI ĐÓ
 * ----------------------------------------------------------------------------
 *  KHÔNG nạp cả file vào RAM. dsPIC33FJ256 có 256 KB chương trình ≈ 87 nghìn lệnh;
 *  giữ hết trong RAM là ~350 KB, mà ESP32 chỉ có ~320 KB dùng được. Nên đọc từng
 *  dòng, ra được từ nào thì đẩy đi ghi từ đó.
 *
 *  ─── HAI CÁI BẪY CỦA FILE .hex dsPIC ──────────────────────────────────────
 *  Đây là chỗ làm sai mà máy VẪN BÁO NẠP THÀNH CÔNG, rồi chương trình chạy trơn
 *  tru tới hết — nên phải chốt bằng bài test, không tin mắt.
 *
 *  1) BYTE MA. Bộ nhớ chương trình dsPIC rộng 24 bit, nhưng trong file .hex mỗi
 *     lệnh chiếm BỐN byte:  LSB · giữa · MSB · 0x00.
 *     Byte thứ tư là byte ma, KHÔNG có thật trong chip, phải vứt đi. Nạp cả nó
 *     vào là lệch hết mọi thứ từ đó trở đi.
 *
 *  2) ĐỊA CHỈ GẤP ĐÔI. Địa chỉ ghi trong file .hex = địa chỉ chương trình × 2.
 *     Lệnh thứ nhất ở địa chỉ chương trình 0x000000, lệnh thứ hai ở 0x000002 —
 *     nhưng trong file .hex chúng nằm ở 0x000000 và 0x000004.
 *     Quên chia 2 là nạp đúng dữ liệu vào sai chỗ, nửa chip trống nửa chip trùng.
 *
 *  Nhờ hai điều trên mà mỗi từ chương trình chiếm đúng 4 đơn vị địa chỉ .hex và
 *  đúng 4 byte -> luôn thẳng hàng, không bao giờ lệch nhịp.
 *
 *  ⚠️ File này CỐ Ý không dùng String của Arduino, chỉ dùng kiểu C thuần. Nhờ vậy
 *     bài test ci/kiem-hex.sh biên dịch thẳng bằng g++, không cần giả lập gì cả —
 *     mà đây đúng là phần đáng được kiểm kỹ nhất trong cả bộ nạp.
 * ========================================================================== */
#pragma once
#include <stdint.h>
#include <string.h>
#include <stdio.h>

#define HEX_DU_LIEU      0x00
#define HEX_KET_THUC     0x01
#define HEX_DIA_CHI_DOAN 0x02
#define HEX_DIA_CHI_CAO  0x04

/** Một từ chương trình dsPIC: địa chỉ CHƯƠNG TRÌNH (đã chia 2) + 24 bit giá trị. */
struct TuChuongTrinh {
  uint32_t diaChi;      // địa chỉ chương trình, luôn chẵn
  uint32_t giaTri;      // 24 bit, byte cao nhất luôn 0
};

class DocHex {
public:
  void batDau() {
    _nen = 0; _duN = 0; _duDiaChi = 0; _xong = false; _loi[0] = 0;
    _daCoTu = false; _diaChiCuoi = 0; _soTu = 0; _soDong = 0;
  }

  /**
   * Đưa vào MỘT dòng của file .hex (không cần kèm CR/LF).
   * Trả về số từ giải mã được, đẩy vào ra[]. Trả -1 nếu dòng hỏng (xem loi()).
   * Một dòng 16 byte dữ liệu cho ra tối đa 4 từ, nên ra[] để 8 phần tử là dư.
   */
  int nap(const char* dong, TuChuongTrinh* ra, int toiDa) {
    _soDong++;
    if (!dong) return _bao("dòng rỗng");

    // Bỏ khoảng trắng và CR/LF hai đầu — trình soạn thảo trên Windows hay kèm CR.
    while (*dong == ' ' || *dong == '\t' || *dong == '\r' || *dong == '\n') dong++;
    int dai = (int)strlen(dong);
    while (dai > 0 && (dong[dai-1] == ' ' || dong[dai-1] == '\t' ||
                       dong[dai-1] == '\r' || dong[dai-1] == '\n')) dai--;
    if (dai == 0) return 0;                       // dòng trắng: bỏ qua, không phải lỗi
    if (dong[0] != ':') return _bao("dòng không bắt đầu bằng ':'");
    if (dai < 11)       return _bao("dòng ngắn hơn mức tối thiểu");
    if ((dai - 1) % 2)  return _bao("số ký tự hex lẻ");

    uint8_t b[280];
    int n = (dai - 1) / 2;
    if (n > (int)sizeof(b)) return _bao("dòng dài bất thường");
    for (int i = 0; i < n; i++) {
      int c = _byte(dong[1 + i*2], dong[2 + i*2]);
      if (c < 0) return _bao("có ký tự không phải hex");
      b[i] = (uint8_t)c;
    }

    uint8_t soByte = b[0];
    uint8_t kieu   = b[3];
    if (n != soByte + 5) return _bao("số byte khai báo không khớp độ dài dòng");

    /* Tổng kiểm: cộng HẾT mọi byte kể cả ô kiểm rồi lấy 8 bit thấp, phải ra 0.
       Bỏ bước này thì một ký tự sai do thẻ nhớ lỗi cũng lọt vào chip. */
    uint8_t tong = 0;
    for (int i = 0; i < n; i++) tong = (uint8_t)(tong + b[i]);
    if (tong != 0) return _bao("sai ô kiểm (checksum)");

    if (_xong) return _bao("còn dữ liệu sau bản ghi kết thúc");

    switch (kieu) {
      case HEX_KET_THUC:
        if (_duN != 0) return _bao("file hết giữa chừng một từ 4 byte");
        _xong = true;
        return 0;

      case HEX_DIA_CHI_CAO:
        if (soByte != 2) return _bao("bản ghi địa chỉ cao phải có đúng 2 byte");
        _nen = ((uint32_t)b[4] << 24) | ((uint32_t)b[5] << 16);
        return 0;

      case HEX_DIA_CHI_DOAN:
        /* Kiểu 02 là dạng đoạn 16 bit thời DOS. XC16 không bao giờ sinh ra nó.
           Gặp là file lấy từ nguồn khác — dừng hẳn chứ không đoán bừa. */
        return _bao("bản ghi kiểu 02 — file này không phải do XC16 sinh ra");

      case HEX_DU_LIEU: break;
      default:          return 0;      // kiểu lạ khác: bỏ qua, không phải lỗi
    }

    uint32_t diaChiHex = _nen + (((uint32_t)b[1] << 8) | b[2]);
    int soRa = 0;
    for (uint8_t i = 0; i < soByte; i++) {
      uint32_t dc = diaChiHex + i;
      if (_duN == 0) {
        if (dc % 4) return _bao("địa chỉ không thẳng hàng 4 byte");
        _duDiaChi = dc;
      } else if (dc != _duDiaChi + (uint32_t)_duN) {
        return _bao("địa chỉ nhảy cóc giữa một từ 4 byte");
      }
      _du[_duN++] = b[4 + i];
      if (_duN < 4) continue;

      _duN = 0;
      /* Byte thứ tư PHẢI là 0. Khác 0 nghĩa là hoặc file không phải của dsPIC,
         hoặc mình đang đọc lệch nhịp — cả hai đều không được nhắm mắt nạp tiếp. */
      if (_du[3] != 0) return _bao("byte thứ tư khác 0 — không phải file .hex của dsPIC");

      TuChuongTrinh t;
      t.diaChi = _duDiaChi / 2;                                   // ĐỊA CHỈ GẤP ĐÔI
      t.giaTri = (uint32_t)_du[0] | ((uint32_t)_du[1] << 8) | ((uint32_t)_du[2] << 16);

      /* Bộ nạp ghi theo hàng, đi tới đâu ghi tới đó, nên file PHẢI tăng dần địa chỉ.
         XC16 luôn xuất tăng dần. File nhảy lùi thì dừng và nói rõ, chứ ghi tiếp là
         hàng đã ghi rồi bị bỏ dở nửa vời. */
      if (_daCoTu && t.diaChi <= _diaChiCuoi)
        return _bao("địa chỉ không tăng dần — file bị xáo thứ tự");
      _daCoTu = true; _diaChiCuoi = t.diaChi;

      if (soRa >= toiDa) return _bao("ra[] quá nhỏ");
      ra[soRa++] = t;
      _soTu++;
    }
    return soRa;
  }

  bool        xong()   const { return _xong; }
  const char* loi()    const { return _loi; }
  uint32_t    soTu()   const { return _soTu; }
  uint32_t    soDong() const { return _soDong; }

private:
  uint32_t _nen = 0;
  uint8_t  _du[4] = {0,0,0,0};
  int      _duN = 0;
  uint32_t _duDiaChi = 0;
  bool     _xong = false;
  bool     _daCoTu = false;
  uint32_t _diaChiCuoi = 0, _soTu = 0, _soDong = 0;
  char     _loi[96] = {0};

  int _bao(const char* v) { snprintf(_loi, sizeof(_loi), "dòng %lu: %s", (unsigned long)_soDong, v); return -1; }
  static int _nibble(char c) {
    if (c >= '0' && c <= '9') return c - '0';
    if (c >= 'a' && c <= 'f') return c - 'a' + 10;
    if (c >= 'A' && c <= 'F') return c - 'A' + 10;
    return -1;
  }
  static int _byte(char a, char b) {
    int x = _nibble(a), y = _nibble(b);
    return (x < 0 || y < 0) ? -1 : (x << 4) | y;
  }
};
