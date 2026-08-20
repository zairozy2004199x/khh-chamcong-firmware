# Bảng đối chiếu — so với màn hình cấu hình cũ rồi mới nạp

Em đọc từ ảnh anh gửi. **Anh soi 30 giây rồi hãy nạp**: sai một số là sai sổ.
Ô trống = mảng đó không dùng loại này.

| Loại chi phí | EVENT FZ | EVENT GHOST | EVENT SNOW | EVENT VR | FARM | FZ | TUTU |
|---|---|---|---|---|---|---|---|
| Chi phí SP đồ uống | | | | | | | |
| Chi phí SP đồ ăn | | | | | | | |
| Chi phí vật dụng | | | | | | | |
| Chi phí NVL đồ uống | | | | | | | |
| Chi phí NVL đồ ăn | | | | | | | |
| Chi phí NVL đồ ăn - Mua lẻ | | 6329 | 6329 | 6329 | 6326 | 6322 | 6320 |
| Chi phí NVL đồ uống - Mua lẻ | | 6329 | 6329 | 6329 | 6326 | 6322 | 6320 |
| Chi phí cơ sở | | 64196 | 64196 | 64196 | 64166 | 64126 | 64106 |
| Chi phí marketing | | 64196 | 64196 | 64196 | 64166 | 64126 | 64106 |
| Chi phí nuôi thú | | | | | 64168 | | |
| Chi phí phát sinh | | | | | | | |
| Chi phí hoạt náo | | 64196 | 64196 | 64196 | 64166 | 64126 | 64106 |
| Chi phí tháo dỡ | | 64125 | 64125 | 64125 | 64125 | 64125 | 64125 |
| Chi phí setup | | | | | | | |
| Chi phí khác | | | | | | | |
| Tháo dỡ *(đơn Kỹ thuật)* | | 64125 | 64125 | 64125 | 64125 | 64125 | 64125 |
| Setup lắp đặt *(đơn Kỹ thuật)* | | 64125 | 64125 | 64125 | 64125 | 64125 | 64125 |
| Vận hành *(đơn Kỹ thuật)* | | | | | | | |
| Marketing | | | | | | | |
| Công tác | TK Nợ cố định **141** — không khai theo ma trận | | | | | | |

Tên theo MISA (diễn giải): Tháo dỡ → `Chi phí tháo dỡ` · Setup lắp đặt → `Chi phí setup`
· Vận hành → `Chi phí khác` · Công tác → `Chi phí khác`.

## Chỗ em cần anh xác nhận

1. **Cột `EVENT FZ MN` trống hoàn toàn** — đúng là chưa dùng, hay bảng cũ bị cuộn che?
2. **Bảng có đúng 7 cột?** Nếu màn hình cuộn ngang còn cột nữa (VR MN, TÀU MN…) thì thiếu.
3. **`Chi phí tháo dỡ` = 64125** = *Chi phí setup Funzone* trong hệ thống tài khoản. Cố ý dùng
   chung mã setup Funzone cho tháo dỡ mọi mảng, hay là mã cũ đặt tạm?
4. **10 loại trống mã** (SP đồ uống/đồ ăn, vật dụng, NVL đồ uống/đồ ăn, phát sinh, setup, khác,
   Marketing, Vận hành KT) — để trống thì nhập xong app báo "thiếu TK Nợ" khi xuất MISA. Có mã
   cho chúng không, hay chúng đi đường khác (mua NCC hạch toán qua 331/152)?

## Nạp vào app

wp-admin → **Vận Hành Chi Phí** → **Nhập dữ liệu**, tích *Dòng đầu là tiêu đề*, nạp theo thứ tự:

1. `CH_CoSo` — file cơ sở của anh (**phải có cột Phân loại lớn**, chính là 7 tên cột ở trên)
2. `CH_LoaiChiPhi.csv`
3. `CH_TKNo.csv`

Xong vào app → ⚙️ Cấu hình → 🧮 ma trận, đối chiếu lại đúng như bảng trên.
