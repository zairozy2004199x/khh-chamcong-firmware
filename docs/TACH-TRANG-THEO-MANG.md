# Tách chi phí theo mảng kinh doanh — bốn trang

> Anh Thắng 14/09/2026: *"Anh đang tách 2 mảng kinh doanh riêng ra 2 trang riêng, không dùng
> chung chi phí kvc nữa. Đồng thời 1 trang chi phí tổng để gom 3 mảng lại"* · *"4 plugin, tạo mã
> riêng không trùng nhau, vì bộ phận có mã riêng, tk khoản riêng, quản lý riêng, chỉ là kho đơn
> đẩy nó gom về 1 trang chi phí cho người duyệt"*

## Bốn trang

| Trang | Plugin | Bảng | Lớp | Trạng thái |
|---|---|---|---|---|
| `/chi-phi-kvc` | `vhcp-chi-phi` | `wp_vhcp_*` | `VHCP_*` | đang chạy, **dữ liệu thật** |
| `/chi-phi-mtd` | `vhcp-chi-phi-mtd` | `wp_vhcpmtd_*` | `VHCPMTD_*` | mới, sổ trống |
| `/chi-phi-vp` | `vhcp-chi-phi-vp` | `wp_vhcpvp_*` | `VHCPVP_*` | mới, sổ trống |
| `/chi-phi-kh` | *(chưa làm)* | — | — | trang tổng, gom kho đơn để duyệt |

🔴 **Bản KVC giữ nguyên bảng và tên lớp.** Nó đang chở sổ chi phí thật; đổi tiền tố bảng là phải
di trú dữ liệu, mà di trú một sổ tiền đang chạy để lấy cái tên đẹp hơn là đổi một thứ chắc chắn
đúng lấy một thứ có thể sai. Chỉ **đường dẫn** đổi: `chi-phi` → `chi-phi-kvc`.

⚠️ **Đường cũ `/chi-phi` vẫn sống.** Nó nằm trong tin nhắn, dấu trang, mã QR đã in, và iframe của
trang tổng. Bỏ nó là mọi thứ ấy trả 404 cùng lúc — mà 404 thì người dùng đọc thành *"hệ thống
sập"*, không đọc thành *"đổi địa chỉ"*. `VHCP_App::init()` khai cả hai luật.

## Địa chỉ trang tổng: `chi-phi-kh`, không phải `chi-phi-k&h`

WordPress rửa đường dẫn qua `sanitize_title()`, và hàm ấy **bỏ dấu `&`**. Gõ `chi-phi-k&h` vào ô
Cài đặt thì nó lưu thành `chi-phi-kh`. Ngoài ra `&` trong URL là ký tự mở phần tham số, nên link
dán vào chat hay Excel sẽ đứt ở đúng chỗ ấy. Dùng thẳng **`/chi-phi-kh`**.

## Hai chỗ nguy đã vá trong `tools/tach-ban-vung.sh`

🔴 **Hai hằng slug, không phải một.** Bản gốc mang `SLUG_MAC_DINH = 'chi-phi-kvc'` và
`SLUG_CU = 'chi-phi'`. Script phải đổi **cả hai**:

- quên `SLUG_MAC_DINH` → bản mới mặc định mở ở `/chi-phi-kvc`, giành đường của bản chở sổ thật;
- quên `SLUG_CU` → bản mới cũng đăng ký `/chi-phi`, và WordPress cho luật khai **sau** đè luật
  trước. Plugin nạp theo thứ tự chữ cái nên `vhcp-chi-phi-mtd` nạp sau `vhcp-chi-phi` →
  `/chi-phi` rơi vào bảng **rỗng** của bản mới.

Y hệt cái đã cắn 08/09/2026 với đường REST: trang mở ra trống trơn, dữ liệu còn nguyên mà nhìn
như mất sạch. Nên sau lượt `sed` có một **chốt**: còn sót chuỗi `'chi-phi'` hay `'chi-phi-kvc'`
trần là script dừng, không giao bản hỏng.

⚠️ **Tên plugin phải mang mã bản.** Trong danh sách Plugin của wp-admin bốn bản trông na ná
nhau; thiếu mã thì gỡ nhầm hay cập nhật nhầm là chuyện sớm muộn, mà gỡ nhầm một bản chi phí là
mất đường vào sổ tiền của cả một mảng. Script tự chèn `(MÃ)` vào cuối nếu tên truyền vào thiếu.

## Bài kiểm nay soi MỌI bản

`tools/test/kiem-tach-ban-vung.php` trước gõ cứng `$MA = 'hn'` — đúng hôm chỉ có một bản, sai
ngay hôm có ba. Nay nó **dò thư mục** `wordpress/vhcp-chi-phi-*` và tự chạy lại cho từng mã (35
phép mỗi bản). Dò chứ không liệt kê: liệt kê thì mảng thứ tư sinh ra tháng sau lại lọt, mà lọt
thì im lặng.

`tools/build-plugin-zip.sh tatca` cũng dò theo thư mục, nên bản rời nào có trong kho là có .zip.

## Việc còn lại — trang tổng

Kho đơn của ba bản nằm ở ba bảng `*_don` khác nhau. Trang tổng phải:

1. **đọc** đơn của cả ba bản;
2. **duyệt được ngay tại đó** — anh Thắng: *"gom về 1 trang chi phí cho người duyệt"*, tức nó
   phải ghi ngược về bảng của đúng bản sinh ra đơn, không phải chép đơn sang kho thứ tư.

🔴 **Không chép đơn sang kho riêng.** Hai bản sao của cùng một đơn là hai sự thật, và chúng lệch
nhau vào đúng lúc người ta cần con số đúng nhất.
