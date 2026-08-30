/* ============================================================================
 *  outbox.h — HÀNG ĐỢI GỬI LẠI (store-and-forward) cho tiền mặt khi MẤT MẠNG
 *  ----------------------------------------------------------------------------
 *  Ý tưởng như bản cũ nhưng tổng quát hơn: mỗi lần ghế NUỐT một tờ tiền mặt, ghi
 *  ngay một bản ghi {số tiền, ref} vào NVS (flash sẵn có). Có mạng thì đẩy lên
 *  server (viec=tien_mat); server idempotent theo `ref` nên gửi lại KHÔNG cộng
 *  đôi. Đẩy xong mới xoá khỏi hàng đợi. Mất điện giữa chừng → bật lại vẫn còn.
 *
 *  Vì sao NVS chịu được: chỉ GHI khi có tờ mới (vài lần/ngày), không ghi theo
 *  nhịp → không mòn flash. Vòng (ring) sức chứa OB_CAP bản ghi; đầy thì bản
 *  cũ nhất bị đè (đã rất khó xảy ra: 30 lượt tiền mặt chưa gửi được liên tiếp).
 *
 *  .ino cấp hàm gửi:  bool guiTienMat(long vnd, const char* ref)  → true nếu
 *  server nhận (thân có "ok":true). Outbox lo phần nhớ + gửi lại.
 * ========================================================================== */
#pragma once
#include <Arduino.h>
#include <Preferences.h>
#include "esp_mac.h"

#define OB_CAP   30      // số bản ghi tiền mặt chưa gửi giữ tối đa

typedef bool (*GuiTienMatFn)(long vnd, const char* ref);

class Outbox {
public:
  void batDau(GuiTienMatFn fn) {
    _gui = fn;
    _p.begin("ob", false);
    _head = _p.getUInt("head", 0);
    _tail = _p.getUInt("tail", 0);
    _seq  = _p.getUInt("seq", 0);
    Serial.printf("[OUTBOX] mo: %u ban ghi cho gui\n", soCho());
  }

  uint32_t soCho() { return _tail - _head; }

  /* Ghi một lượt tiền mặt vào hàng đợi (ref sinh ổn định, không trùng). Gọi NGAY
     khi ghế nuốt tờ — ghế đã chạy rồi, đây chỉ là ghi sổ chịu được gửi lại. */
  void themTienMat(long vnd) {
    if (vnd <= 0) return;
    uint32_t slot = _tail % OB_CAP;
    String ref = _sinhRef(++_seq, vnd);
    char ka[8], kr[8]; snprintf(ka, sizeof ka, "a%u", slot); snprintf(kr, sizeof kr, "r%u", slot);
    _p.putLong(ka, vnd);
    _p.putString(kr, ref);
    _tail++;
    if (_tail - _head > OB_CAP) _head = _tail - OB_CAP;   // đầy: bỏ bản cũ nhất
    _p.putUInt("tail", _tail);
    _p.putUInt("head", _head);
    _p.putUInt("seq", _seq);
    Serial.printf("[OUTBOX] + %ld d ref=%s (cho gui: %u)\n", vnd, ref.c_str(), soCho());
  }

  /* Gọi định kỳ khi CÓ MẠNG: đẩy bản cũ nhất trước; gửi được thì xoá rồi đẩy
     tiếp. Gửi hỏng thì DỪNG (giữ nguyên, thử lại lượt sau). Mỗi lần gọi đẩy tối
     đa `toiDa` bản để không chiếm loop quá lâu. */
  void day(int toiDa = 3) {
    if (!_gui) return;
    for (int i = 0; i < toiDa && soCho() > 0; i++) {
      uint32_t slot = _head % OB_CAP;
      char ka[8], kr[8]; snprintf(ka, sizeof ka, "a%u", slot); snprintf(kr, sizeof kr, "r%u", slot);
      long vnd = _p.getLong(ka, 0);
      String ref = _p.getString(kr, "");
      if (vnd <= 0 || ref.length() == 0) { _head++; _p.putUInt("head", _head); continue; }
      if (!_gui(vnd, ref.c_str())) return;   // server chưa nhận → giữ, thử lại sau
      _head++; _p.putUInt("head", _head);
      Serial.printf("[OUTBOX] da gui %ld d ref=%s (con: %u)\n", vnd, ref.c_str(), soCho());
    }
  }

private:
  Preferences _p;
  GuiTienMatFn _gui = nullptr;
  uint32_t _head = 0, _tail = 0, _seq = 0;

  static String _sinhRef(uint32_t seq, long vnd) {
    uint8_t m[6]; esp_read_mac(m, ESP_MAC_WIFI_STA);
    char b[40];
    snprintf(b, sizeof b, "TM-%02X%02X%02X-%lu-%ld", m[3], m[4], m[5], (unsigned long)seq, vnd);
    return String(b);
  }
};
