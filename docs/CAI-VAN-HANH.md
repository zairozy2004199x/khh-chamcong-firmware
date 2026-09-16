# Vận Hành cơ sở — plugin `vhcp-van-hanh`

*Bản 1.0.0. Dựng lại app "Vận Hành Nhà Ma" (trước chạy trên Firebase) thành plugin WordPress.*

| Địa chỉ | Ai dùng |
|---|---|
| `khmatrix.com/van-hanh` | Nhân viên · Thu ngân · Cửa hàng trưởng · Quản lý — đăng nhập bằng **PIN chấm công** |

---

## 1. Cài

1. wp-admin → Plugin → Cài mới → Tải plugin lên → `dist/vhcp-van-hanh.zip` → Kích hoạt.
2. Cài đè `dist/vhcp-trang-chu.zip` (1.7.0) để nút **🏪 Vận Hành Cơ Sở** hiện trên Cổng K&H.

> ⚠️ **Bắt buộc có plugin Chấm Công.** Trang này **không có cửa đăng nhập riêng** — nó mượn PIN,
> sổ nhân sự, vai trò, cơ sở và bảng phiên của `vhcp-cham-cong`. Thiếu nó thì trang nói thẳng ra
> chứ không hiện màn trắng.

Không phải khai gì thêm. Bảng tự dựng lúc kích hoạt.

---

## 2. 🔴 Vì sao dựng lại, chứ không phải vì thích WordPress hơn

Bản Firebase (`index.html`, 6.451 dòng) khoá cửa **bằng JavaScript**:

| Chỗ | Bản Firebase làm gì | Hậu quả |
|---|---|---|
| Danh tính | Đọc thẳng từ `localStorage`, không kiểm lại | Gõ một dòng vào Console là **thành Quản lý**, không cần mật khẩu |
| Mật khẩu | Tải chuỗi băm về máy khách, băm **SHA-256 không muối**, so **trong trình duyệt** | Chuỗi băm ai cũng đọc được; mật khẩu kiểu `mailinh1234.` dò ra trong vài giây |
| Firebase Auth | `signInAnonymously()` | Ai mở trang cũng là "người dùng hợp lệ" |
| Tiền | Cộng ở máy khách rồi **ghi thẳng con số ấy** | Gọi thẳng API khai 500 vé mà tổng thu 0đ — sổ kế toán nói dối đúng con số mang đi đối chiếu ngân hàng |
| Dự phòng khi không có `crypto.subtle` (chạy qua `http://`) | Rơi xuống một hàm băm 32 bit tự chế | Trùng nhau như cơm bữa |

Ở bản này **mọi câu hỏi "anh là ai, được làm gì" đều hỏi máy chủ**. Trình duyệt chỉ giữ một chuỗi
thẻ phiên; sửa biến trong trình duyệt không đổi được gì.

### Không dựng lại sổ nhân sự

`vhcp-cham-cong` đã giữ nhân sự, PIN, vai trò, cơ sở, phiên, chấm công, đơn xin đi muộn — và đang
chạy thật. Plugin này mượn hết. Hai sổ song song thì:

- nghỉ việc một người phải nhớ xoá hai nơi — quên một nơi là người đã nghỉ vẫn vào được;
- đổi PIN một nơi không đổi nơi kia, nhân viên gõ PIN mới vào trang cũ bị chối mà không hiểu;
- hai danh sách cơ sở lệch tên nhau, báo cáo cộng theo cơ sở ra hai kết quả khác nhau.

---

## 3. Vai trò

Vai trò bên chấm công tự đổi sang vai ở đây:

| Bên chấm công | Ở đây | Làm được gì |
|---|---|---|
| Admin · Quản lý | `quan_ly` | Mọi cơ sở · duyệt · **mở lại báo cáo đã chốt** |
| Cửa hàng trưởng | `cua_hang_truong` | Cơ sở của mình · ghi và duyệt doanh thu |
| Kế toán cá nhân · Kế toán NCC | `thu_ngan` | Cơ sở của mình · ghi doanh thu (phải chờ duyệt) |
| Nhân viên | `nhan_vien` | Xem tổng quan · báo sự cố |

> 🔴 **Vai lạ rơi xuống `nhan_vien`, không phải `quan_ly`.** Gõ sai chính tả một vai trò bên chấm
> công mà lại rơi vào nhánh quyền cao là cả doanh thu mở toang, và không có câu báo nào.

> 🔴 **Chỉ Quản lý xem được mọi cơ sở.** Cửa hàng trưởng cơ sở A mở sổ tiền cơ sở B là chuyện
> không ai muốn giải thích. Chặn này nằm **trong câu SQL**, không phải ở trình duyệt — ẩn cái ô
> chọn cơ sở đi thì gọi thẳng API là qua.

---

## 4. Doanh thu & Chi phí

Một cơ sở · một ngày · **một** bản ghi. Khoá duy nhất `(coso, ngay)` nằm ở **CSDL**: hai người
cùng bấm Lưu lúc giao ca thì người sau ghi đè người trước, ra đúng một dòng. Chặn bằng PHP thôi
("đã có chưa rồi mới ghi") thì hai lượt song song đều thấy "chưa có" rồi cùng chèn — hai dòng cho
một ngày, và mọi báo cáo tháng từ đó **gấp đôi**.

**Trang chỉ gửi SỐ LƯỢNG vé.** Đơn giá và mọi phép cộng làm ở máy chủ. Gửi kèm `tong_thu` là bị
bỏ qua — có phép thử canh đúng chuyện đó.

Hai luật đếm dễ sai:

- **Vé online không vào doanh thu cơ sở** — tiền ấy khách trả cho sàn, cộng vào là cuối tháng đối
  soát thiếu tiền mặt mà không ai hiểu vì sao. Nhưng **vẫn tính là lượt khách**.
- **Bùa chú và trích cam không tính là lượt khách** — món bán thêm cho người đã vào, đếm nữa là
  số khách nhân đôi.

Vòng đời:

```
Thu ngân nộp        -> Chờ duyệt      (người ghi tiền ≠ người duyệt tiền)
CHT/Quản lý ghi     -> Đã duyệt luôn  (họ chính là người duyệt)
CHT bấm DUYỆT       -> Đã duyệt       -> KHOÁ, không sửa được nữa
Quản lý bấm Mở lại  -> Chờ duyệt      (và XOÁ tên người duyệt cũ)
```

> 🔴 **Mở lại thì xoá tên người duyệt cũ.** Giữ lại là màn hình nói "đã duyệt bởi X" trong khi bản
> ghi đang mở cho người khác sửa — X chịu trách nhiệm cho con số họ không ký.

Ba ô *tiền mặt / chuyển khoản / momo* lệch doanh thu thì màn hình **nói ra chứ không chặn**: cuối
ca lệch vài nghìn là chuyện thật, chặn cứng thì người ta bịa số cho khớp, và lúc ấy sổ sạch mà
tiền thì không.

---

## 5. Sự cố

Bắt buộc có **người phụ trách** và **hạn xử lý**. Việc không gắn tên ai thì thành cái danh sách ai
cũng đọc và không ai làm; việc không có hạn thì không bao giờ trễ, nên cũng không bao giờ được
nhắc.

Trễ hạn tính ở **máy chủ** — máy khách có thể sai ngày, và một cái đồng hồ lệch là cả màn hình đỏ
hoặc cả màn hình xanh sai. Hạn **hôm nay** thì chưa tính là trễ.

Ai cũng đóng được sự cố **của cơ sở mình** — người sửa xong cái đèn là người biết nó đã xong; bắt
chờ quản lý bấm thì danh sách lúc nào cũng đỏ và không ai tin nó nữa. Người cơ sở khác thì không,
kể cả khi biết id.

---

## 6. Phép thử

```bash
php tools/test/kiem-van-hanh.php      # 79 — quyền, tiền, khoá duy nhất
node tools/test/bam-thu-van-hanh.js   # 28 — BẤM THẬT trong Chromium
```

Cả hai đã **đột biến ngược** để chắc chúng thật sự cắn:

| Phá cái gì | Trượt |
|---|---|
| Nhận `tong_thu` do trang gửi | 1 |
| Vai lạ → `quan_ly` | 3 |
| Bỏ chặn cơ sở | 14 |
| Thu ngân tự duyệt | 6 |
| Bỏ khoá báo cáo đã duyệt | 1 |
| Bỏ bắt buộc người phụ trách | 3 |
| Trang tự gửi kèm tổng tiền | 1 |
| Bỏ thẻ khỏi header | 1 |
| Vé online cộng vào doanh thu trên màn hình | 3 |

---

## 7. Còn thiếu — bản 1.0.0 mới có 3 trong 16 màn

Đã có: **Tổng quan · Doanh thu & Chi phí · Sự cố**.

Bảng CSDL đã dựng sẵn cho cả những màn chưa làm, nên thêm màn sau này không phải đụng vào sơ đồ:

| Màn | Bảng đã có | Ghi chú |
|---|---|---|
| Checklist đầu/cuối ngày | `checklist` + `danh_muc` | Danh mục 4 khu lấy từ bản cũ |
| Kiểm kho | `kho` + `danh_muc` | Có loại đếm kèm % tình trạng |
| Đánh giá nhân viên | `danh_gia` | Bảng phạt, mức tăng dần theo lần 1/2/3 |
| TikTok | `tiktok` | Thưởng theo bậc lượt xem |
| Trích cam | `trich_cam` | |
| Thông báo | `thong_bao` | |
| Xuất báo cáo | — | |
| Chấm công · Lịch làm · Đổi ca · Xin đi muộn | *(của plugin chấm công)* | **Không dựng lại** — nối sang plugin chấm công |

Chưa chạy thử trên hosting thật: bản này mới chạy trong bộ thử và trong Chromium ở máy dựng.
