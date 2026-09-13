# Bàn giao — Cập nhật thẳng từ GitHub (13/09/2026)

> Anh Thắng: *"cách kết nối github đẩy thẳng code wed lên"* → *"các bộ khác thì sao, cần token
> nữa không, hay dùng chung"* → *"với sau này tạo ra bộ mới thì sao"*.

Từ đợt này, **mỗi bản mới hiện thẳng ở màn Plugin của wp-admin** như mọi plugin khác. Anh bấm
**Cập nhật ngay**, không tải tệp, không upload.

---

## 1. Trạng thái

| | |
|---|---|
| Mã nguồn | nhánh **`claude/rebuild-chi-phi-wordpress-hl2yze`** (repo `khh-chamcong-firmware`) |
| Repo plugin đang trỏ vào | **`zairozy2004199x/khh-chamcong-firmware`** — khai ở hằng `REPO` trong mỗi lớp tự cập nhật |
| Repo đang **công khai** hay riêng tư | **CÔNG KHAI** tính tới 13/09/2026 — xem mục 2 và mục 9 |
| Số bộ tự cập nhật được | **8 / 9** (`vhcp-cong` cố ý không — bản viết lại đã dừng) |
| Bộ thử | **116 mục** đều đạt (`bash tools/test/chay-het.sh`) |

---

## 2. Anh làm ba việc, một lần, rồi thôi

### Bước 1 — cài tám gói `.zip` lần cuối

wp-admin → **Plugin** → **Cài mới** → **Tải plugin lên** → chọn tệp → Cài đặt.

Cài đè lên bản đang chạy, **không mất dữ liệu**.

### Bước 2 — tạo khoá GitHub *(chỉ cần khi repo RIÊNG TƯ)*

> 🔴 **Repo đang công khai thì bỏ qua bước này** — trang tải bản mới được ngay, không cần khoá.
> Nhưng repo công khai nghĩa là **cả mã nguồn lẫn lịch sử ai cũng đọc được**; xem mục 9 trước
> khi quyết để nguyên. Nếu đổi sang riêng tư (nên đổi) thì làm tiếp bước này.
>
> Lớp tự cập nhật chạy được cả hai kiểu: chưa khai khoá thì gọi không kèm khoá, khai rồi thì kèm.
> Khai khoá cả khi repo công khai cũng có lợi nhỏ: GitHub cho khách 60 lượt gọi mỗi giờ, có khoá
> thì 5000 — với tám plugin hỏi mỗi sáu giờ thì 60 vẫn thừa, nên không bắt buộc.


github.com → ảnh đại diện (góc phải trên) → **Settings** → kéo xuống cuối menu trái →
**Developer settings** → **Personal access tokens** → **Fine-grained tokens** →
**Generate new token**.

| Ô | Điền |
|---|---|
| Token name | `khmatrix cap nhat` |
| Expiration | **No expiration** (hoặc 1 năm, nhớ gia hạn) |
| Repository access | **Only select repositories** → chọn `khh-chamcong-firmware` |
| Permissions → Repository permissions → **Contents** | **Read-only** |

Bấm **Generate token**, copy chuỗi hiện ra (dạng `github_pat_…`).

> ⚠️ **GitHub chỉ hiện chuỗi ấy đúng một lần.** Copy ngay, đóng trang là mất, phải tạo lại.

> 🔴 Chỉ chọn **Contents: Read-only** — đúng một mục. Khoá chỉ đọc nên lỡ lộ cũng không ai ghi
> được gì vào mã. Đừng cấp thêm quyền nào khác.

### Bước 3 — dán vào trang

wp-admin → **Vận Hành Chi Phí** → **Cài đặt** → ô **Khoá GitHub** → dán → **Lưu**.

Xong. Ô sẽ đổi thành **"Đã khai khoá"**.

---

## 3. Một khoá dùng cho bảy bộ

Bảy bộ dưới đây đọc **cùng một ô khoá** (`vhcp_gh_token`). Khai một lần ở Cài đặt Vận Hành Chi
Phí là đủ — **không cần tạo token nào nữa**.

| Bộ | Thư mục | Tiền tố tag |
|---|---|---|
| Vận Hành Chi Phí (K&H) | `vhcp-chi-phi` | `vhcp-chi-phi-v…` |
| Chấm Công (K&H) | `vhcp-cham-cong` | `vhcp-cham-cong-v…` |
| Ghế Massage (K&H) | `vhcp-ghe` | `vhcp-ghe-v…` |
| Nội Bộ K&H | `vhcp-noi-bo` | `vhcp-noi-bo-v…` |
| Trang Vận Hành K&H | `vhcp-trang-chu` | `vhcp-trang-chu-v…` |
| Thư Viện Hợp Đồng | `vhcp-hop-dong` | `vhcp-hop-dong-v…` |
| Dự Án & Tiến Độ K&H | `vhcp-du-an` | `vhcp-du-an-v…` |

### 🔴 Trừ bản vùng — khoá RIÊNG

**`vhcp-chi-phi-hn`** (và mọi bản `vhcp-chi-phi-<vùng>` do `tools/tach-ban-vung.sh` sinh ra)
chạy trên **site riêng của vùng ấy**. Luật của bản vùng là **không dùng chung một chuỗi nào**
với bản gốc — không chung tiền tố bảng, không chung ô cấu hình.

Nên nó dùng ô khoá riêng (`vhcphn_gh_token`), khai ở trang Cài đặt của **chính nó**, bằng một
token khác.

> Lượt làm đầu tiên đã chép nguyên ô khoá của bản gốc sang, và `tools/test/kiem-tach-ban-vung.php`
> bắt ngay — **kể cả chuỗi nằm trong chú thích**. Bài kiểm đúng khi khắt khe thế: một chuỗi trong
> chú thích hôm nay rất dễ thành một dòng mã ngày mai.

---

## 4. Từ nay chuyện gì xảy ra khi có bản mới

```
đẩy mã lên GitHub
      ↓
GitHub Actions chạy 116 mục bộ thử          ← .github/workflows/phat-hanh.yml
      ↓  (đỏ một mục là dừng, KHÔNG dựng gói nào)
plugin nào ĐỔI SỐ PHIÊN BẢN thì dựng .zip
      ↓
tạo tag + Release:  vhcp-chi-phi-v1.159.0
      ↓
trang khmatrix.com thấy, hiện "Có bản mới" ở màn Plugin
      ↓
anh bấm Cập nhật ngay
```

**Bộ thử đỏ thì không có gói nào được dựng** — anh sẽ không bao giờ thấy nút Cập nhật trỏ vào
một bản chưa qua bộ thử. Đó là lý do bước chạy bộ thử đứng trước, không song song.

Vài điều lớp tự cập nhật **không** làm, cố ý:

- **Không hạ cấp.** Bản cũ hơn bản đang chạy thì không phải "bản mới" — hiện nó ra là mời anh
  bấm một nút để quay ngược thời gian, mất đúng những bản vá vừa cài.
- **Không nhận bản nháp / bản thử.** Người ta cố ý chưa phát hành.
- **Không nhận release thiếu tệp `.zip`.** Mã nguồn tự đóng của GitHub gói **cả repo** — chín
  plugin cùng lúc, sai cấu trúc thư mục, cài lên là hỏng plugin đang chạy.
- **Hỏng thì im lặng, thử lại sau.** Mạng chập, khoá hết hạn, GitHub quá tải — không cái nào
  đáng làm hỏng màn quản trị của cả trang.

---

## 5. Vì sao tự viết, không dùng "Git Updater" có sẵn

Đã tra tài liệu của nó trước khi quyết, và bỏ vì hai lý do:

1. **Một repo, chín plugin.** Git Updater sinh ra cho *một repo là một plugin*; tài liệu của nó
   không nói gì về nhiều plugin nằm trong thư mục con. Đọc release mới nhất của repo mà không
   lọc theo plugin thì bản chấm công hiện thành "bản mới" của chi phí.
2. **Repo riêng tư.** Tài liệu của nó hướng chuyện này sang bản trả phí.

Lớp tự viết lọc tag theo **tiền tố của chính plugin mình**, nên chín bộ sống chung một repo mà
không ai nhầm bản của ai; repo riêng tư thì dùng khoá đọc, không tốn phí.

---

## 6. Bộ mới sau này — không ai phải nhớ

`tools/test/kiem-tu-cap-nhat.php` **quét thư mục `wordpress/`**, không liệt kê tay từng plugin.
Dựng một bộ mới mà quên nối lớp tự cập nhật là **bộ thử đỏ ngay**.

Cố ý không cho bộ nào tự cập nhật thì khai vào `$KHONG_TU_CAP_NHAT` trong bài kiểm ấy — để chỗ
bỏ qua là một **quyết định có ghi lại**, không phải một chỗ sót. Cùng lối với "chốt chống sót"
của `tools/build-plugin-zip.sh`, vốn dựng lên sau khi `vhcp-noi-bo` suýt bị bỏ quên đúng kiểu ấy.

### Thêm tự cập nhật cho một bộ mới — bốn việc

1. Chép `includes/class-<tiền tố>-tu-cap-nhat.php` từ một bộ đã có.
2. Sửa **ba** dòng trong tệp vừa chép:
   - `const TIEN_TO` → `'<tên thư mục>-v'`  ← **chỗ hay quên nhất**
   - `const O_NHO` → `'<tiền tố>_gh_ban_moi'` (riêng mỗi bộ, không thì hai bộ giẫm lên nhau)
   - tên lớp, hằng `_DIR`, hằng `_VERSION`, tên tệp plugin trong `duong()`
3. Trong tệp plugin chính: thêm `require_once` và gọi `<TIỀN TỐ>_TuCapNhat::init();`
4. Khai bộ ấy vào `tools/build-plugin-zip.sh` để workflow dựng được `.zip`.

Bỏ sót bước nào cũng đỏ ở `kiem-tu-cap-nhat.php` — đã phá thử đúng hai ca dễ xảy ra nhất:
quên nối hẳn, và chép lớp nhưng quên đổi tiền tố tag.

---

## 7. 🔴 Nếu có repo khác

Lớp tự cập nhật hiện trỏ vào **đúng một repo**:

```php
const REPO = 'zairozy2004199x/khh-chamcong-firmware';
```

Dòng ấy nằm ở đầu mỗi tệp `class-*-tu-cap-nhat.php` (tám tệp). Plugin nào nằm ở **repo khác**
thì phải sửa dòng ấy trong tệp của chính nó, và khoá GitHub cũng phải có quyền đọc repo đó.

> Anh Thắng 13/09/2026: *"còn sót repo nào anh gọi là tự hiểu và chèn vào"* — báo tên repo, sẽ
> thêm, kèm bài kiểm canh đúng như tám bộ hiện có.

---

## 8. Khoá là bí mật

Ô nhập khoá **không bao giờ hiện khoá đang lưu** — chỉ nói *"đã có khoá"* hoặc *"chưa khai"*.
Cùng luật đã đặt cho PIN và khoá máy chấm công: **trang chạy ngoài internet, một ảnh chụp màn
hình là mất**.

- Ô để trống khi bấm Lưu = **giữ nguyên khoá cũ**, không phải xoá.
- Muốn bỏ hẳn thì tích ô **"Xoá hẳn khoá đang lưu"** — một việc có ý, không phải hậu quả của
  việc để trống một ô.
- Địa chỉ tải gói có mang khoá (repo riêng tư bắt buộc thế) — **không log, không in ra màn hình**.

---

## 9. 🔴 Chuyện an ninh phải xử trước

Phát hiện 13/09/2026 khi liệt kê repo để làm tài liệu này:

**Repo `khh-chamcong-firmware` đang để CÔNG KHAI**, và tệp `secrets.h` tuy đã gỡ khỏi cây hiện
tại nhưng **vẫn còn trong lịch sử git** — ba commit từ 01/08/2026 (`a87a552`, `20b83c8`,
`fd2589c`). Đếm bằng máy, không mở giá trị ra: **10 / 11 trường mang giá trị THẬT**, không phải
mẫu — mật khẩu WiFi, mật khẩu AP, tài khoản + mật khẩu Hikvision, tài khoản OTA, token máy.

Repo công khai nghĩa là bất kỳ ai trên internet đều đọc được chúng bằng một lệnh `git log`.

**Ba việc, theo đúng thứ tự:**

1. **Đổi repo sang Private.** GitHub → repo → *Settings* → cuối trang → *Change repository
   visibility* → **Private**. Một phút, chặn ngay việc đọc mới.
2. **Đổi hết mười khoá ấy trên thiết bị thật** — WiFi, AP của ESP32, tài khoản Hikvision, tài
   khoản OTA. Đổi sang private **không xoá được** thứ đã bị sao chép trong sáu tuần qua; chỉ đổi
   khoá mới làm chúng thành vô dụng.
3. **Xoá khỏi lịch sử** (`git filter-repo`) — làm SAU cùng, và chỉ sau khi đã đổi khoá. Việc này
   viết lại lịch sử nên mọi bản sao đang có phải clone lại.

> ⚠️ `.gitignore` nay đã chặn `secrets.*` (chỉ chừa `secrets.example.h` và `secrets.ci.h` —
> hai tệp ấy chỉ chứa giá trị mẫu). Nên chuyện này **không lặp lại từ đây**; phần phải xử là
> ba commit cũ đã nằm trong lịch sử.

---

## 10. Lệnh gửi đi các nơi

Anh Thắng 13/09/2026: *"làm chung 1 lệnh để anh gửi các nơi"*.

### Một dòng dán vào là xong

Gửi đúng khối này cho người phụ trách từng site:

```bash
cd <thư mục có wp-config.php>
curl -fsSL https://raw.githubusercontent.com/zairozy2004199x/khh-chamcong-firmware/claude/rebuild-chi-phi-wordpress-hl2yze/tools/tren-host.sh -o tren-host.sh
bash tren-host.sh
```

Script làm ba việc, theo thứ tự: **soát** đang có gì → **khai khoá** GitHub → **hỏi lại** GitHub
xem có bản mới.

> ⚠️ **Tải về rồi mới chạy, không `curl | bash`.** Kéo thẳng mã lạ vào `bash` là chạy thứ mình
> chưa nhìn thấy — kể cả mã của chính mình, vì hôm nay đúng không có nghĩa ngày mai vẫn đúng
> (ai đó đổi nhánh, repo bị chiếm). Tải ra tệp thì còn `cat tren-host.sh` xem trước được.
>
> ⚠️ **Đường dẫn trên chỉ chạy khi repo còn CÔNG KHAI.** Sau khi đổi sang Private (mục 9), gửi
> thẳng tệp `tools/tren-host.sh` qua Zalo hoặc email thay vì dán link.

### Chạy riêng từng phần

```bash
bash tren-host.sh soat      # chỉ xem, KHÔNG ghi gì cả — an toàn tuyệt đối
bash tren-host.sh khoa      # chỉ khai khoá
bash tren-host.sh capnhat   # chỉ hỏi lại GitHub ngay
bash tren-host.sh khoa vhcphn_gh_token   # khoá của bản vùng Hà Nội
```

**`soat` không ghi gì cả** — cố ý. Gửi cho người lạ máy thì việc đầu tiên họ chạy phải là việc
không làm hỏng được gì.

Phần `soat` cũng trả lời luôn câu *"còn nhiều trang chưa thấy trong này"*: nó liệt kê riêng
**những plugin KHÔNG phải của K&H** đang chạy trên trang — bộ nào hiện ở đó là bộ repo không
giữ mã, mất máy chủ là mất luôn.

> 🔴 **Không đưa khoá vào dòng lệnh.** `bash tren-host.sh khoa github_pat_xxx` trông tiện hơn,
> nhưng chuỗi ấy nằm lại trong `~/.bash_history` của máy chủ và hiện ra với bất kỳ ai gõ `ps`
> đúng lúc. Tham số thứ hai là **tên ô**, không phải khoá. Script đọc khoá từ bàn phím bằng
> `read -s` — gõ xong không hiện, không lưu đâu cả.

Nó soát hình dạng khoá **trước khi ghi** (`github_pat_` hoặc `ghp_`, tối thiểu 30 ký tự). Dán
nhầm nửa chuỗi mà vẫn ghi vào thì lần cập nhật sau im lặng không thấy bản mới — một lỗi không
có câu báo nào.

### Gom repo — lệnh riêng, chạy trên máy anh

```bash
bash tools/gom-repo.sh <chủ/repo-nguồn> <tên-thư-mục-đích> [nhánh] [thư-mục-con]

bash tools/gom-repo.sh zairozy2004199x/vhcp-kho vhcp-kho
bash tools/gom-repo.sh zairozy2004199x/Claude vhcp-abc main wordpress/vhcp-abc
```

Dùng **`git subtree`** để **giữ nguyên lịch sử**. Chép tay thì mã về đây nhưng lịch sử ở lại
repo cũ, và sáu tháng sau hỏi *"dòng này sửa hôm nào, vì sao"* thì không còn chỗ nào trả lời.

Ba chốt trước khi đụng vào gì: cây làm việc phải **sạch**, thư mục đích **chưa tồn tại** (gom đè
là mất mã đang có mà không ai báo), và làm trên **nhánh riêng** `gom-<tên>`.

Gom xong **chưa phải là xong**: còn nối bộ tự cập nhật cho plugin mới (mục 6) rồi chạy
`chay-het.sh`. Bỏ bước ấy thì plugin nằm đây im lặng, không bao giờ hiện bản mới.

---

## 11. Tệp liên quan

| Tệp | Việc |
|---|---|
| `.github/workflows/phat-hanh.yml` | chạy bộ thử → dựng `.zip` → tạo Release |
| `wordpress/*/includes/class-*-tu-cap-nhat.php` | tám lớp tự cập nhật |
| `tools/build-plugin-zip.sh` | dựng `.zip` cài được (bỏ `goc/`, giữ `apps-script/`) |
| `tools/test/kiem-tu-cap-nhat.php` | 75 phép — quét mọi plugin, bắt bộ mới quên nối |
| `tools/test/kiem-tach-ban-vung.php` | canh bản vùng không dùng chung chuỗi nào với bản gốc |
| `tools/tren-host.sh` | **một lệnh gửi đi các nơi** — soát · khai khoá · hỏi lại GitHub |
| `tools/gom-repo.sh` | gom một repo khác về `wordpress/<tên>`, giữ nguyên lịch sử |
