# Chi Phí Cơ Sở / Vận Hành Chi Phí — bản WordPress

App Apps Script `VanHanhChiPhi` (Code.gs 1.619 dòng + Index.html 3.129 dòng) đã được dựng lại
thành **1 plugin WordPress** chạy trực tiếp trên hosting, dữ liệu nằm trong **bảng MySQL riêng**
(`wp_vhcp_*`), không còn phụ thuộc Google Sheet / Google Drive.

- Mã plugin: `wordpress/vhcp-chi-phi/`
- File cài đặt: `dist/vhcp-chi-phi.zip` (tạo lại bằng `bash tools/build-plugin-zip.sh`)

## 1. Cài trong 4 bước

1. Vào **wp-admin → Plugin → Cài mới → Tải plugin lên**, chọn `dist/vhcp-chi-phi.zip`.
2. Bấm **Cài đặt** → **Kích hoạt**.
3. Menu bên trái xuất hiện **Vận Hành Chi Phí** → bấm đường dẫn app (mặc định
   `https://<tên-miền>/chi-phi/`).
4. Đăng nhập **PIN 1111** (vai trò Admin) → vào tab **⚙️ Cấu hình** đổi PIN và khai người dùng.

> Yêu cầu hosting: WordPress ≥ 5.6, PHP ≥ 7.2 (thử trên 8.4), MySQL/MariaDB có quyền `CREATE TABLE`.
> Kích hoạt plugin sẽ tự tạo 13 bảng và nạp cấu hình mặc định (14 cơ sở, 13 nhóm mặt hàng,
> ma trận phân quyền 12 hành động × 4 vai trò).

Nếu mở `/chi-phi/` bị **404**: vào **Vận Hành Chi Phí → Bảo trì → "Kiểm tra lại bảng dữ liệu +
làm mới đường dẫn"** (hoặc **Cài đặt → Đường dẫn tĩnh → Lưu**). Hosting để permalink dạng `?p=`
thì dùng `https://<tên-miền>/?vhcp=app`.

## 2. Đường dẫn & cách nhúng

| Việc | Cách làm |
|---|---|
| Mở app trực tiếp | `https://<tên-miền>/chi-phi/` (đổi được ở **Cài đặt**) |
| Nhúng vào 1 trang WordPress | dán shortcode `[vhcp_app height="900"]` |
| Nhúng vào trang tổng K&H | đặt `CHIPHI_URL = 'https://<tên-miền>/chi-phi/'` trong `AttendanceScript/Index.html` |
| Đăng nhập một lần từ trang tổng | `https://<tên-miền>/chi-phi/?sso=<token>` |

**SSO** dùng đúng thuật toán của app cũ — `base64url(payload).base64url(HMAC-SHA256)` — nên trang
tổng **không phải sửa gì**: chỉ cần điền cùng chuỗi bí mật vào **Vận Hành Chi Phí → Cài đặt →
SSO_SECRET** (giá trị `SSO_SECRET` đang đặt trong Script Properties của app cũ). Bảng phân vai trò
theo email (`CH_SSO`) vẫn hoạt động y như trước.

## 3. Nhập chi phí kiểu mới: chọn loại chi phí rồi nhập (bản 1.1.0)

Tab **💵 Sổ chi phí** làm việc đúng như sheet *Chi phí cơ sở* cũ: **không lập đơn, không tạm ứng,
không quyết toán** — chọn **Loại chi phí** rồi nhập, hết.

Thứ quyết định "đây là chi phí gì" nằm ở **⚙️ Cấu hình → 💵 Loại chi phí**: mỗi loại gắn sẵn
**TK Nợ** (và TK Có / Mã đối tượng nếu muốn). Khi nhập, dòng chi **lưu luôn mã tài khoản** vào
chính nó. Nhờ vậy:

- Chọn loại xong là thấy ngay dòng `Nợ 64127 / Có 141` cạnh ô chọn — không phải đoán.
- Sau này dò lại một con số chỉ cần **đọc cột mã trên dòng**; không phải chạy lại hàm dò ma trận
  *nhóm × phân loại lớn* như luồng đơn.
- Lọc / gom được **theo mã tài khoản**: bảng "Tổng theo loại chi phí & mã tài khoản" ở cuối tab,
  và ô lọc *Tất cả mã TK* ở thanh danh sách.
- Xuất MISA của tab này lấy **thẳng mã trên dòng**, chốt "đã xuất" theo từng dòng (dòng đã xuất bị
  khóa sửa/xóa; Admin bỏ chốt được).

Sửa mã trong danh mục **không** làm đổi mã của dòng đã nhập (số cũ giữ đúng lịch sử). Muốn áp mã
mới cho dòng cũ thì bấm **🔗 Gán mã cho dòng cũ** ở ngay thẻ Loại chi phí — nó điền mã cho cả sổ
chi phí lẫn dòng chi của đơn còn trống, và báo lại loại nào chưa khai mã.

**Luồng đơn vận hành (📝 Nhập đơn) vẫn giữ nguyên** cho việc cần tạm ứng → quyết toán thừa/thiếu.
Chỉ khác: dòng chi của đơn giờ cũng **gắn mã tài khoản ngay khi nhập**, và khi xuất MISA thì
mã trên dòng được ưu tiên; ma trận *nhóm × phân loại lớn* chỉ còn là dự phòng cho dữ liệu cũ.
Ô chọn ở form nhập đơn đã đổi nhãn thành **Loại chi phí** và hiện mã TK bên cạnh.

### Cả 5 mảng đều mang mã — và tab 🔎 Tra theo mã (bản 1.2.0)

Từ bản 1.2.0, **Kỹ thuật · Marketing · Công tác · Setup** cũng có ô **Loại chi phí** trên từng dòng
hạng mục, mã tài khoản gắn ngay lúc nhập và hiện dưới nội dung dòng (`Chi phí tháo dỡ · Nợ 2413 / Có 331`).
Trước đây 3 mảng này **gán cứng trong code** `Nợ 141 / Có 331` (trực tiếp NCC) hoặc `Có 64125`
(tạm ứng NV) — sửa được từ Cấu hình là mục đích của thay đổi này.

> ⚠️ **Nói với kế toán trước khi dùng:** dòng đã gắn loại chi phí sẽ hạch toán theo hình mới —
> **Nợ &lt;tài khoản chi phí&gt; / Có 141 (hoặc 331)** — thay cho hình cũ **Nợ 141 / Có 64125**.
> Dòng **chưa** gắn loại vẫn xuất y như cũ, nên có thể chuyển dần từng mảng, không phải làm một lúc.

Tab **🔎 Tra theo mã** là thứ thay hẳn việc gom: chọn 1 mã (vd `64127`) là ra **mọi khoản chi của
mọi mảng** mang mã đó — 💵 Sổ chi phí · 📝 Đơn vận hành · 🔧 Kỹ thuật · 📣 Marketing · ✈️🛠️ Công tác/Setup —
kèm tổng theo mã · theo mảng · theo kỳ · theo cơ sở, lọc thêm được theo mảng/kỳ/cơ sở/từ khóa, và
**tải Excel**. Mã hiện ở đây **chính là mã đi vào MISA** nên tra ra bao nhiêu thì hạch toán đúng bấy nhiêu.

Màn này còn tự cảnh báo 2 việc còn phải làm, kèm số dòng và số tiền:
- **Chưa gắn mã** — loại chi phí chưa khai TK Nợ ở Cấu hình (dòng đó sẽ không hiện khi tra theo mã).
- **Còn dùng mã cũ** — dòng của 3 mảng chưa chọn loại chi phí, vẫn đang mang `141/64125`.

Nút **🔗 Gán mã cho dòng cũ** (⚙️ Cấu hình → 💵 Loại chi phí) xử lý một lượt cho **cả 5 mảng**:
- Sổ chi phí & dòng chi của đơn: điền mã còn trống theo danh mục.
- **Kỹ thuật: tự suy loại chi phí theo loại dự án** (Tháo dỡ → *Chi phí tháo dỡ*, Setup lắp đặt →
  *Chi phí setup lắp đặt gian hàng mới*, Chi phí cơ sở → *Chi phí cơ sở*) — tên trùng khớp danh mục nên
  không phải đoán.
- Marketing & Công tác/Setup: không suy được (không có manh mối), phải mở tab đó chọn loại cho từng
  dòng; nút này báo lại còn bao nhiêu dòng như vậy.
- Dòng sổ chi phí **đã xuất MISA** thì giữ nguyên, không sửa.

### Vận hành tuần: bỏ gom Kỹ thuật / Công tác / Setup

Tab **📅 Vận hành tuần** trước đây gom 5 mảng vào một con số mỗi cơ sở, nên muốn dò một số là phải
lần lại hàm gom. Từ bản 1.1.0 nó chỉ còn **3 nguồn của mảng vận hành**: 📝 Đơn vận hành ·
💵 Sổ chi phí · 📣 Marketing. Chi phí **Kỹ thuật · Công tác · Setup** không bị kéo vào đây nữa —
mỗi mảng đứng riêng ở tab của nó theo mã tài khoản của nó.

Hai chỗ **vẫn gom** (cố ý, vì là báo cáo tra cứu có chia mục rõ ràng, không phải một cục):
- **📊 Báo cáo 1 gian/cơ sở** — tách sẵn từng mục 🔧 Kỹ thuật · 📣 Marketing · ✈️🛠️ Công tác/Setup ·
  💵 Sổ chi phí · 📝 Đơn vận hành, mục Sổ chi phí có kèm mã TK trên từng dòng.
- **📥 Gom hóa đơn** — hộp thư "đơn đang chờ kế toán" của các mảng, không phải cộng tiền vào vận hành.

Muốn bỏ luôn 2 chỗ đó thì nói một câu, em cắt.

### Một bảng duy nhất cho loại chi phí (bản 1.3.0)

Trong ⚙️ Cấu hình chỉ còn **một** bảng quyết định "chi phí này là chi phí gì":
**🧮 Loại chi phí × Mảng kinh doanh**. Hai bảng cũ (*💵 Loại chi phí* và *📒 TK Nợ theo Nhóm mặt
hàng*) đã bỏ; mọi cột của chúng gộp vào bảng này:

| Cột | Nghĩa |
|---|---|
| Loại chi phí | tên nhân viên thấy khi nhập |
| Loại | mua của **NCC** hay **cá nhân** ứng tiền — lọc ô chọn theo hình thức chi |
| Bộ phận | ai được thấy loại này |
| Tên theo MISA | tên đem đi xuất (trống = dùng tên bên trái) |
| TK Có | trống = tự lấy theo hình thức chi (Tạm ứng NV → 141 · Trực tiếp NCC → 331) |
| Mọi mảng | TK Nợ dùng chung khi mảng nào cũng một mã (`6427`, `811`…) |
| *(các cột sau)* | TK Nợ của **từng mảng kinh doanh** |

Bảng khoá sẵn (🔒) để không sửa nhầm mã hạch toán; bấm để mở. Thêm dòng bằng **＋ Thêm loại**,
hoặc khai nhanh bằng thẻ **🆕 Khai mã chi phí** phía trên.

*Lưu ý:* danh sách **nhóm mặt hàng** của tab 📝 Nhập đơn vẫn còn nguyên trong dữ liệu và vẫn dùng
được, chỉ là không sửa được từ giao diện nữa (nạp lại bằng CSV `CH_Nhom` nếu cần đổi).

### Cùng một loại chi phí, mảng kinh doanh khác thì mã khác (bản 1.3.0)

Hệ thống tài khoản của K&H đặt theo kiểu `641<mảng><hạng mục>`: *Chi phí lương* là `64121` ở
Funzone nhưng `64161` ở Farm; *Chi phí khác* là `64196` ở Event · `64166` ở Farm · `64126` ở
Funzone · `64106` ở TuTu. Vì vậy TK Nợ **không** phải một mã cố định cho mỗi loại chi phí.

Thứ tự chốt TK Nợ cho một dòng chi (dừng ở bước đầu tiên có mã):

1. **Mã gõ tay trên dòng** — đặc thù, ưu tiên cao nhất.
2. **Ma trận `[Loại chi phí] × [Mảng kinh doanh]`** — ⚙️ Cấu hình → 🧮 *TK Nợ theo MA TRẬN*.
   Mảng kinh doanh lấy từ cột **Phân loại lớn** của cơ sở, nên chỉ cần chọn cơ sở là ra mã.
3. **Mã cố định** ở cột TK Nợ của thẻ 💵 *Loại chi phí* — dùng cho loại mảng nào cũng một mã
   (`6423` đồ dùng văn phòng, `6427` dịch vụ mua ngoài, `811` chi phí khác…).
4. **Để trống + báo thiếu.** Không đoán, để không âm thầm hạch toán sai.

Cơ sở của từng mảng: sổ chi phí & đơn vận hành lấy **Cơ sở**, kỹ thuật lấy **Gian**, marketing lấy
**Cơ sở của đơn**, công tác/setup lấy **Địa điểm của đợt**.

**Ô trống trong ma trận = mảng đó không dùng loại này** — và ô chọn *Loại chi phí* khi nhập cũng
**không hiện** loại đó nữa. VD *Chi phí nuôi thú* chỉ hiện khi cơ sở thuộc mảng FARM. Nhân viên
đỡ chọn lộn mảng. Ma trận có nút **🔒 khóa** để không sửa nhầm mã hạch toán khi đang xem.

### Kế toán khai mã: gõ tên + số TK, tích cơ sở áp dụng (bản 1.3.0)

Thẻ **🆕 Khai mã chi phí** (⚙️ Cấu hình, trên cùng) là đường chính:

| Ô | VD |
|---|---|
| Tên gọi chi phí | `Chi phí marketing` |
| Số tài khoản (TK Nợ) | `64196` → hiện luôn `Chi phí khác Event` |
| Tên xuất MISA | trống = lấy tên tài khoản |
| Áp dụng cho | ☑ tích **phân loại lớn** (nhanh nhất) — cần ngoại lệ thì mở phần tích lẻ từng cơ sở |

Bấm **💾 Khai mã**. Từ đó cơ sở đã tích mà nhập `Chi phí marketing` là app tự biết cả ba thứ:
**TK Nợ** · **tên tài khoản nội bộ** (lấy từ hệ thống tài khoản theo mã) · **tên xuất MISA**.

- Tích theo **phân loại lớn** là đường nhanh: mọi cơ sở trong đó, kể cả cơ sở **mở sau này**, đều
  dùng mã này — khỏi khai lại. Chỉ mở link *＋ ngoại lệ: tích lẻ từng cơ sở* khi có cơ sở cần mã
  khác; mã lẻ **thắng** mã của phân loại lớn.
- Cơ sở **chưa khai** *Phân loại lớn* vẫn tích được (nằm ở khung "Chưa khai mảng kinh doanh").
- Thẻ báo trước: áp cho bao nhiêu cơ sở, và cơ sở nào đang có mã cũ sẽ bị thay.
- Mã không có trong hệ thống tài khoản thì cảnh báo nhưng vẫn cho lưu (kế toán có thể vừa mở
  tài khoản mới).

#### Một tên gọi chi phí mà có 2 mã

Có khi cùng "Chi phí marketing" ở cùng một mảng lại phải vào 2 tài khoản (VD `64196` chi phí khác
Event và `64197` hoa hồng Event). Khi đó tích ô **thêm mã nữa (không thay mã cũ)** rồi khai mã thứ
hai. Hệ quả:

- Ô ma trận giữ cả hai mã, hiện là `64196 | 64197`.
- Ô **Loại chi phí** lúc nhập **tách thành từng dòng theo mã**, kèm tên tài khoản:
  `Chi phí marketing · TK 64196 Chi phí khác Event` và `Chi phí marketing · TK 64197 Chi phí hoa
  hồng Event`. Người nhập chọn đúng một.
- App **không tự chọn hộ**: dòng nào nhập mà không chỉ mã (VD nạp CSV cũ) thì để trống và báo
  "thiếu TK Nợ" khi xuất MISA — thà báo còn hơn hạch toán sai âm thầm.
- Muốn gộp lại về một mã: khai lại **không** tích ô "thêm mã nữa" — ô đó chỉ còn mã vừa khai.
- Nút **🔗 Gán mã cho dòng cũ** **không** xóa mã người nhập đã chọn tay: dòng nào đang mang một mã
  còn nằm trong danh sách đã khai thì giữ nguyên mã đó, chỉ điền cho dòng còn trống.

Cách còn lại, đơn giản hơn khi hai mã ứng với hai việc khác nhau: khai luôn **hai tên gọi** riêng
("Chi phí marketing" và "Chi phí hoa hồng"). Nhân viên chọn tên là xong, khỏi phải hiểu mã.

**Nội dung xuất MISA sửa được bất cứ lúc nào** — đổi ô *Tên xuất MISA* (ở thẻ khai mã hoặc cột
*Tên theo MISA* của thẻ 💵 Loại chi phí) là bản xuất đổi theo ngay, còn **mã đã lưu trên dòng chi
không đổi**. Dòng chi đã nhập giữ nguyên mã lúc nhập; muốn áp mã mới cho dòng cũ thì bấm
**🔗 Gán mã cho dòng cũ**.

Hệ thống tài khoản chỉ để app **biết một mã có tên là gì** — nạp cả tài khoản doanh thu cũng
được; nó không quyết định mảng.

### Nạp hàng loạt từ tên tài khoản (tùy chọn, bản 1.3.0)

Cách tạo một loại chi phí cho nhân viên nhập gồm 3 phần: **tên gọi** (nhân viên thấy) ·
**mã TK MISA** · **tên chi phí theo MISA** (diễn giải khi xuất). VD
`Chi phí NVL đồ uống - Mua lẻ` · `6329` · `Giá vốn event`.

Để không gõ tay mã:

1. Nạp file tài khoản của kế toán — làm ở **wp-admin**, không phải trong app:
   **wp-admin → Vận Hành Chi Phí → Nhập dữ liệu** → chọn bảng **`CH_TaiKhoan`** → tải file CSV lên
   (3 cột: *Số hiệu · Tên tài khoản · Tính chất*) → **Nạp dữ liệu**. Nạp xong mọi ô mã trong trang Cấu hình có gợi ý
   `số hiệu · tên tài khoản`; gõ mã không có trong hệ thống thì ô đổi màu đỏ.
2. Thẻ **🧭 Mảng kinh doanh → nhóm tài khoản**: bấm **🔎 Dò từ hệ thống tài khoản** — khỏi gõ.
   App thấy tài khoản cha nào có tài khoản con thì lấy số hiệu làm *Nhóm TK* (`6412`) và bỏ chữ
   "Chi phí" khỏi tên cha làm *Từ khóa* (`Funzone`), rồi điền sẵn cột *Mảng* nếu tên phân loại lớn
   của cơ sở khớp được (`Farm` → `FARM MN`; `Event` → mọi cột có chữ EVENT). Dòng nào cột *Mảng*
   còn trống là app **không dám đoán** (VD `Funzone` không tự khớp `FZ MN`) — chọn tay rồi 💾 Lưu.
3. Bấm **🧩 Ghép vào danh mục**: app bỏ từ khóa mảng khỏi tên tài khoản để ra tên loại chi phí dùng
   chung (`Chi phí lương Funzone` → `Chi phí lương`) rồi đưa số hiệu của từng mảng vào đúng ô ma
   trận. Chạy bao nhiêu lần cũng được: **chỉ thêm mới và điền ô trống**, mã đã sửa tay và loại tự
   thêm không bị đụng.

Loại chi phí vẫn **thêm tay được** bất cứ lúc nào bằng **＋ Thêm loại**.

### Dự án = các dòng chi mang cùng mã dự án (bản 1.4.0)

Trước đây dự án kỹ thuật là một bảng riêng, mỗi dự án một tab. Nay bỏ hẳn cách đó: **dòng chi
của dự án nằm chung trong sổ chi phí**, chỉ khác là mang thêm **Mã dự án / công trình**. Gom theo
mã là ra cả dự án — dự toán, thực chi, còn lại — mà không cần bảng riêng và không phải gom.

Sổ chi phí có thêm 3 ô: **Mã dự án** (trống = chi phí cơ sở) · **Hạng mục lớn** · **Dự toán**.
Thanh lọc có thêm ô chọn dự án; chọn `(khong)` để xem các dòng không thuộc dự án nào. Cuối tab có
bảng **🏗 Tổng theo dự án / công trình**. Xuất MISA ghi mã dự án vào diễn giải để kế toán dò lại.

TK Nợ vẫn lấy như mọi dòng khác: **loại chi phí × mảng kinh doanh của cơ sở**. Dòng dự án không
có luật riêng nào.

**Chống đếm hai lần:** tab dự án của bảng tính cũ ghi hạng mục lớn thành một dòng riêng
("Nhân Công" 12.000.000) rồi liệt kê các dòng con bên dưới cùng thuộc hạng mục đó. Nạp cả hai là
cộng đôi. Nên khi nạp, dòng nào có tên trùng với **hạng mục lớn** của dòng khác thì app **giữ dự
toán, đưa thực chi về 0** — tổng đúng mà vẫn còn ngân sách của hạng mục. Báo cáo nạp có ghi số
dòng bị xử lý như vậy.

**Tab không có cột "Loại chi phí"** (đúng như tab dự án cũ): app lấy **hạng mục lớn** làm loại chi
phí, dòng hạng mục thì lấy chính tên nó. Đúng cách gom của bảng tính cũ, và khai mã cho những tên
đó là xong.

### Một loại đơn, hai cách gom (bản 1.5.0)

Dự án kỹ thuật và đơn xin tạm ứng tuần của cơ sở **là cùng một loại đơn chi phí** — chỉ khác cái
gom: bên cơ sở gom theo **tuần**, bên kỹ thuật gom theo **gian thi công**. Nên hai tab 📝 Nhập đơn
và 🔧 Chi phí Kỹ thuật gộp thành một tab **📋 Đơn chi phí**, có thanh **GOM THEO** để đổi qua lại
(app nhớ lựa chọn lần sau). Loại chi phí, mã tài khoản, xuất MISA vẫn dùng chung như trước.

Dữ liệu vẫn nằm ở hai bảng riêng như cũ — gộp bảng để sau khi nạp xong dữ liệu cũ, để không đụng
vào việc đang làm.

**Dự án chi trực tiếp:** dự án không còn bước xin/duyệt tạm ứng. Chỉ còn 2 trạng thái **Đang làm**
và **Đã đóng**; đang làm là nhập được và đóng được luôn. Bỏ 4 ô tạm ứng/trực tiếp và bảng "Kế toán
chi tiền" ở màn dự án — còn lại **dự toán · thực tế · chênh lệch**. Đơn tuần của cơ sở thì giữ
nguyên luồng duyệt tạm ứng → quyết toán.

## 4. Mang dữ liệu cũ từ Google Sheet sang

Vào **Vận Hành Chi Phí → Nhập dữ liệu**. Với từng tab của bảng tính:
**Tệp → Tải xuống → CSV** rồi tải file lên, chọn đúng "Tab đang nạp".

Nạp theo thứ tự này để không bị lệch khóa:

1. `CH_CoSo`, `CH_Nhom`, `CH_PhanLoai`, `CH_DoiTuong`, `CH_TKNo`, `CH_QR`, `CH_NguoiDung`, `CH_SSO`, `CH_Quyen`,
   `CH_LoaiChiPhi` (danh mục loại chi phí + mã tài khoản — lần đầu plugin tự dựng từ `CH_Nhom` nên
   thường không cần nạp)
2. `DonHang` → `TamUng` → `ChiPhi`
3. `DA_Index` → rồi **từng tab dự án** (chọn dự án ở ô "Dự án / Đợt nhận dòng")
4. `MK_Don` → `MK_Line`
5. `BP_Index` → rồi **từng tab đợt** Công tác/Setup
6. `SoChi` — sổ chi phí phẳng, nếu anh có sẵn bảng chi theo dòng (cột: Ngày · Cơ sở · Loại chi phí ·
   Nội dung · ĐVT · SL · ĐG · Số tiền · Hình thức chi · Thuế suất · VAT · Đối tượng · Ghi chú · Ảnh).
   Mã tài khoản được gắn từ danh mục **ngay khi nạp**, y như nhập tay.
7. `NhatKy` (tùy, chỉ để tra lịch sử)

### Nạp dữ liệu cũ có tự gán mã tài khoản không? — CÓ, ngay khi nạp

| Tab nạp | Mã tài khoản lúc nạp |
|---|---|
| `SoChi` | lấy theo cột **Loại chi phí** trong file |
| `ChiPhi` (dòng chi của đơn) | **tự lấy theo cột "Nhóm mặt hàng"** (= loại chi phí) + phân loại thanh toán |
| `DA_Sheet` (tab dự án kỹ thuật) | cột 14 "Loại chi phí" nếu file có; **trống thì tự suy theo loại dự án** (Tháo dỡ / Setup lắp đặt / Chi phí cơ sở) |
| `MK_Line` (marketing) | cột 13 "Loại chi phí" nếu file có; không có thì để trống → dòng đó giữ mã cũ `141/64125` |
| `BP_Sheet` (Công tác/Setup) | cột 12 "Loại chi phí" nếu file có; không có thì để trống → giữ mã cũ |

Nạp xong, trang Nhập dữ liệu **báo lại ngay số dòng chưa có TK Nợ** (do loại chi phí chưa khai mã,
hoặc file thiếu cột Loại chi phí). Hai trường hợp cần bấm thêm **🔗 Gán mã cho dòng cũ**:

1. **Nạp dữ liệu TRƯỚC khi khai mã** trong ⚙️ Cấu hình → 💵 Loại chi phí. Khai mã xong bấm nút là
   xong (nút chỉ điền chỗ trống, không sửa dòng đã có mã nên số cũ không bị nhảy).
2. **Dữ liệu ghi thẳng vào database** bằng script/SQL, không qua trang Nhập dữ liệu.

Chỗ nào còn thiếu thì tab **🔎 Tra theo mã** hiện rõ: số dòng chưa gắn mã, số tiền, thuộc mảng nào,
loại chi phí nào.

Lưu ý:

- **Ngày tháng đọc kiểu Việt Nam** (ngày trước: `20/08/2026`). Nếu bảng tính đang xuất kiểu Mỹ,
  đổi **Cài đặt bảng tính → Ngôn ngữ/Vùng → Việt Nam** trước khi tải CSV.
- Số có dấu phân cách nghìn (`1.500.000` hoặc `1,500,000`) đều đọc được.
- **Ô trống khác 0**: cột *Thực mua* / *Thuế suất* / *Tạm ứng duyệt* để trống sẽ vào DB là `NULL`
  (nghĩa là "chưa nhập"), đúng như app cũ phân biệt.
- Tick **"Xóa dữ liệu cũ của bảng này trước khi nạp"** nếu nạp lại lần 2 để tránh nhân đôi;
  nạp lại cùng mã đơn / cùng ID dòng thì hệ thống tự ghi đè.
- Tab dự án / tab đợt có 4 dòng đầu là tiêu đề + dải tổng hợp — trình nhập tự bỏ 4 dòng đó.

### Nhanh hơn: nạp cả bảng tính bằng 1 đường link (bản 1.4.0)

Khỏi tải từng tab. Vào **Vận Hành Chi Phí → Nạp từ link Sheet**, dán link bảng tính
(chia sẻ ở mức "Bất kỳ ai có đường liên kết → Người xem" là đủ), tick **Chỉ thử** rồi bấm.
App đọc **thẳng file `.xlsx`** của bảng tính — một lần tải là có giá trị gốc của mọi ô, mọi tab.

Chỉ thử KHÔNG ghi gì vào database, chỉ in báo cáo để soát trước:

| Dòng trên báo cáo | Nghĩa |
|---|---|
| `danh sách tab lấy bằng: file .xlsx` | đang đọc đúng đường tốt nhất |
| `sẽ nạp N dòng vào …` | số dòng THẬT (dòng trắng và đuôi bảng kẻ khung sẵn đã bị cắt) |
| `đọc: so_tien ← Chi phí thực tế / Thành tiền` | app lấy trường nào từ cột nào — soi chỗ này là biết có đọc đúng cột tiền chưa |
| `TỔNG TIỀN sẽ nạp …` | đối chiếu với dòng tổng của tab; lệch là do đọc sai cột, đừng nạp |
| `N dòng ra 0đ` | dòng không có số tiền (dòng tiêu đề nhóm, dòng chưa chi) |
| `Cột app không dùng` / `Dòng mồ côi` | dữ liệu sẽ KHÔNG vào — xem có cần không |

Đối chiếu xong, bỏ tick **Chỉ thử** và bấm lại để nạp thật.

Hai chỗ số liệu từng bị sai, nay đã xử (bản 1.5.1) — nếu thấy lại thì là lỗi khác:

- **Tổng tiền ra số khổng lồ** (kiểu 5.492.887.938.004.800đ): Google xuất `.xlsx` giữ đủ độ chính
  xác float (`2405000.0000000005`), chỗ đọc số lại bỏ dấu chấm như dấu nghìn nên `0,04` thành `4`.
  Giờ chỉ bỏ dấu chấm khi nó đúng là dấu nghìn (nhóm 3 chữ số).
- **Số dòng lớn hơn thực tế** (kiểu 981 dòng cho tab chỉ có 135 dòng): bảng tính kẻ khung sẵn cả
  nghìn dòng nên file `.xlsx` chứa cả dòng trắng; và ô rỗng dạng thẻ tự đóng còn "ăn" giá trị của
  ô kế tiếp làm lệch cột. Cả hai đã cắt/sửa.

Nạp sai vẫn sửa được: **Nạp từ link Sheet → Xóa sạch dữ liệu để nạp lại** (gõ chữ `XOA` để xác nhận)
rồi nạp lại từ đầu. Cấu hình, người dùng và PIN không bị xóa.

### Lỗi 403 "Checking your browser" — tường lửa chặn, không phải lỗi app (bản 1.5.2)

Cloudflare và tường lửa của hosting chặn theo **đường dẫn**: `/wp-json/` và
`/wp-admin/admin-ajax.php` bị trả 403 kèm trang "Checking your browser before accessing",
trong khi trang `/chi-phi/` vẫn mở bình thường. Bấm tab nào cũng ra lỗi đỏ.

App nay tự thử **ba đường** rồi nhớ đường nào đi được:

1. `/wp-json/vhcp/v1/call` — đường chuẩn;
2. `/wp-admin/admin-ajax.php` — khi đường 1 bị chặn;
3. `…/chi-phi/?vhcp_api=1` — chính URL của app. Người dùng vừa mở được trang đó nên
   tường lửa không thể chặn đường này.

Cả ba bị chặn thì lỗi ghi rõ "Máy chủ chặn cả 3 đường gọi" — lúc đó là phải sửa ở tường lửa:

- **Cloudflare**: tắt *Bot Fight Mode*, tắt chế độ *Under Attack*, hoặc thêm
  WAF Custom Rule *Skip* cho `URI Path contains /wp-json/` và
  `URI Path equals /wp-admin/admin-ajax.php`.
- **Plugin bảo mật** (Wordfence / All In One WP Security…): bỏ chặn REST API.

### Số tiền dự án ra thiếu — hai chỗ đã xử (bản 1.5.3)

Màn dự án hiện tổng nhỏ hơn hệ cũ nhiều lần, do hai lỗi khi nạp một bảng tính có
**nhiều tab vào cùng một bảng**:

1. **Tick "Xoá dữ liệu cũ" thì mỗi tab đều xoá.** Cả chục tab dự án đều vào sổ chi phí,
   nên tab sau xoá sạch những gì tab trước vừa nạp — chỉ tab CUỐI còn lại. Nay chỉ tab
   đầu tiên của mỗi bảng xoá, và báo cáo ghi rõ `🗑 đã xoá dữ liệu cũ của … (chỉ 1 lần)`.
2. **Tab nhân bản `(2)` thành một dự án khác.** Công trình dài thì hết chỗ, nhân bản tab
   thành `DA NHÀ MA BÀ RỊA (2)`; đuôi `(2)` bị giữ lại nên tiền tách làm hai dự án, màn dự
   án chỉ thấy một nửa. Nay cùng công trình về cùng một mã, báo cáo ghi
   `🔗 tab nhân bản — gộp vào dự án …`.

Đối chiếu sau khi nạp lại: tổng **Thực tế** của màn dự án phải khớp con số trên hệ cũ.
Còn lệch thì so tiếp `TỔNG TIỀN` từng tab trên báo cáo Chỉ thử với dòng tổng của tab đó.

### Gõ tên tab thiếu chữ "DA" → nạp 0 dòng (bản 1.5.4)

Báo cáo ra `nạp 0 dòng · TỔNG TIỀN 0đ` kèm `⚠ tải theo TÊN tab`: tab thật tên
`DA NHÀ MA BÀ RỊA` mà ô "Tên các tab" gõ `NHÀ MA BÀ RỊA`. Trước đây app so tên **nguyên
văn**, trượt là âm thầm rơi xuống đường tải theo tên — đường làm mất tiêu đề cột số.

Nay app dò tên tab kiểu gần đúng: bỏ dấu, bỏ hoa/thường, thiếu hay thừa chữ `DA ` đều ra.
Gõ tên **không có thật** thì báo thẳng kèm danh sách tên tab trong bảng tính, thay vì nạp
0 dòng rồi im. **Cách chắc nhất: xóa trống ô "Tên các tab"** để nạp hết mọi tab.

### Đối chiếu số tiền dự án với hệ cũ

Màn dự án nay ghi rõ mỗi tổng gồm những gì:

- `Tổng dự toán` → `hạng mục X đ + sổ chi phí Y đ`
- `Tổng thực tế` → `hạng mục X đ + sổ chi phí Y đ (N dòng)`

Lệch với hệ cũ thì nhìn hai phần đó là biết thiếu ở đâu: thiếu bên **sổ chi phí** là tab
chưa nạp đủ (soi lại `TỔNG TIỀN` từng tab trên báo cáo Chỉ thử); thiếu bên **hạng mục** là
bảng hạng mục của dự án chưa có dòng.

### Số tiền ra nhỏ xíu: 564.538.680đ thành 5,65đ (bản 1.5.5)

Google xuất file `.xlsx` ghi **số lớn theo dạng khoa học**: `564.538.680` nằm trong file là
`5.6453868E8`. Bộ đọc số của app bỏ mọi ký tự không phải chữ số và dấu chấm — bỏ luôn chữ
`E` — nên `5.6453868E8` thành `5.64538688`, tức **5,65đ**. Vài ví dụ đã gặp:

| Trong bảng tính | Trong file .xlsx | App đọc ra (sai) |
|---|---|---|
| 564.538.680 | `5.6453868E8` | 5,65 |
| 549.256.680 | `5.4925668E8` | 5,49 |
| 10.800.000 | `1.08E7` | 1,09 |

Nay app nhận dạng số khoa học trước mọi thứ khác (cả `5,6453868E8` kiểu dấu phẩy thập
phân), nên đọc ra đúng số gốc. Đây là **đọc sai giá trị**, không phải đọc thiếu dòng — nạp
lại là số về đúng, không cần sửa gì trong bảng tính.

### Bề rộng trang theo từng máy · cột Tên không còn bị bóp (bản 1.6.0)

- **Màn rộng**: trang giãn tới 1600px (từ 1700px trở lên thì 1800px) thay vì chốt 1250px —
  màn 24" không còn bỏ trống hai bên gần 600px, bảng nhiều cột đọc thẳng khỏi trượt.
- **Điện thoại / máy tính bảng**: lề bóp lại (22px → 10px → 8px), chữ và ô nhập to hơn
  chút cho vừa ngón tay, tiêu đề card và nút của nó xuống dòng riêng.
- **Thanh 14 tab** trên điện thoại trước đây xuống 4–5 dòng, đẩy nội dung tụt hẳn; nay
  **trượt ngang một dòng**.
- **Lưới ô nhập** chia 6 → 3 → 2 → 1 cột theo bề rộng máy.
- Mọi bảng đều nằm trong khung **trượt ngang**: máy hẹp thì kéo ngang, không bóp cột.
- **Bảng Người dùng & Phân quyền**: cột *Tên* trước đây không khai bề rộng nên bị các cột
  khai cứng và ô chọn Cơ sở lấy hết chỗ — còn một vạch, không đọc được tên ai. Nay mỗi cột
  có bề rộng riêng, hẹp hơn thì bảng trượt ngang.

### PIN mang đuôi ".0" thì không đăng nhập được

Bảng tính coi PIN là **số**, nên xuất ra thành `2222.0`, `859624.0`; TK Có thành `141.0`.
PIN kiểu đó không khớp luật 4–8 chữ số → người đó **không đăng nhập được**, mà mở bảng
Người dùng vẫn thấy PIN nằm đó nên không ai nghĩ là lỗi. Nay app cắt đuôi `.0` cả khi
**đọc** (dòng đã nạp lệch tự về đúng, khỏi sửa tay) và khi **lưu**. Số thập phân thật
(`1.5`) không bị cắt.

### Bù trừ luân chuyển kỳ trước: hệ thống tự tính (bản 1.7.0)

Nhân viên **không nhập** ô này nữa. App tự lấy phần dư / thiếu của **kỳ trước của chính
người đó**: kỳ trước còn **dư** thì kỳ này **trừ đi**, kỳ trước **thiếu** thì kỳ này **bù
thêm**. Ô nhập thành ô chỉ để xem, kèm một dòng nói rõ số ở đâu ra
(*"kỳ trước (T8/2026 17/8-23/8) còn DƯ 550.000đ → kỳ này trừ đi"*).

Kỳ trước đã đánh dấu **tất toán** (đã thu/bù xong bằng tiền với kế toán) thì bù trừ về 0 —
không thì cộng hai lần. Số được chốt lại đúng lúc bấm **Gửi kế toán duyệt**.

Máy chủ **bỏ qua** số bù trừ do giao diện gửi lên, không chỉ khoá ô nhập: khoá ô chỉ là lớp
sơn, ai cũng sửa được bằng công cụ của trình duyệt.

### Kế toán vào được Cấu hình (bản 1.7.0)

Khai mã tài khoản, tên MISA, mã đơn vị là việc của kế toán, nên vai trò **Kế toán cá nhân**
và **Kế toán NCC** nay thấy tab ⚙️ Cấu hình và làm được mọi việc trong đó — **trừ tài khoản
Admin**: dòng Admin hiện ra nhưng bị khoá 🔒, và máy chủ chặn lần nữa (không đổi được tên,
PIN, vai trò của Admin, cũng không tự phong mình làm Admin).

### Ô "NV thanh toán" mặc định là chính nhân viên đó

Với vai trò **Nhân viên**, ô *Đối tượng — NV thanh toán* điền sẵn tên họ và gợi ý **chỉ có
tên họ** — không liệt kê tên nhân viên khác, vì chọn lộn là tiền vào tay người khác. Vẫn gõ
tay được tên khác khi thật sự có thay đổi. Kế toán / Quản lý vẫn thấy đủ danh sách vì họ
nhập hộ nhiều người.

### Dọn loại chi phí rác sau khi nạp dữ liệu cũ

Lúc nạp, mỗi tên hạng mục lạ đều được thêm vào danh mục loại chi phí để còn khai mã được.
Phần lớn **không phải loại chi phí** (*"Nguyễn Hữu Thọ, Nguyễn Bá Tuấn"*, *"Cấp Mạng
VNPT"*, *"Vật tư và tiếp khách từ 18-26/7"*) nên ô chọn lúc nhập phình ra vài trăm dòng.

Nay ô chọn lúc nhập **chỉ hiện loại ĐÃ KHAI MÃ trong Cấu hình** — loại chưa khai mã bị ẩn
khỏi ô chọn nhưng vẫn nằm trong bảng ma trận để kế toán khai mã cho nó (trỏ chuột vào ô chọn
sẽ thấy còn bao nhiêu loại đang ẩn). Muốn dọn hẳn: **⚙️ Cấu hình → 🧮 Loại chi phí × Mảng
kinh doanh → 🧹 Dọn loại chưa khai mã**. Loại **đã khai mã** thì giữ lại; dòng chi phí cũ
không bị ảnh hưởng — tên loại vẫn nằm trên từng dòng.

Mốc là **có mã hay không**, không dựa vào ghi chú *"(nạp từ dữ liệu cũ)"*: bảng ma trận trên
giao diện không có cột Ghi chú nên chỉ cần bấm 💾 Lưu bảng đó một lần là dấu đó bay hết —
lọc theo dấu là lọc theo thứ tự người dùng bấm nút. (Bản 1.7.1 cũng giữ lại ghi chú cũ khi
lưu bảng ma trận, để dấu không bị xóa nữa.)

### Nút "Gửi duyệt tạm ứng" luôn ở trong tầm mắt (bản 1.7.1)

Nút này chỉ nằm ở thanh trên cùng của trang đơn, mà trang đơn dài mấy màn hình: nhập xong
hạng mục ở giữa trang thì thanh đã cuộn mất, nhân viên tưởng app không có chỗ gửi. Nay:

- thanh thao tác của trang đơn được **ghim** lại, cuộn tới đâu cũng thấy;
- có thêm **một nút nữa ngay dưới bảng "Các dòng đã nhập"** — nhập xong là thấy nút ngay,
  kèm dòng nhắc *"Nhập đủ hạng mục xin rồi bấm gửi — kế toán sẽ duyệt và cấp tạm ứng."*

### Lỗi 403 báo rõ từng đường gọi

Thông báo lỗi nay ghi từng đường thử và thất bại thế nào (*"/wp-json/: 403 bị chặn ·
admin-ajax.php: 403 bị chặn · URL app: 200 trả về không phải JSON"*) kèm vài chữ đầu máy
chủ trả về. **Cách tự kiểm nhanh**: mở `…/chi-phi/?vhcp_api=1` trên trình duyệt —
ra `{"ok":true}` là cổng còn sống (lỗi nằm ở POST / tường lửa), ra trang app là cổng chưa
chạy (plugin chưa cập nhật). Đường dự phòng cuối nay gửi dạng form thường
(`x-www-form-urlencoded`) chứ không phải multipart, vì tường lửa soi multipart chặt hơn.

### Mỗi đơn một cơ sở (bản 1.8.0)

Đơn tạm ứng là tiền giao cho **một người ở một cơ sở**, rồi đối chiếu thừa/thiếu theo cơ sở
đó. Trộn hai cơ sở vào một đơn thì phần đối chiếu và mã đơn vị lúc xuất MISA không còn quy
được về đâu. Nên:

- Đơn **chưa có dòng nào** → chọn cơ sở nào cũng được; ô chọn có dòng nhắc *"Thêm hạng mục
  đầu tiên là đơn chốt cơ sở X"*.
- Thêm dòng đầu tiên → đơn **chốt** cơ sở đó. Ô chọn cơ sở khoá lại 🔒, ghi rõ *"Đơn này của
  cơ sở X · muốn nhập cơ sở khác thì Tạo đơn mới"*.
- Sửa dòng cũng không đổi được sang cơ sở khác — trừ khi đó là **dòng duy nhất** đang giữ cơ
  sở của đơn (lúc đó đổi là đổi cả đơn, hợp lý).
- Máy chủ chặn lần nữa với câu báo rõ, không chỉ khoá ô chọn.

**Đơn cũ đã nạp từ bảng tính** có thể đang mang nhiều cơ sở — luật này chỉ áp cho lần nhập
mới, không sửa lại dữ liệu cũ, nên số liệu đã đối chiếu không bị xáo.

### Không thấy nút "Gửi duyệt tạm ứng"? App nói luôn vì sao (bản 1.8.1)

Trước đây thiếu điều kiện là khối nút **ẩn luôn** — nhập xong không thấy nút nào, không biết
đơn đã gửi rồi, hay vai trò mình không được gửi, hay còn thiếu gì. Nay khối đó **luôn hiện**:
có nút thì nút, không thì đúng một câu nói rõ đang vướng ở đâu, ví dụ:

- *"Vai trò **Nhân viên** chưa được phép gửi duyệt tạm ứng. Bật quyền **“Sửa số tạm ứng (lúc
  Nháp)”** ở ⚙️ Cấu hình → 🔑 Phân quyền chỉnh sửa."*
- *"✔ Đã gửi rồi — đơn đang chờ quản lý / kế toán duyệt tạm ứng. Không cần bấm gì thêm."*
- *"🔒 Đơn đã xuất MISA — khóa, không sửa được nữa."*

Nguyên nhân hay gặp nhất: bảng **CH_Quyen** nạp từ bảng tính cũ bị **lệch cột** hoặc thiếu
hành động mới, nên một vai trò mất quyền mà không ai biết. Chữa một nút: ⚙️ **Cấu hình → 🔑
Phân quyền chỉnh sửa → ↺ Đặt lại mặc định**, rồi tinh chỉnh lại.

### Thanh trên cùng canh thẳng hàng với nội dung

Thanh xanh tiêu đề và các thanh trắng chạy hết bề rộng màn hình, còn thẻ nội dung thì canh
giữa trong 1600px — nên trên màn rộng, chữ ở thanh nằm sát lề trái mà thẻ bên dưới thụt vào
giữa, nhìn như **lệch trang**. Nay đệm hai bên của thanh được tính bằng đúng lề của khối
nội dung, mọi bề rộng màn hình đều thẳng hàng.

## 5. Khác gì so với bản Apps Script

| Việc | App cũ (Apps Script) | Bản WordPress |
|---|---|---|
| Nơi lưu dữ liệu | Google Sheet | Bảng MySQL `wp_vhcp_*` |
| Ảnh chứng từ / hồ sơ | Google Drive | `wp-content/uploads/vhcp/<Cơ sở>/<Người lập>/` |
| Nút "Mở sheet" ở tab Kỹ thuật / Công tác | mở tab Google Sheet | **đã ẩn** (không còn sheet để mở) |
| Dọn ảnh cũ (`migrateOldImages`) | dời ảnh trong Drive | không cần — lưu đúng cây ngay khi tải lên |
| Giới hạn 6 phút/lệnh của Apps Script | có | không còn |
| Giao diện | Index.html | giữ nguyên, **thêm** tab 💵 Sổ chi phí + thẻ Loại chi phí ở Cấu hình |
| Quyết định "chi phí gì" | dò ma trận nhóm × phân loại lớn lúc xuất MISA | **loại chi phí gắn mã tài khoản**, dòng chi lưu sẵn mã |
| Mã của Kỹ thuật/MKT/Công tác | gán cứng trong code (141 · 331/64125) | khai ở Cấu hình, gắn trên từng dòng |
| Xem chi phí 1 mảng | gom nhiều mảng bằng hàm rồi đọc kết quả | **🔎 Tra theo mã**: 1 mã ra mọi mảng |
| Vận hành tuần | gom 5 mảng vào 1 số/cơ sở | chỉ 3 nguồn vận hành (đơn · sổ chi phí · marketing) |
| Đăng nhập | PIN, API mở cho mọi người có link | PIN + **token phiên** (API chặn nếu không có token) và **hãm 10 lần thử PIN sai / 10 phút** |

Giao diện không bị viết lại: `assets/js/gas-shim.js` dựng lại `google.script.run` và chuyển mỗi
lệnh gọi thành `POST /wp-json/vhcp/v1/call {fn, args}`. Nhờ vậy toàn bộ 102 chỗ gọi backend
trong Index.html chạy y như cũ, và về sau sửa giao diện vẫn theo cách quen.

Toàn bộ nghiệp vụ được dịch nguyên văn, gồm những chỗ dễ sai nhất:

- Tạm ứng "1 cục" cho cả đơn = *Tạm ứng duyệt* (nếu có) hoặc *tổng hạng mục xin + dự phòng + bù trừ kỳ trước*.
- Thực chi mỗi dòng = *Thực mua* nếu đã nhập, ngược lại = *Thành tiền*.
- Dòng NCC hiệu lực = phân loại "Nhà cung cấp" **hoặc** dòng cá nhân bị bỏ tích "CN xử lý".
- Hạng mục xin **khóa sau khi cấp tạm ứng** (chỉ sửa Thực chi); mua thêm ghi vào mục **PHÁT SINH**.
- Kế toán sửa số tiền lúc "Chờ quyết toán" → tự gắn cờ đỏ `[KT sửa]`; đơn bị trả lại → cờ `[Trả lại]` mở khóa cho NV sửa.
- Dự án kỹ thuật: hạng mục lớn mang **dự toán**, thực tế chỉ tính ở **mục con** (không cộng trùng);
  xóa hạng mục lớn thì mục con chuyển thành `(Phát sinh)` chứ không mất tiền; mục con thừa hưởng
  *hình thức chi* của cha.
- Xuất MISA: TK Nợ lấy từ **ma trận nhóm × phân loại lớn** (tên nhóm **gốc**, chưa bỏ đuôi `- NCC`/`- Mua lẻ`),
  TK Có ưu tiên **TK Có của người duyệt tạm ứng** rồi mới đến TK Có của phân loại (141/331);
  các dòng cùng nhóm mặt hàng xếp liền nhau; chốt "đã xuất" theo từng bộ phận CN/NCC.

## 6. Kiểm nghiệm

`tools/test/` có bộ tự kiểm chạy bằng PHP CLI, **không cần WordPress hay MySQL**
(dựng $wpdb tối giản trên SQLite):

```bash
php tools/test/test-flows.php
```

300 phép thử, gồm: vòng đời đơn (nháp → duyệt → cấp → thực chi → quyết toán → xuất MISA), trả lại
đơn, "không dùng" tạm ứng, tách dòng sang cơ sở khác, bỏ tích CN↔NCC, dự án kỹ thuật (cộng trùng
cha/con, xóa hạng mục lớn), Marketing, Công tác/Setup, tổng quan dòng tiền, vận hành theo tuần,
báo cáo 1 gian, cả 4 luồng xuất MISA (kể cả nhánh fallback TK Có), cấu hình + hồi lại, phân quyền,
đổi PIN, nhật ký, tải ảnh/hồ sơ (chặn .php), nhập CSV.

Có cả **nạp dữ liệu cũ tự gán mã** (cả 5 đường nạp: đơn theo nhóm mặt hàng, dự án suy theo loại
dự án, marketing/công tác theo cột tùy chọn, và báo lại số dòng chưa có mã), **mã tài khoản của 3 mảng Kỹ thuật/MKT/Công tác** (dòng có loại → mã danh mục, dòng chưa có →
giữ đúng 141/64125 cũ), **tra theo mã** (1 mã ra nhiều mảng, lọc theo mảng/kỳ/cơ sở/từ khóa, đếm
dòng chưa gắn mã), **gán mã cho dòng cũ** (kỹ thuật suy theo loại dự án), **sổ chi phí** (gắn mã TK theo danh mục, TK Có theo hình thức chi, lọc theo mã tài khoản,
chốt/bỏ chốt đã xuất, gán mã cho dòng cũ, nhập CSV) và **cửa API**: gọi thiếu token → 401, token bịa → 401, hàm lạ → 400, Nhân viên gọi `getUsers`
hay `saveConfig` → 403, Quản lý gọi `deleteDonAdmin` → 403, đăng xuất rồi token hết hiệu lực.

Hai phép thử cuối là lưới an toàn quan trọng nhất: **cả 92 hàm public của Code.gs cũ đều có trong
bảng REST**, và **mọi hàm giao diện gọi đều tồn tại ở backend** — thiếu 1 hàm là bộ test đỏ ngay,
không phải chờ người dùng bấm mới vỡ.

## 7. Hiệu năng: đã cắt hết chỗ đọc lặp

Bản đầu port đúng nguyên văn app cũ nên thừa hưởng luôn kiểu "mỗi dự án 1 lượt đọc" của Apps
Script. Đã sửa xong, và có thước đo hẳn hoi — `tools/test/bench-queries.php` đếm **số lệnh xuống
database** của từng màn hình ở 2 mức dữ liệu (3 bộ và 12 bộ dự án/đợt/đơn), cache cấu hình để
nguội (trường hợp xấu nhất):

| Màn hình | Trước | Sau |
|---|---:|---:|
| Xuất MISA — Kỹ thuật | 56 | **6** |
| Vận hành theo tuần | 41 | **8** |
| Báo cáo 1 gian/cơ sở | 29 | **7** |
| Gom đơn chờ kế toán | 28 | **6** |
| Xuất MISA — Công tác/Setup | 26 | **4** |
| Danh sách dự án kỹ thuật | 24 | **4** |
| Danh sách đợt Công tác/Setup | 24 | **4** |
| Xuất MISA — Đơn vận hành | 21 | **4** |
| Khởi động app (getBootstrap) | 16 | **6** |
| Xuất MISA — Marketing | 15 | **4** |
| Danh sách đơn marketing | 13 | **4** |
| Duyệt quyết toán 5 đơn | 30 | **13** |

Quan trọng hơn con số: **không màn hình nào còn tăng theo số bản ghi** (trước đây 7 màn hình tăng
tuyến tính — 30 dự án là 31 lệnh). Bốn thay đổi chính:

- Dòng hạng mục của **mọi** dự án / đợt đọc trong **1 lệnh** rồi gom trong PHP (`all_with_lines()`),
  thay vì mỗi dự án 1 lệnh.
- **6 bảng cấu hình đọc trong 1 lệnh** (`read_all()`), và bỏ 4 lệnh đếm dòng của bước seed —
  seed giờ dùng luôn dữ liệu vừa đọc. Danh sách người dùng lấy từ cấu hình đã cache 5 phút.
- Ghi nhận chi tiền + ngày duyệt của mọi dự án (`daPay_*`, `daApp_*`) đọc **1 lệnh cho cả bảng**
  thay vì 2 lệnh mỗi dự án.
- **Duyệt quyết toán theo lô** tính chênh lệch từ 1 lượt đọc chung — trước đây duyệt 50 đơn là 50
  lượt đọc cả bảng chi phí. Ba luồng xuất MISA không cần danh mục đối tượng nên thôi đọc bảng
  ChiPhi; `getDon` đọc bảng ChiPhi 1 lần thay vì 2.

Chạy lại số đo bất cứ lúc nào (thoát mã ≠ 0 nếu có màn hình đọc lặp trở lại, dùng được trong CI):

```bash
php tools/test/bench-queries.php
php tools/test/bench-queries.php /duong/dan/plugin/khac   # so với 1 phiên bản khác
```

Chỗ còn lại **cố ý giữ nguyên** để không đổi cách vận hành: `getBootstrap` vẫn trả **toàn bộ** đơn
(giao diện lọc/tìm ở phía máy người dùng), nên khi số đơn lên hàng chục nghìn thì nên phân trang —
lúc đó nói em làm, vì việc đó đổi cả giao diện.

## 8. Cập nhật plugin về sau

```bash
bash tools/build-plugin-zip.sh    # tạo lại dist/vhcp-chi-phi.zip
```

rồi tải lên wp-admin như lần đầu (WordPress hỏi "thay thế bản cũ" → chọn thay thế).
Cập nhật **không xóa dữ liệu**; xóa plugin cũng **giữ nguyên bảng dữ liệu** (muốn dọn sạch thì
thêm `define( 'VHCP_DELETE_DATA_ON_UNINSTALL', true );` vào `wp-config.php` trước khi xóa).

Nếu máy anh vào được hosting bằng SSH hoặc FTP thì đẩy thẳng bằng script:

```bash
cp tools/deploy-hosting.env.mau tools/deploy-hosting.env   # điền thông tin hosting
bash tools/deploy-hosting.sh                               # rsync/lftp lên wp-content/plugins
```

File `tools/deploy-hosting.env` đã nằm trong `.gitignore` — **không bao giờ commit mật khẩu hosting**.

## 9. Bảo mật cần biết

- **PIN vẫn lưu nguyên văn** trong bảng cấu hình, vì tab ⚙️ Cấu hình hiện & sửa PIN từng người
  đúng như cách vận hành cũ. Ai vào được wp-admin hoặc database là thấy PIN. Muốn siết thì đổi
  sang đăng nhập SSO từ trang tổng và không phát PIN nữa.
- **Bắt buộc HTTPS** — PIN và token đi qua đường truyền.
- Ảnh chứng từ nằm trong `wp-content/uploads` nên **ai có đúng link là xem được** (giống chế độ
  "bất kỳ ai có liên kết" của Drive trước đây). Cần kín hơn thì chặn `uploads/vhcp/` bằng
  `.htaccess` và cho đọc qua PHP có kiểm phiên — hiện chưa làm.
- Token phiên sống 30 ngày, thu hồi khi bấm **Đăng xuất**.
- API chỉ mở đúng 1 endpoint và chỉ chạy các hàm trong bảng cho phép (`class-vhcp-api.php`);
  file hồ sơ chặn mọi đuôi chạy được trên server (`.php`, …), ảnh giới hạn 15MB.
- **Chặn theo vai trò ở phía máy chủ** (app cũ không có): `getUsers` / `saveConfig` / `undoConfig` /
  `setQuyen` / `getQuyenConfig` / `migrateOldImages` chỉ Admin và Quản lý gọi được, `deleteDonAdmin`
  chỉ Admin. Trước đây ai có link cũng gọi được `getUsers` để đọc PIN của mọi người — kể cả khi giao
  diện đã ẩn tab Cấu hình. Danh sách này khớp đúng những tab mà giao diện vốn chỉ cho Admin/Quản lý
  thấy, nên người dùng thật không thấy khác gì.

## 10. Khắc phục sự cố

| Hiện tượng | Xử lý |
|---|---|
| `/chi-phi/` báo 404 | **Bảo trì → làm mới đường dẫn**, hoặc vào **Cài đặt → Đường dẫn tĩnh → Lưu** |
| Vào app hiện lại cổng PIN liên tục | Token hết hạn/đã thu hồi — đăng nhập lại; kiểm tra trình duyệt không chặn `localStorage` |
| Đính ảnh báo "Không ghi được file" | Sửa quyền ghi cho `wp-content/uploads` (thường 755) |
| Tải MISA ra CSV thay vì Excel | Máy/hosting chặn CDN `cdnjs.cloudflare.com` — CSV là bản dự phòng sẵn có, dùng bình thường |
| Xuất MISA cảnh báo "Thiếu TK Nợ (nhóm × phân loại)" | Vào ⚙️ Cấu hình → ma trận TK Nợ, điền theo **tên nhóm gốc** (giữ đuôi `- NCC`/`- Mua lẻ`) |
| Số liệu lệch sau khi nhập CSV | Nạp lại tab đó với tick "Xóa dữ liệu cũ trước khi nạp"; kiểm tra định dạng ngày kiểu Việt Nam |
