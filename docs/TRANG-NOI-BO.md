# Trang Nội Bộ K&H — bảng tin trao đổi của công ty

Anh Thắng 26/08/2026: *"Tạo 1 trang mạng xã hội nội bộ công ty (để trao đổi trên đó, giống zalo
và facebook)"*, cùng với *"làm 1 trang chủ ghép các trang chấm công chung lại"* và *"tạo 1 trang
chủ công ty K&H để liên kết đến các trang con"*.

Ba việc ấy nằm ở ba chỗ, không phải một:

| Việc anh Thắng nêu | Đã có ở đâu |
|---|---|
| Ghép các trang chấm công chung lại | **Trang chính** của hệ chấm công — `VHCC_Web::the_nha()`, hiện thẻ việc theo đúng quyền của người đang vào |
| Trang chủ công ty K&H liên kết các trang con | Plugin **`vhcp-trang-chu`** — `/van-hanh/`, sáu ô: Chấm Công · Chấm Công Online · Vận Hành Chi Phí · Ghế Massage · **Nội Bộ** · Thư Viện Hợp Đồng |
| Mạng xã hội nội bộ | Plugin **`vhcp-noi-bo`** (tài liệu này) — `/noi-bo/` |

Ba trang nối vòng với nhau: cổng K&H → trang nào cũng tới; trang chính chấm công có khối
**"Trang khác của công ty"**; đầu trang nội bộ có nút về **Cổng K&H** và **Chấm công**.

---

## Cài

```bash
bash tools/build-plugin-zip.sh noi-bo
```

wp-admin → Plugin → Cài mới → Tải plugin lên → `dist/vhcp-noi-bo.zip` → Cài đặt → Kích hoạt.

Địa chỉ: **`https://khmatrix.com/noi-bo/`**

Cài **độc lập** với plugin chấm công, nhưng **phải có plugin chấm công** thì mới đăng nhập được
— xem phần dưới. Thiếu nó thì trang không trắng, nó nói thẳng *"Chưa cài plugin Chấm Công."*

---

## 🔴 Dùng CHUNG mã PIN với hệ chấm công, KHÔNG cấp tài khoản WordPress

240 người mà cấp tài khoản WordPress là cấp 240 đường vào phần quản trị website. Nên trang nội
bộ **không có cửa đăng nhập riêng**: nó đọc đúng thẻ phiên mà trạm chấm công đã đặt
(`VHCC_Web::COOKIE` → `VHCC_Auth::user_by_token`).

Nghĩa là:

- Ai đăng nhập được trạm chấm công thì vào thẳng đây, **không phải nhập PIN lần hai**.
- Đổi PIN ở hồ sơ nhân sự là đổi cho cả hai trang.
- Cho nghỉ việc / khoá tài khoản ở hệ chấm công là mất luôn đường vào bảng tin. Không có danh
  sách người dùng thứ hai để quên dọn.

Chưa đăng nhập thì trang mời sang trang chấm công — **không hỏi PIN ở đây**. Một cửa PIN thôi.

---

## Làm được gì

| Việc | Ai làm được |
|---|---|
| Đăng bài (toàn công ty hoặc một bộ phận) | mọi người đăng nhập được |
| Bình luận | mọi người |
| Thả tim (bấm lại là bỏ) | người **đã có Mã NV** trong hồ sơ |
| Xoá bài | **tác giả** bài đó, hoặc **Admin** |
| Ghim bài lên đầu | **Admin** |

**Nhóm = bộ phận của hệ chấm công** (`VHCC_Luong::BP_DS`), không có danh sách riêng — hai nơi
khai riêng là hai nơi sẽ lệch nhau.

Lọc theo bộ phận thì **vẫn thấy bài chung**: một thông báo toàn công ty mà biến mất chỉ vì đang
lọc bộ phận là bỏ sót đúng thứ quan trọng nhất.

---

## Mấy quyết định đáng nhớ

**Không một dòng JavaScript nào.** Đăng bài, bình luận, thả tim đều là một lượt POST rồi chuyển
hướng. Đổi lại nó chạy trên mọi máy, và thử được bằng bộ thử PHP —
`tools/test/kiem-noi-bo.php`, 115 phép.

**Chữ ký buộc vào thẻ phiên, không dùng `wp_nonce_field`.** Nonce của WordPress buộc vào tài
khoản WordPress, mà ở đây không ai có tài khoản WordPress — nonce sẽ tính theo id 0, ai cũng ra
một chuỗi giống nhau, tức chẳng chặn được gì. Nên mỗi biểu mẫu mang một `hash_hmac` của chính
thẻ phiên. Không có nó thì một trang ngoài dựng cái nút "Bấm để nhận quà" là người trong công ty
bấm phát xoá bài / đăng bài mang tên mình.

**Con số tim và bình luận ĐẾM LẠI, không cộng dồn.** `so_tim = so_tim + 1` thì chỉ cần một lượt
ghi trượt (mạng đứt, bấm hai lần, khoá UNIQUE chặn) là con số lệch **vĩnh viễn**, mà không có
cách nào biết nó đã lệch. Đếm lại từ chính bảng `tim` thì mỗi lượt tự chữa cho lượt trước.

**Một người một bài đúng một tim** — khoá `UNIQUE (bai_id, ma_nv)` trên bảng. Người **chưa có Mã
NV** thì không thả tim được, và trang nói thẳng lý do: khoá là `(bài, mã)`, nên mọi người mã rỗng
sẽ chung một ô — người thứ hai thả tim là ghi đè người thứ nhất.

**Xoá bài so bằng MÃ, không bằng TÊN.** Trong 240 người có trùng tên.

**Thoát chuỗi TRƯỚC, xuống dòng SAU** (`nl2br( esc_html( … ) )`). Làm ngược lại là mấy thẻ `<br>`
vừa chèn cũng bị thoát thành chữ. Đây là trang duy nhất trong cả hệ cho người dùng gõ chữ rồi
hiện lại cho người khác đọc, nên sót một lần thoát chuỗi là một người gõ `<script>` vào bài rồi
240 người chạy đoạn mã đó, kèm theo cả thẻ phiên chấm công.

---

## Bảng

Tiền tố riêng `wp_vhnb_` — **không dùng chung bảng với plugin chấm công**. Hai plugin cài độc
lập, gỡ độc lập; dùng chung bảng thì gỡ một cái là làm hỏng cái kia.

| Bảng | Việc |
|---|---|
| `vhnb_bai` | bài đăng — `nhom` rỗng là bài toàn công ty |
| `vhnb_binh_luan` | bình luận |
| `vhnb_tim` | thả tim, `UNIQUE (bai_id, ma_nv)` |

Xoá bài thì xoá luôn bình luận và tim của nó — để lại là rác không ai với tới được.

---

## Chạy bộ thử

```bash
php tools/test/kiem-noi-bo.php      # 115 phép
php tools/test/test-trang-chu.php   #  69 phép — cổng K&H, gồm ô Nội Bộ
php tools/test/kiem-goi-cheo.php    # lời gọi chéo giữa plugin đều gác method_exists
```
