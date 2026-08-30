# Dự án & Tiến độ — quy trình công việc

Anh Thắng 30/08/2026: *"Sếp nhận hợp đồng, lên phương án, sau đó chốt ngày thi công, mở cửa, và
bàn giao xuống từng bộ phận. Các bộ phận làm và cập nhật tiến độ vào đó, liên kết đến hệ thống
chi phí… Xây dựng trên nền HRM."*

Địa chỉ: **`/du-an`**. Đăng nhập bằng **chính mã PIN chấm công** — không cấp tài khoản WordPress
cho ai.

---

## Bảy chặng

```
Nhận hợp đồng → Lên phương án → Chốt ngày thi công → Bàn giao bộ phận
              → Đang thi công → Mở cửa → Xong · nghiệm thu
```

**Đi tới thì từng bước một.** Nhảy cóc bị chặn, và câu chối kể tên đúng mấy chặng đang bị bỏ
qua — "không hợp lệ" thì người dùng không biết mình thiếu bước nào.

**Lùi thì lùi xa được.** Khách đổi ý, chốt ngày rồi phải quay lại phương án — chuyện thường.
Bắt lùi từng bước là bắt bấm bốn lần cho một việc, rồi họ sẽ đi sửa thẳng cơ sở dữ liệu.

**Huỷ đứng ngoài dãy.** Từ chặng nào cũng huỷ được, và mở lại thì quay về **đúng chặng đang dở**
chứ không về đầu. Chỉ Admin huỷ được.

Hai chốt cứng, cả hai đều để chặn một cái sai im lặng:
- **Chưa chốt ngày thì chưa bàn giao được.** Bộ phận nhận việc mà không có ngày thi công thì
  không xếp được người, và họ sẽ đi hỏi mồm — đúng thứ hệ này định bỏ.
- **Ngày mở cửa không được trước ngày thi công**, và **hạn của bộ phận không được muộn hơn ngày
  mở cửa**. Quán mở rồi thì việc ấy đã trễ, không phải "đúng hạn".

---

## Ai làm được gì

Đo bằng đúng thang vai của hệ nhân sự (`VHCC_Vai`), không dựng thang thứ hai.

| Việc | Cần vai từ |
|---|---|
| Xem dự án | Nhân viên |
| **Cập nhật tiến độ bộ phận mình** | **Nhân viên** |
| Lập dự án · sửa hợp đồng · phương án | Quản lý |
| Chuyển chặng · chốt ngày · bàn giao · gán đơn chi phí | Quản lý |
| Huỷ dự án | Admin |

**Cập nhật tiến độ là việc của người làm, không phải của người quản.** Siết lên bậc cao hơn thì
người thật sự làm việc không báo được, và tiến độ sẽ do quản lý gõ hộ theo trí nhớ — lúc ấy cả
hệ này chỉ còn là một bảng số cho đẹp.

Nhưng **chỉ bộ phận mình**: bên Kỹ thuật sửa được tiến độ của bên Marketing là con số ấy hết
nghĩa, vì ai cũng vào được thì không ai chịu trách nhiệm về nó nữa. Quản lý trở lên vẫn sửa được
mọi bộ phận — người trực tiếp làm có thể đang nghỉ.

---

## 🔴 Hai khái niệm "bộ phận" — đọc kỹ trước khi sửa

Hệ đang có **hai** thứ cùng tên mà khác hẳn nhau. Em sập bẫy này lúc dựng:

**1. Bộ phận TÍNH LƯƠNG** (`VHCC_Luong::BP_DS`) — đúng bốn cái: *Máy tự động · Khu vui chơi ·
Văn phòng · Part time*. Gắn với **cơ sở**, sinh ra để chọn cách tính công. "Kỹ thuật" hay
"Marketing" **không** có trong đó.

**2. Bộ phận CÔNG VIỆC** — *Kỹ thuật, Marketing, Vận hành…* khai ở bảng người dùng của plugin
Chi phí, gắn với **từng người**. Đây mới là thứ anh nói khi bảo "bàn giao xuống từng bộ phận".

Nên `VHDA_Quyen::bo_phan_cua()` hỏi **bảng người dùng bên Chi phí trước**, chỉ khi không có ở đó
mới rơi về bộ phận theo cơ sở. Hỏi nhầm cái thứ nhất thì nó chạy, không báo lỗi gì, và trả về
"Chưa xếp" cho mọi người — tức cả công ty mất quyền cập nhật tiến độ, mà câu chối lại đổ cho hồ
sơ chưa khai.

**Muốn ai cập nhật được tiến độ thì khai bộ phận cho họ ở** *Vận hành chi phí → Cấu hình →
Người dùng → cột Bộ phận*.

---

## Nối với hệ chi phí

Mỗi dự án gán được các đơn chi phí của nó. Màn dự án hiện **Tạm ứng · Đã chi · Còn lại**, và so
với giá trị hợp đồng (có cờ ⚠ khi vượt).

**Bảng dự án chỉ giữ MÃ ĐƠN, không chép tiền sang.** Chép số tiền là hai kho cùng giữ một con
số; đơn được sửa, được duyệt lại, được tất toán — mỗi lần như thế bản chép lại lệch thêm một ít,
và tới lúc hai màn hình nói hai con số thì không ai biết kho nào đúng.

Ba chỗ nói thẳng thay vì im lặng:
- **Chưa cài plugin Chi phí** → nói "chưa gom được số tiền", **không hiện số 0**. Số 0 trông y
  như "dự án chưa tiêu đồng nào".
- **Gán mã đơn không có thật** → chối ngay. Gán bừa một mã gõ nhầm thì tổng thiếu đúng phần của
  đơn thật mà không ai để ý.
- **Đơn đã gán mà bên kia không còn thấy** → kêu ra, kèm mã, để người ta gỡ.

---

## Cài

```
wp-admin → Plugin → Cài mới → Tải plugin lên → vhcp-du-an.zip → Kích hoạt
```

Cài độc lập với ba plugin kia, nhưng:
- thiếu **Chấm công** → không đăng nhập được (trang nói thẳng, không trắng trang);
- thiếu **Chi phí** → khối tiền nói "chưa gom được";
- thiếu **Ghế** → mất mấy dòng chân trang, thế thôi.

Vào không được `/du-an` sau khi cài: vào **Cài đặt → Đường dẫn tĩnh** bấm **Lưu** một cái.

---

## Bài kiểm

`tools/test/kiem-du-an.php` — 113 phép. Bài này canh nặng nhất vào **chỗ hệ TỪ CHỐI**, vì đó mới
là giá trị của một hệ quy trình: cho nhảy cóc, cho bàn giao khi chưa có ngày, cho bên này sửa
tiến độ bên kia — thì nó chỉ còn là một cái bảng ghi chép, và người ta sẽ quay lại hỏi nhau qua
điện thoại như cũ.
