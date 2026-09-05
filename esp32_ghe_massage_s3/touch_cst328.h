/* ============================================================================
 *  CẢM ỨNG — tự nhận diện GT911 (Goodix) hoặc CST328 (Hynitron)
 * ----------------------------------------------------------------------------
 *  Board Waveshare 2.8B thực tế quét I2C KHÔNG có 0x1A (CST328) mà có 0x14 —
 *  đó là GT911. Nên driver này PROBE cả hai:
 *    - GT911: đọc mã sản phẩm ở 0x8140 (4 byte) = "911\0". Địa chỉ 0x14 hoặc 0x5D
 *      (tuỳ mức chân INT lúc reset). Toạ độ ở 0x8150, cờ ở 0x814E (bit7=sẵn sàng,
 *      4 bit thấp=số điểm), đọc xong ghi 0 vào 0x814E để xoá cờ.
 *    - CST328: mã ở 0xD000..., số điểm 0xD005 (giữ lại phòng bản khác).
 *
 *  Thanh ghi 16-bit big-endian, tự đọc qua Wire (I2C_Read của bo chỉ 8-bit).
 *  Kết quả nhận diện đưa lên TP_STATUS để hiện thẳng màn hình (không cần Serial).
 * ========================================================================== */
#pragma once
#include <Arduino.h>
#include <Wire.h>
#include <string.h>

enum TouchKind { TK_NONE = 0, TK_GT911, TK_CST328 };
static TouchKind TP_KIND = TK_NONE;
static uint8_t   TP_I2C  = 0;      // địa chỉ chip cảm ứng thực tế
static char      TP_STATUS[64] = "TP: chua init";
static bool      TP_OK = false;

// Chiều toạ độ (chỉnh nếu chạm lệch — mặc định thẳng, khớp 480×640 dọc)
#ifndef TOUCH_SWAP_XY
  #define TOUCH_SWAP_XY   0
#endif
#ifndef TOUCH_MIRROR_X
  #define TOUCH_MIRROR_X  0
#endif
#ifndef TOUCH_MIRROR_Y
  #define TOUCH_MIRROR_Y  0
#endif
#define TOUCH_W  480
#define TOUCH_H  640

// ── I2C thanh ghi 16-bit ────────────────────────────────────────────────────
static bool _r16(uint8_t addr, uint16_t reg, uint8_t* data, size_t len){
  Wire.beginTransmission(addr);
  Wire.write((uint8_t)(reg >> 8));
  Wire.write((uint8_t)(reg & 0xFF));
  if(Wire.endTransmission(true) != 0) return false;
  size_t got = Wire.requestFrom((uint8_t)addr, (uint8_t)len);
  if(got != len) return false;
  for(size_t i = 0; i < len; i++) data[i] = Wire.read();
  return true;
}
static bool _w16(uint8_t addr, uint16_t reg, uint8_t val){
  Wire.beginTransmission(addr);
  Wire.write((uint8_t)(reg >> 8));
  Wire.write((uint8_t)(reg & 0xFF));
  Wire.write(val);
  return Wire.endTransmission(true) == 0;
}

// ── Quét + nhận diện ────────────────────────────────────────────────────────
static void TP_Scan(char* out, size_t cap){
  int n = snprintf(out, cap, "scan:");
  Serial.print("[TP] I2C scan:");
  for(uint8_t a = 1; a < 127; a++){
    Wire.beginTransmission(a);
    if(Wire.endTransmission() == 0){
      Serial.print(" 0x"); Serial.print(a, HEX);
      if(n < (int)cap - 4) n += snprintf(out + n, cap - n, " %02X", a);
    }
  }
  Serial.println();
}

// Thử GT911 tại địa chỉ addr: đọc mã sản phẩm 0x8140 = '9','1','1'.
static bool _probeGT911(uint8_t addr){
  uint8_t id[4] = {0};
  if(!_r16(addr, 0x8140, id, 4)) return false;
  return (id[0] == '9' && id[1] == '1' && id[2] == '1');
}
// Thử CST328 tại addr: đọc số điểm 0xD005 phải ACK.
static bool _probeCST328(uint8_t addr){
  uint8_t b = 0;
  return _r16(addr, 0xD005, &b, 1);
}

static void TP_Init(){
  char scan[48]; TP_Scan(scan, sizeof scan);

  // Ưu tiên GT911 (địa chỉ 0x14 hay 0x5D)
  if(_probeGT911(0x14))      { TP_KIND = TK_GT911;  TP_I2C = 0x14; }
  else if(_probeGT911(0x5D)) { TP_KIND = TK_GT911;  TP_I2C = 0x5D; }
  else if(_probeCST328(0x1A)){ TP_KIND = TK_CST328; TP_I2C = 0x1A; }
  else if(_probeCST328(0x14)){ TP_KIND = TK_CST328; TP_I2C = 0x14; }

  TP_OK = (TP_KIND != TK_NONE);
  const char* ten = (TP_KIND==TK_GT911)?"GT911":(TP_KIND==TK_CST328)?"CST328":"?";
  if(TP_OK) snprintf(TP_STATUS, sizeof TP_STATUS, "%s @0x%02X OK", ten, TP_I2C);
  else      snprintf(TP_STATUS, sizeof TP_STATUS, "TOUCH ? %s", scan);
  Serial.printf("[TP] nhan dien: %s\n", TP_STATUS);
}

// ── Đọc 1 điểm chạm ─────────────────────────────────────────────────────────
static bool _readGT911(int* px, int* py){
  uint8_t st = 0;
  if(!_r16(TP_I2C, 0x814E, &st, 1)) return false;
  if(!(st & 0x80)){ return false; }                  // buffer chưa sẵn sàng
  uint8_t np = st & 0x0F;
  bool has = false; int x = 0, y = 0;
  if(np >= 1 && np <= 5){
    uint8_t p[8] = {0};
    if(_r16(TP_I2C, 0x8150, p, 8)){
      x = p[1] | (p[2] << 8);                        // x low|high
      y = p[3] | (p[4] << 8);                        // y low|high
      has = true;
      // In byte THÔ ngay tại đây (sạch, không bị dump giành) để chẩn đoán/map.
      Serial.printf("[GT] RAW p= %02X %02X %02X %02X %02X -> x=%d y=%d\n",
                    p[0], p[1], p[2], p[3], p[4], x, y);
    }
  }
  _w16(TP_I2C, 0x814E, 0);                            // xoá cờ buffer
  if(!has) return false;
  *px = x; *py = y; return true;
}
static bool _readCST328(int* px, int* py){
  uint8_t n = 0;
  if(!_r16(TP_I2C, 0xD005, &n, 1)) return false;
  n &= 0x0F;
  if(n == 0 || n > 5){ _w16(TP_I2C, 0xD005, 0); return false; }
  uint8_t b[5];
  if(!_r16(TP_I2C, 0xD000, b, 5)){ _w16(TP_I2C, 0xD005, 0); return false; }
  _w16(TP_I2C, 0xD005, 0);
  *px = ((int)b[1] << 4) | (b[3] >> 4);
  *py = ((int)b[2] << 4) | (b[3] & 0x0F);
  return true;
}

// Trả true nếu đang chạm; (x,y) theo hệ màn 480×640.
static bool TP_Read(int* px, int* py){
  int x, y;
  bool ok = (TP_KIND == TK_GT911)  ? _readGT911(&x, &y)
          : (TP_KIND == TK_CST328) ? _readCST328(&x, &y)
          : false;
  if(!ok) return false;
#if TOUCH_SWAP_XY
  { int t = x; x = y; y = t; }
#endif
#if TOUCH_MIRROR_X
  x = (TOUCH_W - 1) - x;
#endif
#if TOUCH_MIRROR_Y
  y = (TOUCH_H - 1) - y;
#endif
  if(x < 0) x = 0; if(x >= TOUCH_W) x = TOUCH_W - 1;
  if(y < 0) y = 0; if(y >= TOUCH_H) y = TOUCH_H - 1;
  *px = x; *py = y;
  return true;
}
