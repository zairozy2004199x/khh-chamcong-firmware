# App thu tiền — Android

App cho **nhân viên đi thu tiền tại ghế**. Ba việc, không hơn:

1. **Quét QR dán trên ghế** — dùng ké đúng cái tem khách vẫn quét.
2. **Chốt ca** — nhập ① chỉ số trên màn máy đếm tiền, ② tiền mặt đếm được trong ngăn.
3. **Nộp tiền về quầy** — nộp toàn bộ đang cầm, quản lý đếm lại rồi xác nhận.

Không có doanh thu, không có ví khách, không bật/tắt ghế. Đó là việc của vai trò khác.

---

## Vì sao có app riêng, trong khi trang `/ghe` đã làm được việc này

Trang web làm được **khi có sóng**. App thì giữ được lượt chốt ca **khi mất sóng** — và đó là
toàn bộ lý do nó tồn tại:

> Nhân viên mở ngăn ghế, đọc chỉ số, đếm tiền, rồi bấm chốt. Điện thoại báo "không có mạng".
> Ngăn đã đóng, tiền đã cầm trong tay, chỉ số trên màn máy đếm thì đã đi tiếp vì ghế vẫn chạy.
> **Không có cách nào đọc lại con số vừa nhìn thấy.**

Nên app **ghi vào máy trước, đẩy lên máy chủ sau** — luôn luôn, kể cả khi đang có sóng. Không có
nhánh "có mạng thì gửi thẳng": hai nhánh là hai đường, và đường ít chạy hơn là đường hỏng mà
không ai biết.

Mỗi lượt mang một `ma_lan` sinh ngay lúc bấm. Đẩy lại bao nhiêu lần cũng cùng mã, nên máy chủ
nhận ra và **không ghi hai lần** — xem `VHG_Quy::chot()`. Không có nó thì mỗi lần đẩy lại là một
lần chốt mới: chỉ số nhảy hai lần, tiền trên tay cộng đôi, người thu bỗng nợ gấp đôi số đang cầm.

**Nộp tiền thì KHÔNG vào hàng đợi.** Lượt nộp là lúc tiền chuyển tay thật, phải xảy ra khi hai
người đang đứng trước mặt nhau. Đẩy sau là ghi một lần chuyển tay vào lúc không ai chứng kiến.

---

## Dùng chung cổng với trang web

App gọi đúng cổng `/ghe?api=…` mà trang web đang dùng — không có cổng riêng. Nhờ vậy mọi chốt
(phân quyền theo vai trò, cơ sở lấy từ phiên, chống ghi hai lần) áp cho app y hệt, không phải
viết lại dòng nào. Một cổng thứ hai là một bộ luật thứ hai cho cùng những việc đụng tới tiền.

---

## Lấy file cài (.apk)

Đẩy mã lên là GitHub tự dựng — xem `.github/workflows/android.yml`.

* **Nhánh làm việc**: vào tab **Actions** → lần chạy mới nhất → mục **Artifacts** → tải
  `khh-thu-tien-apk`.
* **Nhánh main**: file nằm sẵn ở mục **Releases**, thẻ `apk-thu-tien`.

Bản này **ký bằng khoá gỡ lỗi**: cài được ngay, chạy đầy đủ, nhưng không cập nhật đè lên bản ký
bằng khoá khác được. Đủ cho giai đoạn dùng nội bộ.

### Cài lên máy nhân viên

1. Tải tệp `.apk` về máy.
2. Mở ra — máy hỏi thì cho phép **"cài từ nguồn không rõ"** cho trình duyệt.
3. Mở app: nhập **địa chỉ máy chủ** (`khmatrix.com`) và **PIN** của trang `/ghe`.
4. Địa chỉ và phiên được nhớ lại; phiên sống 30 ngày.

---

## Dựng thử trên máy mình

Cần Android SDK và JDK 17.

```bash
cd android_thu_tien
gradle assembleDebug          # hoặc ./gradlew nếu bạn tự tạo wrapper
# -> app/build/outputs/apk/debug/app-debug.apk
```

Kho này **không giữ tệp `gradle-wrapper.jar`** — một tệp nhị phân không ai đọc được là một tệp
không ai kiểm được. CI cài Gradle qua `gradle/actions/setup-gradle`.

---

## Bố cục mã

| Tệp | Việc |
|---|---|
| `Luu.kt` | Nhớ địa chỉ máy chủ + phiên. Chuẩn hoá địa chỉ người ta gõ vào. |
| `mang/Api.kt` | Gọi cổng `/ghe`. Phân biệt **mất mạng** (gửi lại được) với **máy chủ từ chối** (gửi lại vô ích). |
| `kho/HangDoi.kt` | Hàng đợi ngoại tuyến. Một tệp JSON, ghi qua tệp tạm rồi đổi tên. |
| `kho/DayHangDoi.kt` | Đẩy hàng đợi khi có mạng, qua WorkManager. |
| `Kho.kt` | Trạng thái của cả app + mọi việc đụng tới máy chủ. Một nơi duy nhất. |
| `man/` | Bốn màn: đăng nhập, quỹ, quét QR, chốt ca. |

### Vì sao hàng đợi là một tệp JSON chứ không phải SQLite

Hàng đợi dài nhất là vài chục dòng, sống nhiều nhất vài giờ. Room/SQLite kéo theo trình sinh mã,
phiên bản lược đồ và phép chuyển đổi — ba thứ hỏng được, cho một bài toán mà ghi đè cả tệp là đủ.
Ghi qua tệp tạm rồi đổi tên: mất điện giữa chừng thì tệp cũ còn nguyên, không ra một tệp cụt.
