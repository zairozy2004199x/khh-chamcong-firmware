/* ============================================================================
 *  cong_tien.h — CỔNG TIỀN xen giữa ICT L70 <-> GHẾ thật  (Hướng 1)
 * ----------------------------------------------------------------------------
 *  ĐÃ NGHIỆM THU TRÊN MÁY THẬT (lệnh GATE bên esp32_cau_ict):
 *    - Bus tiền là SERIAL 4800 baud, 8 data + PARITY CHẴN + 1 stop (8E1).
 *      KHÔNG phải MDB 9600/9-bit như bản cũ giả định.
 *    - Bus IM khi rảnh; chỉ nói khi có tờ tiền (không poll).
 *    - Khung "có tiền":  ICT -> ghế:  81 4X   (X = chỉ số mệnh giá)
 *                        ghế -> ICT:  02      (nuốt)
 *                        ICT -> ghế:  10      (đã nuốt)
 *      Mệnh giá: 40=5k 41=10k 42=20k 43=50k 44=100k 45=200k 46=500k = 0x40+chỉsố.
 *    - Điều khiển (ghế -> ICT): 3E = mở khoá (cho nhận), 5E = khoá (đang chạy).
 *
 *  KIẾN TRÚC (Hướng 1 — ESP32 XEN GIỮA, ghế tự chạy bằng tiền):
 *    Dây A (ICT-TX -> ghế-RX, mang 81/4X/10): CẮT, ESP vào giữa.
 *        ICT-TX  -> TIEN_RX_ICT  (đọc, qua chia mức 5V->3,3V)
 *        TIEN_TX_GHE -> ghế-RX   (phát, qua chia mức 3,3V->5V)
 *      -> thường ngày RELAY nguyên văn ICT->ghế: TIỀN MẶT chạy y như cũ.
 *      -> quét QR: tự PHÁT 81 4X vào ghế = ghế tưởng có tiền, chạy.
 *    Dây B (ghế-TX -> ICT-RX, mang 3E/5E/02): KHÔNG cắt, đi thẳng — ghế và ICT
 *      thật vẫn bắt tay trực tiếp. Nên cổng chỉ cần MỘT UART phần cứng (Serial1),
 *      chừa Serial2 cho 4G.
 *
 *  ⚠️ Vì đã cắt dây A: ESP32 tắt/treo thì TIỀN MẶT cũng tắt (ICT không tới ghế).
 *     Bản sản phẩm nên thêm rơ-le thường-đóng nối thẳng A khi mất điện (fail-safe).
 * ========================================================================== */
#pragma once
#include <Arduino.h>

#define TIEN_RX_ICT   35     // IO35 (chỉ-input) — đọc TX của ICT (81/4X/10)
#define TIEN_TX_GHE   27     // IO27 — phát sang chân nhận tiền của ghế
#define TIEN_BAUD     4800

// Mệnh giá VND theo chỉ số (byte trên bus = 0x40 + chỉ số).
static const long TIEN_MENH_GIA[7] = { 5000, 10000, 20000, 50000, 100000, 200000, 500000 };

// Gọi khi PHÁT HIỆN một tờ tiền mặt THẬT do ICT báo (để cộng giờ + ghi sổ).
typedef void (*TienMatCb)(long vnd);

class CongTien {
public:
  /** Mở cổng. cb = hàm xử lý khi có tờ tiền mặt thật (có thể để nullptr). */
  void khoiDong(TienMatCb cb) {
    _cb = cb; _st = 0; _dangBom = false;
    Serial1.begin(TIEN_BAUD, SERIAL_8E1, TIEN_RX_ICT, TIEN_TX_GHE);
    delay(60);
    while (Serial1.available()) Serial1.read();     // bỏ rác lúc mở cổng
    Serial.printf("[TIEN] cong 4800 8E1: doc ICT@IO%d, phat ghe@IO%d. Tien mat relay, QR bom.\n",
                  TIEN_RX_ICT, TIEN_TX_GHE);
  }

  /** Gọi mỗi vòng loop(): RELAY tiền mặt ICT->ghế + phát hiện tờ tiền thật. */
  void tick() {
    while (Serial1.available()) {
      uint8_t b = (uint8_t)Serial1.read();
      if (!_dangBom) Serial1.write(b);              // chuyển thẳng sang ghế (tiền mặt xuyên qua)
      _quan(b);
    }
  }

  /** QR đã trả `vnd`: GIẢ LÀM ICT bơm khung "có tiền" vào ghế (tách thành các tờ). */
  void bom(long vnd) {
    if (vnd <= 0) return;
    _dangBom = true;
    for (int i = 6; i >= 0 && vnd > 0; i--) {
      while (vnd >= TIEN_MENH_GIA[i]) { _phatMotTo(i); vnd -= TIEN_MENH_GIA[i]; }
    }
    _dangBom = false;
  }

  /** Bơm đúng MỘT tờ theo chỉ số (0..6) — tiện test. */
  void bomChiSo(int idx) { if (idx >= 0 && idx <= 6) { _dangBom = true; _phatMotTo(idx); _dangBom = false; } }

private:
  TienMatCb _cb = nullptr;
  int  _st = 0;                 // máy trạng thái dò: 0=thường, 1=vừa thấy 0x81
  long _choVnd = 0;             // mệnh giá tờ ĐANG ở escrow, chờ 0x10 mới tính
  bool _dangBom = false;

  void _phatMotTo(int idx) {
    uint8_t ma = (uint8_t)(0x40 + idx);
    Serial1.write(0x81); Serial1.write(ma); Serial1.flush();
    delay(150);                  // chờ ghế xử lý + đáp 02 trên dây B (đi thẳng tới ICT thật)
    Serial1.write(0x10); Serial1.flush();
    delay(250);                  // để ghế xong trước tờ kế
    Serial.printf("[TIEN] BOM 81 %02X (%ld d)\n", ma, TIEN_MENH_GIA[idx]);
  }

  void _quan(uint8_t b) {
#ifdef TIEN_DEBUG
    Serial.printf("[TIEN] rx %02X\n", b);         // bật để soi từng byte ICT gửi
#endif
    // Tờ tiền THẬT: ICT gửi 81 4X (vào escrow) -> ... -> 10 (đã NUỐT hẳn).
    // CHỈ tính khi có 10: lỡ nhả tờ ra thì không có 10 -> không ghi khống.
    // (Byte ta tự bơm cho QR nằm ở TX, không vào RX nên không bị đếm nhầm.)
    if (_st == 0) {
      if (b == 0x81) _st = 1;
      else if (b == 0x10 && _choVnd > 0) {        // ĐÃ NUỐT -> giờ mới ghi nhận
        Serial.printf("[TIEN] +%ld d (da nuot, 10) -> ghi so\n", _choVnd);
        if (_cb) _cb(_choVnd);
        _choVnd = 0;
      }
    } else {                                       // vừa thấy 0x81
      _st = 0;
      if ((b & 0xF8) == 0x40) {                    // 0x40..0x47 = tờ vào escrow
        int idx = b & 0x07;
        if (idx <= 6) { _choVnd = TIEN_MENH_GIA[idx];
          Serial.printf("[TIEN] to %ld d vao escrow (81 %02X), cho 10...\n", _choVnd, b); }
      }
    }
  }
};

extern CongTien congTien;
