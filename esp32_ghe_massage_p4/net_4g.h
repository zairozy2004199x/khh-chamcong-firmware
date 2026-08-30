/* ============================================================================
 *  net_4g.h — 4G A7680C (SIMCom) qua UART, HTTPS bằng lệnh AT (AT-HTTP)
 *  ----------------------------------------------------------------------------
 *  Port NGUYÊN cách bản cũ (esp32_ghe_massage) chạy 4G, chỉ đổi chân sang
 *  cau_hinh_p4.h (Serial2 = P4_SIM_TX_PIN / P4_SIM_RX_PIN, PWRKEY P4_SIM_PWRKEY).
 *
 *  Vì sao AT-HTTP mà không TinyGSM: cần GIỮ phiên để ĐỌC thân trả về (ghế phải
 *  biết đã trả tiền chưa / giá / lệnh). POST-rồi-đóng không đọc được thân.
 *
 *  Dùng: net4gBatDau() (bật nguồn + đăng ký mạng, ~20–30s, gọi khi RẢNH);
 *        net4gReady(); net4gPost(url, body, &resp) -> mã HTTP (200 = ok).
 *  ⚠️ Bring-up CHẶN luồng ~20s → chỉ gọi net4gBatDau() lúc ST_IDLE, đừng gọi
 *     giữa lúc ghế đang chạy (đơ màn). net4gPost() nhanh (vài giây).
 * ========================================================================== */
#pragma once
#include <Arduino.h>
#include "cau_hinh_p4.h"

#if (P4_SIM_TX_PIN >= 0) && (P4_SIM_RX_PIN >= 0)

static bool     g_4gReady = false;
static int      g_simTx   = P4_SIM_TX_PIN, g_simRx = P4_SIM_RX_PIN;
static long     g_simBaud = 115200;
static int      g_4gFails = 0;

static String _atSend(const char* cmd, unsigned long to) {
  while (Serial2.available()) Serial2.read();
  Serial2.print(cmd); Serial2.print("\r\n");
  unsigned long t0 = millis(); String r = "";
  while (millis() - t0 < to) {
    while (Serial2.available()) r += (char)Serial2.read();
    if (r.indexOf("OK") >= 0 || r.indexOf("ERROR") >= 0) break;
    delay(5);
  }
  r.replace("\r", " "); r.replace("\n", " "); r.trim(); return r;
}
static String _atWait(const char* token, unsigned long to) {
  unsigned long t0 = millis(); String r = "";
  while (millis() - t0 < to) {
    while (Serial2.available()) r += (char)Serial2.read();
    if (r.indexOf(token) >= 0) break;
    delay(3);
  }
  return r;
}
static bool _atProbe(int txPin, int rxPin, long baud) {
  Serial2.begin(baud, SERIAL_8N1, rxPin, txPin); delay(300);
  bool ok = false;
  for (int i = 0; i < 3 && !ok; i++) {
    while (Serial2.available()) Serial2.read();
    Serial2.print("AT\r\n");
    unsigned long t0 = millis(); String r = "";
    while (millis() - t0 < 800) { while (Serial2.available()) r += (char)Serial2.read();
      if (r.indexOf("OK") >= 0) { ok = true; break; } delay(5); }
  }
  Serial2.end(); return ok;
}
static void _modemPowerOn() {
#if P4_USE_PWRKEY && (P4_SIM_PWRKEY >= 0)
  pinMode(P4_SIM_PWRKEY, OUTPUT);
  digitalWrite(P4_SIM_PWRKEY, HIGH); delay(200);
  digitalWrite(P4_SIM_PWRKEY, LOW);  delay(1200);
  digitalWrite(P4_SIM_PWRKEY, HIGH);
#endif
  delay(12000);   // A7680C boot ~12s
}
static bool _net4gDiag() {
  _atSend("ATE0", 800);
  _atSend("AT+CPIN?", 2000);
  _atSend("AT+CFUN=1", 3000);
  _atSend("AT+CTZU=1", 1000);
  _atSend("AT+COPS=0", 12000);
  _atSend((String("AT+CGDCONT=1,\"IP\",\"") + P4_SIM_APN + "\"").c_str(), 1500);
  bool reg = false;
  for (int i = 0; i < 30 && !reg; i++) {
    String e = _atSend("AT+CEREG?", 1200);
    String g = _atSend("AT+CGREG?", 1200);
    if (e.indexOf(",1") >= 0 || e.indexOf(",5") >= 0 || g.indexOf(",1") >= 0 || g.indexOf(",5") >= 0) { reg = true; break; }
    delay(1500);
  }
  if (!reg) return false;
  _atSend("AT+CGACT=1,1", 10000);
  _atSend("AT+CSSLCFG=\"sslversion\",0,4", 1500);
  _atSend("AT+CSSLCFG=\"authmode\",0,0", 1500);
  _atSend("AT+CSSLCFG=\"enableSNI\",0,1", 1500);
  /* HTTPS cần GIỜ ĐÚNG để bắt tay TLS — giờ sai (1970/1980) là mọi lượt gọi lỗi câm. */
  _atSend("AT+CNTPCID=1", 1000);
  _atSend("AT+CNTP=\"pool.ntp.org\",28", 2000);
  _atSend("AT+CNTP", 9000);
  return true;
}
static bool net4gReady() { return g_4gReady; }
static bool net4gBatDau() {
  g_4gReady = false; _modemPowerOn();
  long bauds[] = {115200, 9600}; bool found = false;
  for (int bi = 0; bi < 2 && !found; bi++) {
    if (_atProbe(P4_SIM_TX_PIN, P4_SIM_RX_PIN, bauds[bi])) { g_simTx = P4_SIM_TX_PIN; g_simRx = P4_SIM_RX_PIN; g_simBaud = bauds[bi]; found = true; }
    else if (_atProbe(P4_SIM_RX_PIN, P4_SIM_TX_PIN, bauds[bi])) { g_simTx = P4_SIM_RX_PIN; g_simRx = P4_SIM_TX_PIN; g_simBaud = bauds[bi]; found = true; }
  }
  if (!found) { Serial.println("[4G] khong tra AT — kiem nguon 4V/PWRKEY/day/SIM"); return false; }
  Serial2.begin(g_simBaud, SERIAL_8N1, g_simRx, g_simTx); delay(300);
  if (_net4gDiag()) { g_4gReady = true; g_4gFails = 0; Serial.println("[4G] SAN SANG (LTE)"); return true; }
  Serial.println("[4G] chua dang ky mang — thu lai sau"); return false;
}
static void _net4gFail() { if (++g_4gFails >= 3) { g_4gReady = false; g_4gFails = 0; } }

static int _net4gReadStart(int want) {
  Serial2.print("AT+HTTPREAD=0,"); Serial2.print(want); Serial2.print("\r\n");
  String hdr = ""; unsigned long t0 = millis(); bool sawTag = false, done = false;
  while (!done && millis() - t0 < 8000) {
    while (Serial2.available()) {
      char c = (char)Serial2.read(); t0 = millis(); hdr += c;
      if (!sawTag) { if (hdr.endsWith("+HTTPREAD:")) { sawTag = true; hdr = ""; } }
      else if (c == '\n') { done = true; break; }
    }
    if (!done) delay(2);
  }
  if (!sawTag) return -1;
  int lc = hdr.lastIndexOf(','); int st = (lc >= 0) ? lc + 1 : 0;
  return hdr.substring(st).toInt();
}
/* POST JSON qua AT-HTTP, GIỮ phiên đọc thân. Trả mã HTTP; thân vào `resp`.
   KHÔNG đi theo 30x (mất trọn thân POST). */
static int net4gPost(const String& url, const String& body, String& resp) {
  resp = "";
  if (!g_4gReady) return 0;
  Serial2.print("AT+HTTPTERM\r\n"); delay(120); while (Serial2.available()) Serial2.read();
  Serial2.print("AT+HTTPINIT\r\n"); _atWait("OK", 6000);
  Serial2.print("AT+HTTPPARA=\"CID\",1\r\n"); _atWait("OK", 2000);
  Serial2.print("AT+HTTPPARA=\"SSLCFG\",0\r\n"); _atWait("OK", 1500);
  Serial2.print("AT+HTTPPARA=\"URL\",\""); Serial2.print(url); Serial2.print("\"\r\n"); _atWait("OK", 3000);
  Serial2.print("AT+HTTPPARA=\"CONTENT\",\"application/json\"\r\n"); _atWait("OK", 2000);
  Serial2.print("AT+HTTPDATA="); Serial2.print(body.length()); Serial2.print(",20000\r\n");
  if (_atWait("DOWNLOAD", 6000).indexOf("DOWNLOAD") < 0) { Serial2.print("AT+HTTPTERM\r\n"); return 0; }
  Serial2.print(body); _atWait("OK", 20000);
  Serial2.print("AT+HTTPACTION=1\r\n");
  String r = _atWait("+HTTPACTION:", 40000);
  int status = 0, dl = 0, p = r.indexOf("+HTTPACTION:");
  if (p >= 0) { int c1 = r.indexOf(',', p), c2 = (c1 >= 0) ? r.indexOf(',', c1 + 1) : -1;
                if (c1 >= 0 && c2 >= 0) { status = r.substring(c1 + 1, c2).toInt(); dl = r.substring(c2 + 1).toInt(); } }
  if (status == 0) _net4gFail();
  if (status == 200 && dl > 0) {
    int n = _net4gReadStart(dl);
    if (n > 0) { resp.reserve(n + 4); int got = 0; unsigned long t0 = millis();
      while (got < n && millis() - t0 < 12000) { while (Serial2.available() && got < n) { resp += (char)Serial2.read(); got++; t0 = millis(); } delay(1); } }
    _atWait("OK", 2000);
  }
  Serial2.print("AT+HTTPTERM\r\n"); _atWait("OK", 1500);
  return status;
}

#else   /* Chưa gán chân 4G → bản rỗng để .ino build/chạy chỉ với WiFi. */
static bool net4gReady() { return false; }
static bool net4gBatDau() { return false; }
static int  net4gPost(const String& url, const String& body, String& resp) { resp = ""; return 0; }
#endif
