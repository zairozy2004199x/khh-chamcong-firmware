# Quy ước làm việc trong repo này

> Đọc file này trước khi sửa bất cứ thứ gì. Bàn giao chi tiết từng mảng:
> [`docs/BAN-GIAO-trang-ghe.md`](docs/BAN-GIAO-trang-ghe.md) (trang Ghế),
> [`BAN_GIAO_POSH_VE.md`](BAN_GIAO_POSH_VE.md) (bán vé + Zalo Mini App),
> [`README.md`](README.md) (firmware ESP32).

## 1. GIAO VIỆC BẰNG FILE ZIP, KHÔNG BẰNG ĐOẠN CODE

Anh Thắng cài plugin qua **WordPress → Plugins → Add New → Upload Plugin**, không sửa file trên
host. Nên mỗi lần sửa xong plugin thì **luôn build lại `.zip` và gửi thẳng file đó**, kể cả khi
chỗ sửa chỉ một dòng. Dán đoạn code ra chat là bắt anh ấy sửa tay trên máy chủ — dễ sai, và dễ
quên tăng số bản nên không biết bản nào đang chạy.

Dán code chỉ khi anh ấy hỏi *"sửa chỗ nào"* / *"cho xem code"* — và vẫn kèm zip.

```bash
# build (chạy ở gốc repo, tên thư mục = tên plugin)
rm -f dist/vhcp-ghe.zip && zip -qr dist/vhcp-ghe.zip vhcp-ghe -x '*.DS_Store'
```

- `vhcp-ghe/` → `dist/vhcp-ghe.zip` (đã có sẵn trong repo, commit kèm mỗi lần sửa)
- `vhcp-saoke/` → `dist/vhcp-saoke.zip`
- `vhcp-ve/` → **chưa có zip trong repo**, build theo lệnh trên khi cần giao
- `dist/vhcp-du-an.zip` → chỉ có zip, **không có mã nguồn trong repo**, không sửa được từ đây

Sau khi build: giải nén ra chỗ tạm rồi `diff -rq` với thư mục nguồn để chắc zip đúng bản.

## 2. Bắt buộc trước khi giao

1. `php -l` từng file `.php` đã đụng vào.
2. **`node --check` trọn khối JS heredoc** — JS nằm trong `<<<'JS' … JS;` của
   `class-vhg-trang.php` (`js()`, `js_baocao()`) và `class-vhg-shop.php`. PHP không kiểm cú pháp
   trong heredoc: **một dấu `}` thừa là trắng cả trang** (đã dính ở 2.20.0, vá ở 2.20.1).
3. Tăng số bản ở header `Version:` **và** hằng `VHG_VERSION` — hai chỗ, phải bằng nhau.
   Trang in số bản ra góc phải; số không đổi thì không ai biết bản mới đã lên chưa.
4. Build zip, commit cả zip lẫn source, push.

## 3. Nhánh

Nhánh phát triển: **`claude/posh-qr-kh1urz`**. Chỉ commit/push lên nhánh này.
Không mở PR nếu chưa được yêu cầu. `main` đang tụt lại rất xa (chỉ còn phần firmware).

## 4. Repo này CÔNG KHAI

Không đặt PIN, khoá, token, số tài khoản ngân hàng, khoá merchant vào mã nguồn — kể cả trong
comment hay file mẫu. Cấu hình nhạy cảm nằm trong **Option của WordPress** (DB) hoặc **NVS của
chip**. Bí mật nạp máy đi qua thẻ SD `token.txt` (đã gitignore). Thư mục `esp32_*/build/` cũng bị
chặn: file `.bin`/`.elf`/`.map` chứa bí mật ở dạng chữ đọc được.

## 5. Ghế ⇄ Sao Kê dùng chung một DB

`vhcp-ghe` và `vhcp-saoke` đọc chéo bảng của nhau nên phải cùng cài trên một site.

⚠️ **Hai plugin chuẩn hoá tên cơ sở theo hai kiểu khác nhau** — nguồn của lỗi âm thầm:

| | Hàm | "SÂN BAY CẦN THƠ" ra |
|---|---|---|
| Sao Kê | `chuan_ch()` = `kd()` + bỏ ký tự lạ | `sanbaycantho` (**thường**) |
| Ghế | `VHG_BaoCao::squash()` = `remove_accents` + hoa | `SANBAYCANTHO` (**HOA**) |

Bên Ghế đọc dữ liệu khoá-theo-tên của Sao Kê (ví dụ option `saoke_coso_ma`) thì **phải quy khoá
qua `squash()` trước khi tra**, đừng tra thẳng — tra thẳng không bao giờ khớp và hỏng *im lặng*:
bảng vẫn hiện, chỉ là mọi cơ sở đều "chưa đặt mã" và số tiền bằng 0 (lỗi 2.20.1, vá ở 2.20.2).
Đừng sửa `squash()` để cho khớp: nó đang dùng chung cho phạm vi PIN nhân viên và đối chiếu VietQR.
