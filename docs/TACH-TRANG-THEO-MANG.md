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
| `/chi-phi-kh` | `vhcp-chi-phi-tong` | **không có bảng nào** | `VHCPT_*` | trang tổng, gom kho đơn để duyệt |

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

## Trang tổng — gom, không chép

🔴 **Plugin tổng không dựng bảng nào cả**, và đó là quyết định lớn nhất ở đây. Chép đơn sang một
kho tổng thì có hai bản sao của cùng một đơn, tức **hai sự thật** — chúng lệch nhau vào đúng lúc
người ta cần con số đúng nhất: ai đó sửa đơn bên mảng sau khi trang tổng đã chép, hoặc lượt chép
chạy hụt một đơn và không ai biết. Gỡ plugin này không mất đơn nào.

### Nó tìm các mảng bằng cách dò, không bằng danh sách

`VHCPT_Ban::ds()` quét `get_declared_classes()` tìm `VHCP<MÃ>_Don`. Bản nào đang **kích hoạt** thì
lớp của nó đã nạp, nên nó có mặt. Gõ cứng danh sách mảng thì mảng thứ tư sinh ra tháng sau không
lọt vào trang tổng — mà *"đơn của một mảng không ai duyệt"* là thứ chỉ lộ ra khi có người đi đòi
tiền.

⚠️ Bản gốc mang **mã rỗng** (`VHCP_Don`, không hậu tố). `khoa()` đổi nó thành `kvc` để làm khoá
hiện ra màn, còn `tien_to()` giữ đúng chuỗi rỗng để dựng tên lớp.

### Đăng nhập: mượn sổ của các bản

Không có sổ người dùng riêng. PIN gõ vào được thử qua `<Bản>_Cfg::get_users()` của từng mảng; vào
được mảng nào thì thấy đơn của mảng ấy. Cho nó sổ riêng thì mỗi lần đổi người duyệt phải khai hai
nơi, và nơi bị quên là nơi vẫn còn mở cửa cho người đã nghỉ.

🔴 **Quyền duyệt hỏi chính bảng phân quyền của bản chứa đơn** (`<Bản>_Cfg::get_quyen()['duyetTU']`),
không đoán theo tên vai. Bảng ấy sửa được trên màn và anh Thắng đã sửa nó thật; đoán theo tên vai
là nói ngược lại thứ người ta vừa khai. Một người có thể là Quản lý ở KVC (duyệt được) và Kế toán
ở MTD (không) — trộn thành một cờ chung là cho họ duyệt cả đơn của mảng mà bên ấy đã cố ý không
cho.

🔴 **Một PIN ra hai người khác tên thì chối.** Hai bản có thể có hai người khác nhau trùng PIN —
hiếm, nhưng khi xảy ra thì cho vào là cho một người mang danh người kia đi duyệt tiền.

### Đọc và ghi

| Việc | Cách |
|---|---|
| Danh sách đơn | một `SELECT` trên `*_don` của từng bản, lọc trạng thái ngay trong câu lệnh |
| Số tiền mỗi đơn | gọi `<Bản>_Don::tong_xin_hien_tai()` |
| Đếm cho tab | `COUNT(*)` |
| Duyệt | `<Bản>_Don::duyet_tam_ung()` — ghi ngược về đúng bản sinh ra đơn |
| Trả lại | `<Bản>_Don::tra_lai_don()` |

Số tiền **không** tính lại ở trang tổng: luật gom hạng mục có chỗ tinh (`Nháp` thì gộp cả dòng
phát sinh, sau đó thì không; có hàng tạm ứng tay thì lấy hàng ấy). Chép luật ấy sang đây là dựng
bản thứ hai cho cùng một câu hỏi, rồi người duyệt thấy một con số còn người lập đơn thấy con số
khác.

🔴 **Mọi việc ghi đều kiểm lại ba thứ**: bản có thật không, người này có quyền duyệt **ở bản ấy**
không, và đơn đó có thật nằm trong bản ấy không. Thiếu cái thứ ba thì gửi khoá bản mình có quyền
kèm một mã đơn **trùng** tồn tại ở cả hai bản là duyệt nhầm đơn của mảng khác — mã đơn hai bản
trùng nhau là chuyện thường, chúng đánh số độc lập.

### Trạng thái là giao kèo giữa bốn plugin

`Chờ duyệt tạm ứng` · `Chờ cấp tạm ứng` · `Chờ quyết toán` — chuỗi tiếng Việt có dấu. Đổi một chữ
ở một bản là đơn của bản ấy **biến mất** khỏi trang tổng: không câu lỗi nào, chỉ là bảng ngắn đi.
`tools/test/kiem-trang-tong.php` canh đúng chỗ đó (48 phép).

## Khoá GitHub: mỗi bộ một ô, nhưng khai một lượt

Nay có **mười một** bộ, mỗi bộ một ô khoá riêng (`vhcp_gh_token`, `vhcpmtd_gh_token`,
`vhcpvp_gh_token`, `vhcpt_gh_token`, …). Bốn plugin chi phí cài chung một WordPress nên dùng chung
**ô** cấu hình là đổi bên này đổi luôn bên kia.

Khai cả mười một ô trong **một lượt gõ**:

```bash
bash tren-host.sh khoa tatca
```

⚠️ Cùng một khoá cho mọi ô là cố ý và an toàn: khoá chỉ đọc, trỏ đúng một kho. Cái phải riêng là
**ô**, không phải cái khoá nằm trong ô. Khoá gõ vào không hiện lên màn hình (`read -s`) và không
bao giờ đi vào dòng lệnh — dòng lệnh nằm lại trong `~/.bash_history` và hiện ra với `ps`.

## Việc còn lại

Trang tổng hiện làm đúng hai việc của người duyệt: **duyệt tạm ứng** và **trả lại đơn**. Chưa
làm: cấp tạm ứng, xác nhận quyết toán, và màn báo cáo cộng số ba mảng. Mấy việc ấy đều gọi được
qua cùng một đường (`<Bản>_Don::…`) khi cần.
