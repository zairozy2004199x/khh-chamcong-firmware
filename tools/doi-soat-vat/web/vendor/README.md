# Thư viện ngoài

| File | Thư viện | Phiên bản | Giấy phép |
|---|---|---|---|
| `xlsx.full.min.js` | SheetJS Community Edition | 0.20.3 | Apache-2.0 (`xlsx-LICENSE.txt`) |

Dùng để đọc `.xlsx` / `.xls` và ghi `.xlsx` ngay trong trình duyệt.

Bản này lấy từ gói npm [`@e965/xlsx`](https://www.npmjs.com/package/@e965/xlsx) — bản
mirror của SheetJS 0.20.3. **Đừng thay bằng gói `xlsx` trên npm**: gói đó đứng yên ở
0.18.5 và dính hai lỗ hổng đã công bố (CVE-2023-30533 prototype pollution,
CVE-2024-22363 ReDoS). Muốn nâng cấp thì lấy bản mới ở https://cdn.sheetjs.com.

File được đặt sẵn trong repo (không dùng CDN) để trang chạy được cả khi máy không
có mạng, và để không có yêu cầu nào rời khỏi trình duyệt khi đang xử lý sao kê.
