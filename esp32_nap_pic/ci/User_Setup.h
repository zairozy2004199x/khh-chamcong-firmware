// ============================================================================
//  TFT_eSPI — cấu hình màn CYD ESP32-2432S028 cho CON THỢ NẠP dsPIC (giao diện màn).
//  ----------------------------------------------------------------------------
//  CI ghi đè file này lên User_Setup.h của thư viện để build giống máy thật.
//  Con thợ nạp CHỈ dùng font dựng sẵn (font 2/4) — KHÔNG cần SMOOTH_FONT.
//
//  ⚠️ Bo 2 cổng USB (micro + USB-C) = đời mới ST7789 -> đổi ILI9341_2_DRIVER thành
//     ST7789_DRIVER (+ TFT_RGB_ORDER TFT_BGR). Bo 1 cổng micro = ILI9341, giữ nguyên.
//     Xem dist/ghe-arduino/User_Setup_ST7789_2USB.h. Đây chỉ là bản cho CI biên dịch.
// ============================================================================
#define ILI9341_2_DRIVER
#define TFT_MISO 12
#define TFT_MOSI 13
#define TFT_SCLK 14
#define TFT_CS   15
#define TFT_DC    2
#define TFT_RST  -1
#define TFT_BL   21
#define TFT_BACKLIGHT_ON HIGH
#define LOAD_GLCD
#define LOAD_FONT2
#define LOAD_FONT4
#define LOAD_FONT6
#define LOAD_FONT7
#define LOAD_GFXFF
#define SPI_FREQUENCY       55000000
#define SPI_READ_FREQUENCY  20000000
