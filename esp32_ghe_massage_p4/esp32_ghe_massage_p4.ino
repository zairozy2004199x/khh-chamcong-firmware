/* ============================================================================
 *  POSH QR — bản PORT sang GUITION JC4880P443C (ESP32-P4 + ESP32-C6)
 *  ----------------------------------------------------------------------------
 *  GIAI ĐOẠN 1a — BRING-UP (KHÔNG cần màn hình).
 *
 *  Mục tiêu: xác nhận BO SỐNG trước khi bỏ công vào lớp màn DSI:
 *     · In thông tin chip P4 (model, heap, PSRAM, MAC).
 *     · Quét bus I²C (SDA 7 / SCL 8) — phải thấy GT911 @ 0x5D và codec ES8311.
 *     · Nối WiFi qua ESP32-C6 (ESP-Hosted) — in trạng thái + IP.
 *  (Cổng tiền / 4G / relay / dò xung để GĐ sau — cần chốt chân trong cau_hinh_p4.h.)
 *
 *  ⚠️ MÀN HÌNH (ST7701S MIPI-DSI) CHƯA có ở bước này — cố ý. Panel phải khởi tạo
 *     bằng ĐÚNG init-sequence + timing của bo; sẽ thêm ở GĐ1b khi có tham số panel
 *     chạy được (từ ví dụ vendor / ESPHome PR #12068 / Arduino_GFX ST7701 480x800).
 *
 *  MÔI TRƯỜNG: Arduino-ESP32 ≥ 3.3.6, chọn board ESP32-P4; WiFi qua C6 cần nạp sẵn
 *     firmware ESP-Hosted cho C6 (xem field-notes bo). Nếu WiFi.begin không nối được,
 *     kiểm tra phần C6/ESP-Hosted trước — KHÔNG phải lỗi code này.
 *
 *  Bí mật (SSID/mật khẩu, khoá ký QR, URL server) KHÔNG nằm trong repo — để ở
 *  secrets.h build tại máy (giống các firmware khác). Bring-up này chỉ cần WiFi.
 * ========================================================================== */
#include <Wire.h>
#include <WiFi.h>
#include "cau_hinh_p4.h"

/* secrets.h: chỉ cần SEC_WIFI_SSID / SEC_WIFI_PASS cho bring-up. Nếu chưa có file,
   điền tạm ngay đây để nạp thử (ĐỪNG commit mật khẩu thật — repo công khai). */
#if __has_include("secrets.h")
  #include "secrets.h"
#endif
#ifndef SEC_WIFI_SSID
  #define SEC_WIFI_SSID "__DIEN_SSID__"
  #define SEC_WIFI_PASS "__DIEN_PASS__"
#endif

static void quetI2C() {
  Serial.println("[I2C] quet bus SDA=7 SCL=8 ...");
  Wire.begin(P4_TOUCH_SDA, P4_TOUCH_SCL);
  int thay = 0;
  for (uint8_t a = 1; a < 127; a++) {
    Wire.beginTransmission(a);
    if (Wire.endTransmission() == 0) {
      Serial.printf("[I2C]   thay thiet bi @ 0x%02X%s\n", a,
        a == P4_TOUCH_ADDR ? "  <- GT911 (cam ung)" : "");
      thay++;
    }
  }
  if (!thay) Serial.println("[I2C]   KHONG thay gi — kiem tra day/nguon cam ung.");
}

static void noiWiFi() {
  Serial.printf("[WiFi] noi qua C6 (ESP-Hosted) toi SSID '%s' ...\n", SEC_WIFI_SSID);
  WiFi.mode(WIFI_STA);
  WiFi.begin(SEC_WIFI_SSID, SEC_WIFI_PASS);
  uint32_t t0 = millis();
  while (WiFi.status() != WL_CONNECTED && millis() - t0 < 20000) {
    delay(400); Serial.print('.');
  }
  Serial.println();
  if (WiFi.status() == WL_CONNECTED)
    Serial.printf("[WiFi] OK — IP %s, RSSI %d dBm\n", WiFi.localIP().toString().c_str(), WiFi.RSSI());
  else
    Serial.println("[WiFi] CHUA noi duoc — kiem tra firmware ESP-Hosted tren C6 + SSID/mat khau.");
}

void setup() {
  Serial.begin(115200);
  delay(300);
  Serial.println("\n\n=== POSH QR P4 — BRING-UP GD1a ===");
  Serial.printf("[chip] %s, %d nhan, rev %d\n", ESP.getChipModel(), ESP.getChipCores(), ESP.getChipRevision());
  Serial.printf("[mem]  heap %u  |  PSRAM %u\n", ESP.getFreeHeap(), ESP.getPsramSize());
  Serial.printf("[mac]  %s\n", WiFi.macAddress().c_str());
  quetI2C();
  noiWiFi();
  Serial.println("=== bring-up xong. Man hinh (DSI) + logic o giai doan sau. ===");
}

void loop() {
  static uint32_t t = 0;
  if (millis() - t > 5000) {
    t = millis();
    Serial.printf("[song] uptime %lus  WiFi=%s  heap=%u\n",
      millis() / 1000, WiFi.status() == WL_CONNECTED ? "on" : "off", ESP.getFreeHeap());
  }
}
