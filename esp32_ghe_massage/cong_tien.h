/* ============================================================================
 *  cong_tien.h — CỔNG TIỀN xen giữa ICT L70 <-> GHẾ thật  (Hướng 1)
 * ----------------------------------------------------------------------------
 *  Bus tiền: SERIAL 4800 baud, 8 data + PARITY CHẴN + 1 stop (8E1). Bus IM khi
 *  rảnh, chỉ nói khi có tờ tiền. Khung:
 *      ICT -> ghế:  81 4X (X=chỉ số mệnh giá)   ghế -> ICT: 02 (nuốt)
 *      ICT -> ghế:  10 (đã nuốt)                ghế -> ICT: 3E mở / 5E khoá
 *      Mệnh giá: 40=5k 41=10k 42=20k 43=50k 44=100k 45=200k 46=500k = 0x40+chỉsố.
 *
 *  DÂY:
 *      TIEN_RX_ICT (IO35) đọc TX của ICT (81/4X/10)  — qua chia mức 5->3,3V
 *      TIEN_TX_GHE (IO27) phát sang RX nhận tiền của ghế — qua chia mức 3,3->5V
 *          Dây A đã CẮT, ESP xen giữa: relay tiền mặt + bơm QR (Serial1 phần cứng).
 *      WIRE_B_RX  (IO34) đọc TX của ghế (3E/5E/02) — QUA ISOLATOR/BUFFER CAO-TRỞ
 *          (ADuM1201...), KHÔNG dùng mạch MOSFET (trở kéo 10k của nó làm RỚT ICT
 *          vì dây B là dây sống chở lệnh mở khoá). ADuM ngõ vào cao-trở nên không
 *          tải dây -> ICT chạy bình thường. Đọc mềm, ĐỒNG BỘ (chỉ lúc bơm).
 *
 *  BÁO LỖI (đẩy web qua ghiLoiTien -> nhịp tm_loi):
 *      'ket' = tờ vào escrow (81 4X) mà quá lâu không có 10, LÚC GHẾ RẢNH.
 *      'qr'  = bơm QR mà ghế không đáp 02 -> ghế không ăn khung giả lập.
 * ========================================================================== */
#pragma once
#include <Arduino.h>

#define TIEN_RX_ICT   35     // IO35 (chỉ-input) — đọc TX của ICT
#define TIEN_TX_GHE   27     // IO27 — phát sang chân nhận tiền của ghế
#define WIRE_B_RX     34     // IO34 (chỉ-input) — đọc TX ghế (3E/5E/02) qua ADuM1201 (cao-trở!)
#define TIEN_BAUD     4800
#define TIEN_KET_MS   8000
/* Báo lỗi 'qr' (ghế không đáp 02 khi bơm) cần wire B (IO34) đọc SẠCH. Khi wire B
   còn ra rác (idle không cao / sai kênh ADuM) thì để 0 cho khỏi báo nhầm — vẫn in
   byte wireB để soi. Đọc sạch rồi (idle HIGH, chỉ thấy 3E/5E/02) thì đổi thành 1. */
#define BAT_LOI_QR    0

static const long TIEN_MENH_GIA[7] = { 5000, 10000, 20000, 50000, 100000, 200000, 500000 };

typedef void (*TienMatCb)(long vnd);
typedef void (*LoiTienCb)(const char* ma, bool active);

class CongTien {
public:
  void khoiDong(TienMatCb cb, LoiTienCb loi = nullptr) {
    _cb = cb; _loi = loi; _st = 0; _dangBom = false; _choVnd = 0; _escrowLuc = 0; _daKet = false;
    Serial1.begin(TIEN_BAUD, SERIAL_8E1, TIEN_RX_ICT, TIEN_TX_GHE);
    pinMode(WIRE_B_RX, INPUT);
    delay(60);
    while (Serial1.available()) Serial1.read();
    Serial.printf("[TIEN] cong 4800 8E1: ICT@IO%d, ghe@IO%d, wireB@IO%d (ADuM).\n",
                  TIEN_RX_ICT, TIEN_TX_GHE, WIRE_B_RX);
    // Soi mức IDLE của wire B (lúc rảnh phải là CAO). Cao ~100% = tốt; ~50/50 hoặc
    // thấp = ADuM sai kênh/thả nổi/đảo -> đọc ra rác. (Chỉ để chẩn đoán, in 1 lần.)
    uint32_t hi = 0, lo = 0;
    for (uint32_t i = 0; i < 20000; i++) { if (digitalRead(WIRE_B_RX)) hi++; else lo++; }
    Serial.printf("[TIEN] wireB idle: CAO=%lu%% (%s)\n", (unsigned long)(hi * 100 / (hi + lo)),
                  (hi > lo * 20) ? "OK idle cao" : "!! khong idle cao -> kiem ADuM/kenh/dao");
  }

  void datChay(bool dangChay) { _dangChay = dangChay; }

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

  void bom(long vnd) {
    if (vnd <= 0) return;
    _dangBom = true;
    bool coAn = false;
    for (int i = 6; i >= 0 && vnd > 0; i--) {
      while (vnd >= TIEN_MENH_GIA[i]) { if (_phatMotTo(i)) coAn = true; vnd -= TIEN_MENH_GIA[i]; }
    }
    _dangBom = false;
    if (BAT_LOI_QR && _loi) _loi("qr", coAn ? false : true);   // chỉ báo 'qr' khi wire B đã sạch
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

  /** Phát 1 tờ giả + chờ ghế đáp 02 trên wire B (IO34). Trả true nếu thấy 02 (ghế ăn). */
  bool _phatMotTo(int idx) {
    uint8_t ma = (uint8_t)(0x40 + idx);
    Serial1.write(0x81); Serial1.write(ma); Serial1.flush();
    bool ok = _cho02(500);
    Serial1.write(0x10); Serial1.flush();
    delay(200);
    Serial.printf("[TIEN] BOM 81 %02X (%ld d) %s\n", ma, TIEN_MENH_GIA[idx],
                  ok ? "-> ghe dap 02 (an)" : "-> KHONG thay 02");
    return ok;
  }

  bool _cho02(uint32_t toMs) {
    uint32_t het = millis() + toMs;
    bool thayGi = false;
    while (millis() < het) {
      int b = _docByteB(het);
      if (b >= 0) { thayGi = true; Serial.printf("[TIEN] wireB rx %02X\n", b); }
      if (b == 0x02) return true;
    }
    if (!thayGi) Serial.println("[TIEN] wireB IM (IO34 khong bat duoc canh) — kiem ADuM/day");
    return false;
  }
  int _docByteB(uint32_t hetMs) {
    while (digitalRead(WIRE_B_RX) == HIGH) { if (millis() >= hetMs) return -1; }  // chờ start
    // KHOÁ NGẮT khi lấy mẫu: bit-bang trên ESP32 (có 4G/hệ thống) bị ngắt làm lệch
    // nhịp -> ra rác. Chỉ khoá ~2ms/byte nên không hại 4G.
    noInterrupts();
    uint32_t t0 = micros();
    const long BIT = 208;
    uint8_t v = 0;
    for (int i = 0; i < 8; i++) {
      while ((long)(micros() - t0) < BIT + BIT / 2 + BIT * i) { }
      if (digitalRead(WIRE_B_RX)) v |= (uint8_t)(1u << i);
    }
    while ((long)(micros() - t0) < BIT + BIT / 2 + BIT * 9) { }
    interrupts();
    return v;
  }

  void _quan(uint8_t b) {
#ifdef TIEN_DEBUG
    Serial.printf("[TIEN] rx %02X\n", b);
#endif
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
