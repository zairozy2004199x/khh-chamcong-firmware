/* ============================================================================
 *  mdb_uart.h — UART MỀM 9 BIT cho MDB trên ESP32 (thu + phát, giữ bit mode)
 * ----------------------------------------------------------------------------
 *  VÌ SAO PHẢI TỰ LÀM: ESP32 KHÔNG có UART 9 bit trong phần cứng (chỉ tới 8 bit
 *  + chẵn lẻ, mà chẵn lẻ của ESP32 chỉ EVEN/ODD, không MARK/SPACE nên không mượn
 *  làm bit thứ 9 được). MDB thì cần bit thứ 9 (mode). Ở 9600 baud một bit rộng
 *  ~104us — đủ chậm để lắc chân bằng phần mềm.
 *
 *  MDB LÀ BÁN SONG CÔNG (không nói cùng lúc): chủ hỏi -> chờ -> tớ trả lời. Nhờ
 *  vậy bộ xen-giữa KHÔNG phải thu/phát 4 dây đồng thời — mỗi lúc chỉ một chiều
 *  đang truyền. Đó là lý do vòng lặp relay bằng phần mềm khả thi ở đây.
 *
 *  ⚠️ HAI HẰNG SỐ THỜI GIAN DƯỚI ĐÂY PHẢI CHỈNH TRÊN MÁY THẬT.
 *     BIT_US lý thuyết = 1e6/9600 = 104us, nhưng chân số + overhead làm lệch vài
 *     phần trăm. Cắm vào bo, chạy 'MDB' đọc ra khung sạch (không '?') thì mới đúng.
 *     Lệ thuộc phần cứng nên KHÔNG test được trên máy tính — chỉ phần MÃ HOÁ /
 *     GIẢI MÃ (mdb.h) là test được, và đã test (ci/kiem-mdb.sh).
 *
 *  ⚠️ Còn TỔNG nhịp relay (chủ hỏi -> mình chuyển -> tớ trả lời -> mình chuyển về)
 *     phải lọt trong ~5ms mà MDB cho phép tớ trả lời. Chuyển byte-nối-byte thêm
 *     ~1 byte-time mỗi chiều (~1,15ms). Thường lọt, nhưng phải đo trên bo.
 * ========================================================================== */
#pragma once
#include <Arduino.h>
#include "mdb.h"

#define MDB_BIT_US   104     // 1e6/9600. Chỉnh ±vài us trên máy thật nếu đọc ra '?'
#define MDB_KHUNG_US 3000    // im quá lâu giữa các byte = coi như hết một lượt

class MdbUart {
public:
  /** rx: chân đọc (đã qua chia mức 5V->3,3V). tx: chân phát (-1 = chỉ nghe). */
  void batDau(int chanRx, int chanTx) {
    _rx = chanRx; _tx = chanTx;
    pinMode(_rx, INPUT);
    if (_tx >= 0) { pinMode(_tx, OUTPUT); digitalWrite(_tx, HIGH); }  // idle = mức cao
  }

  /** Có đang bắt đầu một khung không (dây từ cao xuống thấp = start bit). */
  bool coByte() { return digitalRead(_rx) == LOW; }

  /**
   * Đọc MỘT byte 9 bit. Trả true nếu đọc xong, điền ra->giaTri/mode/khungLoi.
   * Gọi khi coByte()==true. Lấy mẫu ở GIỮA mỗi bit cho chắc.
   * ⚠️ Đây là chỗ nhịp phải chuẩn — xem cảnh báo BIT_US ở đầu file.
   */
  bool docByte(MdbByte* ra) {
    // đợi mép xuống của start (đang thấp rồi thì vào luôn)
    uint32_t t0 = micros();
    // canh tới giữa start bit
    _choToi(t0, MDB_BIT_US / 2);
    if (digitalRead(_rx) != LOW) return false;         // nhiễu, không phải start
    uint16_t v = 0;
    for (int i = 0; i < 9; i++) {
      _choToi(t0, MDB_BIT_US / 2 + (long)MDB_BIT_US * (i + 1));
      if (digitalRead(_rx)) v |= (uint16_t)(1u << i);   // bit thấp trước
    }
    _choToi(t0, MDB_BIT_US / 2 + (long)MDB_BIT_US * 10);
    uint8_t stop = digitalRead(_rx) ? 1 : 0;
    ra->giaTri   = (uint8_t)(v & 0xFF);
    ra->mode     = (uint8_t)((v >> 8) & 1);
    ra->khungLoi = (stop != 1);
    return true;
  }

  /** Phát MỘT byte 9 bit (giữ bit mode). Chỉ gọi nếu _tx >= 0. */
  void guiByte(uint8_t giaTri, uint8_t mode) {
    if (_tx < 0) return;
    uint8_t b[11]; mdbMaHoa(giaTri, mode, b);
    uint32_t t0 = micros();
    for (int i = 0; i < 11; i++) {
      digitalWrite(_tx, b[i] ? HIGH : LOW);
      _choToi(t0, (long)MDB_BIT_US * (i + 1));
    }
    digitalWrite(_tx, HIGH);                            // về idle
  }

  int chanRx() const { return _rx; }
  int chanTx() const { return _tx; }

private:
  int _rx = -1, _tx = -1;
  /* Bận-chờ tới mốc us kể từ t0. Dùng bận-chờ (không delayMicroseconds) để mép
     bit không bị trôi tích luỹ — sai số mỗi bit cộng dồn 11 lần là hỏng khung. */
  static void _choToi(uint32_t t0, long moc_us) {
    while ((long)(micros() - t0) < moc_us) { /* bận-chờ */ }
  }
};
