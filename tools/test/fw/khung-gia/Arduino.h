/* Khung GIẢ của Arduino/ESP32 — CHỈ để g++ dịch thử bản .ino trên máy, bắt lỗi kiểu và lỗi cú
   pháp trước khi anh Thắng ngồi nạp. Không mô phỏng hành vi, không thay được máy thật.
   Sinh ra sau lần lỗi "invalid conversion from 'int' to 'uart_port_t'" — thứ mà mọi phép thử
   hiện có đều không chạm tới, vì chúng chỉ trích riêng từng hàm chứ không dịch cả tệp. */
#pragma once
#include <cstdint>
#include <cstdio>
#include <cstdlib>
#include <cstring>
#include <string>

#define F(x) (x)
#define PROGMEM

typedef std::string ArduinoStringBase;

class String {
public:
  std::string s;
  String() {}
  String(const char* p) : s(p ? p : "") {}
  String(const std::string& p) : s(p) {}
  const char* c_str() const { return s.c_str(); }
  size_t length() const { return s.size(); }
  char charAt(size_t i) const { return i < s.size() ? s[i] : 0; }
  String substring(size_t a) const { return a <= s.size() ? String(s.substr(a)) : String(); }
  String substring(size_t a, size_t b) const { return String(s.substr(a, b - a)); }
  long toInt() const { return strtol(s.c_str(), nullptr, 10); }
  void trim() {}
  bool operator==(const char* o) const { return s == o; }
};

static inline unsigned long millis() { return 0; }
static inline unsigned long micros() { return 0; }
static inline void delay(unsigned long) {}

#define SERIAL_8N1 0x800001c
#define SERIAL_8E1 0x800001e
#define SERIAL_8O1 0x800001f

class SerialGia {
public:
  void begin(unsigned long) {}
  void begin(unsigned long, uint32_t, int8_t = -1, int8_t = -1, bool = false) {}
  void end() {}
  int  available() { return 0; }
  int  read() { return -1; }
  size_t write(uint8_t) { return 1; }
  void print(const char*) {}
  void println(const char*) {}
  void println() {}
  template <typename... A> void printf(const char* f, A... a) {
    /* Ép trình dịch soát tham số printf y như thật — sai kiểu là báo ngay tại đây. */
    if (false) std::printf(f, a...);
  }
  String readStringUntil(char) { return String(); }
};

extern SerialGia Serial;
class HardwareSerial : public SerialGia { public: HardwareSerial(int) {} };
