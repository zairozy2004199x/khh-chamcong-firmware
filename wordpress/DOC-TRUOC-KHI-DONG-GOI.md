# Plugin **Ghế Massage (K&H)** KHÔNG nằm trong nhánh này

Nhà thật của nó: nhánh **`claude/posh-qr-kh1urz`** của chính kho này, ở thư mục
**`vhcp-ghe/`** (ngay gốc kho, không nằm dưới `wordpress/`).

```bash
git fetch origin claude/posh-qr-kh1urz
git worktree add /tmp/ghe origin/claude/posh-qr-kh1urz
cd /tmp/ghe && bash tools/build-ghe.sh     # -> dist/vhcp-ghe.zip
```

## 🔴 Vì sao có tệp này

Nhánh chấm công từng giữ một bản `wordpress/vhcp-ghe` **1.48.0** — di tích của một
lượt đồng bộ cũ. Trong khi đó bản chạy thật trên host đã là **2.111.0**, và nó tự
cập nhật từ nhánh `posh-qr-kh1urz` (xem `VHG_TuCapNhat::NHANH` trong chính plugin).

Ngày 18/09/2026 em đóng gói nhầm cái bản 1.48.0 ấy rồi gửi cho anh Thắng cài. Màn
cài của WordPress bắt được và hỏi lại: *"Bạn đang tải lên một phiên bản cũ của
plugin hiện tại"* — **nếu anh bấm Thay thế là mất trắng hơn sáu mươi bản cập nhật**.
Cái cứu được hôm ấy là một dòng cảnh báo của WordPress, không phải kho này.

Nên bản cũ và chín bài kiểm của dòng 1.x đã **gỡ khỏi nhánh chấm công** (lịch sử git
vẫn còn, cần thì `git log --all -- wordpress/vhcp-ghe`): một thư mục mã chết mà vẫn
đóng gói được là một cái bẫy, và nó đã sập một lần.

## Sửa gì bên ghế thì làm ở đâu

Ở nhánh `posh-qr-kh1urz` — mã, bài kiểm (`tools/test/kiem-ghe-*.php`) và script đóng
gói đều nằm sẵn ở đó. **Đừng** chép lại một bản thứ hai sang nhánh này.
