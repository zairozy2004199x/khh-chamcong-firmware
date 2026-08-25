/* Bản Arduino.h GIẢ, chỉ dùng cho bài test chạy trên máy tính (ci/kiem-ma-qr.sh).
   Đủ để biên dịch ma_qr.h bằng g++, KHÔNG dính gì tới firmware thật. */
#pragma once
#include <string>
#include <cstdint>
#include <cstring>
#include <cstdio>
#include <cstdlib>
#include <cctype>

class String {
public:
  std::string s;
  String() {}
  String(const char* p) : s(p ? p : "") {}
  String(const std::string& x) : s(x) {}
  String(char c) { s = std::string(1, c); }
  String(int v)          { char b[24]; snprintf(b, sizeof(b), "%d", v);   s = b; }
  String(unsigned v)     { char b[24]; snprintf(b, sizeof(b), "%u", v);   s = b; }
  String(long v)         { char b[24]; snprintf(b, sizeof(b), "%ld", v);  s = b; }
  String(unsigned long v){ char b[24]; snprintf(b, sizeof(b), "%lu", v);  s = b; }

  unsigned length() const { return (unsigned)s.size(); }
  char charAt(unsigned i) const { return i < s.size() ? s[i] : 0; }
  const char* c_str() const { return s.c_str(); }
  void reserve(size_t n) { s.reserve(n); }
  String substring(unsigned a) const { return a >= s.size() ? String() : String(s.substr(a)); }
  String substring(unsigned a, unsigned b) const {
    if (a >= s.size()) return String();
    if (b > s.size()) b = (unsigned)s.size();
    if (b <= a) return String();
    return String(s.substr(a, b - a));
  }
  int indexOf(char c) const { auto p = s.find(c); return p == std::string::npos ? -1 : (int)p; }
  int indexOf(const char* n) const { auto p = s.find(n); return p == std::string::npos ? -1 : (int)p; }
  int indexOf(const String& n) const { return indexOf(n.c_str()); }
  int indexOf(char c, unsigned tu) const { auto p = s.find(c, tu); return p == std::string::npos ? -1 : (int)p; }
  int indexOf(const char* n, unsigned tu) const { auto p = s.find(n, tu); return p == std::string::npos ? -1 : (int)p; }
  void trim() {
    size_t a = 0, b = s.size();
    while (a < b && (unsigned char)s[a] <= ' ') a++;
    while (b > a && (unsigned char)s[b - 1] <= ' ') b--;
    s = s.substr(a, b - a);
  }
  void toLowerCase() { for (auto& c : s) c = (char)tolower((unsigned char)c); }
  void toUpperCase() { for (auto& c : s) c = (char)toupper((unsigned char)c); }
  char operator[](unsigned i) const { return charAt(i); }
  long toInt() const { return strtol(s.c_str(), nullptr, 10); }
  double toDouble() const { return strtod(s.c_str(), nullptr); }
  bool startsWith(const char* p) const { return s.rfind(p, 0) == 0; }
  bool endsWith(const char* p) const { size_t n = strlen(p); return s.size() >= n && s.compare(s.size() - n, n, p) == 0; }

  String& operator+=(const String& o) { s += o.s; return *this; }
  String& operator+=(const char* o)   { s += o;   return *this; }
  String& operator+=(char c)          { s += c;   return *this; }
  bool operator==(const String& o) const { return s == o.s; }
  bool operator==(const char* o) const   { return s == std::string(o ? o : ""); }
  bool operator!=(const String& o) const { return !(*this == o); }
  bool operator!=(const char* o) const   { return !(*this == o); }
};
inline String operator+(const String& a, const String& b) { String r(a); r += b; return r; }
inline String operator+(const String& a, const char* b)   { String r(a); r += b; return r; }
inline String operator+(const char* a, const String& b)   { String r(a); r += b; return r; }
inline String operator+(const String& a, char b)          { String r(a); r += b; return r; }

/* ---------------------------------------------------------------------------
 *  Phần dưới đây chỉ để test ict_ghe.h: một cổng UART GIẢ và một đồng hồ GIẢ.
 *
 *  ĐỒNG HỒ GIẢ: millis() chỉ nhích khi gọi delay(). Nhờ vậy bài test chạy xong
 *  trong tích tắc thay vì ngồi đợi thật 600ms mỗi lần gửi lại, mà vẫn đi đúng
 *  từng nhánh chờ/gửi lại của driver.
 * ------------------------------------------------------------------------- */
#include <vector>
#include <deque>
#include <functional>

#define SERIAL_8N1 0x800001c
#define SERIAL_8E1 0x800001e   // 8 data + chan le CHAN + 1 stop (giao thuc L70 that)

inline uint32_t& _gioAo() { static uint32_t t = 1000; return t; }
inline uint32_t millis() { return _gioAo(); }
inline void delay(uint32_t ms) { _gioAo() += ms; }
inline void delayMicroseconds(uint32_t) {}
inline void yield() {}
inline void pinMode(int, int) {}
inline void digitalWrite(int, int) {}
inline int  digitalRead(int) { return 1; }
inline bool isHexadecimalDigit(char c) { return isxdigit((unsigned char)c) != 0; }
inline unsigned long pulseIn(int, int, unsigned long) { return 104; }   // ~9600 baud
inline uint32_t micros() { return _gioAo() * 1000; }
#define INPUT 0
#define OUTPUT 1
#define INPUT_PULLUP 2
#define HIGH 1
#define LOW 0

class HardwareSerial {
public:
  std::vector<uint8_t> daGui;     // TẤT CẢ byte driver đã đẩy ra dây
  std::deque<uint8_t>  seNhan;    // byte bo ghế "trả về"
  long baudDangDung = 0;
  int  soLanMoCong  = 0;
  // Bo ghế giả: mỗi lần driver flush(), hàm này được gọi với khung vừa gửi.
  std::function<void(const std::vector<uint8_t>&, HardwareSerial&)> boGhe;

  void begin(long baud, int = 0, int = -1, int = -1) { baudDangDung = baud; soLanMoCong++; }
  void end() {}
  void setTimeout(unsigned long) {}
  int  available() { return (int)seNhan.size(); }
  int  read() { if (seNhan.empty()) return -1; int v = seNhan.front(); seNhan.pop_front(); return v; }
  size_t write(uint8_t b) { daGui.push_back(b); _chua.push_back(b); return 1; }
  size_t write(const uint8_t* d, size_t n) { for (size_t i = 0; i < n; i++) write(d[i]); return n; }
  size_t print(const String& s) { for (unsigned i = 0; i < s.length(); i++) write((uint8_t)s.charAt(i)); return s.length(); }
  size_t print(const char* s) { return print(String(s)); }
  void flush() { if (boGhe && !_chua.empty()) boGhe(_chua, *this); _chua.clear(); }
  void traVe(const char* s) { for (const char* p = s; *p; p++) seNhan.push_back((uint8_t)*p); }
  void traVe(const uint8_t* d, size_t n) { for (size_t i = 0; i < n; i++) seNhan.push_back(d[i]); }
  void xoaSach() { daGui.clear(); seNhan.clear(); _chua.clear(); }
private:
  std::vector<uint8_t> _chua;
};

class _SerialGia {
public:
  void begin(long) {}
  template <typename... A> void printf(const char* f, A... a) { std::printf(f, a...); }
  void print(const String& s)   { std::fputs(s.c_str(), stdout); }
  void print(const char* s)     { std::fputs(s, stdout); }
  void println(const String& s) { std::printf("%s\n", s.c_str()); }
  void println(const char* s)   { std::printf("%s\n", s); }
  void println()                { std::printf("\n"); }
  size_t write(uint8_t b)       { std::fputc(b, stdout); return 1; }
  int  available() { return 0; }
  int  read() { return -1; }
};
inline _SerialGia& _serialGia() { static _SerialGia s; return s; }
#define Serial _serialGia()
