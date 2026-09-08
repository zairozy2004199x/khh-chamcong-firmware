# Nhà Ma · Bán vé theo khung giờ — cài & vận hành

*Plugin `wordpress/vhcp-nha-ma` — bản 1.4.0. Một file PHP, cài qua wp-admin như mọi plugin khác.*

| Địa chỉ | Ai dùng |
|---|---|
| `khmatrix.com/ban-ve-nha-ma` | **Khách** — chọn khung giờ, ghi danh, nhận thiệp + mã QR chuyển khoản |
| `khmatrix.com/ban-ve-nha-ma/#quanly` | **Nhân viên** — 6 màn, gác bằng PIN (mặc định `246810`) |
| `khmatrix.com/nha-ma-tien?token=…` | **Ngân hàng** bắn tiền về (SePay / Casso / Tingo) |

Hai trang đầu **hiện sẵn ở Cổng K&H và trên thanh nút trang Nội bộ** — hai ô riêng: *Bán Vé Nhà Ma*
(gửi cho khách) và *Quản Trị Vé Nhà Ma* (nhân viên). Cần plugin **Cổng K&H từ bản 1.6.0**.

Sổ vé nằm trong **MySQL của chính website** — không Google Sheet, không Firebase. Mọi máy chung
một sổ, nên khách tự đặt từ điện thoại của họ được.

---

## 1. Cài

1. wp-admin → Plugin → Cài mới → **Tải plugin lên** → `dist/vhcp-nha-ma.zip` → Kích hoạt.
2. Mở `khmatrix.com/ban-ve-nha-ma`. Ra **404** thì vào Cài đặt → **Đường dẫn tĩnh** → bấm **Lưu**
   một lần (WordPress nạp lại bảng đường dẫn). Không phải sửa gì khác.
3. Vào `#quanly`, PIN `246810` → **Cài đặt hệ thống** → đổi PIN.

Cài xong plugin tự dựng hai bảng: `wp_nhama_don` (thiệp) và `wp_nhama_tien` (sổ tiền về), và tự
sinh khoá cho cổng tiền.

---

## 2. Ba việc phải khai trước khi bán

### 2.1. Tài khoản nhận tiền — *Cài đặt hệ thống*

| Ô | Ví dụ |
|---|---|
| Mã ngân hàng BIN | `970418` BIDV · `970436` Vietcombank · `970415` VietinBank · `970422` MB · `970407` Techcombank |
| Số tài khoản | `8888815678` |
| Chủ tài khoản | `NGUYEN VAN A` (viết HOA không dấu) |

Bỏ trống cả ba thì **mượn tài khoản đã khai ở plugin Ghế Massage** — cùng cửa hàng, cùng tài
khoản, khai hai lần là hai chỗ để gõ nhầm. Màn Cài đặt in ra tài khoản **đang thực dùng** để soi.

> 🔴 **Soi kỹ số tài khoản.** Bên plugin ghế từng mất một buổi vì số thiếu một chữ số: app BIDV
> báo *"định dạng tài khoản định danh không hợp lệ (174)"* trong khi mã QR trông vẫn bình thường.
> Cách chắc chắn nhất: **chuyển thử 1.000đ** bằng chính app ngân hàng qua mã QR trên thiệp.

### 2.2. Cổng tiền về — *Tiền Về & Zalo*

Chép địa chỉ webhook ở đầu màn, dán vào ô Webhook bên **SePay** (kiểu POST JSON, sự kiện *tiền
vào*). Rồi chuyển thử 1.000đ với nội dung là **một mã thiệp có thật** — sổ bên dưới phải hiện
ngay một dòng.

- **Sổ trống hoàn toàn** → bên gửi chưa bắn tới, hoặc tường lửa hosting chặn. Vietnix thì xin loại
  trừ `/nha-ma-tien` khỏi Imunify360.
- **Có dòng nhưng không khớp** → đọc cột *Kết quả*, nó nói thẳng vì sao.

Khoá cổng tự sinh lúc cài. Muốn đổi thì khai `NHAMA_KHOA_TIEN` trong `wp-config.php` (khai rồi thì
hằng ấy thắng), hoặc xoá option `nhama_khoa_tien` cho nó sinh lại.

### 2.3. (Tuỳ chọn) Gửi vé qua Zalo — *Cài đặt hệ thống*

Khai **access token Zalo OA**. Thiệp được duyệt là tự gửi mã vé + đường dẫn mở thiệp (có QR vé).

> ⚠️ **Zalo không cho gửi tin cho số bất kỳ.** Hai đường, cả hai đều cần **Official Account**:
> - **Tin tư vấn (CS)** — miễn phí, nhưng **chỉ tới được người đã nhắn cho OA trong 7 ngày**.
>   Khách mua vé lần đầu gần như chắc chắn không thoả.
> - **ZNS** — gửi được cho mọi số, nhưng phải **đăng ký mẫu tin và chờ Zalo duyệt**, và **mỗi tin
>   tốn phí**. Khai thêm ô *Mã mẫu tin ZNS*.
>
> Chưa có token thì bỏ trống — mọi thứ khác chạy bình thường, khách vẫn xem thiệp trên web.
>
> ⚠️ Phần bắn đi **chưa chạy thử với OA thật**. Nhật ký Zalo trong màn *Tiền Về & Zalo* sẽ nói ngay
> Zalo trả về gì.

---

## 3. Vòng đời một thiệp

```
Khách ghi danh            -> Đang giữ chỗ           (đã TRỪ CHỖ ngay, chưa thu tiền)
Ngân hàng bắn tiền về     -> Chờ Check-in           (TỰ ĐỘNG, kèm gửi Zalo)
   hoặc kế toán bấm DUYỆT -> Chờ Check-in
Nhân viên soát vé ở cửa   -> Đã vào
Kế toán bấm TỪ CHỐI       -> Bị huỷ                 (chỗ trả lại cho khách khác ngay)
```

**Đơn đang giữ chỗ vẫn chiếm chỗ** dù chưa trả đồng nào — giữ mà không trừ thì hai đoàn cùng một
khung, và đoàn thứ hai chỉ biết mình hụt lúc đã tới cửa.

**Thực thu chỉ đếm đơn đã duyệt tiền.** Cộng cả đơn đang giữ chỗ vào là báo cáo kế toán nói dối
đúng con số người ta mang đi đối chiếu ngân hàng.

### Máy chủ xử các ca lệch thế nào

| Ca | Máy chủ làm gì |
|---|---|
| Thiếu tiền | **Không duyệt**, nhưng ghi vào đơn (`Tiền về THIẾU: 100.000/200.000`) để kế toán gọi khách |
| Tiền ra khỏi tài khoản | Bỏ qua — SePay bắn cả hai chiều, duyệt nhầm là thiệp hợp lệ mà tiền vừa rời tài khoản |
| Tiền về cho thiệp đã huỷ | Không mở lại, ghi **cần hoàn tiền** |
| Nội dung không mang mã thiệp | Vào sổ chờ xử tay |
| Ngân hàng bắn lại cùng giao dịch | Phần trùng tự hoà (`ref` là UNIQUE) — không duyệt hai lần |
| Gói không đọc được | Giữ **nguyên văn** trong sổ, vẫn trả 200 |

> 🔴 **Cổng luôn trả mã HTTP 200**, kể cả với gói không hiểu. Ca duy nhất trả khác: sai khoá (401).
> Bên gửi thấy 5xx là đẩy lại vài lần rồi **TẮT HẲN webhook** — từ lúc ấy tiền về mà hệ thống
> không hay biết, im lặng, không ai phát hiện cho tới khi khách đứng ở cửa.

---

## 4. Trang khách

- Chọn ngày → lưới khung giờ (ô hết chỗ hiện **Đóng**, khung đã qua giờ thì thôi bán — tính bằng
  **giờ máy chủ**, không tin giờ điện thoại).
- Ghi danh → thiệp có **mã QR VietQR**: số tiền và nội dung điền sẵn, khách chỉ quét rồi bấm gửi.
- Nội dung chuyển khoản = **mã thiệp bỏ dấu gạch** (`GB3UXTBBC4`). Nhiều app ngân hàng lọc ký tự
  đặc biệt; để nguyên `GB-XXXX` thì app cắt thành `GBXXXX` và hai bên không khớp.
  Chuỗi hiện trên màn được **bóc ngược ra từ chính chuỗi QR**, nên không thể lệch với cái khách quét.
- **Chuyển xong không phải bấm gì**: trang hỏi lại máy chủ 5 giây/lần, tiền về là thiệp tự đổi.
  Dừng dò sau 3 phút kèm nút hỏi lại.
- Thiệp hợp lệ thì **QR chuyển khoản biến mất, QR vào cửa hiện ra**. Không bao giờ hiện hai mã
  cùng lúc — hai mã đen trắng giống hệt nhau cạnh nhau là khách quét nhầm cái nọ ra cái kia.
- **Tra cứu lời mời** bằng số điện thoại; đường dẫn trong tin Zalo (`#ve=GB-XXXX`) mở thẳng thiệp.

---

## 5. Trang quản trị — 6 màn

| Màn | Việc |
|---|---|
| **Tổng Quan** | 4 ô số kế toán + lưới khung giờ theo ngày (đã đặt / sức chứa) |
| **Xét Duyệt Tiền** | Đơn khách báo đã chuyển — DUYỆT / TỪ CHỐI, có nút Chat Zalo |
| **Soát Vé Tại Cửa** | Quét QR bằng camera, hoặc **gõ tay mã** |
| **Dữ Liệu Đối Soát** | Lọc theo tên/SĐT/mã/ngày/trạng thái, xuất Excel, huỷ / mở lại, gửi lại Zalo |
| **Tiền Về & Zalo** | Địa chỉ webhook, sổ mọi gói ngân hàng bắn tới, nhật ký gửi Zalo |
| **Cài Đặt Hệ Thống** | Giá vé, sức chứa, giờ mở/đóng, bước khung, tài khoản, Zalo, PIN, đơn mẫu |

**Soát vé**: quét bằng bộ đọc **có sẵn trong trình duyệt**, không tải thư viện từ mạng ngoài — cửa
hay sóng yếu, mà trang soát vé chết vì tải không nổi thư viện là cả hàng khách đứng đợi. Máy không
có bộ đọc thì vẫn còn ô gõ tay.

Vé **sai ngày** thì cảnh báo chứ không chặn (khách tới sớm/trễ một khung là chuyện thường, chặn
cứng thì nhân viên phải gọi quản lý giữa lúc đông nhất). Vé **đã vào** thì chặn hẳn lần hai.

---

## 6. Bảo mật — nói thẳng những gì có và không có

- **PIN gác ở MÁY CHỦ**: PIN đi lên máy chủ, máy chủ giữ dấu băm + muối và phát thẻ phiên 8 giờ;
  mọi việc của nhân viên đều phải mang thẻ. Xem mã nguồn trang không cho ai thêm quyền gì.
- Có hãm dò PIN (10 lượt/10 phút mỗi IP), hãm dò số điện thoại (30 lượt/10 phút), hãm giữ chỗ
  hàng loạt (6 đơn/10 phút mỗi số).
- **Trang khách không bao giờ nhận danh sách đơn** — chỉ nhận số đã đặt của từng khung.
- Khoá cổng tiền đi trên đường dẫn (`?token=`) vì bên gửi webhook không cho đặt header tuỳ ý —
  coi như đã lộ một phần, nên nó **khác PIN** và đổi được dễ.
- **Ai biết mã thiệp thì mở được thiệp ấy** (`#ve=GB-XXXX`). Cố ý: đó là vé của họ, và mã 8 ký tự
  từ bảng 29 chữ là khoảng 5×10¹¹ tổ hợp.

---

## 7. Sửa & phát hành

```bash
# sửa: wordpress/vhcp-nha-ma/vhcp-nha-ma.php  (một file, không có tệp phụ)
php tools/test/kiem-nha-ma.php        # 105 phép thử phần máy chủ
bash tools/build-plugin-zip.sh nha-ma # ra dist/vhcp-nha-ma.zip
```

CI chạy `kiem-nha-ma.php` mỗi lần push (`.github/workflows/plugin-php.yml`).

**Bộ dựng QR (`NHAMA_QR`, `NHAMA_QRVe`) chép nguyên từ `wordpress/vhcp-ghe/includes/`.** Sửa bên
này thì sửa cả bên kia. Hai bản lệch nhau là một bên tem in ra không quét được mà bên kia vẫn
chạy, nên không ai nghĩ tới chuyện so hai tệp.

---

## 8. Còn thiếu / chưa chạy thử

| Việc | Tình trạng |
|---|---|
| Chuyển khoản thật qua mã QR | **CHƯA THỬ** — máy dựng plugin không có app ngân hàng. Phải chuyển thử 1.000đ trước khi mở bán |
| Gửi Zalo | **CHƯA THỬ với OA thật** — cần token của anh Thắng; và ZNS còn phải chờ Zalo duyệt mẫu tin |
| Ảnh biên lai (bill) trên màn Xét duyệt | Chưa có — khách chưa gửi ảnh lên được. Có cổng tiền tự động rồi nên phần này ít cần |
| Hoàn tiền | Chỉ ghi chú "cần hoàn tiền", chưa có luồng hoàn |
| Bản HTML rời `web/ban-ve-nha-ma/` | Vẫn giữ cho ai cần trang chạy độc lập không WordPress. Sổ nằm trong trình duyệt nên **mỗi máy một sổ riêng** — đừng dùng để bán thật |
