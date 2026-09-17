# Bốn việc mới trên trang chấm công online

Bản **Chấm Công 4.16.0** + **App Chấm Công 1.1.0**.

Hai việc chạy ngay sau khi cập nhật, hai việc phải **khai ở Quản trị** mới chạy. Trang này nói
đúng chỗ bấm, và nói cả những gì bộ mới **cố ý không làm**.

| Việc | Cần khai gì không | Chỗ khai |
|---|---|---|
| 1. Gác vị trí theo cơ sở | ✅ có | Quản trị → **Cơ sở** → *Vị trí cơ sở* |
| 2. Xin phép đi trễ / đổi lịch | ❌ chạy ngay | — |
| 3. Giữ lượt chấm khi mất mạng | ❌ chạy ngay | — |
| 4. Nhắc chấm công | ✅ có | Cài đặt → **App Chấm Công** |

---

## 1. Gác vị trí theo cơ sở

**Việc nó làm.** Mỗi lượt chấm **bằng điện thoại** tự so khoảng cách với cơ sở. Ở ngoài vùng thì
dán một dòng vào ghi chú của lượt ấy, và nếu cơ sở chọn mức Chặn thì lượt ấy không được ghi.

Trước bản này toạ độ vẫn được ghi đủ, nhưng chỉ là một dòng chữ — muốn biết người đó có đứng ở
cửa hàng thật không thì phải tự chép cặp số ra Google Maps, cho từng lượt, của từng người, của cả
tháng. Nên trên thực tế không ai làm.

### Khai

1. Quản trị → tab **Cơ sở** → cuộn tới khối **📍 Vị trí cơ sở**.
2. Lấy toạ độ: mở **Google Maps trên điện thoại** → nhấn giữ vào cửa hàng → **Chia sẻ → Sao chép
   liên kết** → dán **nguyên cái link** vào ô. Dán cặp số `10.775500, 106.700900` cũng được.
3. Bán kính: để mặc định **150m**. Sàn là 30m — GPS trong nhà hiếm khi chính xác hơn thế.
4. Chế độ: **bắt đầu bằng “Chỉ ghi chú”.** Chạy vài ngày, mở Bảng công đọc cột ghi chú xem có ai
   bị báo “ngoài vùng” oan không, rồi mới chuyển sang **Chặn**.

> ⚠️ Bật Chặn ngay cho cả chuỗi là sáng hôm sau cả cửa hàng không chấm công được mà không ai biết
> vì sao. Bật dần từng cơ sở.

### Bốn điều nó **không bao giờ** làm, kể cả ở mức Chặn

* Không chặn khi máy **không gửi được toạ độ** — cơ sở trong trung tâm thương mại, dưới hầm, lỗi
  nằm ở bê tông chứ không ở người.
* Không chặn khi **sai số GPS thô hơn 2km** — ở mức ấy trình duyệt đoán theo địa chỉ mạng.
* Không chặn khi **sai số còn đủ lớn để che được ranh giới**. Báo “cách 130m ±80m” nghĩa là người
  ấy có thể đang đứng cách 50m.
* Không đụng tới **máy chấm công** ở cửa hàng — máy vốn đứng sẵn tại chỗ và không có GPS.

Lượt bị chặn thì **không có hàng nào được ghi**, chấm lại được ngay khi đứng đúng chỗ.

---

## 2. Xin phép đi trễ / đổi lịch ngay trên trạm

Nhân viên mở trang chấm công → nút **📝 Xin phép** ở thanh trên cùng.

* **Xin phép đi trễ** — cơ sở nào cũng nộp được. Đơn được duyệt thì ô vàng “chấm thiếu giờ” của
  ngày ấy bỏ đi; **số giờ trong ô không đổi**.
* **Xin đổi lịch / xin nghỉ một ngày** — chỉ hiện ở cơ sở **đã bật phân lịch**. Chưa bật thì màn
  nói thẳng là không có lịch nào để đổi.

Đơn về đúng chỗ cũ ở trang quản trị: **Lệnh đi trễ** và **Phân lịch làm**. Không có bảng mới nào.

> ⚠️ Nộp lại đơn đi trễ cho cùng một ngày là **đè lên đơn cũ** và kéo nó về *chờ duyệt* — kể cả
> khi cửa hàng trưởng vừa duyệt xong. Màn có nói câu đó ra mỗi lần nộp lại.

---

## 3. Giữ lượt chấm khi mất mạng

**Chạy ngay, không phải khai gì.**

Chụp ảnh xong, bấm LƯU, rớt mạng → trang hỏi lại máy chủ; hỏi lại cũng không được thì **máy giữ
lấy lượt ấy** và tự gửi khi có sóng. Ô **“Chờ gửi”** hiện ở đầu trang, kèm nút *Thử gửi ngay*.

**Lượt gửi muộn vẫn vào đúng giờ đã bấm**, không phải giờ có sóng lại — vì mỗi lượt mang theo một
*vé giờ* do chính máy chủ phát ra và ký.

Dặn nhân viên đúng một câu: **thấy ô “Chờ gửi” thì ĐỪNG chấm lại.** Chấm lại là hai lượt.

### Giới hạn, nói rõ

* Bộ này cứu lượt đã **mở được trang lúc còn mạng**. Mất mạng từ đầu, chưa từng lấy được giờ máy
  chủ, thì vẫn không chấm được — không có mốc thì không đóng dấu, và đóng dấu bằng giờ điện thoại
  là thứ cả hệ này sinh ra để tránh.
* Giữ quá **12 giờ** thì thôi, máy chủ chối và chỉ sang **chấm bù**. Một lượt của thứ Hai không
  được lặng lẽ chui vào bảng công vào sáng thứ Năm.
* Giữ tối đa **3 lượt** trong máy.

---

## 4. Nhắc chấm công

Tới giờ mà **chưa thấy lượt chấm nào** thì app báo lên điện thoại. Ai đã chấm rồi thì không nhận
gì.

### Khai ở máy chủ

**Cài đặt → App Chấm Công → Nhắc chấm công.**

1. Tích **Bật nhắc**.
2. **Giờ nhắc chấm VÀO** — đặt *trước* giờ vào ca chừng 10 phút. Nhắc đúng lúc đã muộn thì nhắc
   để làm gì.
3. **Giờ nhắc chấm RA** — lời nhắc đáng giá nhất: thiếu một đầu giờ là ngày ấy không tính công.
4. Chọn **ngày trong tuần**, và khai **giờ riêng** cho cơ sở nào mở khác giờ.

> 🔴 **Đặt cron thật ở hosting.** WP-Cron không phải cron — nó chỉ chạy ăn theo một lượt tải trang
> của khách. Website ít khách thì lời nhắc 7h50 đi lúc 9h, hoặc không đi, và trông y như bộ nhắc
> hỏng. Vào cPanel → Cron Jobs, đặt lệnh gọi `wp-cron.php` 5 phút một lượt, rồi thêm
> `define( 'DISABLE_WP_CRON', true );` vào `wp-config.php`. Khối **Tự soát** trên màn Cài đặt có
> dòng canh đúng việc này.

### Nhân viên bật ở máy mình

Mở **app đã cài lên màn hình chính** (không phải trang web thường — iOS không cho web thường đăng
ký thông báo) → dải **“Bật nhắc”** hiện ở cuối màn hình → bấm → cho phép.

> 🔴 **iPhone chỉ hỏi quyền thông báo MỘT LẦN.** Bấm “Không cho phép” thì không có cách nào hỏi
> lại — phải vào *Cài đặt → Chấm Công → Thông báo* của chính iPhone bật tay. Nên dặn nhân viên
> trước khi họ bấm.

Số máy đã đăng ký hiện ngay dưới khối cài đặt.

---

## Chạy bộ thử

```
bash tools/test/chay-het.sh
```

Bốn bài mới, tất cả đã vào CI:

| Bài | Canh gì |
|---|---|
| `kiem-vi-tri.php` | gác vị trí chỉ chặn khi **chắc chắn** ở ngoài, không bao giờ chặn vì máy yếu |
| `kiem-xin-phep.php` | chỉ nộp được đơn **đứng tên chính mình**, và chỉ đọc được đơn của mình |
| `kiem-hang-cho.php` | lượt gửi lại mang giờ của **máy chủ**, và gửi hai lần không đẻ giờ ra giả |
| `kiem-nhac-cham-cong.php` | chữ ký VAPID đúng ở cả **ca bẫy 1/256**, và chỉ nhắc người chưa chấm |
