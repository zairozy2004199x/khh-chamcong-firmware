/* Khung giả của driver/uart.h. Giữ ĐÚNG kiểu uart_port_t — đó chính là chỗ bắt lỗi:
   truyền số trần vào uart_set_line_inverse là trình dịch từ chối, y như trên máy thật. */
#pragma once
#include <cstdint>

typedef enum { UART_NUM_0 = 0, UART_NUM_1 = 1, UART_NUM_2 = 2 } uart_port_t;

#define UART_SIGNAL_INV_DISABLE 0
#define UART_SIGNAL_TXD_INV     (1 << 3)
#define UART_SIGNAL_RXD_INV     (1 << 2)

/* inline ngay tại đây, không khai riêng ở phần đầu tệp dịch: .ino include driver/uart.h ở
   giữa thân, nên khai trước là dùng uart_port_t khi nó chưa tồn tại. */
inline int uart_set_line_inverse(uart_port_t port, uint32_t mask) { (void)port; (void)mask; return 0; }
