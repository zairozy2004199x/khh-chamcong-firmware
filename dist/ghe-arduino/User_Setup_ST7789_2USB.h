// ============================================================================
//  TFT_eSPI — cấu hình cho CYD ESP32-2432S028 ĐỜI MỚI (2 cổng USB: micro + USB-C)
//  ----------------------------------------------------------------------------
//  Bo 2 cổng USB thường dùng chip màn ST7789 (không phải ILI9341). Nếu nạp
//  firmware biên dịch cho ILI9341 vào bo này -> MÀN ĐEN (ESP32 vẫn chạy, đèn
//  nền vẫn nháy khi reset), vì lệnh khởi tạo màn sai chip.
//
//  CÁCH DÙNG (bắt buộc BIÊN DỊCH LẠI, không chỉ đổi file):
//    1) Chép ĐÈ nội dung file này vào  User_Setup.h  của thư viện TFT_eSPI
//       (vd: H:\Arduino\libraries\TFT_eSPI\User_Setup.h) — thay TOÀN BỘ.
//    2) Arduino: Verify/Upload lại sketch (hoặc export merged.bin mới).
//    3) Nạp lại bo qua USB.
//
//  NẾU LÊN HÌNH NHƯNG SAI MÀU (âm bản / xanh-đỏ đảo):
//    - Màu bị ÂM BẢN (trắng thành đen)  -> đổi  TFT_INVERSION_OFF  thành  TFT_INVERSION_ON.
//    - Đỏ và xanh dương đảo nhau        -> đổi  TFT_BGR  thành  TFT_RGB (bỏ dòng RGB_ORDER).
//  NẾU MÀN NHIỄU/CHỚP -> hạ SPI_FREQUENCY xuống 40000000 hoặc 27000000.
//  NẾU VẪN ĐEN sau khi đổi ST7789 -> quay lại ILI9341_2_DRIVER (bo dùng chip cũ).
// ============================================================================
#define ST7789_DRIVER
#define TFT_WIDTH  240
#define TFT_HEIGHT 320
#define TFT_RGB_ORDER TFT_BGR      // 2-USB hay dùng BGR; đỏ/xanh đảo thì bỏ dòng này
#define TFT_INVERSION_OFF          // màu âm bản thì đổi thành TFT_INVERSION_ON

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
// 🔴 BẮT BUỘC: firmware dùng font VLW tiếng Việt có dấu + số vàng (tft.loadFont/unloadFont).
//    THIẾU dòng này -> biên dịch báo: 'class TFT_eSPI' has no member named 'loadFont'.
#define SMOOTH_FONT

#define SPI_FREQUENCY       55000000   // nhiễu/chớp thì hạ 40000000 hoặc 27000000
#define SPI_READ_FREQUENCY  20000000
