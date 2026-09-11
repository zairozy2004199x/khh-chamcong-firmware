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

Nay ở tab **🏗 Dự án · gian thi công** có khối xanh **"🏢 Đơn chi phí cơ sở — đợt mới"**: gõ tên
đợt (`Chi phí cơ sở T9-2026`) rồi bấm **➕ Lập đơn chi phí cơ sở**. Mỗi đợt là **một đơn**, trong
đơn ấy **mỗi dòng ghi gian của nó** ở ô *Gian / cơ sở* — một đơn gom nhiều gian, đúng như đơn bên
POSH. Từ đó đơn đi trọn luồng đã mô tả ở trên: tích hạng mục → xin tạm ứng → kế toán duyệt & cấp
tiền → gửi quyết toán → kế toán chốt sổ → đóng đơn.

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
| `kiem-don-coso-man.js` | màn đơn cơ sở: bốn nút quy trình, bảng theo từng gian |

Chạy PHP **theo lô** (~12 tệp một lượt) — chạy hết một lần làm tiến trình hết bộ nhớ.
