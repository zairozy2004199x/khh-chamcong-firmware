# ⚠️ REPO NÀY ĐANG GIỮ BẢN GHẾ CŨ HƠN LIVE — ĐỪNG CÀI `dist/vhcp-ghe.zip`

**09/09/2026.** Anh Thắng gửi ảnh màn cài plugin của WordPress:

| | Hiện tại (live) | Đã tải lên (từ repo này) |
|---|---|---|
| Ghế Massage (K&H) | **2.16.0** | **1.42.0** |

WordPress nói thẳng: *"Bạn đang tải lên một phiên bản cũ của plugin hiện tại"*. Bấm **Thay thế** là
**hạ cấp**, và mất mọi thứ làm giữa 1.41 → 2.16.

---

## 1. Sự thật đã kiểm

Dò **mọi nhánh** của repo `khh-chamcong-firmware` và cả kho `Claude` ở máy dựng:

```
origin/main                                   (không có plugin ghế)
origin/claude/rebuild-chi-phi-wordpress-hl2yze  VHG_VERSION 1.41.0
origin/claude/posh-qr-kh1urz                  (không có)
origin/claude/tao-cac-wed-nho-n55gu3          (không có)
origin/claude/chao-em-iiyx5i                  (không có)
```

⇒ **Mã của bản ghế đang chạy trên live (2.16.0) KHÔNG nằm trong repo này.** Nó được làm ở đâu đó
khác — kho khác, hoặc một phiên chưa từng đẩy về đây.

---

## 2. Hệ quả — và việc phải làm

🔴 **KHÔNG cài `dist/vhcp-ghe.zip` dựng từ repo này** cho tới khi mã 2.16.0 được đưa về đây và
thay đổi của đợt này được đắp lại lên nó.

Đợt 09/09/2026 có sửa phía ghế (bản 1.42.0 trong repo): cho **Mã NV** đi suốt từ lúc đăng nhập tới
sổ chốt ca, để bên chấm công gắn được cờ *"đi làm mà quên chấm công"*. Ba tệp:

| Tệp | Sửa gì |
|---|---|
| `class-vhg-db.php` | `vhg_phien` + `vhg_chot` thêm cột `ma_nv`, thêm `KEY ma_nv (ma_nv,tao_luc)` |
| `class-vhg-auth.php` | `users()` trả kèm `maNV` · `phat_token()` nhận `$ma_nv` · `user_by_token()` trả `ma_nv` |
| `class-vhg-quy.php` + `class-vhg-trang.php` | `chot()` nhận `$ma_nv` (tham số CUỐI, có mặc định) và ghi vào sổ; nơi gọi lấy mã **từ phiên** |

Cả ba đều là thay đổi **cộng thêm**, không đụng logic cũ — đắp lên 2.16.0 nên nhẹ. Nhưng phải đắp
lên **mã 2.16.0 thật**, không phải merge nhánh này đè lên.

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
