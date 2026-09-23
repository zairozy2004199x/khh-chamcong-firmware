# ⚠️ REPO NÀY ĐANG GIỮ BẢN GHẾ CŨ HƠN LIVE — ĐỪNG CÀI `dist/vhcp-ghe.zip`

**09/09/2026.** Anh Thắng gửi ảnh màn cài plugin của WordPress:

| | Hiện tại (live) | Đã tải lên (từ repo này) |
|---|---|---|
| Ghế Massage (K&H) | **2.16.0** | **1.42.0** |

WordPress nói thẳng: *"Bạn đang tải lên một phiên bản cũ của plugin hiện tại"*. Bấm **Thay thế** là
**hạ cấp**, và mất mọi thứ làm giữa 1.41 → 2.16.

---

## 0. KẾT CỤC: KHÔNG PHẢI SỬA PLUGIN GHẾ NỮA

Anh Thắng chỉ đúng chỗ: mã ghế **2.16.0** nằm ở **cùng repo này**, nhánh
**`claude/posh-qr-kh1urz`**, thư mục **`vhcp-ghe/` ở GỐC** — không phải `wordpress/vhcp-ghe/`.
Em dò sai đường dẫn nên tưởng không có.

Và mở ra thì bản 2.16.0 **đã giải sẵn** vấn đề danh tính: bảng `bc_phien` có **khoá chính
`(pin, ngay)`** — hệ báo cáo vốn nhận diện người bằng **PIN**, mà PIN là thứ dùng chung cả ba hệ.

⇒ **Không phải đụng một dòng nào của plugin ghế.** Thay đổi phía ghế của đợt này đã **lùi lại
hết** (`wordpress/vhcp-ghe/` về đúng 1.41.0 như cũ). Cờ đọc thẳng `bc_phien` từ phía chấm công.

⚠️ **`wordpress/vhcp-ghe/` trong nhánh này vẫn là bản 1.41.0 cũ và KHÔNG phải nguồn sự thật.**
Đừng đóng gói nó, đừng merge nó đè lên `claude/posh-qr-kh1urz`. Nguồn thật là `vhcp-ghe/` ở gốc
nhánh ấy.

---

## 1. Sự thật đã kiểm — và chỗ em dò sót

Dò **mọi nhánh** của repo `khh-chamcong-firmware` và cả kho `Claude` ở máy dựng:

```
origin/main                                   (không có plugin ghế)
origin/claude/rebuild-chi-phi-wordpress-hl2yze  VHG_VERSION 1.41.0
origin/claude/posh-qr-kh1urz    wordpress/vhcp-ghe/  (không có)
origin/claude/posh-qr-kh1urz    vhcp-ghe/            2.16.0   <-- ĐÂY, ở GỐC repo
origin/claude/tao-cac-wed-nho-n55gu3          (không có)
origin/claude/chao-em-iiyx5i                  (không có)
```

🔴 **Bài học của chính lần dò này:** em kết luận "không có trong repo" sau khi chỉ hỏi **một đường
dẫn** (`wordpress/vhcp-ghe/`). Cùng một plugin có thể nằm ở thư mục khác trên nhánh khác. Dò thiếu
rồi kết luận chắc chắn là cách nhanh nhất để nói sai một điều rất dễ kiểm.

---

## 2. Hệ quả — và việc phải làm

🔴 **KHÔNG cài `dist/vhcp-ghe.zip` dựng từ repo này** — nó là 1.41.0, cũ hơn live 2.16.0.

Bản đầu của đợt này có sửa phía ghế (thêm cột `ma_nv` vào `vhg_chot`). **Đã lùi lại hết**, vì hai
lẽ: nó nhắm nhầm bảng (`chot` là chốt **chỉ số ghế**, không phải *"nhập báo cáo"*), và bản 2.16.0
đã có sẵn `bc_phien` khoá theo PIN nên không cần gì thêm.

---

## 3. Trong lúc chờ, phía chấm công KHÔNG hỏng

Chấm công **3.59.0** cài được ngay và chạy bình thường. Cờ *"quên chấm công"* thì tự tắt, **và nói
ra vì sao**: `VHCC_BaoCaoCa::theo_thang()` hỏi cột `chot.ma_nv` trước khi đọc; thiếu cột thì trả cờ
`thieu_cot` và lưới hiện một dải vàng:

> ⚠️ **Cờ "đi làm mà quên chấm công" chưa chạy được.** Bản Ghế Massage đang cài chưa ghi **Mã NV**
> vào sổ chốt ca…

🔴 **Vì sao phải có dải ấy.** Không có nó thì lưới **không bao giờ vàng lên, không một lời giải
thích** — và người dùng kết luận tính năng hỏng. Một tính năng tắt vì thiếu điều kiện thì phải nói
ra điều kiện; im lặng là bắt người ta đi đoán.

⚠️ Site **không cài** plugin ghế thì **không** hiện dải này — cờ vốn không dành cho họ, và cảnh báo
về một plugin người ta không dùng là tiếng ồn. Có phép thử canh cả hai nhánh.

---

## 4. Bài học chung

**Repo không phải là nguồn sự thật nếu có nhánh nào đó cài thẳng lên live.** Ở đây bản live đi
trước repo **75 số bản** mà không có gì báo — và cái báo cuối cùng lại là màn cảnh báo hạ cấp của
WordPress, đúng lúc sắp bấm nút.

Trước khi đóng gói một plugin trong repo này, **đối chiếu số bản với live**:
`wp-admin → Plugins`, so với `VHG_VERSION` / `VHCC_VERSION` trong mã.
