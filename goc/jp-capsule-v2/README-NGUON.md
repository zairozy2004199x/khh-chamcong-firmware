# JP CAPSULE v2 — MÃ NGUỒN GỐC (Google Apps Script)

Bản gốc để **tra cứu và lập bản đồ nghiệp vụ**. Không chạy gì trên hosting.

- Nguồn: `JP-Capsule-v2-ma-nguon-2026-09-15.docx`, anh Thắng gửi 21/09/2026
- Bản mã ngày 15/09/2026 · commit `c6a4bc8`
- 32 file · 28.735 dòng · Google Apps Script + Google Sheets

## Đã soát khi tách ra khỏi .docx

Bản dump trong Word đánh số từng dòng (`   1 │ ...`), nên tách xong phải khớp cả ba:

| Phép soát | Kết quả |
|---|---|
| Số file | 32 / 32 |
| Tổng số dòng | 28.735 — khớp bản khai |
| Số dòng từng file | khớp mục lục, không file nào lệch |
| Số dòng chạy liên tục | không đứt quãng ở file nào |
| Cú pháp 16 file `.gs` | `node --check` xanh hết |
| JavaScript nhúng trong 11 file `.html` | `node --check` xanh hết |
| `appsscript.json` | JSON hợp lệ |
| Thẻ `<script>` cân đối | 15/15 file |
| Khoá · PIN · ID Sheet gán cứng | **không có** |

Nháy cong (`'`, `"`) chỉ xuất hiện trong chú thích tiếng Việt và trong một chuỗi
báo lỗi — `node --check` xanh nên Word không bóp méo mã.

⚠️ Sheet ID **cố ý không nằm trong mã** — tác giả để ở Script Properties, khoá
`JP_SHEET_ID`, vì *"repo này đẩy lên git, ID lọt vào history là vĩnh viễn"*.
Giữ nguyên nếp ấy khi chuyển sang WordPress.

## Hình dạng hệ thống

Hai web dùng CHUNG một Google Sheet và chung máy chủ Apps Script:

- **Web nhân viên** — nhập báo cáo từ chỉ số máy, chụp ảnh, nộp tiền, sửa trong 24h
- **Web kế toán** — duyệt hai phần (doanh thu + hàng hoá), đối soát ngân hàng,
  kho hai tầng, bút toán

| | |
|---|---|
| Bảng dữ liệu (tab sheet) | **23** — `JP_Users` … `JP_ReconLog`; nặng nhất là `JP_Rows` 46 cột, `JP_Reports` 45 cột |
| Hàm máy chủ | **332** |
| Hàm web gọi tới (mặt tiếp xúc) | **100** |
| Cảnh báo nghiệp vụ | W1–W17, nằm ở `JP2_04_TinhToan.gs` |

### Nguyên tắc tác giả tự đặt (đáng giữ khi chuyển)

Chép từ đầu `JP2_00_Config.gs`:

> - Mọi con số tiền do server tính từ chỉ số máy — client không gửi lên tổng.
> - Ghi chỉ đụng đúng ô cần ghi, luôn kèm Audit.
> - Đọc sheet 1 lần rồi cache, không đọc trong vòng lặp.

Và `JP2_01_Core.gs` là **lớp truy cập dữ liệu duy nhất** — không file nào khác
được gọi thẳng `SpreadsheetApp`. Đúng hình dạng cần có để thay tầng lưu trữ.
