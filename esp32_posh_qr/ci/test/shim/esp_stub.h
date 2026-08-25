/* Bộ thư viện ESP32 GIẢ — chỉ để BIÊN DỊCH KIỂM sketch trên máy tính (ci/kiem-bien-dich.sh).
   Không chạy được gì, mục đích duy nhất là bắt lỗi cú pháp/kiểu ngay tại chỗ, vì CI của repo
   chỉ biên dịch khi push vào nhánh main. KHÔNG dính dáng gì tới firmware thật. */
#pragma once
#include <Arduino.h>
#include <ctime>
#include <sys/time.h>
#include <functional>
#include <map>

/* --- esp_mac.h --- */
#define ESP_OK 0
#define ESP_MAC_WIFI_STA 0
inline int esp_read_mac(uint8_t* m, int) { for (int i = 0; i < 6; i++) m[i] = (uint8_t)i; return ESP_OK; }

/* --- WiFi --- */
#define WIFI_AP_STA 3
#define WL_CONNECTED 3
class IPAddress {
public:
  IPAddress() {}
  IPAddress(int, int, int, int) {}
  String toString() const { return String("192.168.4.1"); }
};
class _WiFiGia {
public:
  void mode(int) {}
  bool softAP(const char*, const char* = nullptr) { return true; }
  void begin(const char*, const char*) {}
  int  status() { return 0; }
  IPAddress localIP() { return IPAddress(); }
  String macAddress() { return String("00:00:00:00:00:00"); }
};
inline _WiFiGia& _wifiGia() { static _WiFiGia w; return w; }
#define WiFi _wifiGia()
inline void configTime(long, int, const char*, const char* = nullptr) {}

/* --- WebServer --- */
enum HTTPMethod { HTTP_ANY, HTTP_GET, HTTP_POST };
enum HTTPUploadStatus { UPLOAD_FILE_START, UPLOAD_FILE_WRITE, UPLOAD_FILE_END, UPLOAD_FILE_ABORTED };
struct HTTPUpload {
  HTTPUploadStatus status = UPLOAD_FILE_START;
  String   filename;
  uint8_t  buf[8] = {0};
  size_t   currentSize = 0, totalSize = 0;
};
class WebServer {
public:
  WebServer(int) {}
  void on(const char*, std::function<void()>) {}
  void on(const char*, HTTPMethod, std::function<void()>) {}
  void on(const char*, HTTPMethod, std::function<void()>, std::function<void()>) {}
  void onNotFound(std::function<void()>) {}
  void begin() {}
  void handleClient() {}
  void send(int, const char*, const String&) {}
  void sendHeader(const char*, const String&) {}
  bool hasArg(const char*) { return false; }
  String arg(const char*) { return String(); }
  bool authenticate(const char*, const char*) { return true; }
  void requestAuthentication() {}
  HTTPUpload& upload() { static HTTPUpload u; return u; }
};

/* --- DNSServer --- */
class DNSServer {
public:
  bool start(uint16_t, const char*, const IPAddress&) { return true; }
  void processNextRequest() {}
};

/* --- Update --- */
#define UPDATE_SIZE_UNKNOWN 0xFFFFFFFF
class _UpdateGia {
public:
  bool begin(size_t) { return true; }
  size_t write(uint8_t*, size_t n) { return n; }
  bool end(bool) { return true; }
  bool hasError() { return false; }
  void printError(_SerialGia&) {}
};
inline _UpdateGia& _updateGia() { static _UpdateGia u; return u; }
#define Update _updateGia()

/* --- Preferences --- */
class Preferences {
public:
  bool begin(const char*, bool) { return true; }
  String   getString(const char* k, const String& d) { auto i = _s.find(k); return i == _s.end() ? d : i->second; }
  size_t   putString(const char* k, const String& v) { _s[k] = v; return v.length(); }
  uint16_t getUShort(const char*, uint16_t d = 0) { return d; }
  size_t   putUShort(const char*, uint16_t) { return 2; }
  uint8_t  getUChar(const char*, uint8_t d = 0) { return d; }
  size_t   putUChar(const char*, uint8_t) { return 1; }
  long     getLong(const char*, long d = 0) { return d; }
  size_t   putLong(const char*, long) { return 4; }
  bool     getBool(const char*, bool d = false) { return d; }
  size_t   putBool(const char*, bool) { return 1; }
  uint32_t getULong(const char*, uint32_t d = 0) { return d; }
  size_t   putULong(const char*, uint32_t) { return 4; }
  size_t   getBytes(const char*, void*, size_t n) { return n; }
  size_t   putBytes(const char*, const void*, size_t n) { return n; }
private:
  std::map<std::string, String> _s;
};

/* --- ESP --- */
class _EspGia { public: void restart() {} };
inline _EspGia& _espGia() { static _EspGia e; return e; }
#define ESP _espGia()

/* --- SD + SPI (chi de bien dich kiem tho nap dsPIC) --- */
#define FILE_READ  0
#define FILE_WRITE 1
class File {
public:
  operator bool() const { return _co; }
  bool isDirectory() { return false; }
  const char* name() { return "/mau.hex"; }
  size_t size() { return 0; }
  int  available() { return 0; }
  int  read() { return -1; }
  void close() {}
  File openNextFile() { return File(false); }
  File() {}
  explicit File(bool co) : _co(co) {}
private:
  bool _co = false;
};
class _SdGia {
public:
  bool begin(int, class _SpiGia&, long = 4000000) { return true; }
  bool begin(int) { return true; }
  File open(const char*, int = FILE_READ) { return File(false); }
};
class _SpiGia { public: void begin(int, int, int, int) {} };
inline _SpiGia& _spiGia() { static _SpiGia s; return s; }
inline _SdGia&  _sdGia()  { static _SdGia s; return s; }
#define SPI _spiGia()
#define SD  _sdGia()

extern HardwareSerial Serial1;
extern HardwareSerial Serial2;
