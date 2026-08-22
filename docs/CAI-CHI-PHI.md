# Cài app Vận Hành Chi Phí

> Làm **trước** app Chấm Công — anh Thắng chốt 22/08/2026 sau khi chuyển sang Vietnix.
> Thứ tự này còn tiện một chuyện: danh sách người dùng + PIN khai ở đây **dùng chung được** cho
> app Chấm Công, nên làm xong cái này là cái kia đỡ một bước.

**Khác app Chấm Công ở một điểm quan trọng: app này KHÔNG cần khoá nào trong `wp-config.php`,
KHÔNG cần Apps Script, KHÔNG cần Firebase.** Dữ liệu nằm trọn trong MySQL của hosting. Cài xong
là chạy.

Phần thao tác trong cPanel (tên miền · SSL · PHP · WordPress) xem
[CAI-LEN-VIETNIX.md](CAI-LEN-VIETNIX.md) mục 1–5. Tệp này bắt đầu từ lúc đã có WordPress chạy.

---

## 1. Cài plugin

**Plugin → Cài mới → Tải plugin lên** → `vhcp-chi-phi.zip` → Cài đặt → **Kích hoạt**.

Kích hoạt là nó tự dựng bảng, âm thầm, không có màn nào báo.

## 2. Cài đặt

**Vận Hành Chi Phí → Cài đặt**:

| Ô | Đặt gì |
|---|---|
| Đường dẫn app | để `chi-phi` → app ở `https://khmatrix.com/chi-phi` |
| Múi giờ | `Asia/Bangkok` (GMT+7) — **đúng múi app cũ đang chạy** |
| SSO_SECRET | **để trống** nếu chỉ đăng nhập bằng PIN |

Đổi đường dẫn xong thì **mở app một lần** để WordPress nạp lại đường dẫn, không thì vào ra 404.

## 3. Nạp dữ liệu cũ từ Google Sheet

Hai đường, **dùng đường thứ nhất**:

### 3a. Nạp cả bảng tính từ link ← nên dùng

**Vận Hành Chi Phí → Nạp từ link Sheet** → dán link bảng tính → Nạp.

Trước đó bảng tính phải **cho xem bằng link**: mở Google Sheet → **Chia sẻ → Bất kỳ ai có đường
liên kết → Người xem**. Chưa mở thì plugin báo đúng câu đó, không báo "lỗi không rõ".

Vì sao nên dùng đường này chứ không xuất CSV từng tab: plugin **tự đoán tab nào là bảng gì theo
tên cột**, và **tự sắp danh mục trước, dòng chi sau**. Nạp dòng chi trước danh mục thì dòng nào
cũng thành "mồ côi" và bị bỏ — mà bỏ thì im lặng.

⚠️ Nạp xong đọc kỹ phần **báo lại**: có những thứ plugin **không đoán** mà bắt anh điền, ví dụ
**địa điểm của chuyến công tác** — thứ đó quyết định mảng kinh doanh nên không được đoán bừa.

### 3b. Nhập từng tab

**Nhập dữ liệu** → chọn loại tab → dán. Dùng khi chỉ cần bù một mảng.

Bộ nạp **khớp theo TÊN CỘT ở dòng tiêu đề**, không theo thứ tự cột — thiếu cột thì báo, cột lạ
cũng báo. Đây là chỗ cố ý: bảng tính của anh đặt cột khác thứ tự bộ nạp cũ, mà nạp lệch cột thì
mọi ô đều là chữ hợp lệ nên **không có gì báo lỗi, số liệu sai âm thầm**.

## 4. Khai người dùng + PIN

Trong app (`khmatrix.com/chi-phi`) → tab **⚙️ Cấu hình** → bảng người dùng: **Tên · PIN · Vai
trò · Cơ sở**.

Đây cũng là danh sách app Chấm Công dùng khi chọn *"Dùng chung với plugin Vận hành chi phí"* —
khai một lần cho cả hai hệ thống. Khai hai nơi là sớm muộn xoá một nơi quên nơi kia.

⚠️ **PIN có số 0 ở đầu**: Google Sheets coi `0123` là số nên lưu thành `123`. Ba chữ số thì
không đăng nhập được. Đặt PIN không bắt đầu bằng 0, hoặc định dạng ô đó thành Văn bản.

## 5. Kiểm

Theo thứ tự, dòng nào hỏng thì dừng ở đó:

| Kiểm | Đạt là thế nào |
|---|---|
| `https://khmatrix.com/chi-phi` | Lên màn nhập PIN |
| Đăng nhập bằng PIN vừa khai | Vào được |
| Mở một đơn cũ bất kỳ | Số tiền, cơ sở, kỳ khớp với bảng tính |
| Tổng tạm ứng một kỳ | Khớp với app cũ **cùng kỳ cùng cơ sở** |
| Xuất MISA | Tải được tệp, mã tài khoản có gán |

Dòng **tổng tạm ứng một kỳ** là dòng đáng tin nhất — nó cộng qua nhiều bảng, lệch một chỗ là ra
số khác ngay.

---

## Nếu "Nạp từ link Sheet" báo không gọi được mạng

Vài hosting chia sẻ chặn PHP gọi ra Internet. Mở ticket Vietnix hỏi đúng câu:

> Hosting của tôi có cho PHP gọi ra ngoài bằng `wp_remote_get` không?
> Tôi cần gọi tới `docs.google.com`.

Chặn thật thì nhờ họ mở, hoặc dùng đường **3b** (dán tay, không cần mạng).
