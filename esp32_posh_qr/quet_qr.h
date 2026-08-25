/* ============================================================================
 *  quet_qr.h — ĐỌC MODULE QUÉT MÃ QR NỐI UART
 * ----------------------------------------------------------------------------
 *  Module quét mã (loại GM65 / GM805 / TTL barcode scanner nói chung) đọc xong
 *  thì NHẢ THẲNG chuỗi ra chân TX, hết chuỗi kèm CR và/hoặc LF. Không có giao
 *  thức bắt tay gì cả — việc của mình chỉ là gom byte cho tới hết dòng.
 *
 *  BA CÁI BẪY, tất cả đều IM LẶNG nếu không xử:
 *
 *  1) DÒNG ĐỨT KHÚC. UART về từng mẩu, không phải nguyên dòng. Đọc kiểu
 *     "có byte nào thì coi là xong" sẽ cắt mã làm đôi -> chữ ký không bao giờ khớp,
 *     mà log thì hiện một chuỗi TRÔNG NHƯ ĐÚNG (chỉ cụt đuôi). Nên: chỉ coi là xong
 *     khi gặp CR/LF, hoặc khi im lặng đủ lâu (QR_IM_LANG_MS).
 *
 *  2) MODULE TỰ ĐỘNG QUÉT LIÊN TỤC. Khách giơ mã 2 giây là module bắn ra cả chục
 *     dòng y hệt. Không chặn thì hộp tưởng 10 lượt -> ghi 10 mã vào danh sách đã
 *     dùng, kêu 10 lần. Nên: cùng một chuỗi trong QR_LAP_LAI_MS thì bỏ qua.
 *
 *  3) RÁC KHÔNG IN ĐƯỢC. Bật/tắt nguồn module hay lỗi baud là UART nhả byte rác.
 *     Nên: bỏ mọi byte < 0x20 và > 0x7E (mã POSH toàn ASCII in được).
 *
 *  ⚠️ BAUD phải khớp module. Mặc định của hầu hết module là 9600. Sai baud thì
 *     KHÔNG báo lỗi gì hết, chỉ ra rác — nhìn log thấy chuỗi loạn xạ là biết.
 * ========================================================================== */
#pragma once
#include <Arduino.h>

#define QR_DAI_TOI_DA   200      // dài hơn nữa chắc chắn không phải mã POSH
#define QR_IM_LANG_MS   120      // im ngần này coi như hết dòng (module không gửi CR/LF)
#define QR_LAP_LAI_MS   3000     // cùng chuỗi trong ngần này = một lần giơ mã, không phải lượt mới

class QuetQR {
public:
  /**
   * @param cong    UART dùng cho module (Serial1)
   * @param chanRx  chân ESP32 NHẬN, nối vào TX của module
   * @param chanTx  chân ESP32 GỬI, nối vào RX của module (-1 nếu không đấu)
   * @param baud    baud của module (mặc định module là 9600)
   * @param chanKich chân TRIG/kích quét, -1 = module tự quét liên tục
   */
  void batDau(HardwareSerial* cong, int chanRx, int chanTx, long baud, int chanKich) {
    _cong = cong; _chanKich = chanKich;
    _cong->begin(baud, SERIAL_8N1, chanRx, chanTx);
    if (_chanKich >= 0) {
      pinMode(_chanKich, OUTPUT);
      digitalWrite(_chanKich, HIGH);      // đa số module: kéo xuống mass là quét
    }
    _dem = 0; _cuoiByteMs = 0;
    Serial.printf("[QR] module @ %ld baud, RX=%d TX=%d TRIG=%d\n", baud, chanRx, chanTx, chanKich);
  }

  /** Kích module quét một nhát (chỉ có tác dụng nếu đã đấu chân TRIG). */
  void kich() {
    if (_chanKich < 0) return;
    digitalWrite(_chanKich, LOW);
    delay(30);
    digitalWrite(_chanKich, HIGH);
  }

  /**
   * Gọi liên tục trong loop(). Trả về chuỗi mã khi đọc XONG một dòng, còn lại trả "".
   * Chuỗi trả về đã bỏ CR/LF và khoảng trắng hai đầu.
   */
  String doc() {
    while (_cong && _cong->available()) {
      int b = _cong->read();
      _cuoiByteMs = millis();
      if (b == '\r' || b == '\n') { String s = _chot(); if (s.length()) return s; continue; }
      if (b < 0x20 || b > 0x7E)   continue;                  // rác/không in được: bỏ
      if (_dem < QR_DAI_TOI_DA)   _dem_buf[_dem++] = (char)b;
      else {
        /* Tràn: bỏ CẢ dòng chứ không cắt bớt. Cắt bớt sinh ra một chuỗi trông hợp lệ
           mà chữ ký sai -> lại đi mò xem mã hỏng hay khoá sai. */
        _dem = 0; _tran = true;
      }
    }
    // Module không gửi CR/LF: im lặng đủ lâu thì chốt.
    if (_dem > 0 && _cuoiByteMs && (millis() - _cuoiByteMs) > QR_IM_LANG_MS) {
      String s = _chot(); if (s.length()) return s;
    }
    return "";
  }

  /** Xoá nhớ "vừa đọc chuỗi này" — dùng khi muốn cho phép quét lại ngay lập tức. */
  void quenLanTruoc() { _lanTruoc = ""; _lanTruocMs = 0; }

private:
  HardwareSerial* _cong = nullptr;
  int      _chanKich   = -1;
  char     _dem_buf[QR_DAI_TOI_DA + 1];
  int      _dem        = 0;
  bool     _tran       = false;
  uint32_t _cuoiByteMs = 0;
  String   _lanTruoc   = "";
  uint32_t _lanTruocMs = 0;

  String _chot() {
    if (_tran) {
      Serial.println("[QR] ⚠️ chuỗi dài quá mức -> bỏ cả dòng (sai baud? không phải mã POSH?)");
      _tran = false; _dem = 0; return "";
    }
    if (_dem <= 0) { _dem = 0; return ""; }
    _dem_buf[_dem] = 0;
    String s = String(_dem_buf);
    _dem = 0;
    s.trim();
    if (s.length() == 0) return "";
    uint32_t bg = millis();
    if (s == _lanTruoc && _lanTruocMs && (bg - _lanTruocMs) < QR_LAP_LAI_MS) {
      _lanTruocMs = bg;                       // vẫn đang giơ cùng một mã -> nuốt
      return "";
    }
    _lanTruoc = s; _lanTruocMs = bg;
    return s;
  }
};
