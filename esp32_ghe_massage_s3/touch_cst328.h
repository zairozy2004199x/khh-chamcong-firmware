/* ============================================================================
 *  CST328 — cảm ứng điện dung (Waveshare ESP32-S3-Touch-LCD-2.8B)
 * ----------------------------------------------------------------------------
 *  Chip Hynitron CST328, địa chỉ I2C 0x1A, thanh ghi 16-bit (big-endian) — nên
 *  KHÔNG dùng I2C_Read/I2C_Write của bo (chỉ 8-bit) mà tự đọc qua Wire.
 *  Bản demo Waveshare gửi kèm là touch GIẢ LẬP, nên driver này viết theo tài
 *  liệu CST328 (số điểm ở 0xD005, toạ độ ở 0xD000, xoá bằng ghi 0 vào 0xD005).
 *
 *  Chỉ dùng điểm chạm đầu tiên (đủ cho chọn gói). Poll trong loop, không cần INT.
 *  In toạ độ thô ra Serial để hiệu chỉnh chiều nếu cần.
 * ========================================================================== */
#pragma once
#include <Arduino.h>
#include <Wire.h>

#define CST328_ADDR              0x1A
#define CST328_REG_XY            0xD000   // toạ độ (đọc 5 byte cho điểm 0)
#define CST328_REG_NUM           0xD005   // số điểm chạm (thấp 4 bit)

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
#ifndef TOUCH_W
  #define TOUCH_W  480
#endif
#ifndef TOUCH_H
  #define TOUCH_H  640
#endif

static bool _tpRead(uint16_t reg, uint8_t* data, size_t len){
  Wire.beginTransmission(CST328_ADDR);
  Wire.write((uint8_t)(reg >> 8));
  Wire.write((uint8_t)(reg & 0xFF));
  if(Wire.endTransmission(true) != 0) return false;
  size_t got = Wire.requestFrom((uint8_t)CST328_ADDR, (uint8_t)len);
  if(got != len) return false;
  for(size_t i = 0; i < len; i++) data[i] = Wire.read();
  return true;
}
static bool _tpWrite(uint16_t reg, uint8_t val){
  Wire.beginTransmission(CST328_ADDR);
  Wire.write((uint8_t)(reg >> 8));
  Wire.write((uint8_t)(reg & 0xFF));
  Wire.write(val);
  return Wire.endTransmission(true) == 0;
}

// Quét I2C in ra Serial (chẩn đoán: phải thấy 0x1A cảm ứng, 0x20 TCA9554).
static void TP_Scan(){
  Serial.print("[TP] I2C scan:");
  for(uint8_t a = 1; a < 127; a++){
    Wire.beginTransmission(a);
    if(Wire.endTransmission() == 0){ Serial.print(" 0x"); Serial.print(a, HEX); }
  }
  Serial.println();
}

static void TP_Init(){
  TP_Scan();
  // CST328 tự reset khi cấp nguồn -> vào chế độ báo bình thường, chỉ cần poll.
  uint8_t b[1];
  if(_tpRead(CST328_REG_NUM, b, 1)) Serial.println("[TP] CST328 tra loi OK");
  else Serial.println("[TP] CST328 KHONG tra loi (kiem tra I2C/nguon)");
}

// Đọc 1 điểm chạm. Trả true nếu đang chạm; (x,y) theo hệ màn 480×640.
static bool TP_Read(int* px, int* py){
  uint8_t n = 0;
  if(!_tpRead(CST328_REG_NUM, &n, 1)) return false;
  n &= 0x0F;
  if(n == 0 || n > 5){ _tpWrite(CST328_REG_NUM, 0); return false; }
  uint8_t b[5];
  if(!_tpRead(CST328_REG_XY, b, 5)){ _tpWrite(CST328_REG_NUM, 0); return false; }
  _tpWrite(CST328_REG_NUM, 0);              // xoá cờ để lần sau đọc mới
  int x = ((int)b[1] << 4) | (b[3] >> 4);   // điểm 0: X[11:4]|X[3:0]
  int y = ((int)b[2] << 4) | (b[3] & 0x0F); // Y[11:4]|Y[3:0]
#if TOUCH_SWAP_XY
  int t = x; x = y; y = t;
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
