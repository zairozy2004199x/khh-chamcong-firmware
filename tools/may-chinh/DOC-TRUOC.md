# Nối máy chấm công ZKTeco về website

Đọc log từ mấy đầu đọc trong mạng xưởng rồi đẩy về `khmatrix.com/cham-cong-may`.

## Vì sao phải có con này

Mấy đầu đọc nằm ở `192.168.0.20-24` — địa chỉ **mạng nội bộ** của xưởng. Website chạy ngoài
internet không có đường nào gọi vào đó, và cũng **không nên** có: mở một lối từ internet vào
mạng nội bộ để đọc chấm công là mở luôn cho mọi thứ khác.

Nên giữ chiều **máy tự gọi ra**: một máy tính đứng sẵn trong mạng ấy — chính cái máy đang chạy
HR V5.2 — đọc log qua LAN rồi POST về cổng của web.

## Ba bước

### 1. Cài (một lần, trên máy ở cơ sở)

```
py -m pip install pyzk requests
copy cau-hinh.mau.json cau-hinh.json
```

Mở `cau-hinh.json`, sửa hai chỗ:
- `khoa` — dán giá trị `VHCC_KHOA_MAY` (cùng khoá web đang dùng; xem tab **Máy & Firmware**,
  ở đó chỉ nói CÓ hay KHÔNG, không in giá trị ra màn hình)
- danh sách `may` — IP đã điền sẵn theo ảnh anh gửi, đối chiếu lại cho chắc

### 2. Chạy thử — chưa gửi gì

```
py doc-may-zk.py --thu
```

Nó chỉ nối vào từng máy, đếm số lượt đọc được, rồi thoát. Máy nào không nối được thì nó nói
tên máy và lý do. **Chạy bước này trước, đừng bỏ qua.**

### 3. Chạy thật

```
py doc-may-zk.py
```

Rồi đặt Task Scheduler của Windows gọi đúng lệnh ấy **mỗi 10 phút**. Không cần chạy nền — mỗi
lượt tự đọc, tự gửi, tự thoát.

## Ghép mã nhân viên

Máy trả mã dạng `20000601`; sổ nhân sự dùng `MNNV1CTY0002`. Hai bộ mã khác nhau, nên phải khai
ghép — **không đoán theo tên**, vì tên người Việt trùng rất nhiều và đoán sai là gộp lương hai
người khác nhau.

Khai ở wp-admin → **Nhân sự → Mã chạy song song**: một dòng cho mỗi người, một bên mã máy, một
bên mã nhân sự. Chiều nào trước cũng được — hệ tự nhận ra bên nào có hồ sơ.

Chưa khai thì lượt ấy **vẫn vào bảng công**, mang chính mã máy, và hiện ở khối "chưa có hồ sơ".
Không mất lượt nào — nhưng cũng chưa ra lương của ai, nên khai sớm.

## Mấy điều đã dựng sẵn để không mất dữ liệu

- **Không xoá log trên máy.** Con này chỉ đọc. Đầu đọc giữ được hàng chục nghìn lượt; xoá đi là
  mất đường đối chiếu khi web và máy lệch nhau.
- **Mốc chỉ dời khi web nói đã nhận.** Web trả lỗi mà vẫn dời mốc là mất trắng phần vừa đọc,
  im lặng. Mốc giữ theo từng máy trong `moc.json`.
- **Đẩy lại bao nhiêu lần cũng không sao.** Cổng chỉ nới giờ vào/giờ ra, gặp lượt trùng thì bỏ,
  và không đè lên ô đã có người sửa hoặc bù. Nghi mất dữ liệu thì cứ chạy lại:

```
py doc-may-zk.py --tu 2026-08-01
```

Thà gửi thừa còn hơn thiếu.

- **Không khoá màn hình đầu đọc trong lúc đọc.** Người tới chấm công đúng lúc ấy vẫn bấm được.

## Đọc kết quả

Mỗi lô gửi xong, con này in ra đúng những con số web trả về:

```
→ gửi 93 lượt · web: ghi 88 · trùng 4 · giữ tay 1 · chờ gán 0
```

- **ghi** — vào bảng công thật
- **trùng** — gói lặp, chuyện thường, bỏ qua
- **giữ tay** — máy định đè lên giờ đã có người sửa hoặc bù, và bị chặn. Hoặc máy lệch đồng hồ,
  hoặc lượt sửa kia sai — **cần người nhìn**
- **chờ gán** — máy chưa được gán cơ sở trên web. Vào tab **Máy & Firmware** gán, rồi mấy lượt
  đang giữ tạm sẽ tự vào bảng
