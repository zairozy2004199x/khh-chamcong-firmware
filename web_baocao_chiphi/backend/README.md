# Triển khai chạy nhiều người: GitHub Pages + Google Sheets

Kiến trúc (không cần thuê máy chủ):

```
Nhân viên (điện thoại/máy tính)  ──►  nhap.html   ──┐
                                                     ├──►  Google Apps Script (API)  ──►  Google Sheets (dữ liệu)
Kế toán                          ──►  index.html  ──┘         backend/apps-script/Code.gs      ChiPhi · State · NhatKy
        ▲
        └── trang web tĩnh trên GitHub Pages (thư mục web_baocao_chiphi/, tự deploy khi push main)
```

- **Giao diện** là file tĩnh, nằm trên GitHub Pages: `https://<chủ-repo>.github.io/khh-chamcong-firmware/`
  (kế toán) và `…/nhap.html` (nhân viên). Không chứa mật khẩu nào.
- **Dữ liệu** nằm trong một Google Sheet của công ty, kế toán mở xem trực tiếp được. Apps Script làm API,
  phân quyền bằng 2 mã truy cập: **mã nhân viên** (chỉ thêm/sửa/xoá khoản của mình khi còn chờ duyệt) và
  **mã kế toán** (toàn quyền, duyệt khoản, lưu cấu hình kỳ).
- Không nối máy chủ thì trang kế toán vẫn chạy **cục bộ** như cũ (lưu trên trình duyệt).

## Bước 1 — Bật GitHub Pages (làm 1 lần)

1. Repo → **Settings → Pages → Build and deployment → Source: GitHub Actions**.
2. Workflow `.github/workflows/pages.yml` đã có sẵn: mỗi lần push vào `main` có thay đổi trong `web_baocao_chiphi/`
   là tự chạy test rồi deploy. Lần đầu có thể vào **Actions → Deploy web báo cáo chi phí → Run workflow**.
3. Địa chỉ trang hiện ở Settings → Pages (dạng `https://<chủ-repo>.github.io/khh-chamcong-firmware/`).

## Bước 2 — Tạo API trên Google (làm 1 lần, ~5 phút)

1. Tạo một **Google Sheet** mới, đặt tên ví dụ `KH - Du lieu bao cao chi phi`.
2. Trong Sheet: **Tiện ích mở rộng → Apps Script**. Xoá nội dung mặc định, dán toàn bộ file
   [`apps-script/Code.gs`](apps-script/Code.gs) vào, **Lưu** (Ctrl+S).
3. Trên thanh công cụ chọn hàm **`CAI_DAT_MA_TRUY_CAP`** → **Run**. Lần đầu Google hỏi cấp quyền:
   *Review permissions → chọn tài khoản → Advanced → Go to … (unsafe) → Allow*.
   Hàm này tạo 3 sheet `ChiPhi`, `State`, `NhatKy` và sinh 2 mã truy cập ngẫu nhiên.
4. Xem mã: **Run `XEM_MA_TRUY_CAP`** rồi mở **Execution log** (hoặc *Project Settings → Script Properties*).
   Muốn đặt mã dễ nhớ: *Project Settings → Script Properties → sửa `TOKEN_KETOAN`, `TOKEN_NHAP`*.
5. **Deploy → New deployment → ⚙ chọn Web app**:
   - Description: `bao cao chi phi`
   - Execute as: **Me**
   - Who has access: **Anyone**  ← bắt buộc, để trang web gọi được từ trình duyệt
   → **Deploy** → copy **Web app URL** (kết thúc bằng `/exec`).
6. Kiểm tra: mở URL đó trên trình duyệt phải thấy dòng "API Báo cáo chi phí K&H đang chạy".

> Mỗi lần sửa `Code.gs` phải **Deploy → Manage deployments → ✎ → Version: New version → Deploy** thì URL cũ mới nhận code mới.

## Bước 3 — Kết nối trang web

**Kế toán** mở trang tổng hợp → nút **⚙ Kết nối** → dán URL, mã kế toán, tên → *Lưu & kiểm tra kết nối*.
Lần đầu kết nối vào một kỳ chưa có trên máy chủ, ứng dụng hỏi có đẩy cấu hình đang có trên máy lên không
(nhóm, bộ phận, điểm, cột nhập tay, lương…). Các khoản chi phí đang có trên máy cũng được đẩy lên với trạng thái **Đã duyệt**.

**Nhân viên** mở `nhap.html` → **⚙ Kết nối** → dán URL, mã nhân viên, tên → nhập khoản.
Kế toán có thể gửi sẵn link cấu hình: `…/nhap.html?url=<URL API>&token=<mã nhân viên>` (mở 1 lần là lưu vào máy).

## Luồng làm việc hàng tháng

1. Nhân viên nhập khoản chi phí trong kỳ (tên, số tiền, chia MTĐ/KVC, nội dung MISA, ghi chú). Trạng thái **Chờ duyệt**.
2. Kế toán mở tab **Chi phí đầu vào**: thấy khoản mới (có tên người nhập), sửa kiểu chia / loại trừ / gộp cột nếu cần,
   rồi **Duyệt** (hoặc Từ chối). Chỉ khoản **Đã duyệt** mới vào báo cáo; có tuỳ chọn *tính cả khoản chờ duyệt* để xem trước.
3. Kế toán nhập/nhập Excel doanh thu, bảng lương như cũ → **File tổng báo cáo** → **Xuất Excel**.
4. Mọi thay đổi của kế toán tự lưu lên máy chủ (mỗi kỳ một dòng trong sheet `State`, có số phiên bản để phát hiện
   hai người cùng sửa). Trang tự tải khoản mới mỗi 60 giây; bấm **↻** để tải ngay.

## Dữ liệu trong Google Sheet

| Sheet | Nội dung |
|---|---|
| `ChiPhi` | Mỗi dòng một khoản: `id, period (yyyy-mm), status (cho_duyet/da_duyet/tu_choi), createdAt/By, updatedAt/By, kind, name, misaGeneral, misaDetail, account, objectCode, total, split, shares (JSON), excludeDepts (JSON), groupKey, method, note, approvedBy/At, deleted, sortOrder`. Kế toán sửa trực tiếp trên sheet cũng được. |
| `State` | Mỗi kỳ một dòng: `period, version, updatedAt, updatedBy, json1..N` (cấu hình + doanh thu + lương, JSON cắt thành nhiều ô). |
| `NhatKy` | Ai làm gì lúc nào. |

Sao lưu: Google Sheet có lịch sử phiên bản (*Tệp → Lịch sử phiên bản*). Ngoài ra kế toán vẫn có thể **⋯ → Lưu file cấu hình (.json)**.

## Bảo mật ở mức phù hợp

- URL Apps Script + mã truy cập là "chìa khoá": chỉ gửi trong nội bộ. Đổi mã bất kỳ lúc nào ở Script Properties.
- Script chạy dưới tài khoản Google của người deploy; Sheet không cần chia sẻ công khai.
- Nhân viên không đọc được cấu hình lương/doanh thu (API chỉ trả danh mục nhóm/bộ phận cho quyền nhân viên).
- Muốn chặt hơn (đăng nhập Google từng người), cần chuyển sang Firebase Auth hoặc Google Identity — có thể làm ở bước sau.
