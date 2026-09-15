# Web app "Báo cáo chi phí" — phân bổ chi phí ra tab *File tổng báo cáo*

Ứng dụng web chạy **hoàn toàn trên trình duyệt** (không cần máy chủ, không gửi dữ liệu đi đâu),
thay cho việc kéo công thức tay trong file Excel "File chi phí MN Txx/yyyy".

Nhập dữ liệu đầu vào (doanh thu, danh mục khoản chi phí, bảng lương) → ứng dụng tính ra đúng
nội dung tab **File tổng báo cáo** (3 mục), kèm bảng kiểm và phân bổ xuống từng điểm để hạch toán MISA.

## Chạy nhiều người (web hosting + nhân viên nhập chi phí)

- **Giao diện** deploy lên **GitHub Pages** bằng workflow `.github/workflows/pages.yml`
  (bật 1 lần: *Settings → Pages → Source: GitHub Actions*).
- **Dữ liệu dùng chung** lưu trên **Google Sheets** qua **Google Apps Script** (`backend/apps-script/Code.gs`).
  Nhân viên nhập khoản chi phí ở **`nhap.html`** (điện thoại được), kế toán **duyệt** trong tab *Chi phí đầu vào*,
  web tự phân bổ và ra *File tổng báo cáo*. Mã truy cập riêng cho nhân viên và kế toán.
- Hướng dẫn triển khai từng bước: **[`backend/README.md`](backend/README.md)**.
- Không kết nối máy chủ thì trang kế toán vẫn chạy cục bộ (lưu trên trình duyệt) như mô tả dưới.

## Mở ứng dụng

Cách 1 — mở trực tiếp: tải thư mục `web_baocao_chiphi/` về máy, mở `index.html` bằng Chrome / Edge.

Cách 2 — chạy qua máy chủ tĩnh bất kỳ (khi muốn dùng chung trong mạng nội bộ):

```bash
cd web_baocao_chiphi
python3 -m http.server 8080
# mở http://localhost:8080
```

Cách 3 — nhúng vào web app khác bằng `<iframe src=".../web_baocao_chiphi/index.html">`.
Trang cho phép đọc/ghi dữ liệu từ ngoài qua `window.BaoCaoApp` (`getState()`, `setState()`, `getReport()`, `exportExcel()`).

Dữ liệu tự lưu trong trình duyệt (localStorage). Muốn chuyển máy hoặc lưu trữ: menu **⋯ → Lưu file cấu hình (.json)**.

## Quy trình hàng tháng

1. **Nhập Excel** (kéo-thả file vào trang cũng được). Ứng dụng đọc các sheet theo tên:

   | Sheet trong file Excel | Lấy gì |
   |---|---|
   | `Doanh thu Posh`, `Doanh thu JP`, `Doanh Thu KVC` | Mã đơn vị, tên điểm, doanh thu tháng (cột *Total*) → doanh thu từng bộ phận |
   | `total general` (vùng G..N) | Danh mục khoản chi phí: tên, nội dung MISA chung/chi tiết, tài khoản, tổng tiền, số chia MTĐ / KVC, mã đối tượng. Tự đọc các khối phân bổ phía dưới để biết bộ phận bị loại trừ (VD: thuê nhà NV không chia cho Pinball) |
   | `Bảng tổng lương NV cơ sở` | Lương NV cơ sở theo điểm ("Theo báo cáo" = cột bộ phận, "Thực lĩnh" = cột Số tiền), dòng *Lương VP HCM* → lương văn phòng theo bộ phận |
   | `File tổng báo cáo` (kỳ trước, nếu có) | Cột nhập tay (Phân bổ chi phí 154, 1543…), mã đơn vị và nội dung MISA cho các dòng lương |

   Kỳ báo cáo tự nhận diện từ tên sheet `… T8.2026`. Sau khi nhập có hộp nhật ký: đọc được gì, cảnh báo gì, và nút **Hoàn tác**.

2. Tab **Doanh thu**: kiểm tra tỷ lệ Posh / JP (mặc định 60 / 40), doanh thu từng bộ phận.
   Có thể thêm điểm, dán nhanh từ Excel, hoặc tick *Nhập tay* để gõ thẳng doanh thu bộ phận.

3. Tab **Chi phí đầu vào**: mỗi dòng là một khoản. Chọn *Kiểu chia nhóm* (chia đều MTĐ/KVC, 100 % một nhóm, hoặc tuỳ chỉnh),
   *Loại trừ bộ phận*, *Gộp cột* (các khoản cùng mã gộp thành một cột trên báo cáo, VD Scholarship HH / PH),
   *Phương pháp* (ghi đè cách chia của nhóm cho riêng khoản đó). Bên dưới là **cột nhập tay** (CP 154, CP mua máy 1543).

4. Tab **Lương**: Mục II (lương theo bộ phận) và Mục III (lương NV cơ sở theo điểm, có nhóm hiển thị, mã đơn vị, nội dung MISA).

5. Tab **Kiểm tra**: phải không còn lỗi đỏ. Ứng dụng kiểm tra tổng tỷ lệ = 100 %, tổng chia nhóm = tổng tiền,
   bảo toàn tổng khi phân bổ, mã đơn vị trùng, doanh thu = 0…

6. Tab **File tổng báo cáo** → **Xuất Excel**. File `File_tong_bao_cao_MN_Txx_yyyy.xlsx` gồm:
   - `File tổng báo cáo` — 3 mục đúng bố cục file gốc
   - `Phân bổ theo bộ phận` — bảng kiểm từng khoản: tổng tiền → chia nhóm → chia bộ phận
   - `Posh T8.2026`, `JP T8.2026`, `Funzone T8.2026`… — phân bổ xuống điểm theo tỷ trọng doanh thu (giống các sheet "… T8.2026")
   - `Doanh thu` — doanh thu & tỷ trọng từng điểm

   Ngoài ra có **In / Lưu PDF** và **Sao chép Mục I** để dán thẳng vào Excel.

7. Tháng sau: **⋯ → Kỳ mới** giữ toàn bộ cấu hình (nhóm, bộ phận, điểm, mã đơn vị, nội dung MISA), xoá số tiền, rồi nhập Excel mới.
   Cấu hình *loại trừ / gộp cột* của khoản cùng tên được kế thừa tự động khi nhập.

Phím tắt: `Ctrl+O` nhập Excel, `Ctrl+S` xuất Excel, `Enter` trong bảng nhảy xuống ô dưới.

## Cách tính (đúng theo file Excel gốc)

- Mỗi khoản chi phí có **tổng tiền** và phần rơi vào từng **nhóm**: *Máy tự động (MTĐ = Posh, JP)* và *Khu vui chơi (KVC = Funzone, Event, Farm, Tutu, Pinball)*.
- Phần của nhóm MTĐ chia cho bộ phận theo **tỷ lệ cố định** (Posh 60 %, JP 40 %).
- Phần của nhóm KVC chia theo **tỷ trọng doanh thu** tháng của các bộ phận trong nhóm (bỏ các bộ phận bị loại trừ, tỷ trọng tự chuẩn hoá về 100 %).
- Phân bổ xuống điểm: số của bộ phận × doanh thu điểm ÷ tổng doanh thu bộ phận (kể cả lương NV cơ sở, lương vận hành và cột nhập tay).
- Nhóm, phương pháp chia và bộ phận đều **thêm/sửa được** — không khoá cứng 7 bộ phận.

## Cấu trúc mã nguồn

| File | Vai trò |
|---|---|
| `index.html`, `style.css` | Giao diện (sáng/tối theo hệ thống, in được, chạy trên điện thoại) |
| `engine.js` | Lõi tính toán thuần (không phụ thuộc giao diện): phân bổ, báo cáo, phân bổ theo điểm, kiểm tra dữ liệu |
| `importer.js` | Đọc file Excel đầu vào → dữ liệu ứng dụng (SheetJS) |
| `exporter.js` | Dựng workbook Excel đầu ra |
| `app.js` | Trạng thái, tự lưu, các tab, nhập/xuất, đồng bộ máy chủ (duyệt khoản, phát hiện xung đột) |
| `api.js` | Gọi API Apps Script (dùng chung cho trang kế toán và trang nhân viên) |
| `nhap.html`, `nhap.js` | Trang nhập chi phí cho nhân viên |
| `backend/apps-script/Code.gs` | API trên Google Apps Script, dữ liệu trong Google Sheets (`ChiPhi`, `State`, `NhatKy`) |
| `sample-data.js` | Dữ liệu mẫu T8/2026 (trích từ file gốc) — hiển thị khi mở lần đầu |
| `vendor/xlsx.full.min.js` | Thư viện SheetJS (Apache-2.0) để trang chạy được offline |
| `test/engine.test.js` | Kiểm thử tự động, đối chiếu 185 con số với file Excel gốc T8/2026 |

Chạy kiểm thử:

```bash
node web_baocao_chiphi/test/engine.test.js
```

## Định dạng file cấu hình (.json)

```jsonc
{
  "period": { "month": 8, "year": 2026 },
  "groups": [ { "id": "MTD", "name": "Máy tự động (MTĐ)", "method": "ratio" },
              { "id": "KVC", "name": "Khu vui chơi (KVC)", "method": "revenue" } ],
  "departments": [ { "id": "posh", "name": "Posh", "group": "MTD", "ratio": 0.6, "unitCode": "Posh MN" }, … ],
  "sites": [ { "dept": "posh", "code": "50AMBT", "name": "POSH MN AEON MALL BÌNH TÂN", "revenue": 197260000 }, … ],
  "costItems": [ { "name": "Chi phí thuê kho VP", "misaDetail": "Chi phí thuê kho VP", "total": 55000000,
                   "split": "equal", "shares": { "MTD": 27500000, "KVC": 27500000 },
                   "excludeDepts": [], "groupKey": "", "method": "" }, … ],
  "manualCols": [ { "name": "Phân bổ chi phí 154", "values": { "posh": 80503841, … } } ],
  "salaryDept": [ { "dept": "posh", "luongVP": 51116966, "misaGeneral": "…", "misaDetail": "…" }, … ],
  "salarySites": [ { "dept": "tutu", "groupTitle": "Tàu HCM", "name": "AMTP", "reported": 27878190,
                     "actual": 29878190, "unitCode": "TTAMTP", "misaGeneral": "…", "misaDetail": "…" }, … ]
}
```

`split`: `"equal"` chia đều các nhóm · `"<mã nhóm>"` 100 % một nhóm · `"custom"` dùng `shares` nhập tay.
`method` của nhóm / khoản: `"ratio"` tỷ lệ cố định · `"revenue"` theo doanh thu · `"equal"` chia đều.
