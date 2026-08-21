# Cài lên Hostinger — làm theo đúng thứ tự

Làm sai thứ tự thì **mất chấm công**, nên đọc hết mục 1 trước khi bấm gì.

---

## 0. Ba tệp cài

| Tệp | Plugin | Cần gì thêm |
|---|---|---|
| `vhcp-cham-cong.zip` | Chấm Công | 1 dòng `wp-config.php` (`VHCC_KHOA_MAY`) + múi giờ + 1 khoá Firebase |
| `vhcp-chi-phi.zip` | Vận Hành Chi Phí | — |
| `vhcp-hop-dong.zip` | Thư Viện Hợp Đồng | dán `cau-noi.gs` + đặt `WEB_KEY` |

Cài: **wp-admin → Plugin → Cài mới → Tải plugin lên → chọn tệp → Cài đặt → Kích hoạt**.
Ba plugin độc lập, cài cái nào trước cũng được.

Trong bản cài **không có** `goc/` và `apps-script/` — hai thư mục đó không chạy gì mà lại
đọc được từ web bằng một địa chỉ đoán ra được. Mấy tệp `.gs` cần dán tay thì lấy trong repo.

---

## 1. Thứ tự bắt buộc cho app Chấm Công

Máy chấm công ngoài cơ sở **đang chạy** qua Apps Script. Việc dưới đây thêm một đường thứ hai
vào MySQL, **không cắt đường cũ**. Làm sai thứ tự là cắt đường cũ trước khi đường mới sống.

### 1.1 · `wp-config.php` — hai dòng

Thêm **trên** dòng `/* That's all, stop editing! */`:

```php
define( 'VHCC_KHOA_MAY',  '…chuỗi ngẫu nhiên dài, ít nhất 32 ký tự…' );
define( 'VHCC_PIN_ADMIN', '…PIN admin của app chấm công…' );
```

- `VHCC_KHOA_MAY` — khoá cổng nhận chấm công. **Chưa có thì cổng ĐÓNG**, mọi lượt bị chối.
  Đặt ở `wp-config.php` chứ không trong cơ sở dữ liệu: bảng cài đặt thì app đọc được,
  mà app thì có màn hình.
- `VHCC_PIN_ADMIN` — để cầu nối gọi được 23 hàm máy/OTA của Apps Script.

> **Đổi PIN `888888` ngay.** Nó là PIN admin mặc định của app gốc, đã nằm trong lịch sử chat,
> và giờ mở được cả màn đẩy firmware toàn chuỗi. Đổi ở **Chấm Công → Phân quyền & PIN**.

### 1.2 · Múi giờ

**Settings → General → Timezone** = `Asia/Ho_Chi_Minh`. Không có hằng nào cho việc này —
plugin dùng luôn múi giờ của WordPress. Đặt sai là **giờ vào/ra lệch cả buổi**, và ca đêm
lệch sang hẳn ngày khác.

### 1.3 · Kích hoạt plugin

Kích hoạt là nó tự dựng **20 bảng**, âm thầm, không có màn nào báo. Chưa có bảng thì mọi màn
đều trống.

Kiểm: **Chấm Công → Cổng nhận từ máy** phải hiện dòng
`✔️ Đã cấu hình khoá VHCC_KHOA_MAY`. Nếu hiện chữ đỏ thì quay lại 1.1.

### 1.4 · Firebase `/cfg/wp` — để máy tự nhận liên kết

Vào Firebase Console → Realtime Database, tạo nhánh `cfg/wp`:

```json
{ "url": "https://khmatrix.com/cham-cong-may", "key": "…đúng VHCC_KHOA_MAY ở trên…" }
```

**Địa chỉ không có dấu `/` ở cuối.** Firmware không đi theo chuyển hướng: WordPress chuyển
hướng để thêm dấu gạch là máy gọi lại bằng `GET` và **mất trọn lượt chấm công** — mà log
vẫn có thể trông như thành công.

Nhờ nhánh này mà **nạp OTA là xong**, không phải gõ tay ở portal từng máy.

### 1.5 · Apps Script — ghi song song

1. Mở project Apps Script của app chấm công → **File → New → Script file**, tên
   `GhiSongSongWP`, dán toàn bộ `wordpress/vhcp-cham-cong/apps-script/ghi-song-song.gs`.
2. **Project Settings → Script properties**, thêm:
   - `WP_URL` = `https://khmatrix.com/cham-cong-may` (không dấu `/` cuối)
   - `WP_KEY` = đúng `VHCC_KHOA_MAY`
3. Chèn **đúng một dòng** vào đầu hàm `doPost` đang có, ngay sau `try {`:
   ```javascript
   wpXepHang(e && e.postData ? e.postData.contents : '');
   ```
   Dòng này không bao giờ ném lỗi, nên không thể làm `doPost` chết.
4. **Deploy → New version.**
5. Chạy tay hàm `wpBatDongBo()` một lần.

Kiểm: chạy `wpTinhTrang()` → phải thấy `daKhaiUrl: true`, `daKhaiKey: true`, `coLich: true`.

### 1.6 · Đợi vài ngày rồi ĐỐI SỐ HÀNG

Chạy `wpDoiSoHang('TUTU_BT', '2026-08')` cho từng cơ sở.

> **Còn dòng `treo` thì ĐỪNG nạp OTA.** Dòng treo là lượt bấm *có* trong sheet mà *không* có
> trong MySQL. Nạp firmware lúc đang lệch là không còn cách nào biết bên nào đúng.

### 1.7 · Nạp OTA — máy nào tiện thì nạp, không cần nạp hết

**Chấm Công → Máy & Firmware → Đẩy cập nhật.** Link phải là link `raw` của nhánh `bin`.

> Link **release** của GitHub trả HTTP 302 rồi chuyển hướng dài ~943 ký tự, mà module 4G chết
> ở khoảng 532 ký tự. Đẩy link đó là **mọi máy 4G không bao giờ tải được** — mất luôn đường sửa
> từ xa, phải đi từng cửa hàng cắm USB. Hệ thống sẽ chặn, nhưng đọc kỹ vẫn hơn.

Máy đã nạp thì đẩy **cả hai** nơi. Máy chưa nạp vẫn chạy nguyên như cũ qua hàng đợi.
Không có mốc "phải nạp hết mới chạy được".

---

## 2. Bật chấm công online

Chấm công online **chạy thẳng** vào MySQL, không qua hàng đợi.

**Hệ quả phải biết trước:** từ lúc bật cho một cơ sở, lượt online không còn vào sheet nữa,
còn lượt máy thì vẫn vào. Nên:

| | Có gì |
|---|---|
| MySQL | lượt máy (sao lại) + lượt online → **đủ** |
| Sheet | chỉ lượt máy → **thiếu lượt online** |

Với cơ sở đã bật, **lương phải tính từ MySQL**.

**Thứ tự an toàn:** bật cho **văn phòng trước** — họ không dùng máy nên sheet của họ không
thiếu gì cả. Cơ sở có cả máy lẫn điện thoại thì bật sau, khi đã đọc lương từ MySQL.

---

## 3. Khai dữ liệu ban đầu

Theo thứ tự này, vì cái sau cần cái trước:

1. **Nhân sự → Bộ phận của cơ sở.** Bộ phận quyết định **công thức lương**. Tên ngoài danh
   sách (`Máy tự động` · `Khu vui chơi` · `Văn phòng`) là cơ sở đó thành *Chưa xếp* và
   **không được tính lương** — hệ thống sẽ từ chối chứ không lặng lẽ đổi.
2. **Nhân sự → hồ sơ.** Nhập tay hoặc dán hàng loạt (có bước xem trước).
3. **Phân quyền & PIN → Cấp PIN.** Sinh PIN 6 số chưa ai dùng, không bao giờ sinh PIN dễ đoán.
4. **Cấu hình lương → đơn giá** (Nhóm Máy Tự Động) và **số ngày công** của từng
   (cơ sở, tháng). Chưa khai số ngày công thì cột Tiền hiện `—`, không đoán 26.
5. **Cấu hình lương → cấu hình công Văn phòng.** Bấm **Xem đối chiếu (KHÔNG lưu)** trước —
   nó chỉ ra chênh bao nhiêu công và bao nhiêu tiền của **từng người**.
6. **Máy & Firmware → Soi lại (sheet → MySQL).** Bắt buộc: nếu hai nơi nói khác nhau về
   "máy này thuộc cơ sở nào" thì cùng một lượt bấm rơi vào **hai cơ sở**, và không có gì tự báo.

---

## 4. Ba việc bảo mật còn treo

| Mức | Việc |
|---|---|
| **Nặng nhất** | **Khoá rule Firebase RTDB.** Nếu đang mở thì ai biết địa chỉ là **ghi được `/ota`** — nạp firmware bất kỳ vào cả chuỗi máy. Nặng hơn mọi thứ khác trong danh sách này. |
| Nặng | Đổi PIN `888888`, và mấy PIN yếu 859624 · 2222 · 4444 · 3333 · 1000 (giờ mở luôn thư viện hợp đồng). |
| Vừa | Lỗ `?data=1` của app hợp đồng. **Chưa vá có chủ ý** — vá trước khi trang web chạy được là có nguy cơ khoá anh ra ngoài. Vá sau khi mục 1 xong. |

---

## 5. Có gì không như ý thì xem đâu

| Triệu chứng | Xem |
|---|---|
| Máy bíp mà web không thấy | **Cổng nhận từ máy** → nhật ký. Mọi lượt bị bỏ đều ghi ở đó, kèm lý do. |
| Cơ sở tự nhiên im | **Bảng chấm công → Thống kê lượt đẩy.** |
| Số công trông sai | **Bảng công & Lương** → cột **Cần soi** (ca lạ · đêm thiếu giờ · đêm thiếu cặp giờ). |
| Lượt bấm không vào cơ sở nào | **Máy & Firmware → Lượt bấm chờ gán.** Máy chưa gán cơ sở vẫn được giữ lượt bấm. |
| Hai bên lệch cơ sở | **Máy & Firmware → Đối chiếu** (ở đầu trang). |
| **Trang đứt giữa, mất nút Lưu** | Nội dung hiện được một nửa rồi tới câu *"Đã xảy ra lỗi nghiêm trọng trên trang web này"* là **lỗi PHP**, không phải anh làm sai. Cài bản mới nhất; còn thì bật `WP_DEBUG_LOG` rồi gửi em dòng cuối của `wp-content/debug.log`. |
| Cổng nhận chối mọi lượt | **Cổng nhận từ máy** — chưa thấy dòng ✔️ xanh thì `VHCC_KHOA_MAY` chưa vào `wp-config.php`. |
| Giờ vào/ra lệch cả buổi | **Settings → General → Timezone** phải là `Asia/Ho_Chi_Minh`. Plugin dùng múi giờ của WordPress, không tự đặt. |

Cổng nhận trả `SUCCESS` cho **cả những gói nó bỏ** — buộc phải vậy, không thì firmware đẩy lại
vô hạn. Nên **nhật ký là chỗ duy nhất** thấy được cái gì đã bị bỏ và vì sao.
