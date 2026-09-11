# Đơn dự án Kỹ thuật — tạm ứng & quyết toán

Anh Thắng 10/09/2026: *"Trong 1 đơn chứ, trong 1 đơn mà nhiều lệnh tạm ứng, đơn nào bấm xin thì
nó tổng tổng tạm ứng cần xin"*.

Tệp này mô tả luồng đơn của bộ phận **Kỹ thuật** trong app Vận Hành Chi Phí — khác hẳn đơn tuần
của nhân viên cơ sở. Cách cài app xem [CAI-CHI-PHI.md](CAI-CHI-PHI.md).

---

## Điều cốt lõi: một dự án là MỘT đơn

Đây là chỗ dễ hiểu sai nhất, và bản 1.117.0 đã phải làm lại vì hiểu sai nó.

**Một dự án = một đơn.** Trong đơn ấy có **nhiều lệnh tạm ứng** và **nhiều lệnh quyết toán**.
Mỗi lệnh gom mấy hạng mục mà nhân viên tích chọn, và mang **một số tiền** — tổng của chúng.

Bản trước làm mỗi hạng mục lớn thành một đơn riêng. Cái giá: bốn hạng mục là bốn lần duyệt, bốn
lần cấp tiền, **bốn tờ uỷ nhiệm chi** cho một đợt setup. Mười hạng mục thì hai mươi lượt bấm, và
tiền đi thành mười lệnh chuyển khoản.

## Hai hình thức chi, hai đường đi

Bảng hạng mục tự tách làm hai khi trong đơn có cả hai:

| | Ai trả | Đường đi |
|---|---|---|
| 💰 **NV tự trả** | nhân viên ứng tiền đi mua | vào phép tạm ứng & quyết toán (Có 141) |
| 🏢 **Trực tiếp** | kế toán trả thẳng nhà cung cấp | chỉ lên bảng để tổng (Có 331) |

Khoản 🏢 **không** đi qua tạm ứng — nhân viên chẳng xin gì cả. Kế toán tự tích *"đã chi"*, đính
chứng từ, rồi khoá. Anh Thắng: *"người gửi lỡ người kia quên"* — nếu không có nút ấy thì đơn nằm
treo dù tiền đã đi từ lâu.

Chứng từ của đơn 🏢 chỉ cần **ít nhất một** (uỷ nhiệm chi *hoặc* hoá đơn): kế toán hay cầm về uỷ
nhiệm chi trước, hoá đơn nhà cung cấp xuất sau vài hôm. Đơn 💰 thì **vẫn bắt hoá đơn** — uỷ nhiệm
chi ở đó chỉ nói kế toán đã đưa tiền cho nhân viên, không nói họ tiêu vào đâu.

---

## Luồng đầy đủ

```
📝 Nháp
  │  nhân viên tích mấy hạng mục → 📤 Xin tạm ứng          → LỆNH TẠM ỨNG đợt N
Chờ duyệt
  │  quản lý ✔ Duyệt (hoặc ↩ Trả kèm lý do)
Chờ cấp tiền
  │  kế toán 💵 Cấp tiền + MỘT uỷ nhiệm chi cho cả lệnh
Đã cấp tiền
  │  nhân viên 🧾 Chốt xong + hoá đơn (từng hạng mục một)
Đã chốt (khoá)
  │  nhân viên tích mấy hạng mục → 🧾 Gửi quyết toán       → LỆNH QUYẾT TOÁN đợt N
Chờ kế toán chốt sổ
  │  kế toán ✔ Chốt sổ (hoặc ↩ Trả kèm lý do)
✔ Đã quyết toán
```

**Chốt hoá đơn theo từng hạng mục, xin tiền và quyết toán theo lệnh.** Mỗi hạng mục một hoá đơn
riêng, nhưng tiền thì đi theo cả nhóm.

### Chốt an toàn — vì sao có

Mỗi chốt dưới đây có một cách hỏng thật đứng sau nó:

- **Một hạng mục không nằm trong hai lệnh.** Xin hai lần cùng một khoản là tiền ra khỏi két gấp
  đôi cho một việc.
- **Số tiền của lệnh chốt LÚC GỬI**, không tính lại lúc đọc. Tính lại thì nhân viên sửa một dòng
  sau khi gửi là con số quản lý đã duyệt tự đổi sau lưng họ — duyệt 20 triệu, cấp ra 25 triệu.
- **Trả lại thì GỠ số đợt** khỏi hạng mục. Giữ lại là nhân viên sửa xong tích lại sẽ bị chối
  *"đã nằm trong một lệnh rồi"*, mà lệnh ấy đã bị trả — đơn chết cứng, không đường nào gửi lại.
- **Cấp tiền hai lần cho một lệnh bị chối.** Bấm nhầm hai lần là chuyển đi hai lần.
- **Đường xin/duyệt/cấp LẺ từng hạng mục đã bịt hẳn.** Để hở là hạng mục nhảy sang *"xin"* mà
  không thuộc lệnh nào — kế toán không bao giờ thấy nó, nhân viên tưởng đã gửi.
- **Hạng mục đã chốt thì khoá sửa và khoá xoá**, áp cho cả mục con. Anh Thắng: *"Chốt xong bill
  quyết toán thì không cho xoá dòng"*. Mục con mới là chỗ chứa tiền; khoá mỗi hàng cha là hở hẳn
  đường sau. Sửa chặn như xoá — gõ tiền về 0 là xoá trá hình. Kế toán bấm **🔓 Mở lại** thì đụng
  được.

---

## Bốn thẻ số ở đầu trang dự án

Ba trong bốn thẻ đã bị thay vì thẻ cũ không nói được gì (dự toán thường để 0, nên thẻ *"Vượt dự
toán"* luôn báo vượt đúng bằng tổng thực tế).

| Thẻ | Nói gì |
|---|---|
| 🎯 Dự phòng cả dự án | con số ước lượng, **không** phải số tạm ứng |
| Dự kiến tạm ứng tổng đơn | tổng mọi hạng mục 💰 kể cả còn nháp · đã xin · đã chi |
| Tổng thực tế | và phần **đã quyết toán** (đã chốt sổ) |
| 💳 Còn treo trên TK 141 | tạm ứng đã chi − quyết toán đã chốt |

Vài chỗ đếm dễ sai, đã chốt bằng bài kiểm:

- **Dự kiến tạm ứng bỏ khoản 🏢** — tiền ấy không đi đường tạm ứng.
- **Lệnh bị trả lại không tính là "đã xin"** — cộng vào là con số phình lên bởi lệnh không còn
  tồn tại, rồi nhân viên gửi lại là cộng thêm lần nữa.
- **"Đã quyết toán" là ĐÃ CHỐT SỔ, không phải đã gửi.** Gửi rồi mà kế toán chưa đối chiếu thì
  khoản ấy vẫn treo trên 141.
- **Quyết toán vượt số đã ứng** (nhân viên bỏ tiền túi mua thêm) thì nói thẳng *"công ty còn nợ
  nhân viên"*, không in số âm.

### Dải tiến trình

Dự án **không có một trạng thái**. Đơn tuần đi một đường thẳng nên tô sáng được một ô; dự án thì
nhiều hạng mục nằm ở nhiều bước cùng lúc — tô sáng một ô ở đây là nói dối. Nên dải đếm **số hạng
mục** ở từng bước, bước rỗng thì mờ đi.

Chỉ đếm hạng mục **lớn**; hạng mục **bị trả lại** xếp cùng chỗ với nháp (đang chờ nhân viên sửa);
hạng mục đã chốt mà lệnh quyết toán đã chốt sổ thì sang bước cuối, không đếm hai chỗ.

---

## Kế toán nhìn ở đâu

| Tab | Bảng |
|---|---|
| ✅ Duyệt đơn | 💵 **Lệnh tạm ứng theo dự án** — duyệt · cấp tiền (một UNC) · trả |
| ✅ Duyệt đơn | 🏗 **Hạng mục dự án** — chỉ để nhắc chốt hoá đơn |
| 🧾 Quyết toán | 🧾 **Quyết toán theo dự án** — chốt sổ · trả · 10 dòng/trang |

Nút duyệt / cấp tiền / chốt sổ **cũng có ngay trong trang dự án** — kế toán đang mở dự án soi
từng dòng thì bấm tại chỗ, khỏi nhảy sang tab khác rồi tìm lại đúng lệnh ấy giữa danh sách của
mọi dự án. Hai chỗ dùng **chung một hàm dựng nút**; viết hai bộ là hai nơi sẽ lệch nhau.

Bảng quyết toán mặc định lọc **Tất cả** — anh Thắng: *"Đơn đã duyệt quyết toán sẽ nằm đó luôn"*.
Lọc sẵn *"chờ chốt sổ"* thì bấm chốt xong là dòng biến mất, kế toán không còn chỗ tra lại.

---

## Kỳ của đơn cơ sở Kỹ thuật

Anh Thắng: *"quyết toán theo tuần (nhưng không ép buộc tuần nào, khi nào gửi quyết toán thì mới
chốt)"*.

Đợt setup rồi tháo dỡ dài ngắn tuỳ nơi, chẳng nằm gọn trong tuần lịch nào. Nên Kỹ thuật chọn được
**🗓 Khoảng ngày tự chọn** lúc tạo đơn, và nắn lại được bằng nút **🗓 Đặt lại khoảng ngày** trên
trang đơn — cho tới lúc kế toán xác nhận quyết toán.

⚠️ **Nhân viên cơ sở vẫn KHÔNG** được chọn kỳ tự do (chốt của anh Thắng 25/08/2026). Đơn của họ là
đơn xin tạm ứng cho **một tuần vận hành**; nới ra là thừa/thiếu luân chuyển giữa các tuần hết
đường tính.

Chuỗi kỳ (`T9/2026 (7/9-20/9/2026)`) được dựng ở **hai nơi** — trình duyệt lúc tạo đơn và máy chủ
lúc đặt lại. Lệch một dấu gạch là cùng một khoảng ngày rơi vào hai kỳ khác nhau và lọc theo tuần
không gom lại được nữa. Có bài kiểm chạy **thật cả hai** rồi so từng cặp
(`tools/test/kiem-khuon-ky-hai-noi.js`).

---

## Đơn chi phí cơ sở — một đơn nhiều gian

Anh Thắng 11/09/2026: *"tiếp tới chi phí kỹ thuật cơ sở, sẽ giống kiểu chi phí bên POSH, 1 đơn
nhiều cơ sở chung 1 đơn"*.

Trước bản 1.124.0, chi phí cơ sở của Kỹ thuật là **một sổ chung xuyên suốt**: mọi gian, mọi tháng,
mọi khoản dồn vào một chỗ. Chỗ ấy không đóng được, nên cũng không xin tạm ứng hay quyết toán theo
đợt được — đúng thứ vừa dựng xong cho đơn dự án.

Nay ở tab **🏗 Dự án · gian thi công** chỉ còn **một nút ➕ Tạo đơn**. Anh Thắng 11/09/2026:
*"Đối với kỹ thuật 2 cái này gộp lại, khi bấm tạo đơn nó mới xổ ra để chọn chi phí cơ sở hay chi
phí dự án"*. Bấm nút ấy mới xổ ra câu hỏi **"Đơn này là gì?"** với hai nút:

| Bấm | Rồi hỏi tiếp |
|---|---|
| 🏢 **Chi phí cơ sở** | **TUẦN** — chọn trong 8 tuần gần đây |
| 🏗 **Chi phí dự án** | **Loại** (Setup / Tháo dỡ) rồi **GIAN** |

🔴 **Không loại nào được chọn sẵn.** Bản trước bày một ô `<select>` ba mục ngay trên trang; anh
Thắng bảo *"nên hiện cái cuối cùng này là sai"* — và đúng: một ô `<select>` **luôn** có một mục
đang chọn, nên nó nói dối. Người chưa chọn gì vẫn thấy "Chi phí cơ sở" nằm sẵn, bấm Tạo là ra đơn
sai loại mà không ai gõ nhầm chữ nào. Nay chưa bấm một trong hai nút thì không có loại nào, và
bấm **Lập đơn** lúc ấy bị chối kèm câu nói rõ phải chọn cơ sở hay dự án.

Lập xong, khối tạo tự thu lại để không che mất đơn vừa mở.

### Một lối vào, hỏi loại đơn đúng lúc

Anh Thắng 11/09/2026: *"Chọn chi phí tuần, mà hiện bảng chi phí dự án"*, rồi *"nên anh mới cần
gộp nó lại thành 1, chọn xong tự hỏi ra đơn gì tránh lộn"*.

Cặp nút **LOẠI ĐƠN** ở đầu trang (📅 Chi phí · cơ sở / 🏗 Dự án · gian thi công) trông như một bộ
**chọn loại đơn**, nên người ta bấm nó để chọn loại đơn mình sắp lập — trong khi nó chỉ đổi
**trang đang xem**. Bấm xong thấy màn khác hẳn thứ mình định lập, và không có gì nói vì sao. Đã
bỏ hẳn.

Nay việc chọn loại nằm đúng chỗ của nó — lúc bấm tạo đơn — và **hỏi ở đâu cũng ra đủ ba lối**:

| Lối | Sang đâu | Hỏi tiếp |
|---|---|---|
| 📅 **Đơn tuần của cơ sở** | trang đơn tuần | kỳ/tuần + người lập |
| 🏢 **Chi phí cơ sở · Kỹ thuật** | tab Kỹ thuật, mở sẵn nhánh cơ sở | **TUẦN** |
| 🏗 **Chi phí dự án · Kỹ thuật** | tab Kỹ thuật, mở sẵn nhánh dự án | loại + **GIAN** |

🔴 **Tên phải tự phân biệt được.** Hai trong ba đều mang chữ *"cơ sở"* — đúng chỗ bị lộn — nên
mỗi dòng nói rõ **ai lên** và **gom theo gì**. Có bài kiểm canh đủ ba tên và canh không tên nào
là tiền tố của tên nào.

🔴 **Hỏi đúng một lần.** Anh Thắng 11/09/2026: *"Sao lại hỏi lần 2"*. Chọn loại ở hộp
*"＋ Tạo đơn mới"* rồi sang tab Kỹ thuật mà khối tạo bên ấy vẫn bày nguyên hàng *"Đơn này là
gì?"* thì trông như lựa chọn vừa rồi không được ghi nhận, và người ta bấm lại lần nữa. Nay loại
đi **cùng lời gọi** mở khối tạo (`daMoTao('coso'|'duan')`): hàng hỏi ẩn, chỉ còn dòng
**"Đang lập: …"** kèm nút **↺ Đổi loại** cho ai bấm nhầm. Đóng khối là trả hàng hỏi về chỗ cũ
ngay — không thì lần sau mở ra vẫn là dòng "Đang lập", không còn nút nào để chọn loại.

⚠️ **Chiều về cũng vậy.** Bản 1.129.0 vá đúng chiều đi (tab đơn → tab Kỹ thuật) nhưng bỏ sót
chiều ngược: chọn **📅 Đơn tuần của cơ sở** ở khối tạo bên tab Kỹ thuật thì hộp *"＋ Tạo đơn
mới"* bên kia mở lên và hỏi *"Đơn này là loại nào?"* thêm lần nữa — đúng câu vừa trả lời xong.
Nay `daSangDonTuan()` truyền cờ sang `newDon(true)`, và `newDon` chỉ hỏi khi
`_hoiLoaiDon() && !daChonTuan`.

🔴 **Không tự đẻ ra đơn khi chọn loại.** Đơn dự án cần TÊN gian, đơn cơ sở cần TUẦN; tạo bừa là
ra một đơn không ai đặt, rồi phải xoá đi làm lại.

Chuyển qua lại giữa hai danh sách thì để đúng **một nút một chiều** (`data-dcsw-di`), chỉ hiện
khi người ấy thật sự vào được cả hai.

⚠️ `_vaoDuocDuAn()` trước đây dò `style.display` của nút trong thanh vừa bỏ. Dò một nút không
còn tồn tại thì hàm luôn trả `false` — hộp *"Đơn này là loại nào?"* tắt hẳn, không báo gì. Nay
nó tra thẳng bảng quyền `QUYEN_TAB`.

### Chữ trên màn gọi đúng thứ đang mở

Đơn chi phí cơ sở mở ra mà mọi nhãn đều là *"dự án"* — **Đóng dự án**, **Xoá dự án**, *Dự phòng
cả dự án*, *Lịch sử dự án*, khoảng ngày ghi *Setup* — thì người dùng đọc màn và tin rằng mình
đang đứng ở đơn dự án. Bộ máy bên dưới dùng chung là đúng; **chữ thì không**. Nay các nhãn ấy
đổi theo `isCoSo`, và dải tiến trình đếm *"khoản chi"* thay cho *"hạng mục lớn"*.

### Kỹ thuật không thấy tab đơn tuần của cơ sở

Anh Thắng 11/09/2026, chỉ vào màn *"1) Nhập hạng mục xin tạm ứng"* của đơn tuần: *"này của nhân
viên cơ sở, không phải của kỹ thuật, ẩn đi"*, rồi chỉ vào khối 🔧 Chi phí Kỹ thuật: *"Đây mới
chính là chi phí do kỹ thuật lên"*.

Nhân viên bộ phận **Kỹ thuật** nay không còn tab **📅 Chi phí · cơ sở** (`BP_KHONG_DON_COSO`), và
đăng nhập vào thẳng tab Kỹ thuật. Vẫn **mở lại được** bằng ô `donCoSo` ở ⚙️ Cấu hình → 🔑 Phân
quyền chỉnh sửa — một người vừa làm kỹ thuật vừa phụ đơn cơ sở là chuyện có thật.

⚠️ Ẩn tab thì phải đổi **cả trang mặc định**, và kẹp **tab gộp 📋 Đơn chi phí** (nó nhớ loại lần
trước bằng `localStorage`) — không thì người Kỹ thuật mở app ra trúng đúng trang vừa bị ẩn, hàng
tab không nút nào sáng, và họ tưởng app hỏng.

### Ô Gian chỉ bày gian của đơn vị mình

Anh Thắng 11/09/2026: *"Thêm đơn vị KVC để tách ra được không. Vì để bên K&H vẫn thấy bên
Posh"*, kèm ảnh ô **Gian / cơ sở** của đơn Kỹ thuật xổ ra cả *"POSH MN CGV VINCOM LANDMARK"*.

Gốc **không** phải thiếu đơn vị KVC, mà là ba màn nhập (Kỹ thuật · Marketing · Công tác/Setup)
lấy **thẳng toàn bộ danh mục cơ sở**, không qua lớp tách đơn vị. Cả bộ máy `VHCP_DonVi` dựng
công phu (nhà của người · tầm nhìn của vai · đơn vị của từng cơ sở) trở nên vô nghĩa ngay tại ô
người ta gõ hằng ngày — chọn nhầm một gian của bên kia là dòng chi rơi sang sổ của họ.

Nay cả ba màn đi qua `VHCP_DonVi::coso_xem_duoc()`.

⚠️ Hàm ấy trả `null` nghĩa là **xem cả** (Admin · Quản lý · Kế toán) — lúc ấy phải bày **đủ**,
không phải bày rỗng. Hiểu nhầm `null` thành "không có gì" là ô chọn trống trơn với chính những
người phải soát cả hệ.

⚠️ Người kỹ thuật làm cho **cả hai bên** vẫn chọn được: tích thêm ở ô **Xem đơn vị** (⚙️ Cấu hình
→ bảng Người dùng) là gian của bên ấy hiện ra.

### Ô Gian và ô Loại chi phí

Ô **Gian / cơ sở** của từng dòng nay **xổ ra danh sách cơ sở** (`dl_gian_da`) thay vì ô gõ tay
trơn. Vẫn là `<input>` chứ không phải `<select>` — gian mới chưa có trong danh mục thì phải gõ
được. Nhưng phải gợi ý: mã tài khoản tra theo **mảng** của gian, nên "Gian A" / "GIAN A" / "gian a"
ra ba mã khác nhau trong cùng một đơn.

🔴 **Đơn chi phí cơ sở của Kỹ thuật cũng là "đơn nhiều gian".** Đây là gốc của chuyện *"bổ sung
loại chi phí ( chi phí cơ sở )"*: ô Loại chi phí chỉ xổ ra tháo dỡ / setup. Mã tài khoản tra theo
mảng của gian, mà lúc mới mở form ô Gian còn trống — và nhánh "chưa chọn gian thì gom mã của MỌI
mảng" trong `_tkNoList()` lại gác sau cờ `_donNhieuCoSo()`. Đơn Kỹ thuật không bật cờ ấy nên mọi
loại khai mã theo **ma trận** (đúng là "Chi phí cơ sở": 64166 ở FARM, 64126 ở FZ) bị coi là chưa
có mã và bị ẩn. Chọn gian xong thì nó hiện lại — nên nhìn như lúc có lúc không.

⚠️ Cờ ấy rào bằng `CUR_PAGE==='duan'`: `DA_CUR` sống suốt phiên, không rào thì mở một đơn cơ sở
Kỹ thuật rồi quay về đơn tuần là trang đơn tuần cũng tưởng mình ghép nhiều gian, và ô Cơ sở của
nó nhảy xuống cuối form.

Kèm theo, loại **"Chi phí cơ sở"** được mở thêm cho bộ phận Kỹ thuật (seed `seeded_coso_kythuat_v1`)
— **cộng thêm** vào cột Bộ phận, không ghi đè, và **để yên** khi cột ấy đang trống (trống = dùng
chung cho mọi bộ phận, điền vào là bó nó lại).

Mỗi tuần là **một đơn**, trong đơn ấy **mỗi dòng ghi gian của nó** ở ô *Gian / cơ sở* — một đơn
gom nhiều gian, đúng như đơn bên POSH. Từ đó đơn đi trọn luồng đã mô tả ở trên: tích hạng mục →
xin tạm ứng → kế toán duyệt & cấp tiền → gửi quyết toán → kế toán chốt sổ → đóng đơn.

🔴 **Tuần đi cùng lời gọi tạo, không phải lời gọi thứ hai.** Tạo đơn xong rồi mới gọi tiếp
`datKyDuAn` là hai lượt mạng cho một việc: lượt sau hỏng (rớt mạng, đóng tab) thì đơn nằm đó
**không có tuần**, màn vẫn báo "đã tạo", và không ai biết thiếu cho tới lúc đi tìm đơn của tuần
ấy. Khoảng ngày cũng được kiểm **trước** khi thêm dòng — kiểm sau là ngày ngược thì đơn rác đã
sinh ra rồi, chối cũng muộn.

🔴 **Tên đơn không chứa dấu `/`.** `VHCP_Util::san()` thay mọi ký tự cấm tên sheet
(`[ ] * ? / \ :`) bằng khoảng trắng — di sản từ thời mỗi dự án là một tab Google Sheet. Nên tên
tuần dùng dấu chấm (`Chi phí cơ sở tuần 07.09-13.09.2026`), còn khoảng ngày **thật** lưu riêng ở
kỳ của đơn (`kyDA`) — đó mới là thứ màn và bộ lọc đọc.

Trang đơn có thêm bảng **🏢 Chi phí theo từng cơ sở**: mỗi gian một dòng, kèm dự toán · thực tế ·
chênh lệch, và dòng tổng.

🔴 **Sổ chung cũ vẫn còn nguyên** — nút **🔧 Mở sổ chung (cũ)**. Dữ liệu trong đó đã xuất MISA;
đóng hay xoá nó là khoá mất lối vào của những dòng ấy. Nó là thứ duy nhất **không đổi tên, không
gửi duyệt, không đóng, không xoá**.

🔴 **Phân biệt bằng một vết ghim, không bằng loại.** Cả hai cùng loại `Chi phí cơ sở` — loại là
thứ quyết mã tài khoản và bộ lọc xuất MISA, đổi loại của đơn mới là đổi luôn cách hạch toán mọi
dòng trong nó. Sổ chung là đơn được ghim ở khoá `da_coso_chung`, và khi chưa có vết ghim thì luật
tra ngã về **dự án loại `Chi phí cơ sở` cũ nhất** — đúng thứ bản cũ vẫn chọn, nên cài bản mới lên
sổ đang chạy không đổi chủ sổ chung.

🔴 **Sổ chưa từng có sổ chung thì ghim vết trống `-`.** Không có vết ấy thì đơn theo đợt *đầu
tiên* lại chính là dự án `Chi phí cơ sở` cũ nhất, và lần tra sau ghim nhầm nó làm sổ chung: đơn
của nhân viên lặng lẽ bất động, không đóng được, không báo gì.

🔴 **Mục con thừa hưởng gian của hạng mục cha.** Nhân viên gõ gian ở hạng mục lớn rồi thôi; không
thừa hưởng thì tiền thật rơi hết vào rổ *"chưa ghi gian"* — đúng chỗ nó nằm trong sổ, sai chỗ
người ta đi tìm.

🔴 **Tổng các gian phải bằng tổng đơn.** Hạng mục có mục con thì tiền nằm ở **con**; gom theo gian
mà cộng cả cha lẫn con là gian nào cũng phình lên. Bảng theo gian lấy số **từ máy chủ**, không
cộng lại ở màn — cộng lại là khai luật ấy lần thứ hai, và lần thứ hai bao giờ cũng lệch.

---

## Đính chứng từ

Cột **Ảnh** và **Hồ sơ** có nút 📎 ngay trên từng dòng — chọn tệp là xong, khỏi mở form sửa. Rê
chuột vào thẻ 📷 / 📁 là ảnh tự phóng to.

Hồ sơ **cộng thêm**, ảnh **thay**: một dòng có nhiều hồ sơ (hợp đồng, biên bản, báo giá) nhưng chỉ
một tấm bill; đính hồ sơ thứ hai mà đè mất cái thứ nhất là mất chứng từ.

Máy chủ có đường riêng đổi **đúng một ô** (`dat_anh_line` / `them_ho_so_line`). Đi qua
`update_line` là ghi lại cả dòng — ô nào màn đọc thiếu thì bị xoá trắng, im lặng.

---

## Nhật ký dự án

Khối 🕘 ở đầu trang. Vết của một dự án nằm ở **bốn kiểu khoá**, phải tra đủ cả bốn:

| Khoá | Loại việc |
|---|---|
| `DA_x` | việc của cả dự án (đặt dự toán, đặt kỳ) |
| `DA_x#12` | việc của một dòng (đính ảnh, đổi trạng thái hạng mục) |
| `DA_x · đợt 2` | việc của một lệnh tạm ứng / quyết toán |
| *tên dự án* | vết màn ghi (`_log()` gửi tên, không gửi mã) |

⚠️ So tiền tố bằng **`SUBSTR`, không bằng `LIKE`**. Mã dự án có dấu gạch dưới, mà `_` trong LIKE là
ký tự đại diện — không thoát thì hai dự án trộn nhật ký. Thoát bằng `esc_like` thì lại rơi vào chỗ
**MySQL và SQLite cư xử khác nhau** (MySQL mặc định coi `\` là ký tự thoát, SQLite thì không): mã
chạy đúng trên máy thật mà sai trên bài kiểm — kiểu hỏng tệ nhất, vì nó dạy người ta rằng bài
kiểm sai.

---

## Bài kiểm

| Tệp | Lo việc gì |
|---|---|
| `kiem-lenh-tam-ung-du-an.php` | lệnh tạm ứng: gộp, số tiền, phân vai, trả lại |
| `kiem-quyet-toan-du-an.php` | lệnh quyết toán, hai loại lệnh không đè nhau |
| `kiem-don-ncc-ke-toan-tich.php` | đơn 🏢 kế toán trả thẳng NCC |
| `kiem-dinh-tep-dong-du-an.php` | đính tệp, khoá sửa/xoá, nhật ký dự án |
| `kiem-dat-khoang-ky-don.php` | đặt lại khoảng ngày (máy chủ) |
| `kiem-tich-xin-tam-ung.js` | ô tích, thanh tổng, chia lịch, các thẻ số, phân trang |
| `kiem-nut-tam-ung-bang-du-an.js` | nút trên hàng, ô nhập chứng từ, bảng lệnh |
| `kiem-dat-khoang-ky-man.js` | đặt lại khoảng ngày (màn) |
| `kiem-khuon-ky-hai-noi.js` | chuỗi kỳ dựng ở hai nơi phải ra cùng kết quả |
| `kiem-gop-hang-muc.js` | dời mục con sang hạng mục lớn khác |
| `kiem-don-coso-nhieu-gian.php` | đơn chi phí cơ sở: sổ chung vs đơn theo đợt, gom theo gian |
| `kiem-don-coso-man.js` | màn đơn cơ sở: bốn nút quy trình, bảng theo từng gian, luồng tạo đơn |
| `kiem-tab-kythuat-va-gian.js` | ẩn tab đơn tuần với Kỹ thuật · ô Gian xổ danh sách · ô Loại chi phí |
| `kiem-loai-coso-ky-thuat.php` | loại "Chi phí cơ sở" mở thêm cho bộ phận Kỹ thuật |
| `kiem-o-gian-theo-don-vi.php` | ô chọn cơ sở lọc theo đơn vị ở cả ba màn nhập |

Chạy PHP **theo lô** (~12 tệp một lượt) — chạy hết một lần làm tiến trình hết bộ nhớ.
