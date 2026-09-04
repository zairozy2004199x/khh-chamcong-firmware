/*
 * CẤU HÌNH BOARD — Waveshare ESP32-S3-Touch-LCD-2.8B (480×640, ST7701 RGB, GT911)
 * Dựa trên template gốc esp_panel_board_custom_conf.h của ESP32_Display_Panel v1.2.
 * Chân LCD/touch điền theo SCHEMATIC chính thức của Waveshare. Chép cạnh file .ino.
 */
#pragma once

// *INDENT-OFF*

#define ESP_PANEL_BOARD_DEFAULT_USE_CUSTOM  (1)

#if ESP_PANEL_BOARD_DEFAULT_USE_CUSTOM

#define ESP_PANEL_BOARD_NAME                "Waveshare:ESP32-S3-Touch-LCD-2.8B"

#define ESP_PANEL_BOARD_WIDTH               (480)
#define ESP_PANEL_BOARD_HEIGHT              (640)

/* ───────────────────────────────── LCD ──────────────────────────────────── */
#define ESP_PANEL_BOARD_USE_LCD             (1)

#if ESP_PANEL_BOARD_USE_LCD
#define ESP_PANEL_BOARD_LCD_CONTROLLER      ST7701
#define ESP_PANEL_BOARD_LCD_BUS_TYPE        (ESP_PANEL_BUS_TYPE_RGB)

#if ESP_PANEL_BOARD_LCD_BUS_TYPE == ESP_PANEL_BUS_TYPE_RGB

    /* Control panel 3-wire SPI: SDA=GPIO1, SCK=GPIO2 (GPIO thật), CS=EXIO3 (TCA9554). */
    #define ESP_PANEL_BOARD_LCD_RGB_USE_CONTROL_PANEL       (1)
#if ESP_PANEL_BOARD_LCD_RGB_USE_CONTROL_PANEL
    #define ESP_PANEL_BOARD_LCD_RGB_SPI_IO_CS               (3)     // EXIO3 = LCD_CS
    #define ESP_PANEL_BOARD_LCD_RGB_SPI_IO_SCK              (2)     // GPIO2 = LCD_SCK
    #define ESP_PANEL_BOARD_LCD_RGB_SPI_IO_SDA              (1)     // GPIO1 = LCD_SDA
    #define ESP_PANEL_BOARD_LCD_RGB_SPI_CS_USE_EXPNADER     (1)     // CS trên TCA9554
    #define ESP_PANEL_BOARD_LCD_RGB_SPI_SCL_USE_EXPNADER    (0)
    #define ESP_PANEL_BOARD_LCD_RGB_SPI_SDA_USE_EXPNADER    (0)
    #define ESP_PANEL_BOARD_LCD_RGB_SPI_MODE                (0)
    #define ESP_PANEL_BOARD_LCD_RGB_SPI_CMD_BYTES           (1)
    #define ESP_PANEL_BOARD_LCD_RGB_SPI_PARAM_BYTES         (1)
    #define ESP_PANEL_BOARD_LCD_RGB_SPI_USE_DC_BIT          (1)
#endif
    #define ESP_PANEL_BOARD_LCD_RGB_CLK_HZ          (14 * 1000 * 1000)
    #define ESP_PANEL_BOARD_LCD_RGB_HPW             (8)
    #define ESP_PANEL_BOARD_LCD_RGB_HBP             (10)
    #define ESP_PANEL_BOARD_LCD_RGB_HFP             (20)
    #define ESP_PANEL_BOARD_LCD_RGB_VPW             (2)
    #define ESP_PANEL_BOARD_LCD_RGB_VBP             (12)
    #define ESP_PANEL_BOARD_LCD_RGB_VFP             (16)
    #define ESP_PANEL_BOARD_LCD_RGB_PCLK_ACTIVE_NEG (0)
    #define ESP_PANEL_BOARD_LCD_RGB_DATA_WIDTH      (16)
    #define ESP_PANEL_BOARD_LCD_RGB_PIXEL_BITS      (ESP_PANEL_LCD_COLOR_BITS_RGB565)
    #define ESP_PANEL_BOARD_LCD_RGB_BOUNCE_BUF_SIZE (ESP_PANEL_BOARD_WIDTH * 10)
    #define ESP_PANEL_BOARD_LCD_RGB_IO_HSYNC        (38)
    #define ESP_PANEL_BOARD_LCD_RGB_IO_VSYNC        (39)
    #define ESP_PANEL_BOARD_LCD_RGB_IO_DE           (40)
    #define ESP_PANEL_BOARD_LCD_RGB_IO_PCLK         (41)
    #define ESP_PANEL_BOARD_LCD_RGB_IO_DISP         (-1)
    /* Data RGB565: Blue B0..4, Green G0..5, Red R0..4 — theo schematic 2.8B */
    #define ESP_PANEL_BOARD_LCD_RGB_IO_DATA0        (5)     // DB3
    #define ESP_PANEL_BOARD_LCD_RGB_IO_DATA1        (45)    // DB4
    #define ESP_PANEL_BOARD_LCD_RGB_IO_DATA2        (48)    // DB5
    #define ESP_PANEL_BOARD_LCD_RGB_IO_DATA3        (47)    // DB6
    #define ESP_PANEL_BOARD_LCD_RGB_IO_DATA4        (21)    // DB7
    #define ESP_PANEL_BOARD_LCD_RGB_IO_DATA5        (14)    // DG2
    #define ESP_PANEL_BOARD_LCD_RGB_IO_DATA6        (13)    // DG3
    #define ESP_PANEL_BOARD_LCD_RGB_IO_DATA7        (12)    // DG4
#if ESP_PANEL_BOARD_LCD_RGB_DATA_WIDTH > 8
    #define ESP_PANEL_BOARD_LCD_RGB_IO_DATA8        (11)    // DG5
    #define ESP_PANEL_BOARD_LCD_RGB_IO_DATA9        (10)    // DG6
    #define ESP_PANEL_BOARD_LCD_RGB_IO_DATA10       (9)     // DG7
    #define ESP_PANEL_BOARD_LCD_RGB_IO_DATA11       (46)    // DR3
    #define ESP_PANEL_BOARD_LCD_RGB_IO_DATA12       (3)     // DR4
    #define ESP_PANEL_BOARD_LCD_RGB_IO_DATA13       (8)     // DR5
    #define ESP_PANEL_BOARD_LCD_RGB_IO_DATA14       (18)    // DR6
    #define ESP_PANEL_BOARD_LCD_RGB_IO_DATA15       (17)    // DR7
#endif

#endif // ESP_PANEL_BOARD_LCD_BUS_TYPE == RGB

#if (ESP_PANEL_BOARD_LCD_BUS_TYPE == ESP_PANEL_BUS_TYPE_RGB) && ESP_PANEL_BOARD_LCD_RGB_USE_CONTROL_PANEL
#define ESP_PANEL_BOARD_LCD_FLAGS_ENABLE_IO_MULTIPLEX       (0)
#define ESP_PANEL_BOARD_LCD_FLAGS_MIRROR_BY_CMD             (!ESP_PANEL_BOARD_LCD_FLAGS_ENABLE_IO_MULTIPLEX)
#endif

/* Init ST7701: để trống -> dùng init mặc định. Sai màu tone thì dán init Waveshare vào
   ESP_PANEL_BOARD_LCD_VENDOR_INIT_CMD() (xem template gốc). */

#define ESP_PANEL_BOARD_LCD_COLOR_BITS          (ESP_PANEL_LCD_COLOR_BITS_RGB565)
#define ESP_PANEL_BOARD_LCD_COLOR_BGR_ORDER     (0)     // đỏ↔xanh dương đảo thì đổi thành 1
#define ESP_PANEL_BOARD_LCD_COLOR_INEVRT_BIT    (0)

#define ESP_PANEL_BOARD_LCD_SWAP_XY             (0)
#define ESP_PANEL_BOARD_LCD_MIRROR_X            (0)
#define ESP_PANEL_BOARD_LCD_MIRROR_Y            (0)
#define ESP_PANEL_BOARD_LCD_GAP_X               (0)
#define ESP_PANEL_BOARD_LCD_GAP_Y               (0)

#define ESP_PANEL_BOARD_LCD_RST_IO              (-1)    // RST qua EXIO1 (expander) -> -1 ở đây
#define ESP_PANEL_BOARD_LCD_RST_LEVEL           (0)

#endif // ESP_PANEL_BOARD_USE_LCD

/* ──────────────────────────────── TOUCH (GT911) ─────────────────────────── */
#define ESP_PANEL_BOARD_USE_TOUCH               (1)

#if ESP_PANEL_BOARD_USE_TOUCH
#define ESP_PANEL_BOARD_TOUCH_CONTROLLER        GT911
#define ESP_PANEL_BOARD_TOUCH_BUS_TYPE          (ESP_PANEL_BUS_TYPE_I2C)

#if ESP_PANEL_BOARD_TOUCH_BUS_TYPE == ESP_PANEL_BUS_TYPE_I2C
    #define ESP_PANEL_BOARD_TOUCH_I2C_HOST_ID           (0)
    #define ESP_PANEL_BOARD_TOUCH_I2C_CLK_HZ            (400 * 1000)
    #define ESP_PANEL_BOARD_TOUCH_I2C_SCL_PULLUP        (1)
    #define ESP_PANEL_BOARD_TOUCH_I2C_SDA_PULLUP        (1)
    #define ESP_PANEL_BOARD_TOUCH_I2C_IO_SCL            (7)     // TP_SCL
    #define ESP_PANEL_BOARD_TOUCH_I2C_IO_SDA            (15)    // TP_SDA
    #define ESP_PANEL_BOARD_TOUCH_I2C_ADDRESS           (0)     // 0 = tự dò (0x5D/0x14)
#endif

#define ESP_PANEL_BOARD_TOUCH_SWAP_XY           (0)
#define ESP_PANEL_BOARD_TOUCH_MIRROR_X          (0)
#define ESP_PANEL_BOARD_TOUCH_MIRROR_Y          (0)

#define ESP_PANEL_BOARD_TOUCH_RST_IO            (-1)    // TP_RST qua EXIO2 (expander) -> -1
#define ESP_PANEL_BOARD_TOUCH_RST_LEVEL         (0)
#define ESP_PANEL_BOARD_TOUCH_INT_IO            (16)    // TP_INT = GPIO16
#define ESP_PANEL_BOARD_TOUCH_INT_LEVEL         (0)

#endif // ESP_PANEL_BOARD_USE_TOUCH

/* ─────────────────────────────── BACKLIGHT ──────────────────────────────── */
#define ESP_PANEL_BOARD_USE_BACKLIGHT           (1)

#if ESP_PANEL_BOARD_USE_BACKLIGHT
#define ESP_PANEL_BOARD_BACKLIGHT_TYPE          (ESP_PANEL_BACKLIGHT_TYPE_PWM_LEDC)

#if (ESP_PANEL_BOARD_BACKLIGHT_TYPE == ESP_PANEL_BACKLIGHT_TYPE_SWITCH_GPIO) || \
    (ESP_PANEL_BOARD_BACKLIGHT_TYPE == ESP_PANEL_BACKLIGHT_TYPE_SWITCH_EXPANDER) || \
    (ESP_PANEL_BOARD_BACKLIGHT_TYPE == ESP_PANEL_BACKLIGHT_TYPE_PWM_LEDC)
    #define ESP_PANEL_BOARD_BACKLIGHT_IO        (6)     // BL_PWM = GPIO6
    #define ESP_PANEL_BOARD_BACKLIGHT_ON_LEVEL  (1)
#if ESP_PANEL_BOARD_BACKLIGHT_TYPE == ESP_PANEL_BACKLIGHT_TYPE_PWM_LEDC
    #define ESP_PANEL_BOARD_BACKLIGHT_PWM_FREQ_HZ          (5000)
    #define ESP_PANEL_BOARD_BACKLIGHT_PWM_DUTY_RESOLUTION  (10)
#endif
#endif

#define ESP_PANEL_BOARD_BACKLIGHT_IDLE_OFF      (0)

#endif // ESP_PANEL_BOARD_USE_BACKLIGHT

/* ─────────────────────────────── IO EXPANDER (TCA9554) ──────────────────── */
#define ESP_PANEL_BOARD_USE_EXPANDER            (1)

#if ESP_PANEL_BOARD_USE_EXPANDER
#define ESP_PANEL_BOARD_EXPANDER_CHIP           TCA95XX_8BIT
#define ESP_PANEL_BOARD_EXPANDER_SKIP_INIT_HOST     (0)
#define ESP_PANEL_BOARD_EXPANDER_I2C_HOST_ID        (0)
#if !ESP_PANEL_BOARD_EXPANDER_SKIP_INIT_HOST
#define ESP_PANEL_BOARD_EXPANDER_I2C_CLK_HZ         (400 * 1000)
#define ESP_PANEL_BOARD_EXPANDER_I2C_SCL_PULLUP     (1)
#define ESP_PANEL_BOARD_EXPANDER_I2C_SDA_PULLUP     (1)
#define ESP_PANEL_BOARD_EXPANDER_I2C_IO_SCL         (7)
#define ESP_PANEL_BOARD_EXPANDER_I2C_IO_SDA         (15)
#endif
#define ESP_PANEL_BOARD_EXPANDER_I2C_ADDRESS        (0x20)  // TCA9554; init lỗi thử 0x24/0x38
#endif // ESP_PANEL_BOARD_USE_EXPANDER

/* Ghi chú: EXIO1=LCD_RST, EXIO2=TP_RST trên TCA9554. Nếu begin() OK mà màn tối /
 * touch không ăn, khả năng cần nhả reset qua expander trước begin — em sẽ thêm
 * hàm ESP_PANEL_BOARD_PRE_BEGIN_FUNCTION với API đã kiểm chứng khi tới bước đó. */

/* ─────────────────────────────── File Version ───────────────────────────── */
#define ESP_PANEL_BOARD_CUSTOM_FILE_VERSION_MAJOR 1
#define ESP_PANEL_BOARD_CUSTOM_FILE_VERSION_MINOR 2
#define ESP_PANEL_BOARD_CUSTOM_FILE_VERSION_PATCH 0

#endif // ESP_PANEL_BOARD_DEFAULT_USE_CUSTOM

// *INDENT-ON*
