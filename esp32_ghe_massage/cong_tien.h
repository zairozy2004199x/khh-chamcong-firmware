/* ============================================================================
 *  cong_tien.h — CỔNG TIỀN xen giữa ICT L70 <-> GHẾ thật  (Hướng 1)
 * ----------------------------------------------------------------------------
 *  ĐÃ NGHIỆM THU TRÊN MÁY THẬT (lệnh GATE bên esp32_cau_ict):
 *    - Bus tiền là SERIAL 4800 baud, 8 data + PARITY CHẴN + 1 stop (8E1).
 *    - Bus IM khi rảnh; chỉ nói khi có tờ tiền (không poll).
 *    - Khung "có tiền":  ICT -> ghế:  81 4X   (X = chỉ số mệnh giá)
 *                        ghế -> ICT:  02      (nuốt)
 *                        ICT -> ghế:  10      (đã nuốt)
 *      Mệnh giá: 40=5k 41=10k 42=20k 43=50k 44=100k 45=200k 46=500k = 0x40+chỉsố.
 *    - Điều khiển (ghế -> ICT): 3E = mở khoá (cho nhận), 5E = khoá (đang chạy).
 *
 *  DÂY:
 *    Dây A (ICT-TX -> ghế-RX): CẮT, ESP xen giữa.
 *        TIEN_RX_ICT (IO35) đọc ICT ; TIEN_TX_GHE (IO27) phát sang ghế.
 *        -> relay tiền mặt + bơm QR. Serial1 phần cứng.
 *    Dây B (ghế-TX -> ICT-RX, mang 3E/5E/02): KHÔNG cắt (ghế↔ICT thẳng), ta chỉ
 *        TAP đọc ở WIRE_B_RX (IO21, qua chia mức 5V->3,3V) để BÁO LỖI:
 *        - biết ghế có đáp 02 khi ta bơm QR không (lỗi 'qr' = ghế không ăn).
 *        Đọc mềm, ĐỒNG BỘ (chỉ đọc lúc đang bơm) nên không lo trượt bit vì màn/4G.
 *
 *  BÁO LỖI (đẩy lên web qua ghiLoiTien -> nhịp tm_loi):
 *    'ket' = tờ vào escrow (81 4X) mà quá lâu không có 10, LÚC GHẾ ĐANG RẢNH ->
 *            cục nhận kẹt/lỗi hoặc ghế không nhận. (Đang chạy thì bỏ tờ là bình
 *            thường, KHÔNG báo.)
 *    'qr'  = bơm QR mà ghế không đáp 02 -> ghế không ăn khung giả lập.
 * ========================================================================== */
#pragma once
#include <Arduino.h>

#define TIEN_RX_ICT   35     // IO35 (chỉ-input) — đọc TX của ICT (81/4X/10)
#define TIEN_TX_GHE   27     // IO27 — phát sang chân nhận tiền của ghế
#define WIRE_B_RX     21     // IO21 — tap dây B (ghế phát 3E/5E/02), qua chia mức 5->3,3V
#define TIEN_BAUD     4800
#define TIEN_KET_MS   8000   // escrow quá ngần này không có 10 (lúc rảnh) = kẹt

static const long TIEN_MENH_GIA[7] = { 5000, 10000, 20000, 50000, 100000, 200000, 500000 };

// Có tờ tiền mặt THẬT (đã nuốt): cộng giờ + ghi sổ.
typedef void (*TienMatCb)(long vnd);
// Báo lỗi: active=true đang lỗi, false = hết lỗi. Khớp chữ ký ghiLoiTien wrapper.
typedef void (*LoiTienCb)(const char* ma, bool active);

class CongTien {
public:
  void khoiDong(TienMatCb cb, LoiTienCb loi = nullptr) {
    _cb = cb; _loi = loi; _st = 0; _dangBom = false; _choVnd = 0; _escrowLuc = 0; _daKet = false;
    Serial1.begin(TIEN_BAUD, SERIAL_8E1, TIEN_RX_ICT, TIEN_TX_GHE);
    pinMode(WIRE_B_RX, INPUT);
    delay(60);
    while (Serial1.available()) Serial1.read();
    Serial.printf("[TIEN] cong 4800 8E1: ICT@IO%d, ghe@IO%d, wireB@IO%d.\n",
                  TIEN_RX_ICT, TIEN_TX_GHE, WIRE_B_RX);
  }

  /** .ino báo trạng thái ghế đang chạy hay không (để phân biệt kẹt-tiền với đang-chạy). */
  void datChay(bool dangChay) { _dangChay = dangChay; }

  /** Gọi mỗi vòng loop(): relay tiền mặt + phát hiện tờ tiền + dò kẹt. */
  void tick() {
    while (Serial1.available()) {
      uint8_t b = (uint8_t)Serial1.read();
      if (!_dangBom) Serial1.write(b);
      _quan(b);
    }
    // Dò KẸT: escrow treo quá lâu lúc ghế RẢNH = cục nhận kẹt/không nuốt.
    if (_choVnd > 0 && !_dangChay && !_daKet && _escrowLuc &&
        (millis() - _escrowLuc > TIEN_KET_MS)) {
      _daKet = true;
      if (_loi) _loi("ket", true);
      Serial.println("[TIEN] LOI 'ket': to tien vao escrow qua lau khong nuot (ghe ranh).");
    }
  }

  /** QR đã trả `vnd`: bơm khung "có tiền" vào ghế. Kiểm ghế có đáp 02 không (lỗi 'qr'). */
  void bom(long vnd) {
    if (vnd <= 0) return;
    _dangBom = true;
    bool coAn = false;
    for (int i = 6; i >= 0 && vnd > 0; i--) {
      while (vnd >= TIEN_MENH_GIA[i]) { if (_phatMotTo(i)) coAn = true; vnd -= TIEN_MENH_GIA[i]; }
    }
    _dangBom = false;
    if (_loi) _loi("qr", coAn ? false : true);   // không thấy 02 lần nào -> báo lỗi
  }

  void bomChiSo(int idx) { if (idx >= 0 && idx <= 6) { _dangBom = true; _phatMotTo(idx); _dangBom = false; } }

private:
  TienMatCb _cb = nullptr;
  LoiTienCb _loi = nullptr;
  int  _st = 0;
  long _choVnd = 0;
  uint32_t _escrowLuc = 0;
  bool _daKet = false;
  bool _dangBom = false;
  bool _dangChay = false;

  /** Phát 1 tờ giả + chờ ghế đáp 02 trên wire B. Trả true nếu thấy 02 (ghế ăn). */
  bool _phatMotTo(int idx) {
    uint8_t ma = (uint8_t)(0x40 + idx);
    Serial1.write(0x81); Serial1.write(ma); Serial1.flush();
    bool ok = _cho02(500);        // ghế đáp 02 trong ~500ms là đã ăn
    Serial1.write(0x10); Serial1.flush();
    delay(200);
    Serial.printf("[TIEN] BOM 81 %02X (%ld d) %s\n", ma, TIEN_MENH_GIA[idx],
                  ok ? "-> ghe dap 02 (an)" : "-> KHONG thay 02");
    return ok;
  }

  /** Đọc mềm wire B (8E1, 4800) tìm byte 0x02 trong toMs. Đồng bộ, chỉ dùng lúc bơm. */
  bool _cho02(uint32_t toMs) {
    uint32_t het = millis() + toMs;
    while (millis() < het) {
      int b = _docByteB(het);
      if (b == 0x02) return true;
    }
    return false;
  }
  int _docByteB(uint32_t hetMs) {
    // chờ start bit (cao->thấp)
    while (digitalRead(WIRE_B_RX) == HIGH) { if (millis() >= hetMs) return -1; }
    uint32_t t0 = micros();
    const long BIT = 208;                 // 1e6/4800
    uint8_t v = 0;
    for (int i = 0; i < 8; i++) {          // 8 data bit, LSB trước, lấy mẫu giữa bit
      while ((long)(micros() - t0) < BIT + BIT / 2 + BIT * i) { }
      if (digitalRead(WIRE_B_RX)) v |= (uint8_t)(1u << i);
    }
    while ((long)(micros() - t0) < BIT + BIT / 2 + BIT * 9) { }   // bỏ parity + tới stop
    return v;
  }

  void _quan(uint8_t b) {
#ifdef TIEN_DEBUG
    Serial.printf("[TIEN] rx %02X\n", b);
#endif
    if (_st == 0) {
      if (b == 0x81) _st = 1;
      else if (b == 0x10 && _choVnd > 0) {        // đã nuốt -> ghi sổ
        Serial.printf("[TIEN] +%ld d (da nuot, 10) -> ghi so\n", _choVnd);
        if (_cb) _cb(_choVnd);
        _choVnd = 0; _escrowLuc = 0;
        if (_daKet) { _daKet = false; if (_loi) _loi("ket", false); }   // nuốt được -> hết kẹt
      }
    } else {
      _st = 0;
      if ((b & 0xF8) == 0x40) {                    // tờ vào escrow
        int idx = b & 0x07;
        if (idx <= 6) { _choVnd = TIEN_MENH_GIA[idx]; _escrowLuc = millis(); _daKet = false;
          Serial.printf("[TIEN] to %ld d vao escrow (81 %02X), cho 10...\n", _choVnd, b); }
      }
    }
  }
};

extern CongTien congTien;
