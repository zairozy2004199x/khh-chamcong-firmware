# Dựng máy TURN cho tính năng gọi thoại trong app

Không có máy này thì **cuộc gọi vẫn bấm được, vẫn đổ chuông, rồi im lặng và tự tắt** — hỏng đúng
kiểu tệ nhất, vì trông như máy yếu chứ không ai nghĩ là thiếu hạ tầng. Nên plugin **chối mở cuộc
gọi** khi chưa khai TURN, thay vì thử rồi thất bại.

---

## 🔴 Vì sao bắt buộc phải có, không thể bỏ qua

Âm thanh đi **thẳng** giữa hai điện thoại, không qua WordPress. Nhưng hai điện thoại 4G ở Việt
Nam gần như luôn nằm sau NAT của nhà mạng (CGNAT): **cả hai đều không có địa chỉ để bên kia gọi
tới**.

* **STUN** chỉ giúp mỗi máy biết "địa chỉ ngoài của tôi là gì". Đủ cho hai máy cùng wifi cửa
  hàng. **Không đủ** cho CGNAT.
* **TURN** là một máy đứng giữa chuyển gói tin khi hai máy không tự thấy nhau.

Plugin khai **cả hai**, STUN trước. Hai máy cùng wifi sẽ tự nối thẳng và không tốn băng thông
TURN; chỉ khi không nối được mới chạy vòng.

---

## Cần gì

Một VPS nhỏ nhất cũng đủ — **1 vCPU, 1 GB RAM**. Băng thông mới là thứ tốn: một cuộc gọi thoại
đi qua TURN tốn khoảng **50–100 KB/s mỗi chiều**, tức ~**40 MB cho một cuộc 10 phút**. Mười cuộc
cùng lúc vẫn nhẹ với một VPS 5 USD/tháng.

Cần một **tên miền trỏ về VPS**, ví dụ `turn.khmatrix.com`.

---

## 1. Cài coturn

```bash
sudo apt update && sudo apt install -y coturn
sudo sed -i 's/^#TURNSERVER_ENABLED=1/TURNSERVER_ENABLED=1/' /etc/default/coturn
```

## 2. Sinh chuỗi bí mật

```bash
openssl rand -hex 32
```

🔴 **Chuỗi này không bao giờ được đưa vào kho mã** (kho `khh-chamcong-firmware` là kho **công
khai**). Nó chỉ nằm ở hai chỗ: `/etc/turnserver.conf` trên VPS, và `wp-config.php` trên hosting.

## 3. Cấu hình coturn

Sửa `/etc/turnserver.conf`:

```
listening-port=3478
fingerprint
lt-cred-mech
use-auth-secret
static-auth-secret=<CHUỖI_BÍ_MẬT_VỪA_SINH>
realm=turn.khmatrix.com

# 🔴 BẮT BUỘC trên VPS có NAT (AWS, GCP, một số nhà cung cấp VN).
#    Thiếu hai dòng này thì coturn quảng cáo địa chỉ nội bộ 10.x, và điện thoại cố nối vào một
#    địa chỉ không tồn tại — cuộc gọi im lặng chết, log coturn thì sạch bong.
# external-ip=<IP_CÔNG_KHAI>
# listening-ip=<IP_NỘI_BỘ>

# Chặn coturn thành bàn đạp tấn công mạng nội bộ. Bỏ mấy dòng này là mở một cổng
# chuyển tiếp cho người lạ dùng chính máy mình đi quét mạng khác.
no-multicast-peers
denied-peer-ip=10.0.0.0-10.255.255.255
denied-peer-ip=172.16.0.0-172.31.255.255
denied-peer-ip=192.168.0.0-192.168.255.255
denied-peer-ip=169.254.0.0-169.254.255.255
denied-peer-ip=127.0.0.0-127.255.255.255

# Giới hạn để một người không nuốt hết băng thông.
user-quota=12
total-quota=100
```

```bash
sudo systemctl enable --now coturn
sudo systemctl status coturn
```

## 4. Mở cổng

```bash
sudo ufw allow 3478/tcp
sudo ufw allow 3478/udp
sudo ufw allow 49152:65535/udp     # dải cổng chuyển tiếp của coturn
```

⚠️ **Dải UDP 49152–65535 là chỗ hay quên nhất.** Thiếu nó thì coturn nhận được yêu cầu, trả lời
tử tế, rồi không chuyển được gói nào — và triệu chứng y hệt như chưa cài gì.

## 5. Khai vào WordPress

Thêm vào `wp-config.php`, **trên** dòng `/* That's all, stop editing! */`:

```php
define( 'VHCC_TURN_MAY', 'turn.khmatrix.com:3478' );
define( 'VHCC_TURN_BI_MAT', '<CHUỖI_BÍ_MẬT_VỪA_SINH>' );
```

🔴 **Khai ở `wp-config.php`, không khai trong màn quản trị.** Plugin đọc được cả hai (option là
đường dự phòng), nhưng option nằm trong cơ sở dữ liệu — mà bản sao lưu cơ sở dữ liệu thì đi lại
nhiều nơi hơn `wp-config.php` rất nhiều.

---

## Kiểm tra

Mở https://icetest.info hoặc trang "Trickle ICE" của WebRTC, nhập:

* `turn:turn.khmatrix.com:3478`
* username / credential: lấy từ `?viec=goi_ve` của trạm (đăng nhập rồi gọi)

Thấy dòng nào có `typ relay` là **TURN chạy**. Chỉ thấy `srflx` và `host` là chưa.

---

## Cách hệ này dùng nó

Plugin **không gửi chuỗi bí mật xuống điện thoại**. Nó phát cho mỗi người một cặp
tên/mật khẩu **tạm**, tự hết hạn sau 1 giờ:

* tên = `<mốc hết hạn>:<mã NV>`
* mật khẩu = `base64(HMAC-SHA1(tên, chuỗi bí mật))`

Đây đúng là kiểu `use-auth-secret` của coturn. Nhờ vậy một cặp lọt ra ngoài cũng chết sau một
giờ, và không ai mượn được máy TURN của mình làm nơi chuyển tiếp.

---

## ⚠️ Giới hạn còn lại của bản này

**Cả hai người phải đang mở app thì chuông mới đổ.** App hỏi máy chủ 4 giây một lần trong lúc
đang mở; app đóng hẳn thì không có gì đánh thức nó. Muốn đổ chuông khi app đóng thì cần thông
báo đẩy của Google (FCM) — một việc riêng, có phần máy chủ và một tài khoản Firebase đi kèm.
