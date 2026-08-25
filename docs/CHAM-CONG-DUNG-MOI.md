# Chấm công dựng mới — đặc tả dữ liệu

Anh Thắng chốt 25/08/2026: **viết lại toàn bộ**, cột lấy **đúng như trong Sheet**, nạp dữ liệu
bằng **CSV**, và **mọi thao tác nằm ngoài web** (không dùng trang quản trị WordPress).

Cột dưới đây trích thẳng từ `wordpress/vhcp-cham-cong/goc/Code.gs` chứ không chép tay — sai một
cột là lệch cả hồ sơ, và lệch kiểu này hỏng im lặng.

## Sheet 1 — `NhanVien` · 26 cột

| # | Cột | # | Cột |
|---|---|---|---|
| 1 | Mã NV | 14 | SĐT khẩn cấp |
| 2 | Họ tên | 15 | Chức vụ |
| 3 | Cửa hàng | 16 | Ngày vào làm |
| 4 | PIN máy | 17 | Trạng thái làm việc |
| 5 | photoFileId | 18 | Loại hợp đồng |
| 6 | Trạng thái đồng bộ | 19 | Lương cơ bản |
| 7 | Cập nhật | 20 | Số tài khoản |
| 8 | SĐT | 21 | Ngân hàng |
| 9 | Ngày sinh | 22 | Ảnh CCCD (fileId) |
| 10 | Giới tính | 23 | Hợp đồng (fileId) |
| 11 | CCCD | 24 | Nhiệm vụ |
| 12 | Địa chỉ | 25 | Cơ sở phụ |
| 13 | Người liên hệ khẩn | 26 | PIN đăng nhập |

Cột 1–7 giữ nguyên vì máy chấm công phụ thuộc vào chúng. Cột 8–26 là hồ sơ mở rộng.

⚠️ **Cột 26 `PIN đăng nhập` là mật khẩu đăng nhập web.** Nằm trong sổ nhân sự nghĩa là ai mở
được sổ là đăng nhập được tài khoản người khác. Bản gốc đã ghi rõ anh Thắng biết và vẫn chọn
cách này; dựng mới thì giữ nguyên hành vi nhưng **không hiện cột này ra màn cho vai trò thấp**.

## Sheet 2 — `CS_<cơ sở>` · bảng chấm công, dạng NGANG

Đây không phải bảng phẳng. Một sheet cho mỗi cơ sở, trong đó **mỗi tháng là một khối xếp dọc**,
cách nhau 2 hàng trống:

```
hàng ngày   :  (A trống) (B trống) │  01/08  ·  ·  ·  ·  │  02/08  ·  ·  ·  ·  │ …
hàng tiêu đề:  Họ tên    Mã NV     │  5 cột cho mỗi ngày │ …
hàng NV     :  Nguyễn A  NV001     │  07:58  ảnh  17:30  ảnh  9:32 │ …
```

Năm cột của mỗi ngày, đúng thứ tự:

```
1. Giờ Vào / Checkin
2. Ảnh Checkin
3. Giờ Ra / CheckOut
4. Ảnh Checkout
5. Thời gian trong ngày
```

Ngày đầu tiên luôn bắt đầu ở **cột C**. Cách nhận ra một khối: ô cột C của hàng tiêu đề luôn là
chuỗi `"Giờ Vào / Checkin"`.

### Xác nhận bằng ảnh chụp Sheet thật (25/08/2026)

Ba chi tiết ảnh cho thấy mà đọc mã không ra:

- Cột A tiêu đề là **`Họ và Tên`**, cột B là **`ID`** — KHÔNG phải `Mã NV` như trong `NhanVien`.
  Bộ nạp phải nhận cả hai tên, đừng khớp cứng một chuỗi.
- Mã nhân viên dạng dài: `MNNV2KVC0017`. Không phải số, không cắt được, không suy ra cơ sở từ mã.
- Khối tháng 7 và tháng 8 xếp dọc thật, cách nhau vài hàng trống — đúng như mã mô tả, và khối
  đầu có thể **thiếu ô `Họ và Tên`** ở hàng tiêu đề (ảnh cho thấy A1 trống, chỉ B1 có `ID`).
  Nên **không được dựa vào cột A để nhận ra hàng tiêu đề** — chỉ dựa vào cột C bằng
  `"Giờ Vào / Checkin"`, đúng như `Code.gs` đã dặn.

### Các sheet khác trong cùng bảng tính

Thanh tab cho thấy hệ còn nhiều hơn hai sheet đang bàn:

| Nhóm | Sheet |
|---|---|
| Chấm công | `CS_FZ_ADV_AL` · `CS_FF_SC` · `CS_GHOST_BRIDE_BD` — mỗi cơ sở một sheet |
| Nhân sự | `NhanVien` · `NV_POSH_HCM` |
| Gộp mã / quy đổi | `MaSongSong` · `QuyDoiCoSo` |
| Luật tính công | `QuyTacTinhCong` · `VP_NgayCong` · `TangCuong` |
| Cấu hình | `CaiDat` · `DongBoWP` |
| Nhật ký | `NhatKySuaGio` · `NhatKyTraPin` |

Bản dựng mới làm **hai sheet đầu trước** theo đúng yêu cầu. Nhưng ghi ra đây để lúc chốt lương
không ai quên rằng luật tính công nằm ở `QuyTacTinhCong` / `VP_NgayCong` / `TangCuong` — thiếu
chúng thì bảng công lên đủ mà tiền vẫn tính sai.

## Luật giờ vào / giờ ra — giữ nguyên, không được đổi

Ô giờ vào và giờ ra là **cặp [sớm nhất, muộn nhất] của ngày, chỉ NỚI RỘNG, không bao giờ THU
HẸP**. Nhờ vậy nạp lại cả tháng theo thứ tự nào, đứt ở đâu, chạy lại bao nhiêu lần cũng ra một
kết quả — điều kiện bắt buộc để nạp CSV nhiều lần mà không hỏng dữ liệu.

Bốn nhánh:

| Nhánh | Khi nào | Làm gì |
|---|---|---|
| trùng | lượt đã có ở ô vào hoặc ô ra | không đụng |
| vào | chưa có giờ vào | đặt giờ vào |
| giữa | nằm trong khoảng đã phủ | không đụng |
| ra | muộn hơn khoảng | nới giờ ra |
| đảo thứ tự | sớm hơn giờ vào | thành giờ vào mới; giờ vào cũ chỉ tụt xuống làm giờ ra khi ô giờ ra còn TRỐNG |

Ca đêm: trải phẳng trục thời gian (giờ sau nửa đêm + 86400 giây) **trước** khi vào luật trên.
Bản Apps Script phải viết hàm riêng cho ca đêm vì ô sheet giữ chuỗi `'HH:mm:ss'`; ở đây giờ là
**số giây** nên một luật chạy đúng cho cả hai. Một luật thay vì hai — chính điều `Code.gs` tự
cảnh báo: hai bản tính giờ lệch nhau là lệch tiền lương.

## Hai sheet phụ, cần cho đăng nhập và gộp mã

`PhanQuyen` · 6 cột: `PIN` · `Họ tên` · `Vai trò` · `Cửa hàng phụ trách (cách nhau dấu phẩy)` ·
`Mã NV chấm công online` · `Cơ sở chấm công online`

`MaSongSong` · 6 cột: `Mã A` · `Mã B` · `Họ tên` · `Lý do` · `Người khai` · `Tạo lúc`

Mã song song là chuyện có thật: một người có hai mã vì máy cũ chưa nhận lệnh đổi mã.
🔴 Phải **KHAI BÁO**, tuyệt đối không tự suy "hai mã này chắc là một người" từ tên — tên người
Việt trùng rất nhiều, đoán sai là gộp lương hai người khác nhau.
