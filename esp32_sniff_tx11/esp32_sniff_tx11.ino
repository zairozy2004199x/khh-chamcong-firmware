/* ============================================================================
 *  esp32_sniff_tx11 — SNIFF cổng UART "TX11" của bo ghế STM32 để tìm tín hiệu
 *  CHẠY/DỪNG (vd bus tiền 4800 8E1: chân ghế phát 3E=rảnh / 5E=đang chạy khóa).
 * ----------------------------------------------------------------------------
 *  ĐẤU:  TX11 (chân STM32 phát) -> IO34 ,  GND ghế -> GND ESP (chung mass).
 *        IO34 chỉ đọc, không phá tín hiệu ghế. Tín hiệu phải là mức 0/3.3V
 *        (nếu ghế 5V thì qua chia mức/ADuM). CHỈ đọc, an toàn.
 *
 *  CHẠY:  Nạp sketch này. Mở Serial Monitor 115200. Sketch tự thử lần lượt các
 *         baud phổ biến, mỗi baud ~4 giây, IN RA byte HEX nhận được.
 *         Vừa xem vừa: cho ghế CHẠY rồi DỪNG, coi baud nào ra byte gọn (không '?')
 *         và byte có ĐỔI theo chạy/dừng không. Báo lại byte thấy được.
 *
 *  Đọc log:
 *    "[4800 8E1] G81 G41 ..."  = byte sạch (chữ G = một byte, số = hex).
 *    "?"                        = lỗi khung -> baud/parity SAI, bỏ qua baud đó.
 *    "(im)"                     = không có byte nào -> ghế không phát ở baud này,
 *                                 hoặc dây chưa tới, hoặc sai baud.
 * ========================================================================== */
#include <Arduino.h>

#define RX_PIN   34            // TX11 của ghế -> đây

// Danh sách (baud, cấu hình) sẽ thử lần lượt. 8E1 = bus tiền L70 đã biết.
struct Thu { long baud; uint32_t cfg; const char* ten; };
Thu DS[] = {
  { 4800,  SERIAL_8E1, "4800 8E1"  },   // bus tiền L70 (khả năng cao nhất)
  { 9600,  SERIAL_8N1, "9600 8N1"  },
  { 9600,  SERIAL_8E1, "9600 8E1"  },
  { 19200, SERIAL_8N1, "19200 8N1" },
  { 38400, SERIAL_8N1, "38400 8N1" },
  { 115200,SERIAL_8N1, "115200 8N1"},
  { 4800,  SERIAL_8N1, "4800 8N1"  },
};
const int SO_THU = sizeof(DS)/sizeof(DS[0]);

void thuMotBaud(const Thu& t){
  Serial.printf("\n===== THU %s (RX=IO%d) — cho ghe CHAY/DUNG de so =====\n", t.ten, RX_PIN);
  Serial1.begin(t.baud, t.cfg, RX_PIN, -1);
  delay(50);
  while(Serial1.available()) Serial1.read();     // xả rác đầu
  uint32_t het = millis() + 4000;                // nghe 4 giây
  uint32_t soByte = 0, soLoi = 0, lanCuoi = 0;
  while((int32_t)(het - millis()) > 0){
    while(Serial1.available()){
      int b = Serial1.read();
      if(b < 0) continue;
      uint32_t now = millis();
      if(lanCuoi && now - lanCuoi > 8) Serial.print(" | ");   // khoảng lặng = khung mới
      lanCuoi = now;
      // ESP không báo parity error qua read() thường; in hex thô để mình soi.
      Serial.printf("%02X ", (uint8_t)b);
      soByte++;
    }
    delay(2);
  }
  Serial1.end();
  Serial.printf("\n  -> %s: nhan %lu byte%s\n", t.ten, (unsigned long)soByte,
    soByte==0 ? "  (IM — sai baud / chua toi day / ghe khong phat)" : "");
  (void)soLoi;
}

void setup(){
  Serial.begin(115200);
  delay(300);
  pinMode(RX_PIN, INPUT);
  Serial.println("\n\n=== SNIFF TX11 cua bo ghe STM32 ===");
  Serial.println("Dau: TX11 -> IO34, GND chung. Cho ghe CHAY roi DUNG trong luc thu.");
}

void loop(){
  for(int i=0;i<SO_THU;i++){
    thuMotBaud(DS[i]);
    delay(300);
  }
  Serial.println("\n----- het 1 vong, lap lai. Ghi lai baud nao ra byte gon + doi theo chay/dung -----\n");
  delay(500);
}
