/* ============================================================================
 *  cong_tien.h — CỔNG TIỀN xen giữa ICT L70 <-> GHẾ thật  (Hướng 1)
 * ----------------------------------------------------------------------------
 *  Bus tiền: SERIAL 4800 baud, 8 data + PARITY CHẴN + 1 stop (8E1). Bus IM khi
 *  rảnh, chỉ nói khi có tờ tiền. Khung:
 *      ICT -> ghế:  81 4X (X=chỉ số mệnh giá)   ghế -> ICT: 02 (nuốt)
 *      ICT -> ghế:  10 (đã nuốt)                ghế -> ICT: 3E mở / 5E khoá
 *      Mệnh giá: 40=5k 41=10k 42=20k 43=50k 44=100k 45=200k 46=500k = 0x40+chỉsố.
 *
 *  DÂY (chỉ MỘT UART phần cứng — Serial1; chừa Serial2 cho 4G):
 *      TIEN_RX_ICT (IO35) đọc TX của ICT (81/4X/10)
 *      TIEN_TX_GHE (IO27) phát sang RX nhận tiền của ghế
 *      Dây A đã CẮT, ESP xen giữa: relay tiền mặt ICT->ghế + bơm QR.
 *      Dây B (ghế->ICT: 3E/5E/02): KHÔNG đọc. Đã thử tap qua ADuM1200 -> nhiễu
 *      (idle ~92%, đọc mềm ra rác vì bit-bang trên bo có 4G/màn). Không đáng cố.
 *
 *  BÁO LỖI (đẩy web qua ghiLoiTien -> nhịp tm_loi):
 *      'ket' = tờ vào escrow (81 4X) mà quá lâu không có 10, LÚC GHẾ RẢNH -> cục
 *              nhận kẹt/không nuốt. (Đang chạy thì bỏ tờ là bình thường, KHÔNG báo.)
 * ========================================================================== */
#pragma once
#include <Arduino.h>

/* 🔧 BẬT ĐỂ ĐO: in RA MỌI byte ICT-TX (IO35) — dùng để tìm mã lỗi ICT phát ra lúc
   hỏng (kẹt/đầy thùng/cảm biến). Gây lỗi ICT + nhét tiền, xem [TIEN] rx XX. Tìm ra
   byte lỗi rồi thì XÓA dòng này (tắt log) cho đỡ rác Serial. */
#define TIEN_DEBUG    1

#define TIEN_RX_ICT   35     // IO35 (chỉ-input) — đọc TX của ICT (81/4X/10)
#define TIEN_TX_GHE   27     // IO27 — phát sang chân nhận tiền của ghế
#define TIEN_BAUD     4800
#define TIEN_KET_MS   8000

static const long TIEN_MENH_GIA[7] = { 5000, 10000, 20000, 50000, 100000, 200000, 500000 };

typedef void (*TienMatCb)(long vnd);
typedef void (*LoiTienCb)(const char* ma, bool active);

class CongTien {
public:
  void khoiDong(TienMatCb cb, LoiTienCb loi = nullptr) {
    _cb = cb; _loi = loi; _st = 0; _dangBom = false; _choVnd = 0; _escrowLuc = 0; _daKet = false;
    Serial1.begin(TIEN_BAUD, SERIAL_8E1, TIEN_RX_ICT, TIEN_TX_GHE);
    delay(60);
    while (Serial1.available()) Serial1.read();
    Serial.printf("[TIEN] cong 4800 8E1: doc ICT@IO%d, phat ghe@IO%d.\n", TIEN_RX_ICT, TIEN_TX_GHE);
  }

  /** .ino báo ghế đang chạy hay không (để phân biệt kẹt-tiền với đang-chạy-bị-từ-chối). */
  void datChay(bool dangChay) { _dangChay = dangChay; }

  /** Gọi mỗi vòng loop(): relay tiền mặt ICT->ghế + phát hiện tờ tiền + dò kẹt. */
  void tick() {
    while (Serial1.available()) {
      uint8_t b = (uint8_t)Serial1.read();
      if (!_dangBom) Serial1.write(b);
      _quan(b);
    }
    if (_choVnd > 0 && !_dangChay && !_daKet && _escrowLuc &&
        (millis() - _escrowLuc > TIEN_KET_MS)) {
      _daKet = true;
      if (_loi) _loi("ket", true);
      Serial.println("[TIEN] LOI 'ket': to tien vao escrow qua lau khong nuot (ghe ranh).");
    }
  }

  /** QR đã trả `vnd`: bơm khung "có tiền" vào ghế (tách thành các tờ). */
  void bom(long vnd) {
    if (vnd <= 0) return;
    _dangBom = true;
    for (int i = 6; i >= 0 && vnd > 0; i--) {
      while (vnd >= TIEN_MENH_GIA[i]) { _phatMotTo(i); vnd -= TIEN_MENH_GIA[i]; }
    }
    _dangBom = false;
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

  void _phatMotTo(int idx) {
    uint8_t ma = (uint8_t)(0x40 + idx);
    Serial1.write(0x81); Serial1.write(ma); Serial1.flush();
    delay(150);                  // chờ ghế xử lý + đáp 02 (đi thẳng ghế<->ICT trên dây B)
    Serial1.write(0x10); Serial1.flush();
    delay(250);
    Serial.printf("[TIEN] BOM 81 %02X (%ld d)\n", ma, TIEN_MENH_GIA[idx]);
  }

  void _quan(uint8_t b) {
#ifdef TIEN_DEBUG
    Serial.printf("[TIEN] rx %02X\n", b);
#endif
    // Tờ THẬT: 81 4X (escrow) -> ... -> 10 (đã nuốt). CHỈ tính khi có 10 (nhả ra thì không có).
    if (_st == 0) {
      if (b == 0x81) _st = 1;
      else if (b == 0x10 && _choVnd > 0) {
        Serial.printf("[TIEN] +%ld d (da nuot, 10) -> ghi so\n", _choVnd);
        if (_cb) _cb(_choVnd);
        _choVnd = 0; _escrowLuc = 0;
        if (_daKet) { _daKet = false; if (_loi) _loi("ket", false); }
      }
    } else {
      _st = 0;
      if ((b & 0xF8) == 0x40) {
        int idx = b & 0x07;
        if (idx <= 6) { _choVnd = TIEN_MENH_GIA[idx]; _escrowLuc = millis(); _daKet = false;
          Serial.printf("[TIEN] to %ld d vao escrow (81 %02X), cho 10...\n", _choVnd, b); }
      }
    }
  }
};

extern CongTien congTien;
