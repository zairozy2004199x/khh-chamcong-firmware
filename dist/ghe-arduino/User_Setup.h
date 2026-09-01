// ============================================================================
//  TFT_eSPI — cấu hình cho màn CYD ESP32-2432S028
//  ----------------------------------------------------------------------------
//  TRƯỚC ĐÂY file này CHỈ nằm trên máy anh Thắng (thư mục thư viện TFT_eSPI),
//  repo không có bản nào -> mất máy là mất cấu hình, và CI không build đúng được.
//  Nay lưu ở đây: workflow ghi đè file này lên User_Setup.h của thư viện, nên
//  bản CI build ra GIỐNG bản anh build ở máy.
//
//  ⚠️ Sai driver ở đây thì máy vẫn nạp được nhưng MÀN HÌNH TRẮNG/ĐEN. Nội dung
//     dưới copy đúng từ tft_display_addon.md. Màn trắng thì thử ILI9341_DRIVER
//     hoặc ST7789_DRIVER (kèm TFT_RGB_ORDER TFT_BGR) — sửa ở đây, không sửa
//     trong thư mục thư viện, để CI và máy anh không lệch nhau.
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
