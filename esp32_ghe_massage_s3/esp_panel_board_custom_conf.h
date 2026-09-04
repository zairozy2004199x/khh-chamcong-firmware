/* ============================================================================
 *  CẤU HÌNH BOARD — Waveshare ESP32-S3-Touch-LCD-2.8B (480×640, ST7701 RGB, GT911)
 *  Dùng với thư viện  ESP32_Display_Panel  >= 1.2.0  (esp-arduino-libs)
 * ----------------------------------------------------------------------------
 *  ✅ CHÂN ĐÃ ĐIỀN theo SCHEMATIC CHÍNH THỨC của Waveshare (bảng phân bổ GPIO).
 *     Chép file này cạnh file .ino (cùng thư mục sketch).
 *
 *  🟡 DUY NHẤT còn cần xác nhận: thứ tự bit DATA XANH DƯƠNG/ĐỎ (đọc từ ảnh chụp
 *     schematic hơi mờ). Nếu bring-up lên hình nhưng SAI MÀU (đỏ↔xanh dương, hoặc
 *     màu loang), sửa theo ghi chú ở khối DATA bên dưới — KHÔNG gây màn đen.
 *
 *  ⚠️ Nếu biên dịch báo THIẾU macro: mở bản gốc của thư viện
 *     Arduino/libraries/ESP32_Display_Panel/esp_panel_board_custom_conf.h, chép
 *     nguyên vào đây rồi chỉ sửa các giá trị theo file này (bản gốc đủ mọi macro).
 * ========================================================================== */
#pragma once

#define ESP_PANEL_BOARD_DEFAULT_USE_CUSTOM   (1)
#define ESP_PANEL_BOARD_USE_LCD              (1)
#define ESP_PANEL_BOARD_USE_TOUCH            (1)
#define ESP_PANEL_BOARD_USE_BACKLIGHT        (1)
#define ESP_PANEL_BOARD_USE_EXPANDER         (1)

/* ─────────────────────────────── MÀN LCD (ST7701, RGB) ───────────────────── */
#define ESP_PANEL_BOARD_WIDTH                (480)
#define ESP_PANEL_BOARD_HEIGHT               (640)

#define ESP_PANEL_BOARD_LCD_CONTROLLER       ST7701
#define ESP_PANEL_BOARD_LCD_BUS_TYPE         (ESP_PANEL_BUS_TYPE_RGB)

/* ST7701 nạp init qua 3-wire SPI: SDA=GPIO1, SCK=GPIO2 (GPIO THẬT), CS=EXIO3 (qua
   TCA9554). Nên CS đánh dấu dùng expander, còn SCL/SDA là GPIO thật. */
#define ESP_PANEL_BOARD_LCD_RGB_USE_CONTROL_PANEL   (1)
#define ESP_PANEL_BOARD_LCD_RGB_SPI_IO_CS           (3)     /* EXIO3 (expander) = LCD_CS */
#define ESP_PANEL_BOARD_LCD_RGB_SPI_IO_SCK          (2)     /* GPIO2 = LCD_SCK  */
#define ESP_PANEL_BOARD_LCD_RGB_SPI_IO_SDA          (1)     /* GPIO1 = LCD_SDA  */
#define ESP_PANEL_BOARD_LCD_RGB_SPI_CS_USE_EXPNADER  (1)    /* CS nằm trên TCA9554 */
#define ESP_PANEL_BOARD_LCD_RGB_SPI_SCL_USE_EXPNADER (0)
#define ESP_PANEL_BOARD_LCD_RGB_SPI_SDA_USE_EXPNADER (0)

/* Chân điều khiển RGB (GPIO thật). */
#define ESP_PANEL_BOARD_LCD_RGB_IO_HSYNC     (38)
#define ESP_PANEL_BOARD_LCD_RGB_IO_VSYNC     (39)
#define ESP_PANEL_BOARD_LCD_RGB_IO_DE        (40)
#define ESP_PANEL_BOARD_LCD_RGB_IO_PCLK      (41)
#define ESP_PANEL_BOARD_LCD_RGB_IO_DISP      (-1)

/* 16 chân dữ liệu RGB565 = 5 Blue + 6 Green + 5 Red.
   ✅ GREEN chắc chắn: DG2..DG7 = GPIO 14,13,12,11,10,9  (thấp->cao).
   🟡 BLUE/RED: đọc từ ảnh schematic, thứ tự nhóm CÓ THỂ đảo. Nếu test ra
      đỏ↔xanh dương -> ĐỔI CHÉO cả cụm DATA0..4 với DATA11..15. Nếu 1 màu bị
      loang/sai sắc -> đảo thứ tự trong cụm đó. */
/* Blue B0..B4 */
#define ESP_PANEL_BOARD_LCD_RGB_IO_DATA0     (5)     /* DB (blue) */
#define ESP_PANEL_BOARD_LCD_RGB_IO_DATA1     (3)
#define ESP_PANEL_BOARD_LCD_RGB_IO_DATA2     (8)
#define ESP_PANEL_BOARD_LCD_RGB_IO_DATA3     (18)
#define ESP_PANEL_BOARD_LCD_RGB_IO_DATA4     (17)
/* Green G0..G5 (chắc chắn) */
#define ESP_PANEL_BOARD_LCD_RGB_IO_DATA5     (14)    /* DG2 */
#define ESP_PANEL_BOARD_LCD_RGB_IO_DATA6     (13)    /* DG3 */
#define ESP_PANEL_BOARD_LCD_RGB_IO_DATA7     (12)    /* DG4 */
#define ESP_PANEL_BOARD_LCD_RGB_IO_DATA8     (11)    /* DG5 */
#define ESP_PANEL_BOARD_LCD_RGB_IO_DATA9     (10)    /* DG6 */
#define ESP_PANEL_BOARD_LCD_RGB_IO_DATA10    (9)     /* DG7 */
/* Red R0..R4 */
#define ESP_PANEL_BOARD_LCD_RGB_IO_DATA11    (21)    /* DR (red) */
#define ESP_PANEL_BOARD_LCD_RGB_IO_DATA12    (45)
#define ESP_PANEL_BOARD_LCD_RGB_IO_DATA13    (46)
#define ESP_PANEL_BOARD_LCD_RGB_IO_DATA14    (47)
#define ESP_PANEL_BOARD_LCD_RGB_IO_DATA15    (48)

/* Xung/porch RGB — GIÁ TRỊ MẶC ĐỊNH GẦN ĐÚNG cho ST7701 480×640. Hình trôi/sọc
   thì tinh chỉnh (Waveshare demo hay dùng PCLK ~14-16MHz). */
#define ESP_PANEL_BOARD_LCD_RGB_CLK_HZ       (14 * 1000 * 1000)
#define ESP_PANEL_BOARD_LCD_RGB_HPW          (8)
#define ESP_PANEL_BOARD_LCD_RGB_HBP          (10)
#define ESP_PANEL_BOARD_LCD_RGB_HFP          (20)
#define ESP_PANEL_BOARD_LCD_RGB_VPW          (2)
#define ESP_PANEL_BOARD_LCD_RGB_VBP          (12)
#define ESP_PANEL_BOARD_LCD_RGB_VFP          (16)
#define ESP_PANEL_BOARD_LCD_RGB_DATA_WIDTH   (16)
#define ESP_PANEL_BOARD_LCD_RGB_PIXEL_BITS   (16)   /* RGB565 */

/* Init ST7701: để trống -> dùng init mặc định của thư viện. Sai màu tone/âm bản
   mới cần dán init riêng của Waveshare vào ESP_PANEL_BOARD_LCD_VENDOR_INIT_CMD(). */

/* ─────────────────────────────── CẢM ỨNG (GT911, I2C) ────────────────────── */
#define ESP_PANEL_BOARD_TOUCH_CONTROLLER     GT911
#define ESP_PANEL_BOARD_TOUCH_BUS_TYPE       (ESP_PANEL_BUS_TYPE_I2C)
#define ESP_PANEL_BOARD_TOUCH_I2C_IO_SCL     (7)     /* TP_SCL = GPIO7  */
#define ESP_PANEL_BOARD_TOUCH_I2C_IO_SDA     (15)    /* TP_SDA = GPIO15 */
#define ESP_PANEL_BOARD_TOUCH_I2C_ADDRESS    (0x00)  /* 0x00 = tự dò (0x5D/0x14) */
#define ESP_PANEL_BOARD_TOUCH_INT_IO         (16)    /* TP_INT = GPIO16 */
#define ESP_PANEL_BOARD_TOUCH_RST_IO         (-1)    /* TP_RST = EXIO2 (expander lo, xem ghi chú) */

/* ─────────────────────────────── ĐÈN NỀN + EXPANDER ──────────────────────── */
#define ESP_PANEL_BOARD_BACKLIGHT_TYPE       (ESP_PANEL_BACKLIGHT_TYPE_PWM_LEDC)
#define ESP_PANEL_BOARD_BACKLIGHT_IO         (6)     /* BL_PWM = GPIO6 */
#define ESP_PANEL_BOARD_BACKLIGHT_ON_LEVEL   (1)

#define ESP_PANEL_BOARD_EXPANDER_CHIP        TCA95XX_8BIT
#define ESP_PANEL_BOARD_EXPANDER_I2C_ADDRESS (0x20)  /* TCA9554; nếu init lỗi thử 0x24 hoặc 0x38 */
#define ESP_PANEL_BOARD_EXPANDER_I2C_IO_SCL  (7)
#define ESP_PANEL_BOARD_EXPANDER_I2C_IO_SDA  (15)

/* 🔴 RESET LCD/TOUCH QUA EXPANDER (TCA9554):
 *     EXIO1 = LCD_RST, EXIO2 = TP_RST, EXIO3 = LCD_CS, EXIO4 = SD_CS, EXIO8 = Còi.
 *  ESP32_Display_Panel sẽ tự nhả reset qua expander khi đã khai EXPANDER + CS trên
 *  expander. Nếu màn/không cảm ứng KHÔNG lên do reset chưa nhả: trong .ino gọi
 *  expander đặt EXIO1=EXIO2=HIGH trước board->begin() (xem ghi chú trong .ino). */
