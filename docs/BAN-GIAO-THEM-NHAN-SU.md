# Bàn giao — luồng THÊM NHÂN SỰ (08/09/2026)

Đợt sửa đi từ một câu của anh Thắng: *"Việc thêm nhân sự rất rối. Không rõ ràng ở trang nào. Gộp
lại chỉ cần 1 trang thêm được là được"*, rồi lần lượt tới *"không có ô rõ ràng tạo nhân sự mới"*,
*"bấm cũng không chạy"*, *"trước khi tạo làm sao biết nhân viên đó có chưa"*, *"loại bỏ chỗ này
tránh nhầm"*, và *"chặn trường hợp tạo mã pin trùng nhé"*. Anh xác nhận cuối đợt: **"chạy ổn"**.

Chi tiết từng thay đổi nằm ở **`docs/TRANG-QUAN-LY-NHAN-SU.md`** (mỗi bản một mục). File này chỉ
là bản đồ để người tới sau không phải dò lại.

---

## 1. Trạng thái

| Việc | Ở đâu |
|---|---|
| Mã nguồn | nhánh **`claude/quan-tri-cham-cong-ovmiud`** (repo `khh-chamcong-firmware`) |
| Bản plugin | **3.47.0** (đi từ 3.41.0 trong đợt này) |
| Bản cài | `dist/vhcp-cham-cong.zip` — đã đóng gói đúng 3.47.0 |
| Live | anh Thắng đã cài và xác nhận chạy ổn |
| Bộ thử | **4821 phép** đạt (`test-cham-cong.php`), 8 bộ khác xanh |

⚠️ **Nhánh này CHƯA gộp** vào `claude/rebuild-chi-phi-wordpress-hl2yze` (nhánh anh Thắng đang làm
WordPress). Nó dựng TRÊN nhánh đó, nên gộp là chạy thẳng, không xung đột. Muốn lên live thì cài đè
`dist/vhcp-cham-cong.zip` (Plugins → Add New → Upload → *Replace current with uploaded*) — dữ liệu
giữ nguyên.

⚠️ **Mã WordPress nằm trong repo FIRMWARE**, không phải repo `Claude`. Đợt này đã mất khá nhiều
thời gian vì tưởng app nhân sự là Apps Script trong repo `Claude` — nó từng là vậy
(`ChamCongLive`), nhưng **đã chuyển sang plugin WordPress** `wordpress/vhcp-cham-cong/`. Ai tìm mã
nhân sự thì tìm ở đây trước.

---

## 2. Một cửa duy nhất tạo hồ sơ

Đường duy nhất tạo người: **`/quan-tri-cham-cong/?man=ho_so&sua=moi`** — thẻ *➕ Tạo nhân sự mới*
đứng ĐẦU màn *Hồ sơ & tài khoản*.

| Việc | Tệp · hàm |
|---|---|
| Thẻ "Tạo nhân sự mới" (đường vào, không phải biểu mẫu) | `class-vhcc-web.php` · `the_tao_moi()` |
| Biểu mẫu tạo/sửa hồ sơ (20 ô + ảnh thẻ + lưới cơ sở) | `class-vhcc-web.php` · `the_sua_ho_so()`, hằng `NHOM_SUA` / `COT_SUA` |
| Đường ghi của biểu mẫu ấy | `class-vhcc-web.php` · nhánh `viec=sua_hs` |
| Khối "nhân viên đang có của cơ sở" + cảnh báo trùng tên | `class-vhcc-web.php` · `khoi_nv_dang_co()` + hằng `JS_NV_DANG_CO` |
| Thẻ 🔑 Tài khoản đăng nhập (chỉ hiện khi cổng đọc sai chỗ) | `class-vhcc-web.php` · `the_tai_khoan()` |
| Chốt PIN trùng dùng chung cho MỌI đường ghi | `class-vhcc-nhan-su.php` · `pin_trung_loi()`, `pin_dang_dung()`, `pin_may_dang_dung()` |
| Màn wp-admin: chỉ SỬA, không tạo | `class-vhcc-admin.php` · `trang_nhan_su()`, `url_them_hs()` |
| Trang `/nhan-su/` (chỉ khai *ai vào được trang nào*) | `class-vhcc-trang-ns.php` |

**Bốn đường ghi được vào hồ sơ** — đường nào cũng phải đi qua `pin_trung_loi()`:
`sua_hs` (màn web) · `luu_ho_so()` (wp-admin) · `VHCC_NapCsv::nap()` (.csv) · `VHCC_Keo::keo_nhan_su()`
(kéo từ app gốc). Thêm đường thứ năm thì **nhớ gọi chốt ấy** — đó là lý do nó là một hàm riêng.

---

## 3. Bốn cái bẫy đã trả giá — đừng lặp

**1. Dấu `+` trong địa chỉ.** Mã lệnh "hồ sơ mới" từng là `sua=+`. Liên kết sinh ra đúng
(`sua=%2B`), nhưng chỉ cần một chặng trả `%2B` về `+` nguyên hình là PHP đọc thành **dấu cách**,
`sanitize_text_field` cắt còn rỗng, và trang vẽ lại y như chưa bấm — **không một dòng báo lỗi**.
Nay mã lệnh là chữ **`moi`**, vẫn nhận `+` và `sua=` rỗng cho liên kết cũ. **Đừng dùng ký tự đặc
biệt làm mã lệnh trong URL.**

**2. PIN máy phải so THEO CƠ SỞ, PIN đăng nhập so CẢ CHUỖI.** Mỗi đầu đọc chỉ giữ người của cơ sở
nó, nên chặn PIN máy trùng trên cả chuỗi thì tới cơ sở thứ mười là hết số 4 chữ số để cấp, và
người ta sẽ đi vòng bằng cách bỏ trống ô. Còn PIN đăng nhập trùng là cổng nhận người *gặp trước* —
phải chặn toàn chuỗi.

**3. Ô PIN để trống = GIỮ NGUYÊN, không phải xoá.** Ô này không bao giờ điền sẵn PIN cũ (một ảnh
chụp là mất sạch mật khẩu cả chuỗi), nên "trống" là trạng thái bình thường của nó. Mọi phép soát
PIN phải bỏ qua ô trống, kẻo sửa số điện thoại cũng bị chối oan.

**4. Phép thử XANH GIẢ.** Hai lần vấp trong đợt này:
* đo "không thấy thẻ 🔑" sau khi **đổi nguồn người dùng** — đổi nguồn là đổi luôn chỗ cổng tra PIN,
  nên đăng nhập trượt, trang chỉ có ô PIN, và mọi phép "không thấy" đều xanh mà chẳng chứng minh
  gì. Nay phép thử tự kiểm *"đã đăng nhập được chưa"* trước;
* đo lọc quyền của Cửa hàng trưởng **qua trang** — họ vốn không mở được màn Hồ sơ, nên trang rỗng
  và phép thử cũng xanh. Nay gọi **thẳng** hàm dựng khối.

Luật rút ra: phép thử phủ định ("không thấy X") phải kèm một phép khẳng định chứng minh trang đã
dựng thật.

---

## 4. Chạy kiểm & đóng gói

```bash
php tools/test/test-cham-cong.php        # 4821 phép — bộ chính
php tools/test/kiem-noi-bo.php           # 439
php tools/test/kiem-tram.php             # 291
php tools/test/kiem-mat.php              # 189
php tools/test/kiem-phan-quyen.php       # 117
php tools/test/kiem-du-an.php            # 149
php tools/test/kiem-nap-cong-csv.php     # 92
php tools/test/test-flows.php
python3 tools/test/kiem-phien-ban.py     # hai chỗ khai số bản phải bằng nhau

bash tools/build-plugin-zip.sh cham-cong # -> dist/vhcp-cham-cong.zip
```

⚠️ **Nâng số bản TRƯỚC khi đóng gói.** `test-cham-cong.php` cũng tự build lại zip, nên bump version
sau lượt build cuối là zip mang số CŨ — đã hớ một lần, phải build lại rồi `--amend`. Kiểm bằng:

```bash
unzip -p dist/vhcp-cham-cong.zip vhcp-cham-cong/vhcp-cham-cong.php | grep VHCC_VERSION
```

⚠️ Số bản khai **hai chỗ**: header `Version:` và hằng `VHCC_VERSION` trong `vhcp-cham-cong.php`.
`kiem-phien-ban.py` gác việc này.

⚠️ `dist/` nằm trong `.gitignore` **nhưng file zip vẫn được git theo dõi** — commit nó phải dùng
`git add -f dist/vhcp-cham-cong.zip`.

---

## 5. Việc còn treo

1. **Gộp nhánh** `claude/quan-tri-cham-cong-ovmiud` → `claude/rebuild-chi-phi-wordpress-hl2yze`.
2. **Dọn PIN đang trùng sẵn trong sổ.** Chặn từ nay không sửa được chỗ đã trùng (sổ kéo về từ
   Sheets, PIN gõ tay). Mở thẻ *Hồ sơ nhân sự*: có dải đỏ *"N mã PIN đang bị M người dùng chung"*
   thì bấm **Xem và sửa** (hoặc lọc **⚠ PIN đang TRÙNG nhau**) rồi đổi PIN cho người đứng sau.
3. **Commit "thêm nhanh nhiều nhân viên" cho ChamCongLive (Apps Script)** ở repo `Claude` — nằm
   local, chưa push, và **đã vô dụng** vì nhân sự chuyển sang WordPress. Bỏ được.
4. **wp-admin vẫn SỬA được hồ sơ** (chỉ bỏ đường TẠO). Nếu sau này muốn một cửa cho cả sửa thì
   phải bù hai ô *Người liên hệ khẩn / SĐT khẩn*… — thực ra đã bù sang màn web ở 3.43.0, nên giờ
   bỏ hẳn biểu mẫu sửa bên wp-admin là làm được, chỉ cần chuyển nút **Sửa** ở bảng sang trỏ ra màn
   web.

---

## 6. Đọc thêm

| File | Nội dung |
|---|---|
| `docs/TRANG-QUAN-LY-NHAN-SU.md` | chi tiết từng bản 3.42 → 3.47, kèm lý do và phép thử |
| `docs/CHAM-CONG-DUNG-MOI.md` | luồng chấm công tổng thể |
| `docs/CAI-LEN-HOSTING.md` | cài lên host |
