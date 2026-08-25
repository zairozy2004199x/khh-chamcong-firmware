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

---

## Tệp `CS_VP_KHHCM_1` — "cơ sở này chưa nạp được" (25/08/2026)

Anh Thắng gửi tệp này kèm câu trên. Chạy bộ đọc trên chính tệp đó:

```
so hang : 50
so khoi : 2        (tháng 7 · tháng 8)
so luot : 722      (374 + 348)
trung   : 0
```

**Tệp đọc được.** Không có chỗ nào chặn ở bước đọc. Nhưng nó lòi ra bốn chỗ hỏng trong
**dữ liệu nguồn** mà bản đầu nuốt im lặng — và đó mới là thứ đáng sợ, vì im lặng nghĩa là
mấy tháng nay không ai biết.

| # | Chỗ hỏng | Ví dụ thật | Hậu quả nếu để im |
|---|---|---|---|
| 1 | Ô Giờ Vào có **hai mốc** | `08:30 13:23:38` — Huỳnh Thị Ngọc Nhiên, 01/08 | `giay()` khớp cả chuỗi nên trả `null` → **mất trắng cả buổi làm**, không lỗi nào hiện |
| 2 | Mã NV là **số trần** | `1` · `2` · `15` · `17` · `24` | Đụng nhau giữa các cơ sở, không nối được với sheet nhân viên |
| 3 | **Một người hai mã** | Nguyễn Hữu Thọ: `2` và `15`, cùng khối tháng 8 | Công bị chia thành hai người → bảng lương trả thiếu mà nhìn vẫn bình thường |
| 4 | Người có trong CS mà **không có trong sheet NV** | 22/23 mã | Bảng công có người "không rõ là ai" |

### Đã sửa

* `moc_gio()` — đọc **mọi** mốc giờ trong một ô, rồi lấy sớm nhất làm giờ vào và muộn nhất
  làm giờ ra. Đúng bằng định nghĩa của hai ô đó, nên ô sạch cho kết quả **y hệt** cách cũ,
  còn ô bẩn thì không mất dữ liệu nữa.
* Kênh **cảnh báo** đi kèm bước *Xem trước* (và cả sau khi nạp): bảng vàng liệt kê đúng từng
  chỗ cần sửa trong Sheet gốc. Vàng chứ không đỏ — tệp vẫn nạp được.
* 🔴 Cảnh báo **chỉ báo, tuyệt đối không tự gộp**. Gộp hai mã thành một người phải khai qua
  sheet `MaSongSong`, đúng luật đã ghi ở trên: tên người Việt trùng rất nhiều, đoán sai là
  gộp lương hai người khác nhau.

Chạy thử lại cho thấy **`CS_TUTU_TP` cũng không sạch** — 3 chỗ nằm im từ đầu: mã `1`
(Huỳnh Quang Thắng), mã `855146747` (số điện thoại lọt vào cột ID, ô tên trống), và
Nguyễn Thành Pháp mang cả `MNNV2KVC0107` lẫn `TUTP05`.

### 🔴 CÒN TREO — tên cơ sở hai hệ, chưa nối được

Đây là chỗ **chưa giải quyết được** và cần anh Thắng chốt:

| Nguồn | Ghi cơ sở kiểu gì | Ví dụ |
|---|---|---|
| tệp `CS_*.csv` (chấm công) | **mã ngắn** lấy từ tên tệp | `TUTU_TP` · `VP_KHHCM_1` |
| cột "Đơn vị làm việc" (sheet NV) | **tên đầy đủ** | `Tutu Train Aeon Mall Tân Phú` · `Funzone ADV SC Vivo` |

Hai hệ tên này **không khớp nhau**, nên hiện chưa nối được người ↔ cơ sở giữa hai bảng.
Riêng `VP_KHHCM_1` (văn phòng) còn không có tên nào tương ứng trong 60 đơn vị của sheet NV.

Cần một **bảng quy đổi mã ngắn ↔ tên đầy đủ**. Tuyệt đối không đoán bằng cách so tên gần
giống — `Posh Go Bà Rịa` và `Posh Go Bạc Liêu` chỉ khác vài chữ.

### Chỗ đã sửa thêm — cột "Đơn vị làm việc" nhiều đơn vị trong một ô

8 dòng trong sheet NV khai nhiều đơn vị trong **cùng một ô**, ngăn bằng dấu phẩy; ô nhiều
nhất có **sáu** đơn vị (HUỲNH THỊ THU THẢO — `MNNV2KVC0173`). Bản đầu giữ nguyên cả chuỗi,
tức đẻ ra một "đơn vị" ảo không có thật và tám người đó không thuộc về cơ sở nào trong số
các cơ sở họ thật sự làm. `tach_don_vi()` tách theo dấu phẩy.

Số người làm nhiều cơ sở vì vậy đổi từ **21 → 29**.
