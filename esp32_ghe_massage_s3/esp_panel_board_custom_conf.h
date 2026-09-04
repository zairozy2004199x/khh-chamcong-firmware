/* ============================================================================
 *  CẤU HÌNH BOARD — Waveshare ESP32-S3-Touch-LCD-2.8B (480×640, ST7701 RGB, GT911)
 *  Dùng với thư viện  ESP32_Display_Panel  >= 1.2.0  (esp-arduino-libs)
 * ----------------------------------------------------------------------------
 *  🔴 VÌ SAO CÓ FILE NÀY: thư viện KHÔNG có preset sẵn cho bản "2.8B" (chỉ có 2.8C
 *     480×480). Nên phải khai CUSTOM. File này chép cạnh file .ino (cùng thư mục
 *     sketch) — Arduino sẽ ưu tiên nó thay cho bản mặc định của thư viện.
 *
 *  🟡 ANH CHỈ CẦN ĐIỀN SỐ CHÂN (các chỗ ghi  ĐIỀN ). Lấy từ:
 *       Wiki Waveshare ESP32-S3-Touch-LCD-2.8B  ->  mục "Hardware / Pinout" hoặc
 *       "Schematic", hoặc file demo Arduino (thường là  Display_ST7701.* /
 *       pins_config.h). Chép đúng GPIO của TỪNG chân RGB vào đây.
 *
 *  ⚠️ NẾU biên dịch báo THIẾU macro nào: mở file gốc của thư viện
 *       Arduino/libraries/ESP32_Display_Panel/esp_panel_board_custom_conf.h
 *     chép NGUYÊN bản đó vào đây rồi chỉ sửa các giá trị theo bảng dưới — chắc ăn
 *     hơn là dùng bản rút gọn này (bản gốc có ~600 dòng đủ mọi macro mặc định).
 *
 *  ⚠️ Chuỗi init ST7701: ĐỂ TRỐNG -> thư viện dùng init MẶC ĐỊNH của ST7701 (đa số
 *     panel chạy được). Nếu lên hình mà SAI MÀU/ÂM BẢN/LỆCH thì mới cần dán init
 *     riêng của Waveshare vào ESP_PANEL_BOARD_LCD_VENDOR_INIT_CMD() ở cuối.
 * ========================================================================== */
#pragma once

/* Bật chế độ board tự khai (custom) thay cho preset. */
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

/* ST7701 cần 3-wire SPI để nạp lệnh init. Trên 2.8B ba chân này đi QUA TCA9554
   (bộ mở rộng), nên đánh dấu _USE_EXPANDER = 1 và số chân là chân CỦA EXPANDER
   (0..7), KHÔNG phải GPIO của ESP32. */
#define ESP_PANEL_BOARD_LCD_RGB_USE_CONTROL_PANEL   (1)
#define ESP_PANEL_BOARD_LCD_RGB_SPI_IO_CS           (/* ĐIỀN: chân expander CS  */ 0)
#define ESP_PANEL_BOARD_LCD_RGB_SPI_IO_SCK          (/* ĐIỀN: chân expander SCK */ 0)
#define ESP_PANEL_BOARD_LCD_RGB_SPI_IO_SDA          (/* ĐIỀN: chân expander SDA */ 0)
#define ESP_PANEL_BOARD_LCD_RGB_SPI_CS_USE_EXPNADER  (1)
#define ESP_PANEL_BOARD_LCD_RGB_SPI_SCL_USE_EXPNADER (1)
#define ESP_PANEL_BOARD_LCD_RGB_SPI_SDA_USE_EXPNADER (1)

/* Chân điều khiển RGB (GPIO thật của ESP32-S3). */
#define ESP_PANEL_BOARD_LCD_RGB_IO_HSYNC     (/* ĐIỀN */ -1)
#define ESP_PANEL_BOARD_LCD_RGB_IO_VSYNC     (/* ĐIỀN */ -1)
#define ESP_PANEL_BOARD_LCD_RGB_IO_DE        (/* ĐIỀN */ -1)
#define ESP_PANEL_BOARD_LCD_RGB_IO_PCLK      (/* ĐIỀN */ -1)
#define ESP_PANEL_BOARD_LCD_RGB_IO_DISP      (-1)   /* thường -1 (không dùng) */

/* 16 chân dữ liệu RGB565. Thứ tự B0..B4, G0..G5, R0..R4 tùy panel — chép ĐÚNG
   theo bảng của Waveshare (đừng đảo, đảo là sai màu hoặc trắng màn). */
#define ESP_PANEL_BOARD_LCD_RGB_IO_DATA0     (/* ĐIỀN B0 */ -1)
#define ESP_PANEL_BOARD_LCD_RGB_IO_DATA1     (/* ĐIỀN B1 */ -1)
#define ESP_PANEL_BOARD_LCD_RGB_IO_DATA2     (/* ĐIỀN B2 */ -1)
#define ESP_PANEL_BOARD_LCD_RGB_IO_DATA3     (/* ĐIỀN B3 */ -1)
#define ESP_PANEL_BOARD_LCD_RGB_IO_DATA4     (/* ĐIỀN B4 */ -1)
#define ESP_PANEL_BOARD_LCD_RGB_IO_DATA5     (/* ĐIỀN G0 */ -1)
#define ESP_PANEL_BOARD_LCD_RGB_IO_DATA6     (/* ĐIỀN G1 */ -1)
#define ESP_PANEL_BOARD_LCD_RGB_IO_DATA7     (/* ĐIỀN G2 */ -1)
#define ESP_PANEL_BOARD_LCD_RGB_IO_DATA8     (/* ĐIỀN G3 */ -1)
#define ESP_PANEL_BOARD_LCD_RGB_IO_DATA9     (/* ĐIỀN G4 */ -1)
#define ESP_PANEL_BOARD_LCD_RGB_IO_DATA10    (/* ĐIỀN G5 */ -1)
#define ESP_PANEL_BOARD_LCD_RGB_IO_DATA11    (/* ĐIỀN R0 */ -1)
#define ESP_PANEL_BOARD_LCD_RGB_IO_DATA12    (/* ĐIỀN R1 */ -1)
#define ESP_PANEL_BOARD_LCD_RGB_IO_DATA13    (/* ĐIỀN R2 */ -1)
#define ESP_PANEL_BOARD_LCD_RGB_IO_DATA14    (/* ĐIỀN R3 */ -1)
#define ESP_PANEL_BOARD_LCD_RGB_IO_DATA15    (/* ĐIỀN R4 */ -1)

/* Xung/porch RGB — GIÁ TRỊ MẶC ĐỊNH GẦN ĐÚNG cho ST7701 480×640. Nếu wiki có
   thông số khác thì sửa cho khớp; sai nhiều -> hình trôi/lệch/sọc. */
#define ESP_PANEL_BOARD_LCD_RGB_CLK_HZ       (16 * 1000 * 1000)
#define ESP_PANEL_BOARD_LCD_RGB_HPW          (8)
#define ESP_PANEL_BOARD_LCD_RGB_HBP          (10)
#define ESP_PANEL_BOARD_LCD_RGB_HFP          (10)
#define ESP_PANEL_BOARD_LCD_RGB_VPW          (2)
#define ESP_PANEL_BOARD_LCD_RGB_VBP          (8)
#define ESP_PANEL_BOARD_LCD_RGB_VFP          (8)
#define ESP_PANEL_BOARD_LCD_RGB_DATA_WIDTH   (16)
#define ESP_PANEL_BOARD_LCD_RGB_PIXEL_BITS   (16)   /* RGB565 */

/* Init ST7701: để trống dùng mặc định thư viện. Nếu sai màu mới điền của Waveshare.
   #define ESP_PANEL_BOARD_LCD_VENDOR_INIT_CMD() {  ...các dòng ST7701_INIT...  } */

/* ─────────────────────────────── CẢM ỨNG (GT911, I2C) ────────────────────── */
#define ESP_PANEL_BOARD_TOUCH_CONTROLLER     GT911
#define ESP_PANEL_BOARD_TOUCH_BUS_TYPE       (ESP_PANEL_BUS_TYPE_I2C)
/* I2C dùng chung với header: SDA=GPIO15, SCL=GPIO7 (đã ghi trên board). */
#define ESP_PANEL_BOARD_TOUCH_I2C_IO_SCL     (7)
#define ESP_PANEL_BOARD_TOUCH_I2C_IO_SDA     (15)
#define ESP_PANEL_BOARD_TOUCH_I2C_ADDRESS    (0x00)  /* 0x00 = tự dò (0x5D/0x14) */
#define ESP_PANEL_BOARD_TOUCH_INT_IO         (16)    /* TP_INT = GPIO16 (theo silk) */
#define ESP_PANEL_BOARD_TOUCH_RST_IO         (-1)    /* RST qua expander -> -1 ở đây */

/* ─────────────────────────────── ĐÈN NỀN + EXPANDER ──────────────────────── */
#define ESP_PANEL_BOARD_BACKLIGHT_TYPE       (ESP_PANEL_BACKLIGHT_TYPE_SWITCH_EXPANDER)
#define ESP_PANEL_BOARD_BACKLIGHT_IO         (/* ĐIỀN: chân expander đèn nền */ 0)
#define ESP_PANEL_BOARD_BACKLIGHT_ON_LEVEL   (1)

#define ESP_PANEL_BOARD_EXPANDER_CHIP        TCA95XX_8BIT
#define ESP_PANEL_BOARD_EXPANDER_I2C_ADDRESS (0x20)  /* TCA9554: 0x20 (A0..A2=GND); 0x38 nếu bản PWR */
#define ESP_PANEL_BOARD_EXPANDER_I2C_IO_SCL  (7)
#define ESP_PANEL_BOARD_EXPANDER_I2C_IO_SDA  (15)
